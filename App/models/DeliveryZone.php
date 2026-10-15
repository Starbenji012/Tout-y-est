<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class DeliveryZone
{
    public function __construct(private readonly PDO $database) {}

    public function all(): array
    {
        return $this->database->query('SELECT id_zone_livraison, nom, frais_reference, devise_reference, statut FROM zone_livraison ORDER BY nom')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT id_zone_livraison, nom, frais_reference, devise_reference, statut FROM zone_livraison WHERE id_zone_livraison = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function save(?int $id, string $name, string $fees, string $status): int
    {
        if ($id === null) {
            $statement = $this->database->prepare("INSERT INTO zone_livraison (nom, frais_reference, devise_reference, statut) VALUES (?, ?, 'USD', ?)");
            $statement->execute([$name, $fees, $status]);
            return (int) $this->database->lastInsertId();
        }
        $statement = $this->database->prepare("UPDATE zone_livraison SET nom = ?, frais_reference = ?, devise_reference = 'USD', statut = ? WHERE id_zone_livraison = ?");
        $statement->execute([$name, $fees, $status, $id]);
        return $id;
    }

    public function active(): array
    {
        return $this->database->query("SELECT id_zone_livraison, nom, frais_reference, devise_reference FROM zone_livraison WHERE statut = 'actif' ORDER BY nom")->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $statement = $this->database->prepare("SELECT id_zone_livraison, nom, frais_reference, devise_reference FROM zone_livraison WHERE id_zone_livraison = ? AND statut = 'actif'");
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function lockActive(int $id): ?array
    {
        $query = $this->database->prepare("SELECT id_zone_livraison, nom, frais_reference, devise_reference FROM zone_livraison WHERE id_zone_livraison = ? AND statut = 'actif' LOCK IN SHARE MODE");
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }
}
