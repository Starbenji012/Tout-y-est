<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

/** Ne lit et n'enregistre que les adresses du compte demande. */
final class UserAddress
{
    public function __construct(private readonly PDO $database) {}

    public function forUser(int $userId): array
    {
        $statement = $this->database->prepare('SELECT id_adresse, id_zone_livraison, nom_destinataire, prenom_destinataire, telephone, commune, adresse, repere, instructions, adresse_par_defaut FROM adresse_utilisateur WHERE id_utilisateur = ? ORDER BY adresse_par_defaut DESC, id_adresse DESC');
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function findForUser(int $userId, int $addressId): ?array
    {
        $statement = $this->database->prepare('SELECT id_adresse, id_zone_livraison, nom_destinataire, prenom_destinataire, telephone, commune, adresse, repere, instructions, adresse_par_defaut FROM adresse_utilisateur WHERE id_utilisateur = ? AND id_adresse = ?');
        $statement->execute([$userId, $addressId]);
        return $statement->fetch() ?: null;
    }

    public function lockForUser(int $userId, int $addressId): ?array
    {
        $query = $this->database->prepare('SELECT * FROM adresse_utilisateur WHERE id_utilisateur = ? AND id_adresse = ? LOCK IN SHARE MODE');
        $query->execute([$userId, $addressId]);
        return $query->fetch() ?: null;
    }

    /** Le verrou du compte serialise les changements d'adresse par defaut. */
    public function createForUser(int $userId, array $address, bool $makeDefault): int
    {
        $this->database->beginTransaction();
        try {
            $lock = $this->database->prepare('SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = ? FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetchColumn()) {
                throw new \RuntimeException('Compte introuvable.');
            }
            if ($makeDefault) {
                $clear = $this->database->prepare('UPDATE adresse_utilisateur SET adresse_par_defaut = 0 WHERE id_utilisateur = ?');
                $clear->execute([$userId]);
            }
            $statement = $this->database->prepare('INSERT INTO adresse_utilisateur (id_utilisateur, id_zone_livraison, nom_destinataire, prenom_destinataire, telephone, commune, adresse, repere, instructions, adresse_par_defaut) VALUES (:user_id, :id_zone_livraison, :nom_destinataire, :prenom_destinataire, :telephone, :commune, :adresse, :repere, :instructions, :is_default)');
            $statement->execute($address + ['user_id' => $userId, 'is_default' => (int) $makeDefault]);
            $id = (int) $this->database->lastInsertId();
            $this->database->commit();
            return $id;
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }
}
