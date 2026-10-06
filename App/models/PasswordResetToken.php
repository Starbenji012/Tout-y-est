<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Persiste uniquement les hash des jetons de réinitialisation actifs. */
final class PasswordResetToken
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** Remplace atomiquement le jeton actif d'un utilisateur. */
    public function replaceForUser(int $userId, string $tokenHash, string $expiresAt, string $createdAt): void
    {
        $this->database->beginTransaction();

        try {
            $delete = $this->database->prepare(
                'DELETE FROM password_reset_token WHERE id_utilisateur = :user_id',
            );
            $delete->execute(['user_id' => $userId]);

            $insert = $this->database->prepare(
                'INSERT INTO password_reset_token (id_utilisateur, token_hash, expires_at, date_creation)
                 VALUES (:user_id, :token_hash, :expires_at, :date_creation)',
            );
            $insert->execute([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'date_creation' => $createdAt,
            ]);
            $this->database->commit();
        } catch (\Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    /** Retourne le propriétaire et l'expiration d'un hash exact. */
    public function findByHash(string $tokenHash): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id_utilisateur, expires_at
             FROM password_reset_token
             WHERE token_hash = :token_hash
             LIMIT 1',
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $token = $statement->fetch();

        return is_array($token) ? $token : null;
    }

    /** Supprime un hash lors d'une réussite ou d'un échec d'envoi. */
    public function deleteByHash(string $tokenHash): bool
    {
        $statement = $this->database->prepare(
            'DELETE FROM password_reset_token WHERE token_hash = :token_hash',
        );
        $statement->execute(['token_hash' => $tokenHash]);

        return $statement->rowCount() === 1;
    }
}