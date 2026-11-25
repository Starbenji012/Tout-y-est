<?php
declare(strict_types=1);
namespace App\Migrations;

use PDO;
use RuntimeException;

require_once dirname(__DIR__,2).'/App/services/CatalogueWriteLock.php';

/** DDL MariaDB : hors transaction métier, sous verrou de maintenance. */
final class SkuMigration
{
    public static function apply(PDO $db): array
    {
        if ($db->inTransaction()) throw new RuntimeException('Migration hors transaction requise.');
        \App\Services\CatalogueWriteLock::acquire($db,30);
        try {
            self::preflight($db);
            $db->exec(file_get_contents(__DIR__.'/S1.17_sku_registry.sql'));
            // Bloque aussi les écritures directes/commandes sur les sources pendant l'import.
            $db->exec('LOCK TABLES variante_produit READ, ligne_commande READ, sku_sequence WRITE, sku_attribution WRITE');
            try {
                $sources=self::sources($db);
                self::checkSources($sources);
                $insert=$db->prepare("INSERT INTO sku_attribution(cle,sku,origine,id_variante) VALUES(?,?,'historique',?)");
                $find=$db->prepare('SELECT id_variante FROM sku_attribution WHERE sku=?');
                $added=0;
                foreach ($sources as $row) {
                    $find->execute([$row['sku']]);$owner=$find->fetchColumn();
                    if ($owner!==false) {
                        if ((string)$owner!==(string)$row['id_variante']) throw new RuntimeException('SKU du registre incompatible avec son propriétaire historique.');
                        continue;
                    }
                    $insert->execute([hash('sha256','historique:'.$row['sku']),$row['sku'],$row['id_variante']]);$added++;
                }
                return ['references_sources'=>count($sources),'references_importees'=>$added];
            } finally { $db->exec('UNLOCK TABLES'); }
        } finally { \App\Services\CatalogueWriteLock::release($db); }
    }

    public static function preflight(PDO $db): void
    {
        if (!str_contains((string)$db->query('SELECT VERSION()')->fetchColumn(),'MariaDB')) throw new RuntimeException('Migration prévue pour MariaDB ; compatibilité du serveur à valider.');
        $columns=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='variante_produit' AND COLUMN_NAME='sku') OR (TABLE_NAME='ligne_commande' AND COLUMN_NAME='sku_snapshot'))")->fetchAll();
        if (count($columns)!==2) throw new RuntimeException('Colonnes SKU sources manquantes.');
        foreach ($columns as $column) if ($column['COLUMN_TYPE']!=='varchar(100)'||$column['COLLATION_NAME']!=='utf8mb4_unicode_ci') throw new RuntimeException('Type ou collation SKU incompatible.');
        if (!(int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='variante_produit' AND COLUMN_NAME='sku' AND NON_UNIQUE=0 AND INDEX_NAME='uq_variante_produit_sku'")->fetchColumn()) throw new RuntimeException('Index unique SKU absent.');
        $expected=[
            'sku_sequence'=>['id','dernier_numero'],
            'sku_attribution'=>['cle','sku','origine','id_variante','empreinte','resultat','cree_at'],
        ];
        foreach ($expected as $table=>$names) {
            $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$actual=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($actual!==[]&&$actual!==$names) throw new RuntimeException('Table SKU préexistante incompatible : '.$table);
            if ($actual!==[]) {
                $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
                if ($q->fetchColumn()!=='InnoDB') throw new RuntimeException('Moteur SKU incompatible : '.$table);
                if ($table==='sku_sequence') {
                    $type=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sku_sequence' AND COLUMN_NAME='dernier_numero'")->fetchColumn();
                    if ($type!=='bigint(20) unsigned') throw new RuntimeException('Compteur SKU incompatible.');
                    $rows=$db->query('SELECT id FROM sku_sequence')->fetchAll(PDO::FETCH_COLUMN);
                    if (array_diff(array_map('intval',$rows),[1])) throw new RuntimeException('Plusieurs compteurs SKU détectés.');
                } else {
                    $skuColumn=$db->query("SELECT COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sku_attribution' AND COLUMN_NAME='sku'")->fetch();
                    if ($skuColumn!==['COLUMN_TYPE'=>'varchar(100)','COLLATION_NAME'=>'utf8mb4_unicode_ci']) throw new RuntimeException('Type ou collation du registre SKU incompatible.');
                    $primary=$db->query("SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sku_attribution' AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_COLUMN);
                    if ($primary!==['cle']) throw new RuntimeException('Clé idempotente du registre incompatible.');
                    if ((int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sku_attribution' AND COLUMN_NAME='sku' AND NON_UNIQUE=0 AND INDEX_NAME='uq_sku_attribution_reference'")->fetchColumn()!==1) throw new RuntimeException('Unicité du registre SKU incompatible.');
                    $q=$db->prepare('SELECT id_variante FROM sku_attribution WHERE sku=?');
                    foreach (self::sources($db) as $row) {
                        $q->execute([$row['sku']]);$owner=$q->fetchColumn();
                        if ($owner!==false&&(string)$owner!==(string)$row['id_variante']) throw new RuntimeException('Attribution historique incompatible avec le registre existant.');
                    }
                }
            }
        }
        self::checkSources(self::sources($db));
    }

    private static function sources(PDO $db): array
    {
        return $db->query('SELECT sku,MIN(id_variante) AS id_variante,COUNT(DISTINCT id_variante) AS proprietaires FROM (SELECT sku,id_variante FROM variante_produit UNION ALL SELECT sku_snapshot AS sku,id_variante FROM ligne_commande) sources GROUP BY sku ORDER BY sku')->fetchAll();
    }

    private static function checkSources(array $sources): void
    {
        foreach ($sources as $row) if ((int)$row['proprietaires']!==1 || trim($row['sku'])==='' || $row['sku']!==trim($row['sku'])) throw new RuntimeException('Référence historique ambiguë ou incompatible : import arrêté.');
    }
}
