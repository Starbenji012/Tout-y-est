<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

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

    /** Garde transitoire avant Phase 5, compatible avec la base non migrée. */
    public static function hasOptionsTable(PDO $database): bool
    {
        return (int)$database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='option_produit'")->fetchColumn() === 1;
    }

    public static function publicProductSql(PDO $database): string
    {
        return self::productSql() . (self::hasOptionsTable($database)
            ? ' AND NOT EXISTS (SELECT 1 FROM option_produit public_options WHERE public_options.id_produit=p.id_produit)'
            : '');
    }
}
