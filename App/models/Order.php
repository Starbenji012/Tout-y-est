<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

/** Persistance des commandes et de leur état de réservation. */
final class Order
{
    public function __construct(private readonly PDO $database) {}

    public function findByKey(int $userId, string $hash): ?array
    {
        // Le compte est déjà verrouillé : éviter un gap lock entre deux clients.
        $query = $this->database->prepare('SELECT * FROM commande WHERE id_utilisateur = ? AND cle_idempotence = ?');
        $query->execute([$userId, $hash]);
        return $query->fetch() ?: null;
    }

    public function find(int $id, bool $lock = false): ?array
    {
        $query = $this->database->prepare('SELECT *, reservation_expire_at <= NOW() AS reservation_expired FROM commande WHERE id_commande = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $query = $this->database->prepare("INSERT INTO commande (id_utilisateur, numero_commande, statut, sous_total, frais_livraison, total, devise_reference, taux_usd_cdf, cle_idempotence, reservation_expire_at) VALUES (:user_id, :number, 'en_attente_paiement', :subtotal, :shipping, :total, 'USD', :rate, :key_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        $query->execute($data);
        return (int) $this->database->lastInsertId();
    }

    public function release(int $id, string $status): void
    {
        $query = $this->database->prepare('UPDATE commande SET statut = ?, stock_restitue_at = NOW() WHERE id_commande = ?');
        $query->execute([$status, $id]);
    }

    public function expiredIds(int $limit): array
    {
        $query = $this->database->prepare("SELECT id_commande FROM commande WHERE statut = 'en_attente_paiement' AND reservation_expire_at <= NOW() ORDER BY reservation_expire_at LIMIT ?");
        $query->bindValue(1, $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }
}
