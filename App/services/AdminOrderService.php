<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{Order, Payment, Delivery, PaymentMethod, AdminOrderJournal};
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
    public function prepare(int $id): array { return $this->apply($id, 'prepare'); }
    public function ship(int $id): array { return $this->apply($id, 'ship'); }
    public function deliver(int $id): array { return $this->apply($id, 'deliver'); }
    public function collectCash(int $id): array { return $this->apply($id, 'collect-cash'); }
    public function cancel(int $id, string $reason): array { return $this->apply($id, 'cancel', $reason); }

    private function apply(int $id, string $action, string $reason = ''): array
    {
        $this->database->beginTransaction();
        try {
            // Droits verrouillés puis commande, paiement, livraison ; stock seulement à l'annulation.
            $actor = $this->access->requireCurrentAdmin(true);
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
            $existing = $journal->findAction($id,$action);
            if ($existing) {
                $this->checkReplay($action,$existing,$before,$order,$payment,$reason);
                $this->database->commit();
                return ['success'=>true,'commande'=>$id,'action'=>$action,'reused'=>true];
            }
            if ($order['stock_restitue_at'] !== null || $order['reservation_expire_at'] !== null
                || $payment['statut'] !== 'a_encaisser' || $payment['date_paiement'] !== null) $this->conflict();
            $data = [];
            if (isset(self::PROGRESS[$action])) {
                [$source,$shipping,$target,$stage] = self::PROGRESS[$action];
                if ($order['statut'] !== $source || $delivery['statut'] !== $shipping || !$this->datesMatch($delivery,$stage)) $this->conflict();
                $orders->advance($id,$target);
                $deliveries->advance($id,$target);
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
            $this->database->commit();
            return ['success'=>true,'commande'=>$id,'action'=>$action,'reused'=>false];
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
            'date_livraison'=>$delivery['date_livraison'],'date_paiement'=>$payment['date_paiement']];
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
