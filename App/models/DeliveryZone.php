<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class DeliveryZone
{
    public function __construct(private readonly PDO $database) {}

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
}
