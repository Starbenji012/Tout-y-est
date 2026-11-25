<?php
declare(strict_types=1);
namespace App\Migrations;
use PDO;
use RuntimeException;
require_once dirname(__DIR__,2).'/App/services/CatalogueWriteLock.php';
final class PhotoPreparationMigration
{
    public static function preflight(PDO $db): void
    {
        if($db->inTransaction())throw new RuntimeException('Migration hors transaction requise.');
        $expected=['product_photo_batch'=>['ticket','owner_id','expires_at','product_id','cleaned_at'],
            'product_photo_item'=>['batch_ticket','ticket','state','source_hash','manifest','updated_at']];
        foreach($expected as $table=>$columns){
            $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$actual=$q->fetchAll(PDO::FETCH_COLUMN);
            if($actual!==[]&&$actual!==$columns)throw new RuntimeException('Table préparatoire incompatible : '.$table);
            if($actual!==[]){
                $ddl=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                $required=$table==='product_photo_item'?['PRIMARY KEY (`batch_ticket`,`ticket`)','UNIQUE KEY `photo_source_per_batch` (`batch_ticket`,`source_hash`)','FOREIGN KEY (`batch_ticket`) REFERENCES `product_photo_batch` (`ticket`) ON DELETE CASCADE']:['PRIMARY KEY (`ticket`)'];
                foreach(array_merge($required,['ENGINE=InnoDB','ascii_bin']) as $fragment)if(!str_contains($ddl,$fragment))throw new RuntimeException('Contraintes préparatoires incompatibles : '.$table);
            }
        }
    }
    public static function apply(PDO $db): array
    {
        \App\Services\CatalogueWriteLock::acquire($db);
        try {
            self::preflight($db);
            $db->exec(file_get_contents(__DIR__.'/S1.18_product_photo_preparation.sql'));
            self::preflight($db);
            return ['migration'=>'S1.18','tables'=>['product_photo_batch','product_photo_item']];
        } finally {\App\Services\CatalogueWriteLock::release($db);}
    }
}
