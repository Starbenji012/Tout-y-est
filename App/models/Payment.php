<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

final class Payment
{
    public function __construct(private readonly PDO $database) {}
    public function forOrder(int $orderId, bool $lock = false): array
    {
        $query = $this->database->prepare('SELECT p.*, m.nom AS mode FROM paiement p JOIN mode_paiement m USING (id_mode_paiement) WHERE id_commande = ? ORDER BY id_paiement' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$orderId]);
        return $query->fetchAll();
    }
    /** Le montant attendu ne prouve jamais l'encaissement. */
    public function createCash(array $order, int $modeId): int
    {
        $query = $this->database->prepare("INSERT INTO paiement (id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES (?,?,?,'a_encaisser',?)");
        $query->execute([$order['id_commande'], $modeId, $order['total'], $order['taux_usd_cdf']]);
        return (int) $this->database->lastInsertId();
    }
    public function cancel(int $id): void
    {
        $query = $this->database->prepare("UPDATE paiement SET statut = 'annule' WHERE id_paiement = ?");
        $query->execute([$id]);
    }
}
