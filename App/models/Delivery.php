<?php

declare(strict_types=1);
namespace App\Models;
use PDO;

final class Delivery
{
    public function __construct(private readonly PDO $database) {}

    public function create(int $orderId, array $address, array $zone): void
    {
        $columns = ['nom_destinataire','prenom_destinataire','telephone','commune','adresse','repere','instructions','latitude','longitude'];
        $values = [$orderId, $zone['id_zone_livraison'], $zone['nom'], $zone['frais_reference']];
        foreach ($columns as $name) $values[] = $address[$name] ?? null;
        $snapshots = implode(',', array_map(static fn (string $name): string => $name . '_snapshot', $columns));
        $query = $this->database->prepare('INSERT INTO livraison (id_commande,id_zone_livraison,nom_zone_snapshot,frais_snapshot,' . $snapshots . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $query->execute($values);
    }

    public function forOrder(int $id): ?array
    {
        $query = $this->database->prepare('SELECT * FROM livraison WHERE id_commande = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function lockForOrder(int $id): ?array
    {
        $query = $this->database->prepare('SELECT * FROM livraison WHERE id_commande = ? FOR UPDATE');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function cancel(int $id): void
    {
        $query = $this->database->prepare("UPDATE livraison SET statut = 'annulee' WHERE id_commande = ?");
        $query->execute([$id]);
    }

    public function assignCourier(int $id, string $name): void
    {
        $query=$this->database->prepare('UPDATE livraison SET nom_livreur_snapshot=? WHERE id_commande=?');
        $query->execute([$name,$id]);
    }

    public function advance(int $id, string $status): void
    {
        $date = match ($status) {
            'en_preparation'=>'date_preparation', 'en_livraison'=>'date_expedition', 'livree'=>'date_livraison',
            default=>throw new \InvalidArgumentException('Progression invalide.'),
        };
        $query = $this->database->prepare("UPDATE livraison SET statut=?, $date=COALESCE($date,NOW()) WHERE id_commande=?");
        $query->execute([$status,$id]);
    }
}
