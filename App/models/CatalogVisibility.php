<?php
declare(strict_types=1);
namespace App\Models;

/** Contrat public : seul un état explicitement actif autorise l'exposition. */
final class CatalogVisibility
{
    public static function isActive(string $status): bool
    {
        return in_array(strtolower($status), ['actif', 'active'], true);
    }

    /** Expression interne uniquement, jamais un nom de colonne fourni par le client. */
    public static function activeSql(string $column): string
    {
        return "LOWER($column) IN ('actif', 'active')";
    }

    public static function productSql(): string
    {
        return self::activeSql('p.statut') . ' AND ' . self::activeSql('c.statut');
    }
}
