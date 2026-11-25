<?php
declare(strict_types=1);

// Commande locale explicite. Pas d'application implicite au démarrage HTTP.
require_once __DIR__.'/PhotoPreparationMigration.php';
if (PHP_SAPI!=='cli'||($argv[1]??'')!=='--local-backup-and-migrate') throw new RuntimeException('Usage : php Database/migrations/migrate-photo-preparation.php --local-backup-and-migrate');
$root=dirname(__DIR__,2);$config=require $root.'/Config/database.php';
if ($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Cible locale tout_y_est obligatoire.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$db=$connect('tout_y_est');
$dumpTool='C:/xampp/mysql/bin/mysqldump.exe';$mysqlTool='C:/xampp/mysql/bin/mysql.exe';
if(!is_file($dumpTool)||!is_file($mysqlTool))throw new RuntimeException('Outils MariaDB locaux absents : adapter les chemins avant utilisation.');
$snapshot=static function(PDO $db,array $tables):array {
    $result=[];
    foreach($tables as $table){
        if(str_contains($table,"\0"))throw new RuntimeException('Nom de table inattendu.');
        // Noms historiques accentués conservés ; citation SQL des identifiants.
        $rows=$db->query('SELECT * FROM `'.str_replace('`','``',$table).'`')->fetchAll();
        // serialize conserve aussi les octets binaires, sans substitution UTF-8.
        $encoded=array_map(static fn(array $row):string=>serialize($row),$rows);sort($encoded);
        $result[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$encoded))];
    }
    ksort($result);return $result;
};
$env=getenv();$env['MYSQL_PWD']=$config['password'];
$run=static function(array $command,?string $input=null)use($env):void {
    $descriptors=[0=>$input===null?['pipe','r']:['file',$input,'r'],1=>['pipe','w'],2=>['pipe','w']];
    $process=proc_open($command,$descriptors,$pipes,null,$env);
    if(!is_resource($process))throw new RuntimeException('Outil MariaDB non démarré.');
    if($input===null)fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Échec outil MariaDB : '.substr($err.$out,0,600));
};
$common=['--host='.$config['host'],'--port='.$config['port'],'--user='.$config['username'],'--default-character-set=utf8mb4'];
App\Services\CatalogueWriteLock::acquire($db,30);
$clone=null;
try {
    App\Migrations\PhotoPreparationMigration::preflight($db);
    $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $before=$snapshot($db,$tables);
    $directory=$root.'/Storage/backups';if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('Dossier sauvegarde indisponible.');
    $backup=$directory.'/photos-before-S1.18-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
    $run(array_merge([$dumpTool],$common,['--single-transaction','--quick','--routines','--events','--triggers','--hex-blob','--result-file='.$backup,'tout_y_est']));
    if(!is_file($backup)||filesize($backup)<100)throw new RuntimeException('Sauvegarde vide : migration interdite.');
    $dump=file_get_contents($backup);
    if(preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE)/mi',$dump))throw new RuntimeException('Sauvegarde non isolable : restauration de contrôle refusée.');
    // Une restauration réelle en base jetable vérifie la sauvegarde AVANT tout DDL local.
    $clone='tout_y_est_photo_backup_test_'.bin2hex(random_bytes(8));
    $db->exec('CREATE DATABASE `'.$clone.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $run(array_merge([$mysqlTool],$common,['--database='.$clone]),$backup);
    $copy=$connect($clone);
    if($snapshot($copy,$tables)!==$before)throw new RuntimeException('Sauvegarde restaurée différente : aucune migration locale autorisée.');
    if($snapshot($db,$tables)!==$before)throw new RuntimeException('Données modifiées pendant la sauvegarde : migration arrêtée.');
    echo 'BACKUP_VERIFIED '.basename($backup).' SHA256='.hash_file('sha256',$backup).PHP_EOL;
    $trial=App\Migrations\PhotoPreparationMigration::apply($copy);
    if($snapshot($copy,$tables)!==$before)throw new RuntimeException('Migration jetable : donnees existantes modifiees.');
    echo 'DISPOSABLE_MIGRATION_OK '.json_encode($trial).PHP_EOL;
    $result=App\Migrations\PhotoPreparationMigration::apply($db);
    $after=$snapshot($db,$tables);
    $manifest=['backup'=>basename($backup),'backup_sha256'=>hash_file('sha256',$backup),'database'=>'tout_y_est','migration'=>$result,'before'=>$before,'after'=>$after,'unchanged'=>$before===$after];
    file_put_contents($backup.'.verification.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($before!==$after)throw new RuntimeException('Différence de données détectée après migration : consulter le manifeste, ne pas restaurer automatiquement.');
    echo 'LOCAL_PHOTO_MIGRATION_OK '.json_encode($result,JSON_THROW_ON_ERROR).' ORIGINAL_TABLES_IDENTICAL='.count($tables).PHP_EOL;
} finally {
    if($clone!==null){
        if(!preg_match('/^tout_y_est_photo_backup_test_[a-f0-9]{16}$/D',$clone))throw new RuntimeException('Nettoyage clone refusé.');
        $copy=null;$db->exec('DROP DATABASE IF EXISTS `'.$clone.'`');
    }
    App\Services\CatalogueWriteLock::release($db);
}
