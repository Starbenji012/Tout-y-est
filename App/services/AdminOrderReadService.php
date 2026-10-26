<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{AdminOrderRead,AdminOrderJournal,OrderItem,Delivery,Payment};
use DomainException;

/** Présentation seule : ne dépend jamais du service de réservation. */
final class AdminOrderReadService
{
    public const STATES = [''=>'Toutes','confirmee'=>'À préparer','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrées','annulee'=>'Annulées'];
    public function __construct(private readonly AdminAccessService $access,private readonly AdminOrderRead $orders,private readonly OrderItem $items,private readonly Delivery $deliveries,private readonly Payment $payments,private readonly AdminOrderJournal $journal,private readonly MoneyService $money,private readonly ?ProductService $products=null) {}

    public function listing(array $input): array
    {
        $this->access->requireCurrentAdmin();
        $state=is_string($input['etat']??null)&&array_key_exists($input['etat'],self::STATES)?$input['etat']:'';
        $cash=($input['encaisser']??null)==='1';
        $page=filter_var($input['page']??1,FILTER_VALIDATE_INT); $page=max(1,min(100000,$page?:1));
        $rows=$this->orders->listing($state,$cash,($page-1)*20);
        return ['orders'=>array_map($this->present(...),array_slice($rows,0,20)),'page'=>$page,'hasNext'=>count($rows)>20,'state'=>$state,'cash'=>$cash,'states'=>self::STATES,'filters'=>array_filter(['etat'=>$state,'encaisser'=>$cash?'1':''])];
    }

    public function detail(string $reference): array
    {
        $this->access->requireCurrentAdmin();
        $row=strlen($reference)<=100?$this->orders->byReference($reference):null;
        if (!$row) throw new DomainException('Commande introuvable.',404);
        $order=$this->present($row); $items=$this->items->forOrder((int)$row['id_commande']);
        $images=$this->products?->productImages(array_column($items,'id_produit'))??[];
        foreach($items as &$item){$item['image']=$images[(int)$item['id_produit']][0]??null;$item['unit']=$this->amount($item['prix_unitaire_applique'],$row['devise_reference']);$item['lineTotal']=$this->amount($item['total_ligne'],$row['devise_reference']);} unset($item);
        $events=$this->journal->forOrder((int)$row['id_commande']);
        foreach($events as &$event){$event['label']=match($event['action']){'prepare'=>'Préparation commencée','ship'=>'Mise en livraison','deliver'=>'Livraison confirmée','collect-cash'=>'Encaissement confirmé','cancel'=>'Commande annulée',default=>'Événement de commande'};$extra=json_decode($event['donnees_complementaires'],true);$event['details']=is_array($extra)?array_intersect_key($extra,array_flip(['motif','devise','montant','taux_usd_cdf'])):[];} unset($event);
        return ['order'=>$order,'items'=>$items,'events'=>$events];
    }

    public static function deliveryStatus(?string $status): string
    {
        return match($status){'en_attente'=>'En attente de préparation','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrée','annulee','annule'=>'Annulée',null=>'Non renseignée',default=>'État à vérifier'};
    }

    private function present(array $row): array
    {
        $id=(int)$row['id_commande'];$payments=$this->payments->forOrder($id);
        foreach($payments as &$p){$p['label']=$p['statut']==='a_encaisser'?'À encaisser':CustomerOrderService::paymentStatus($p['statut'],$p['mode']);$p['amount']=$this->amount($p['montant'],$row['devise_reference']);} unset($p);
        $selected=null;foreach($payments as $p){if($selected===null||!in_array($selected['statut'],['paye','payee'],true))$selected=$p;}
        $delivery=$this->deliveries->forOrder($id);$cdf=null;
        // Un ancien taux invalide n'empêche pas de consulter la commande.
        if($row['devise_reference']==='USD'&&$row['taux_usd_cdf']!==null){try{$cdf=$this->money->format($this->money->usdToCdfAtRate($this->money->minor($row['total']),$row['taux_usd_cdf']),'CDF');}catch(\InvalidArgumentException|\DomainException){}}
        return ['row'=>$row,'number'=>$row['numero_commande'],'date'=>(new \DateTimeImmutable($row['date_commande']))->format('d/m/Y à H:i'),'status'=>CustomerOrderService::orderStatus($row['statut']),'total'=>$this->amount($row['total'],$row['devise_reference']),'subtotal'=>$this->amount($row['sous_total'],$row['devise_reference']),'shipping'=>$this->amount($row['frais_livraison'],$row['devise_reference']),'cdf'=>$cdf,'payments'=>$payments,'mode'=>$selected['mode']??'Non choisi','paymentStatus'=>$selected['label']??'Aucun paiement enregistré','delivery'=>$delivery,'deliveryStatus'=>self::deliveryStatus($delivery['statut']??null)];
    }

    private function amount(string $value,?string $currency): string
    {
        return $currency==='USD'?$this->money->format($this->money->minor($value),'USD'):$value.' '.($currency?:'— devise non renseignée');
    }
}
