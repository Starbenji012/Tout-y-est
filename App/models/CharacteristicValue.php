<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class CharacteristicValue
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findByProduct(int $productId): array
    {
        $statement = $this->database->prepare(
            'SELECT c.nom, c.type, v.valeur
             FROM `valeur_caractéristique` v
             INNER JOIN `caractéristique` c ON c.id_caracteristique = v.id_caracteristique
             WHERE v.id_produit = :product_id
             ORDER BY c.nom, v.id_valeur',
        );
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
