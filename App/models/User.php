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
            'SELECT id_utilisateur, nom, prenom, email, telephone, mot_de_passe, role, statut
             FROM utilisateur
             WHERE email = :email
             LIMIT 1',
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
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

}
