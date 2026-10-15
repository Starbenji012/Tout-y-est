<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{Cart, Delivery, DeliveryZone, Order, OrderItem, ProductVariant, User, UserAddress};
use DomainException;
use PDO;
use Throwable;

/** Une seule transaction possède la création et tous ses mouvements de stock. */
final class OrderService
{
    public function __construct(
        private readonly PDO $database,
        private readonly User $users,
        private readonly Cart $cart,
        private readonly ProductVariant $variants,
        private readonly DeliveryZone $zones,
        private readonly UserAddress $addresses,
        private readonly Order $orders,
        private readonly OrderItem $items,
        private readonly Delivery $deliveries,
        private readonly CheckoutService $checkout,
        private readonly MoneyService $money,
    ) {}

    public function create(int $userId, array $draft, string $creationKey): array
    {
        if ($userId < 1) throw new DomainException('Connectez-vous pour créer une commande.');
        if (!preg_match('/^[a-f0-9]{64}$/D', $creationKey)) throw new DomainException('Clé de création invalide. Rechargez le Checkout.');
        $this->database->beginTransaction();
        try {
            $user = $this->users->lockForOrder($userId);
            if ($user === null || !in_array($user['statut'], ['actif','active'], true)) throw new DomainException('Votre compte est indisponible.');
            if ($user['email_verified_at'] === null) throw new DomainException('Vérifiez votre adresse e-mail avant de créer une commande.');
            $existing = $this->orders->findByKey($userId, hash('sha256', $creationKey));
            if ($existing !== null) {
                $this->database->commit();
                return ['id' => (int) $existing['id_commande'], 'reused' => true];
            }
            $rawLines = $this->cart->lockForUser($userId);
            if ($rawLines === []) throw new DomainException('Votre panier est vide.');
            $pricedLines = $this->priceLines($rawLines);
            $source = $draft['address'] ?? [];
            $addressId = (int) ($draft['addressId'] ?? 0);
            if ($addressId > 0) {
                $source = $this->addresses->lockForUser($userId, $addressId);
                if ($source === null) throw new DomainException('Cette adresse ne vous appartient plus ou n’existe plus.');
            }
            ['address' => $address, 'errors' => $errors] = $this->checkout->normalizeAddress(is_array($source) ? $source : []);
            if ($errors !== []) throw new DomainException('Revalidez vos informations de livraison.');
            $zone = $this->zones->lockActive((int) ($source['id_zone_livraison'] ?? 0));
            if ($zone === null || $zone['devise_reference'] !== 'USD') throw new DomainException('La zone ou sa devise n’est plus disponible.');
            foreach (['latitude','longitude'] as $coordinate) $address[$coordinate] = $source[$coordinate] ?? null;
            $subtotal = array_sum(array_column($pricedLines, 'totalMinor'));
            $shipping = $this->money->minor($zone['frais_reference']);
            $total = $subtotal + $shipping;
            // Les sommes doivent rester dans les DECIMAL(12,2) du schéma officiel.
            if ($total > 999999999999) throw new DomainException('Le montant de cette commande dépasse la limite autorisée.');
            $rate = (string) $this->money->configuration()['usd_cdf_rate'];
            $this->money->usdToCdfAtRate($total, $rate);
            $orderId = $this->orders->create([
                'user_id' => $userId, 'number' => 'TYE-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8))),
                'subtotal' => $this->money->decimal($subtotal), 'shipping' => $this->money->decimal($shipping),
                'total' => $this->money->decimal($total), 'rate' => $rate, 'key_hash' => hash('sha256', $creationKey),
            ]);
            foreach ($pricedLines as $line) {
                $variantId = $line['variant_id']; $quantity = $line['quantity'];
                unset($line['totalMinor']);
                $this->items->create(['order_id' => $orderId] + $line);
                if (!$this->variants->reserve($variantId, $quantity)) throw new DomainException('Le stock vient de changer. Revalidez votre panier.');
            }
            $this->deliveries->create($orderId, $address, $zone);
            $this->database->commit();
            return ['id' => $orderId, 'reused' => false];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    /** Aucune variante manquante ni quantité insuffisante n'est ignorée. */
    private function priceLines(array $rawLines): array
    {
        $lines = [];
        foreach ($rawLines as $raw) {
            $variant = $this->variants->lock((int) $raw['id_variante']);
            $quantity = (int) $raw['quantite'];
            if ($variant === null || !in_array($variant['statut'], ['actif','active'], true) || !in_array($variant['produit_statut'], ['actif','active'], true)) throw new DomainException('Un article n’est plus disponible. Corrigez votre panier.');
            if ($quantity < 1 || $quantity > 99 || $quantity > (int) $variant['stock']) throw new DomainException('Stock insuffisant ou quantité invalide. Corrigez votre panier.');
            if ($variant['devise_reference'] !== 'USD') throw new DomainException('Un article possède une devise non définie.');
            $base = $this->money->minor($variant['prix_reference']);
            $price = $this->money->discounted($base, $this->variants->promotion((int) $variant['id_produit']));
            $lines[] = ['product_id' => (int) $variant['id_produit'], 'variant_id' => (int) $variant['id_variante'],
                'name' => $variant['nom'], 'sku' => $variant['sku'], 'description' => $variant['description_variante'],
                'quantity' => $quantity, 'price' => $this->money->decimal($price), 'discount' => $this->money->decimal($base - $price),
                'total' => $this->money->decimal($price * $quantity), 'totalMinor' => $price * $quantity];
        }
        return $lines;
    }

    /** Interne : jamais exposée comme changement de statut libre depuis le navigateur. */
    public function releaseReservation(int $orderId, string $status = 'expiree'): bool
    {
        if (!in_array($status, ['expiree','annulee'], true)) throw new DomainException('Transition de réservation interdite.');
        $this->database->beginTransaction();
        try {
            $order = $this->orders->find($orderId, true);
            if ($order === null || $order['reservation_expire_at'] === null || $order['statut'] !== 'en_attente_paiement' || $order['stock_restitue_at'] !== null || ($status === 'expiree' && !$order['reservation_expired'])) {
                $this->database->commit(); return false;
            }
            foreach ($this->items->forOrder($orderId) as $line) {
                $this->variants->lock((int) $line['id_variante']);
                $this->variants->restore((int) $line['id_variante'], (int) $line['quantite']);
            }
            $this->orders->release($orderId, $status);
            $this->database->commit(); return true;
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    /** Point d'entrée borné pour un futur cron ; aucun scheduler ajouté. */
    public function expireReservations(int $limit = 100): int
    {
        $released = 0;
        foreach ($this->orders->expiredIds(max(1, min(1000, $limit))) as $id) $released += (int) $this->releaseReservation((int) $id);
        return $released;
    }

    public function forUser(int $userId, int $orderId): array
    {
        $order = $this->orders->find($orderId);
        if ($userId < 1 || $order === null || (int) $order['id_utilisateur'] !== $userId) throw new DomainException('Commande introuvable pour ce compte.');
        if ($order['reservation_expired'] && $order['statut'] === 'en_attente_paiement') {
            $this->releaseReservation($orderId);
            $order = $this->orders->find($orderId);
        }
        $order['totalFormatted'] = $this->money->format($this->money->minor($order['total']));
        $order['cdfFormatted'] = $this->money->format($this->money->usdToCdfAtRate($this->money->minor($order['total']), $order['taux_usd_cdf']), 'CDF');
        return ['order' => $order, 'lines' => $this->items->forOrder($orderId), 'delivery' => $this->deliveries->forOrder($orderId)];
    }
}
