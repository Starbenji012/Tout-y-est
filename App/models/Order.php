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
        $query = $this->database->prepare('SELECT *, reservation_expire_at <= NOW() AS reservation_expired, TIMESTAMPDIFF(SECOND, NOW(), reservation_expire_at) AS reservation_remaining_seconds FROM commande WHERE id_commande = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $query = $this->database->prepare("INSERT INTO commande (id_utilisateur, numero_commande, statut, sous_total, frais_livraison, total, devise_reference, taux_usd_cdf, cle_idempotence, reservation_expire_at) VALUES (:user_id, :number, 'en_attente_paiement', :subtotal, :shipping, :total, 'USD', :rate, :key_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        $query->execute($data);
        return (int) $this->database->lastInsertId();
    }

    /** Numéro commercial indépendant de l'ID commande, alloué par InnoDB. */
    public function createWithNumber(array $data): int
    {
        if (!$this->database->inTransaction()) {
            throw new \LogicException('L’attribution du numéro exige la transaction de création.');
        }
        // Même en cas de rollback, MariaDB 10.4 ne recycle pas la valeur consommée.
        $this->database->exec('INSERT INTO numero_commande_sequence () VALUES ()');
        $number = self::formatNumber($this->database->lastInsertId());
        return $this->create(array_replace($data, ['number' => $number]));
    }

    public static function formatNumber(string $number): string
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $number)) throw new \InvalidArgumentException('Numéro commercial invalide.');
        return 'TYE-' . str_pad($number, 3, '0', STR_PAD_LEFT);
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

    public function confirmCash(int $id): void
    {
        $query = $this->database->prepare("UPDATE commande SET statut = 'confirmee', reservation_expire_at = NULL WHERE id_commande = ?");
        $query->execute([$id]);
    }

    public function advance(int $id, string $status): void
    {
        if (!in_array($status, ['en_preparation','en_livraison','livree'], true)) throw new \InvalidArgumentException('Progression invalide.');
        $query = $this->database->prepare('UPDATE commande SET statut=? WHERE id_commande=?');
        $query->execute([$status,$id]);
    }

    /** Lecture client bornée, sans clés techniques de création ni effet sur le stock. */
    public function listForUser(int $userId, int $limit, int $offset, ?string $from = null, ?string $until = null): array
    {
        // Bornes de dates plutôt que MONTH(date_commande), pour préserver l'index existant.
        $range = $from !== null && $until !== null;
        $query = $this->database->prepare($this->customerSelect() . ' WHERE c.id_utilisateur = ?' . ($range ? ' AND c.date_commande >= ? AND c.date_commande < ?' : '') . ' ORDER BY c.date_commande DESC, c.id_commande DESC LIMIT ? OFFSET ?');
        $query->bindValue(1, $userId, PDO::PARAM_INT);
        $position = 2;
        if ($range) { $query->bindValue($position++, $from); $query->bindValue($position++, $until); }
        $query->bindValue($position++, $limit, PDO::PARAM_INT);
        $query->bindValue($position, $offset, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }

    /** Seulement les mois ayant des commandes du propriétaire, jamais ceux d'un autre compte. */
    public function monthsForUser(int $userId): array
    {
        $query = $this->database->prepare("SELECT DISTINCT DATE_FORMAT(date_commande, '%Y-%m') AS month FROM commande WHERE id_utilisateur = ? ORDER BY month DESC");
        $query->execute([$userId]);
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findForUser(int $userId, int $orderId): ?array
    {
        $query = $this->database->prepare($this->customerSelect() . ' WHERE c.id_utilisateur = ? AND c.id_commande = ?');
        $query->execute([$userId, $orderId]);
        return $query->fetch() ?: null;
    }

    private function customerSelect(): string
    {
        // Un paiement encaissé prime sur une tentative ultérieure échouée.
        return "SELECT c.id_commande, c.numero_commande, c.date_commande, c.statut,
            c.sous_total, c.frais_livraison, c.total, c.devise_reference, c.taux_usd_cdf,
            p.statut AS paiement_statut, m.nom AS paiement_mode
            FROM commande c
            LEFT JOIN paiement p ON p.id_paiement = (SELECT pp.id_paiement FROM paiement pp
                WHERE pp.id_commande = c.id_commande
                ORDER BY (pp.statut IN ('paye','payee')) DESC, pp.id_paiement DESC LIMIT 1)
            LEFT JOIN mode_paiement m ON m.id_mode_paiement = p.id_mode_paiement";
    }
}
