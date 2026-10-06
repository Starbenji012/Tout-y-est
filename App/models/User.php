<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Effectue uniquement les opérations de persistance des utilisateurs. */
final class User
{
    /** Reçoit la connexion PDO centralisée. */
    public function __construct(private readonly PDO $database)
    {
    }

    /** Recherche un utilisateur grâce à son adresse e-mail normalisée. */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id_utilisateur, nom, prenom, email, telephone, mot_de_passe, role, statut,
                    email_verified_at
             FROM utilisateur
             WHERE email = :email
             LIMIT 1',
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** Remplace le hash du mot de passe après validation d'un jeton. */
    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $statement = $this->database->prepare(
            'UPDATE utilisateur SET mot_de_passe = :password_hash WHERE id_utilisateur = :user_id',
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    /** Vérifie l'unicité d'une adresse avant l'inscription. */
    public function emailExists(string $email): bool
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM utilisateur WHERE email = :email',
        );
        $statement->execute(['email' => $email]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** Enregistre un nouvel utilisateur et retourne son identifiant. */
    public function create(array $user): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, statut, date_creation)
             VALUES (:nom, :prenom, :email, :mot_de_passe, :role, :statut, NOW())',
        );
        $statement->execute($user);

        return (int) $this->database->lastInsertId();
    }

    /** Retourne les informations nécessaires à l'état de vérification d'un compte. */
    public function findVerificationState(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id_utilisateur, nom, prenom, email, email_verified_at,
                    email_verification_expires_at, email_verification_sent_at
             FROM utilisateur
             WHERE id_utilisateur = :user_id
             LIMIT 1',
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** Enregistre un nouveau token si le délai de renvoi est respecté. */
    public function storeVerificationToken(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        string $sentAt,
        string $cooldownThreshold,
    ): bool {
        $statement = $this->database->prepare(
            'UPDATE utilisateur
             SET email_verification_token_hash = :token_hash,
                 email_verification_expires_at = :expires_at,
                 email_verification_sent_at = :sent_at
             WHERE id_utilisateur = :user_id
               AND email_verified_at IS NULL
               AND (email_verification_sent_at IS NULL OR email_verification_sent_at <= :cooldown_threshold)',
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'sent_at' => $sentAt,
            'user_id' => $userId,
            'cooldown_threshold' => $cooldownThreshold,
        ]);

        return $statement->rowCount() === 1;
    }

    /** Lit un token hashé afin de distinguer expiration et invalidité. */
    public function findByVerificationTokenHash(string $tokenHash): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id_utilisateur, email_verified_at, email_verification_expires_at
             FROM utilisateur
             WHERE email_verification_token_hash = :token_hash
             LIMIT 1',
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** Consomme définitivement un token encore valide. */
    public function consumeVerificationToken(string $tokenHash, string $verifiedAt): bool
    {
        $statement = $this->database->prepare(
            'UPDATE utilisateur
             SET email_verified_at = :verified_at,
                 email_verification_token_hash = NULL,
                 email_verification_expires_at = NULL
             WHERE email_verification_token_hash = :token_hash
               AND email_verified_at IS NULL
               AND email_verification_expires_at >= :valid_at',
        );
        $statement->execute([
            'verified_at' => $verifiedAt,
            'valid_at' => $verifiedAt,
            'token_hash' => $tokenHash,
        ]);

        return $statement->rowCount() === 1;
    }

    /** Invalide un token qui n'a pas pu être envoyé sans effacer le délai de renvoi. */
    public function clearUnsentVerificationToken(int $userId, string $tokenHash): void
    {
        $statement = $this->database->prepare(
            'UPDATE utilisateur
             SET email_verification_token_hash = NULL,
                 email_verification_expires_at = NULL
             WHERE id_utilisateur = :user_id
               AND email_verification_token_hash = :token_hash',
        );
        $statement->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
    }
}
