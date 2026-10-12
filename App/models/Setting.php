<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Reutilise les parametres existants, sans connexion supplementaire. */
final class Setting
{
    public function __construct(private readonly PDO $database) {}

    public function moneyConfiguration(): ?string
    {
        $value = $this->database->query("SELECT valeur FROM parametre WHERE cle = 'monetary_config'")->fetchColumn();
        return is_string($value) ? $value : null;
    }

    public function updateMoneyConfiguration(string $json): void
    {
        $statement = $this->database->prepare("UPDATE parametre SET valeur = ? WHERE cle = 'monetary_config'");
        $statement->execute([$json]);
    }
}
