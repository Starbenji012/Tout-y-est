<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{User, Order, OrderItem, Payment, PaymentMethod, Cart, Delivery, ProductVariant};
use DomainException;
use PDO;
use Throwable;

/** Une transaction unique confirme le cash, sans simuler un encaissement. */
final class PaymentService
{
    public function __construct(private readonly PDO $database) {}

    public function cashAvailable(): bool
    {
        return ((new PaymentMethod($this->database))->cash()['statut'] ?? null) === 'actif';
    }

    public function confirmCash(int $userId, int $orderId): array
    {
        $this->database->beginTransaction();
        try {
            // Même ordre de verrouillage que S1.7 : compte, puis commande.
            $user = (new User($this->database))->lockForOrder($userId);
            if (!$user || !in_array($user['statut'], ['actif','active'], true) || $user['email_verified_at'] === null) throw new DomainException('Connectez-vous avec un compte actif et une adresse e-mail vérifiée.');
            $orders = new Order($this->database);
            $order = $orders->find($orderId, true);
            if (!$order || (int) $order['id_utilisateur'] !== $userId) throw new DomainException('Commande introuvable pour ce compte.');
            $payments = new Payment($this->database);
            $existing = $payments->forOrder($orderId);
            if (in_array($order['statut'], ['confirmee','en_preparation','en_livraison','livree'], true) && count($existing) === 1 && $existing[0]['mode'] === PaymentMethod::CASH && ($existing[0]['statut'] === 'a_encaisser' || ($order['statut'] === 'livree' && $existing[0]['statut'] === 'paye'))) {
                $this->database->commit();
                return ['id' => $orderId, 'paymentId' => (int) $existing[0]['id_paiement'], 'reused' => true];
            }
            // Nouvelle requête après le verrou : l'heure ne précède pas son attente.
            $order = $orders->find($orderId);
            if ($order['statut'] !== 'en_attente_paiement' || $order['reservation_expire_at'] === null || $order['reservation_expired'] || $order['stock_restitue_at'] !== null || $existing !== []) throw new DomainException('Cette commande ne peut plus être confirmée. Retrouvez votre panier.');
            $mode = (new PaymentMethod($this->database))->cash(true);
            if (!$mode || $mode['statut'] !== 'actif') throw new DomainException('Le paiement en espèces est momentanément indisponible.');
            $paymentId = $payments->createCash($order, (int) $mode['id_mode_paiement']);
            $orders->confirmCash($orderId);
            (new Cart($this->database))->subtractOrder($userId, (new OrderItem($this->database))->forOrder($orderId));
            $this->database->commit();
            return ['id' => $orderId, 'paymentId' => $paymentId, 'reused' => false];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    public function confirmedForUser(int $userId, int $orderId): array
    {
        $order = (new Order($this->database))->find($orderId);
        $payments = (new Payment($this->database))->forOrder($orderId);
        if ($userId < 1 || !$order || (int) $order['id_utilisateur'] !== $userId || !in_array($order['statut'], ['confirmee','en_preparation','en_livraison','livree'],true) || count($payments) !== 1 || $payments[0]['mode'] !== PaymentMethod::CASH || !in_array($payments[0]['statut'], ['a_encaisser','paye'],true)) throw new DomainException('Confirmation introuvable pour ce compte.');
        return ['order' => $order, 'delivery' => (new Delivery($this->database))->forOrder($orderId)];
    }

    /** Entrée existante : conserve la gestion transactionnelle pour les appels internes. */
    public function cancelCash(int $orderId): bool
    {
        $this->database->beginTransaction();
        try {
            $result = $this->cancelCashInTransaction($orderId);
            $this->database->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    /** Noyau unique de restitution, partagé avec la transaction Admin + journal. */
    public function cancelCashInTransaction(int $orderId): bool
    {
        if (!$this->database->inTransaction()) throw new \LogicException('Transaction requise pour annuler.');
        $orders = new Order($this->database);
        $order = $orders->find($orderId, true);
        if ($order && $order['statut'] === 'annulee' && $order['stock_restitue_at'] !== null) {
            return false;
        }
        $payments = new Payment($this->database);
        $rows = $payments->forOrder($orderId, true);
        $deliveryModel = new Delivery($this->database);
        $delivery = $deliveryModel->lockForOrder($orderId);
        if (!$order || !in_array($order['statut'], ['confirmee','en_preparation'], true) || $order['stock_restitue_at'] !== null || count($rows) !== 1 || $rows[0]['mode'] !== PaymentMethod::CASH || $rows[0]['statut'] !== 'a_encaisser' || $rows[0]['date_paiement'] !== null || !$delivery || !in_array($delivery['statut'], ['en_attente','en_preparation'], true) || $delivery['date_expedition'] !== null || $delivery['date_livraison'] !== null) throw new DomainException('Cette commande ne peut pas être annulée avant livraison.');
        $variants = new ProductVariant($this->database);
        $lines = (new OrderItem($this->database))->forOrder($orderId);
        usort($lines, static fn (array $a, array $b): int => $a['id_variante'] <=> $b['id_variante']);
        foreach ($lines as $line) {
            $variants->lock((int) $line['id_variante']);
            $variants->restore((int) $line['id_variante'], (int) $line['quantite']);
        }
        $orders->release($orderId, 'annulee');
        $payments->cancel((int) $rows[0]['id_paiement']);
        $deliveryModel->cancel($orderId);
        return true;
    }
}
