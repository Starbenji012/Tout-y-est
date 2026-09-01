<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Crée et conserve l'unique connexion PDO de l'application. */
final class Database
{
    private static ?PDO $connection = null;

    /** Empêche l'instanciation de cette classe utilitaire. */
    private function __construct()
    {
    }

    /** Ouvre la connexion au premier appel puis réutilise la même instance. */
    public static function connect(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        self::$connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'],
        );

        return self::$connection;
    }
}
