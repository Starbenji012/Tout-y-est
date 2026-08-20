<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Review
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findPublishedByProduct(int $productId, int $limit = 5): array
    {
        $statement = $this->database->prepare(
            "SELECT a.note, a.commentaire, a.date_avis, u.prenom, u.nom
             FROM avis a
             INNER JOIN utilisateur u ON u.id_utilisateur = a.id_utilisateur
             WHERE a.id_produit = :product_id
               AND LOWER(a.status) NOT IN ('rejete', 'rejected')
             ORDER BY a.date_avis DESC
             LIMIT :limit",
        );
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
