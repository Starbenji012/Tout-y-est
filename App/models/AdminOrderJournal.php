<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

/** Journal append-only côté application : aucun UPDATE/DELETE proposé. */
final class AdminOrderJournal
{
    public function __construct(private readonly PDO $database) {}

    public function findAction(int $orderId, string $action): ?array
    {
        $query = $this->database->prepare('SELECT * FROM journal_commande WHERE id_commande=? AND action=?');
        $query->execute([$orderId,$action]);
        return $query->fetch() ?: null;
    }

    /** Les noms actuels identifient les acteurs ; les événements restent historiques. */
    public function forOrder(int $orderId): array
    {
        $query = $this->database->prepare('SELECT j.*,u.nom AS admin_nom,u.prenom AS admin_prenom FROM journal_commande j LEFT JOIN utilisateur u ON u.id_utilisateur=j.id_administrateur WHERE j.id_commande=? ORDER BY j.date_action,j.id_journal');
        $query->execute([$orderId]); return $query->fetchAll();
    }

    public function append(int $orderId, int $adminId, int $paymentId, string $action, array $before, array $after, array $data): void
    {
        if (!$this->database->inTransaction()) throw new \LogicException('Le journal exige la transaction métier.');
        $query = $this->database->prepare('INSERT INTO journal_commande(id_commande,id_administrateur,id_paiement,action,ancien_etat,nouvel_etat,donnees_complementaires) VALUES(?,?,?,?,?,?,?)');
        $encode = static fn(array $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $query->execute([$orderId,$adminId,$paymentId,$action,$encode($before),$encode($after),$encode($data)]);
    }
}
