<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

/** Verrou de maintenance commun aux écritures catalogue et à la migration SKU. */
final class CatalogueWriteLock
{
    public static function name(PDO $db): string
    {
        return 'tye:catalogue:'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn());
    }

    public static function acquire(PDO $db, int $seconds = 30): void
    {
        $q=$db->prepare('SELECT GET_LOCK(?,?)');
        $q->execute([substr(self::name($db),0,64),$seconds]);
        if ((int)$q->fetchColumn()!==1) throw new DomainException('Catalogue temporairement en maintenance. Réessayez dans quelques instants.',503);
    }

    public static function release(PDO $db): void
    {
        $q=$db->prepare('SELECT RELEASE_LOCK(?)');
        $q->execute([substr(self::name($db),0,64)]);
    }
}
