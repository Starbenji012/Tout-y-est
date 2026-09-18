<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Effectue uniquement les opérations de persistance des favoris. */
final class Favorite
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** Vérifie que le compte lié à la session existe toujours. */
    public function userExists(int $userId): bool
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM utilisateur WHERE id_utilisateur = :user_id',
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** Retourne uniquement les produits favoris du compte demandé. */
    public function productIdsForUser(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT id_produit FROM favori WHERE id_utilisateur = :user_id ORDER BY date_ajout DESC, id_favori DESC',
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Ajoute un favori sans dupliquer une relation existante. */
    public function add(int $userId, int $productId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO favori (id_utilisateur, id_produit, date_ajout)
             VALUES (:user_id, :product_id, NOW())
             ON DUPLICATE KEY UPDATE id_favori = id_favori',
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);
    }

    /** Retire uniquement la relation appartenant au compte demandé. */
    public function remove(int $userId, int $productId): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM favori WHERE id_utilisateur = :user_id AND id_produit = :product_id',
        );
        $statement->execute(['user_id' => $userId, 'product_id' => $productId]);
    }

    /** Fusionne une sélection validée dans une transaction courte. */
    public function merge(int $userId, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'INSERT INTO favori (id_utilisateur, id_produit, date_ajout)
                 VALUES (:user_id, :product_id, NOW())
                 ON DUPLICATE KEY UPDATE id_favori = id_favori',
            );

            foreach ($productIds as $productId) {
                $statement->execute(['user_id' => $userId, 'product_id' => $productId]);
            }

            $this->database->commit();
        } catch (\Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }
}
