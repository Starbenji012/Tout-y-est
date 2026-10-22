<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{Order, OrderItem, Delivery};
use DomainException;

/** Historique en lecture seule : aucun recalcul depuis le catalogue ou l'adresse actuelle. */
final class CustomerOrderService
{
    public function __construct(private readonly Order $orders, private readonly OrderItem $items, private readonly Delivery $deliveries, private readonly MoneyService $money, private readonly ?ProductService $products = null) {}

    public function listing(int $userId, int $page = 1, array $filters = []): array
    {
        if ($userId < 1) throw new DomainException('Connectez-vous pour consulter vos commandes.');
        $page = max(1, min(100000, $page));
        $period = is_string($filters['period'] ?? null) ? $filters['period'] : 'all';
        if (!in_array($period, ['all','current','previous','month'], true)) $period = 'all';
        $available = $this->orders->monthsForUser($userId);
        $month = is_string($filters['month'] ?? null) ? $filters['month'] : '';
        $month = $month === '' ? ($available[0] ?? '') : $month;
        $error = null; $from = null; $until = null;
        $start = new \DateTimeImmutable('first day of this month 00:00:00');
        if ($period === 'previous') $start = $start->modify('-1 month');
        if ($period === 'month') {
            if (!in_array($month, $available, true)) $error = 'Choisissez un mois disponible dans vos commandes.';
            else $start = new \DateTimeImmutable($month.'-01 00:00:00');
        }
        if ($period !== 'all') {
            $from = $start->format('Y-m-d H:i:s');
            $until = $start->modify('+1 month')->format('Y-m-d H:i:s');
        }
        $rows = $error === null ? $this->orders->listForUser($userId, 21, ($page - 1) * 20, $from, $until) : [];
        $monthNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $months = [];
        foreach ($available as $value) $months[$value] = ucfirst($monthNames[(int)substr($value,5,2)-1]).' '.substr($value,0,4);
        $paginationFilters = $period === 'all' ? [] : ['period'=>$period] + ($period === 'month' ? ['month'=>$month] : []);
        return ['orders' => array_map($this->present(...), array_slice($rows, 0, 20)), 'page' => $page, 'hasNext' => count($rows) > 20,
            'period'=>$period, 'month'=>$month, 'months'=>$months, 'periodError'=>$error, 'paginationFilters'=>$paginationFilters];
    }

    public function detail(int $userId, int $orderId): array
    {
        $row = $userId > 0 && $orderId > 0 ? $this->orders->findForUser($userId, $orderId) : null;
        // Même refus pour un numéro absent ou appartenant à un autre compte.
        if (!$row) throw new DomainException('Commande introuvable pour ce compte.');
        $lines = [];
        $items = $this->items->forOrder($orderId);
        // Illustration actuelle uniquement : l'ancien nom et les montants ne viennent jamais du catalogue.
        $images = $this->products?->productImages(array_column($items, 'id_produit')) ?? [];
        foreach ($items as $item) {
            $lines[] = ['name' => $item['nom_produit_snapshot'], 'variant' => $item['description_variante_snapshot'], 'quantity' => (int) $item['quantite'],
                'unitPrice' => $this->amount($item['prix_unitaire_applique'], $row['devise_reference']), 'total' => $this->amount($item['total_ligne'], $row['devise_reference']), 'image' => $images[(int) $item['id_produit']][0] ?? null];
        }
        $snapshot = $this->deliveries->forOrder($orderId);
        $delivery = $snapshot ? array_intersect_key($snapshot, array_flip(['nom_destinataire_snapshot','prenom_destinataire_snapshot','telephone_snapshot','commune_snapshot','adresse_snapshot','repere_snapshot','instructions_snapshot','nom_zone_snapshot'])) : null;
        return ['order' => $this->present($row), 'lines' => $lines, 'delivery' => $delivery];
    }

    public static function orderStatus(string $status): string
    {
        return match ($status) {
            'en_attente_paiement', 'en_attente' => 'En attente de paiement',
            'confirmee' => 'Commande confirmée', 'payee' => 'Paiement confirmé',
            'en_preparation' => 'En préparation', 'en_livraison' => 'En livraison',
            'livree' => 'Livrée', 'annulee' => 'Annulée', 'expiree' => 'Expirée',
            default => 'Statut en cours de mise à jour',
        };
    }

    public static function paymentStatus(?string $status, ?string $mode): string
    {
        return match ($status) {
            'a_encaisser' => $mode === \App\Models\PaymentMethod::CASH ? 'À régler à la livraison' : 'À régler',
            'paye', 'payee' => 'Payé', 'annule', 'annulee' => 'Paiement annulé',
            'echoue' => 'Paiement non abouti', 'en_attente' => 'En attente de règlement',
            null => 'Aucun paiement enregistré', default => 'État du paiement en cours de mise à jour',
        };
    }

    private function present(array $row): array
    {
        $cdf = null;
        if ($row['devise_reference'] === 'USD' && $row['taux_usd_cdf'] !== null) {
            $cdf = $this->money->format($this->money->usdToCdfAtRate($this->money->minor($row['total']), $row['taux_usd_cdf']), 'CDF');
        }
        return ['id' => (int) $row['id_commande'], 'number' => $row['numero_commande'],
            'date' => (new \DateTimeImmutable($row['date_commande']))->format('d/m/Y à H:i'),
            'status' => self::orderStatus($row['statut']), 'paymentMode' => $row['paiement_mode'] ?? 'Non choisi',
            'paymentStatus' => self::paymentStatus($row['paiement_statut'], $row['paiement_mode']),
            'subtotal' => $this->amount($row['sous_total'], $row['devise_reference']), 'shipping' => $this->amount($row['frais_livraison'], $row['devise_reference']),
            'total' => $this->amount($row['total'], $row['devise_reference']), 'cdf' => $cdf,
            'rate' => $cdf !== null ? (str_contains($row['taux_usd_cdf'], '.') ? rtrim(rtrim($row['taux_usd_cdf'], '0'), '.') : $row['taux_usd_cdf']) : null];
    }

    private function amount(string $decimal, ?string $currency): string
    {
        $minor = $this->money->minor($decimal);
        return $currency === 'USD' ? $this->money->format($minor, 'USD') : $this->money->decimal($minor) . ' — devise non renseignée';
    }
}
