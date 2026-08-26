<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private readonly PDO $database)
    {
    }

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

    public function emailExists(string $email): bool
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM utilisateur WHERE email = :email',
        );
        $statement->execute(['email' => $email]);

        return (int) $statement->fetchColumn() > 0;
    }

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
