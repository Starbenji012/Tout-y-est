<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{Order, Payment, Delivery, PaymentMethod, AdminOrderJournal, Courier};
use DomainException;
use PDO;
use Throwable;

/** Actions fermées : autorisation, changement et journal partagent une transaction PDO. */
final class AdminOrderService
{
    private const PROGRESS = [
        'prepare'=>['confirmee','en_attente','en_preparation',0],
        'ship'=>['en_preparation','en_preparation','en_livraison',1],
        'deliver'=>['en_livraison','en_livraison','livree',2],
    ];

    public function __construct(private readonly PDO $database, private readonly AdminAccessService $access, private readonly MoneyService $money) {}

    public function authorize(): int { return $this->access->requireCurrentAdmin(); }
    public function prepare(int $id): array {
        $tours=new CourierTourService($this->database);
        return $tours->installed()?$tours->ready($id,$this->access):$this->apply($id,'prepare');
    }
    public function ship(int $id, int $courierId = 0): array { return $this->apply($id, 'ship', '', $courierId); }
    public function deliver(int $id): array { return $this->apply($id, 'deliver'); }
    public function collectCash(int $id): array { return $this->apply($id, 'collect-cash'); }
    public function cancel(int $id, string $reason): array { return $this->apply($id, 'cancel', $reason); }

    public function assign(int $id,int $courierId,int $expectedId): array
    {
        $this->database->beginTransaction();
        try {
            $actor=$this->access->requireCurrentAdmin(true);
            $dispatch=new CourierAssignmentService($this->database);$dispatch->lock();
            if ($id<1 || $courierId<1 || $expectedId<0) throw new DomainException('Affectation invalide.',422);
            $changed=$dispatch->assign($id,$courierId,$actor,$expectedId?:null);
            $order=(new Order($this->database))->find($id);
            $dispatch->dispatch($actor);
            $this->database->commit();
            return ['success'=>true,'commande'=>$id,'action'=>'assign','reused'=>!$changed,
                'reference'=>$order['numero_commande'],'message'=>'Affectation enregistrée. Le départ reste à confirmer.'];
        } catch (Throwable $error) {
            if($this->database->inTransaction())$this->database->rollBack();throw $error;
        }
    }

