<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Lit les caractéristiques associées aux produits. */
final class CharacteristicValue
{
    /** Reçoit la connexion PDO centralisée. */
    public function __construct(private readonly PDO $database)
    {
    }

    /** Retourne les caractéristiques affichables d'un produit. */
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

    /** Retourne les valeurs disponibles pour construire les filtres contextuels. */
    public function findCatalogFacets(array $categorySlugs = []): array
    {
        $conditions = ["LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"];
        $parameters = [];

        if ($categorySlugs !== []) {
            $placeholders = [];

            foreach (array_values($categorySlugs) as $index => $slug) {
                $key = 'category_' . $index;
                $placeholders[] = ':' . $key;
                $parameters[$key] = $slug;
            }

            $conditions[] = 'cat.slug_ IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = $this->database->prepare(
            'SELECT c.nom, v.valeur, COUNT(DISTINCT p.id_produit) AS product_count
             FROM `valeur_caractéristique` v
             INNER JOIN `caractéristique` c ON c.id_caracteristique = v.id_caracteristique
             INNER JOIN produit p ON p.id_produit = v.id_produit
             INNER JOIN categorie cat ON cat.id_categorie = p.id_categorie
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY c.nom, v.valeur
             ORDER BY c.nom, product_count DESC, v.valeur',
        );

        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }

        $statement->execute();

        return $statement->fetchAll();
    }
}