    private function apply(int $id, string $action, string $reason = '', int $courierId = 0): array
    {
        if($action!=='cancel'&&(new CourierTourService($this->database))->installed()
            && !($action==='collect-cash'&&$this->legacyOrder($id)))throw new DomainException('Utilisez l’espace Livreur ou la remise cash par tournée.',409);
        $courierName='';
        $this->database->beginTransaction();
        try {
            // Droits puis livreurs, commande, paiement, livraison ; stock seulement à l'annulation.
            $actor = $this->access->requireCurrentAdmin(true);
            $dispatch=new CourierAssignmentService($this->database);$dispatch->lock();
            if ($id < 1) throw new DomainException('Commande invalide.',422);
            if ($action === 'cancel') $reason = $this->reason($reason);
            $orders = new Order($this->database);
            $payments = new Payment($this->database);
            $deliveries = new Delivery($this->database);
            $journal = new AdminOrderJournal($this->database);
            $order = $orders->find($id,true);
            if (!$order) throw new DomainException('Commande introuvable.',404);
            $rows = $payments->forOrder($id,true);
            $delivery = $deliveries->lockForOrder($id);
            if (count($rows) !== 1 || $rows[0]['mode'] !== PaymentMethod::CASH || !$delivery) $this->conflict();
            $payment = $rows[0];
            $before = $this->state($order,$payment,$delivery);
            if ($action==='ship') {
                if (!array_key_exists('nom_livreur_snapshot',$delivery)||!array_key_exists('id_livreur',$delivery)) throw new DomainException('Affectation du livreur temporairement indisponible.',503);
                if ($courierId<1) throw new DomainException('Sélectionnez un livreur actif disponible.',422);
            }
            $existing = $journal->findAction($id,$action);
            if ($existing) {
                $this->checkReplay($action,$existing,$before,$order,$payment,$reason);
                if ($action==='ship') {
                    if ((int)($delivery['id_livreur']??0)!==$courierId) $this->conflict();
                    $courierName=(string)$delivery['nom_livreur_snapshot'];
                }
                $this->database->commit();
                return ['success'=>true,'commande'=>$id,'action'=>$action,'reused'=>true,'reference'=>$order['numero_commande'],'message'=>$this->message($action,$courierName)];
            }
            if ($order['stock_restitue_at'] !== null || $order['reservation_expire_at'] !== null
                || $payment['statut'] !== 'a_encaisser' || $payment['date_paiement'] !== null) $this->conflict();
            $data = [];
            if (isset(self::PROGRESS[$action])) {
                [$source,$shipping,$target,$stage] = self::PROGRESS[$action];
                if ($order['statut'] !== $source || $delivery['statut'] !== $shipping || !$this->datesMatch($delivery,$stage)) $this->conflict();
                if ($action==='ship') {
                    $courier=(new Courier($this->database))->findForAssignment($courierId);
                    if (!$courier||$courier['statut']!=='actif') throw new DomainException('Ce livreur n’est plus disponible. Choisissez un livreur actif.',422);
                    if ((int)($delivery['id_livreur']??0)!==$courierId) throw new DomainException('Confirmez d’abord l’affectation de ce livreur avant le départ.',409);
                    $courierName=(string)$delivery['nom_livreur_snapshot'];
                }
                $orders->advance($id,$target);
                $deliveries->advance($id,$target);
                if ($action==='ship') {
                    $deliveries->assignCourier($id,$courierId,$courierName);
                    $data=['id_livreur'=>$courierId,'livreur'=>$courierName];
                } elseif ($action==='deliver' && !empty($delivery['nom_livreur_snapshot'])) $data=['id_livreur'=>$delivery['id_livreur']??null,'livreur'=>$delivery['nom_livreur_snapshot']];
            } elseif ($action === 'cancel') {
                $stage = match ($order['statut']) { 'confirmee'=>0, 'en_preparation'=>1, default=>-1 };
                $shipping = $stage === 0 ? 'en_attente' : 'en_preparation';
                if ($stage < 0 || $delivery['statut'] !== $shipping || !$this->datesMatch($delivery,$stage)) $this->conflict();
                if (!(new PaymentService($this->database))->cancelCashInTransaction($id)) $this->conflict();
                $data = ['motif'=>$reason];
            } else {
                if ($order['statut'] !== 'livree' || $delivery['statut'] !== 'livree' || !$this->datesMatch($delivery,3)) $this->conflict();
                $data = $this->receipt($order,$payment);
                $payments->collectCash((int)$payment['id_paiement'],$data['devise'],$data['montant']);
            }
            $afterOrder = $orders->find($id);
            $afterPayment = $payments->forOrder($id)[0];
            $afterDelivery = $deliveries->forOrder($id);
            $journal->append($id,$actor,(int)$payment['id_paiement'],$action,$before,$this->state($afterOrder,$afterPayment,$afterDelivery),$data);
            if (in_array($action,['prepare','deliver','cancel'],true)) $dispatch->dispatch($actor);
            $this->database->commit();
            return ['success'=>true,'commande'=>$id,'action'=>$action,'reused'=>false,'reference'=>$order['numero_commande'],'message'=>$this->message($action,$courierName)];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    private function checkReplay(string $action, array $event, array $state, array $order, array $payment, string $reason): void
    {
        $after = json_decode($event['nouvel_etat'],true,512,JSON_THROW_ON_ERROR);
        $data = json_decode($event['donnees_complementaires'],true,512,JSON_THROW_ON_ERROR);
        if ((int)$event['id_paiement'] !== (int)$payment['id_paiement']) $this->conflict();
        foreach ($after as $key=>$value) {
            // L'affectation est un événement distinct et peut évoluer avant le départ.
            if ($action==='prepare' && in_array($key,['livreur','id_livreur'],true)) continue;
            // La livraison déjà faite reste faite après encaissement, sans toucher à ses dates.
            if ($action === 'deliver' && in_array($key,['paiement','date_paiement'],true)) continue;
            if (!array_key_exists($key,$state) || $state[$key] !== $value) $this->conflict();
        }
        if ($action === 'deliver' && !in_array($payment['statut'],['a_encaisser','paye'],true)) $this->conflict();
        if ($action === 'cancel' && ($data['motif'] ?? null) !== $reason) $this->conflict();
        if ($action === 'collect-cash') {
            if ($data !== $this->receipt($order,$payment) || $payment['date_paiement'] === null) $this->conflict();
        }
    }

    private function datesMatch(array $delivery, int $stage): bool
    {
        return ($delivery['date_preparation'] !== null) === ($stage >= 1)
            && ($delivery['date_expedition'] !== null) === ($stage >= 2)
            && ($delivery['date_livraison'] !== null) === ($stage >= 3);
    }

    private function state(array $order, array $payment, array $delivery): array
    {
        return ['commande'=>$order['statut'],'livraison'=>$delivery['statut'],'paiement'=>$payment['statut'],
            'reservation_expire_at'=>$order['reservation_expire_at'],'stock_restitue_at'=>$order['stock_restitue_at'],
            'date_preparation'=>$delivery['date_preparation'],'date_expedition'=>$delivery['date_expedition'],
            'date_livraison'=>$delivery['date_livraison'],'date_paiement'=>$payment['date_paiement'],
            'livreur'=>$delivery['nom_livreur_snapshot']??null,'id_livreur'=>isset($delivery['id_livreur'])?(int)$delivery['id_livreur']:null];
    }

    /** Prévisualisation sans écriture ; apply() revalide tout sous verrou au POST. */
    public function availableActions(array $order,array $rows,?array $delivery): array
    {
        if (count($rows)!==1 || !$delivery || $rows[0]['mode']!==PaymentMethod::CASH
            || $rows[0]['statut']!=='a_encaisser' || $rows[0]['date_paiement']!==null
            || $order['stock_restitue_at']!==null || $order['reservation_expire_at']!==null) return [];
        $actions=[];
        foreach(self::PROGRESS as $action=>[$source,$shipping,$target,$stage]) {
            if ($order['statut']===$source && $delivery['statut']===$shipping && $this->datesMatch($delivery,$stage)
                && ($action!=='ship'||array_key_exists('id_livreur',$delivery))) $actions[$action]=true;
        }
        if (isset($actions['prepare'])||isset($actions['ship'])||($order['statut']==='en_preparation'&&$delivery['statut']==='en_preparation'&&$this->datesMatch($delivery,1))) $actions['cancel']=true;
        if ($order['statut']==='livree' && $delivery['statut']==='livree' && $this->datesMatch($delivery,3)) {
            try {$actions['collectCash']=$this->receipt($order,$rows[0]);}catch(\InvalidArgumentException|DomainException) {}
        }
        if((new CourierTourService($this->database))->installed())$actions=array_intersect_key($actions,['prepare'=>true,'cancel'=>true]+($this->legacyOrder((int)$order['id_commande'])?['collectCash'=>true]:[]));
        return $actions;
    }

    public function couriersForAssignment(int $orderId=0): array
    {
        $this->authorize();
        return (new Courier($this->database))->active($orderId);
    }
    public function tourNotice(array $delivery): ?string
    {
        if(!(new CourierTourService($this->database))->installed())return null;
        $q=$this->database->prepare('SELECT a.resultat,t.statut FROM tentative_livraison a JOIN tournee t ON t.id_tournee=a.id_tournee WHERE a.id_livraison=? ORDER BY a.id_tentative DESC LIMIT 1');$q->execute([$delivery['id_livraison']]);$row=$q->fetch();
        if($row&&$row['resultat']==='probleme')return $row['statut']==='retour_termine'?'Colis retourné après un problème : décision humaine nécessaire. Aucun stock restitué automatiquement.':'Problème signalé. Le colis reste sous la responsabilité du livreur jusqu’au retour.';
        if($row&&$row['statut']==='chargement')return 'Colis affecté à '.$delivery['nom_livreur_snapshot'].' — départ groupé depuis l’espace Livreur.';
        if($row&&$row['statut']==='en_cours')return 'Tournée en cours. Le livreur doit confirmer son retour physique après ses livraisons.';
        return $delivery['statut']==='en_preparation'?'Commande prête en attente d’un livreur et d’une tournée éligible.':null;
    }

    private function courier(string $value): string
    {
        return Courier::cleanName($value);
    }
    private function legacyOrder(int $id): bool
    {
        $q=$this->database->prepare('SELECT COUNT(*) FROM tentative_livraison a JOIN livraison l ON l.id_livraison=a.id_livraison WHERE l.id_commande=?');$q->execute([$id]);return (int)$q->fetchColumn()===0;
    }

    private function message(string $action,string $courier): string
    {
        return match($action){'prepare'=>'La préparation de la commande a commencé.','ship'=>'La commande a été confiée à '.$courier.' et mise en livraison.','deliver'=>'La commande a été marquée comme livrée.','collect-cash'=>'Paiement encaissé avec succès.','cancel'=>'La commande a été annulée.'};
    }

    /** Aucun taux courant et aucun montant navigateur : uniquement les snapshots serveur. */
    private function receipt(array $order, array $payment): array
    {
        $rate = (string)$order['taux_usd_cdf'];
        $normalizeRate = static fn(string $value): string => str_contains($value,'.') ? rtrim(rtrim($value,'0'),'.') : $value;
        if ($order['devise_reference'] !== 'USD' || $payment['taux_usd_cdf'] === null
            || $normalizeRate((string)$payment['taux_usd_cdf']) !== $normalizeRate($rate)
            || $this->decimalValue($payment['montant']) !== $this->decimalValue($order['total'])) $this->conflict();
        $cdf = $this->money->usdToCdfAtRate($this->money->minor($order['total']),$rate);
        $currency = $order['devise_paiement'] ?? $payment['devise_reglement'] ?? $order['devise_reference'];
        if (!in_array($currency,['USD','CDF'],true)
            || ($payment['devise_reglement'] !== null && $payment['devise_reglement'] !== $currency)) $this->conflict();
        // MoneyService retourne des francs CDF entiers, pas des centimes USD.
        $amount = $currency === 'USD' ? $this->decimalValue($order['total']) : $cdf . '.00';
        foreach ([$order['total_paiement'],$payment['montant_reglement']] as $stored) {
            if ($stored !== null && $this->decimalValue($stored) !== $amount) $this->conflict();
        }
        return ['devise'=>$currency,'montant'=>$amount,'taux_usd_cdf'=>$rate];
    }

    private function decimalValue(string $value): string
    {
        if (!preg_match('/^([0-9]{1,16})(?:\.([0-9]{1,2}))?$/D',$value,$parts)) $this->conflict();
        return (ltrim($parts[1],'0') ?: '0') . '.' . str_pad($parts[2] ?? '',2,'0');
    }

    private function reason(string $value): string
    {
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length < 1 || $length > 500 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)) throw new DomainException('Indiquez un motif d’annulation de 1 à 500 caractères.',422);
        return $value;
    }

    private function conflict(): never
    {
        throw new DomainException('Cette action n’est plus autorisée pour l’état actuel de la commande.',409);
    }
}
