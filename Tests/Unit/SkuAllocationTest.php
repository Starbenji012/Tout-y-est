<?php
declare(strict_types=1);

// Aucune écriture sur tout_y_est : seules des bases jetables de ce préfixe sont modifiées.
$root=dirname(__DIR__,2);
ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if (!str_starts_with($class,'App\\')) return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
require_once $root.'/Database/migrations/SkuMigration.php';
$config=require $root.'/Config/database.php';
if (!in_array($config['host'],['localhost','127.0.0.1','::1'],true)||$config['database']!=='tout_y_est') throw new RuntimeException('Serveur de test local requis.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$assert=static function(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $fn,?int $code=null)use($assert):void {try{$fn();}catch(Throwable $e){if($code!==null)$assert($e->getCode()===$code,'Code incorrect : '.$e->getMessage());return;}throw new RuntimeException('Refus attendu.');};
$build=static fn(PDO $db):App\Services\AdminProductWriteService=>new App\Services\AdminProductWriteService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminProductWrite($db),new App\Services\MoneyService(new App\Models\Setting($db)));
$input=['nom'=>'TEST SKU','description'=>'','id_categorie'=>'1','sku'=>'','prix'=>'10','devise'=>'USD','stock_initial'=>'4','statut'=>'brouillon'];
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);session_write_close();
if (($argv[1]??'')==='worker') {
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_sku_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Cible worker refusée.');
    $db=$connect($name);$data=json_decode(base64_decode($argv[3]),true,512,JSON_THROW_ON_ERROR);
    try {
        if(isset($data['allocate'])) {
            $s=new App\Services\SkuService($db);$op=$s->operation('race',$data['key'],[$data['manual']]);$result=$s->reserve($op,'race',$data['manual']);
        } else $result=$build($db)->save(array_replace($input,$data));
        echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);
    }catch(DomainException $e){echo json_encode(['ok'=>false,'code'=>$e->getCode(),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);}
    exit;
}
$name='tout_y_est_sku_test_'.bin2hex(random_bytes(8));$db=$connect('tout_y_est');
$start=static function(array $data)use($name):array {
    $p=proc_open([PHP_BINARY,__FILE__,'worker',$name,base64_encode(json_encode($data,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);return [$p,$pipes];
};
$finish=static function(array $worker)use($assert):array {[$p,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,'Worker : '.$err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);};
$fingerprint=static function(PDO $db,array $tables):array {
    $out=[];foreach($tables as $table){$rows=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();$encoded=array_map(static fn(array $r):string=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($encoded);$out[$table]=hash('sha256',implode("\n",$encoded));}ksort($out);return $out;
};
try {
    $schema=explode('-- S1.17 :',file_get_contents($root.'/Database/schema.sql'))[0];
    $db->exec(str_replace('tout_y_est',$name,$schema));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Base jetable requise.');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','sku@example.invalid','unused','admin','actif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,statut) VALUES(1,1,'TEST ancien','test-ancien','actif'),(2,1,'TEST autre','test-autre','actif')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,1,'TYE-0123456789ABCDEF',10,'USD',12,'actif'),(2,2,'LEGACY-SECOND',10,'USD',6,'actif')");
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,1)');
    $db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,1,2)');
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,sous_total,frais_livraison,total) VALUES(1,1,'TYE-001',10,0,10)");
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,1,1,'TEST historique','HISTORIQUE-RETIRE',1,10,10)");
    // Une référence attribuée à deux variantes doit arrêter avant tout DDL.
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,2,2,'TEST ambigu','TYE-0123456789ABCDEF',1,10,10)");
    $reject(fn()=>App\Migrations\SkuMigration::apply($db));
    $assert((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sku_sequence'")->fetchColumn()===0,'Ambiguïté refusée avant DDL');
    $db->exec("DELETE FROM ligne_commande WHERE nom_produit_snapshot='TEST ambigu'");
    $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$before=$fingerprint($db,$tables);
    $migration=App\Migrations\SkuMigration::apply($db);
    $assert($migration['references_importees']===3,'Import catalogue et SKU historique retiré');
    $assert($before===$fingerprint($db,$tables),'Toutes les anciennes tables identiques après migration');
    $assert(App\Migrations\SkuMigration::apply($db)['references_importees']===0,'Migration rejouable sans réinitialisation');
    $writer=$build($db);$key=bin2hex(random_bytes(16));
    $first=$writer->save($input+['sku_request'=>$key]);
    $q=$db->prepare('SELECT id_variante,sku,stock FROM variante_produit WHERE id_produit=?');$q->execute([$first['id']]);$v=$q->fetch();
    $assert($v['sku']==='TYE-P000001','Premier SKU simple');
    $assert($writer->save($input+['sku_request'=>$key])===$first,'Rejeu retourne le même produit');
    $reject(fn()=>$writer->save(array_replace($input,['nom'=>'Autre','sku_request'=>$key])),409);
    $manual=$writer->save(array_replace($input,['sku'=>' tye-p000002 ']));
    $next=$writer->save($input);$q->execute([$next['id']]);$assert($q->fetch()['sku']==='TYE-P000003','Collision auto/manuelle sautée');
    $db->exec('DELETE FROM variante_produit WHERE id_produit='.(int)$manual['id']);
    $reject(fn()=>$writer->save(array_replace($input,['sku'=>'TYE-P000002'])),422);
    $assert((int)$db->query("SELECT COUNT(*) FROM sku_attribution WHERE sku='TYE-P000002'")->fetchColumn()===1,'Référence conservée après suppression de la variante');
    $reject(fn()=>$writer->save(array_replace($input,['sku'=>'HISTORIQUE-RETIRE'])),422);
    // Échec après réservation : pas de produit partiel, même SKU à la nouvelle tentative.
    $db->exec("CREATE TRIGGER test_sku_fail BEFORE INSERT ON variante_produit FOR EACH ROW BEGIN IF @test_sku_fail=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST échec après réservation'; END IF; END");
    $failKey=bin2hex(random_bytes(16));$products=(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn();
    $db->exec('SET @test_sku_fail=1');$reject(fn()=>$writer->save($input+['sku_request'=>$failKey]));
    $assert((int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn()===$products,'Rollback produit complet');
    $reserved=$db->query("SELECT sku FROM sku_attribution WHERE origine='automatique' AND id_variante IS NULL ORDER BY sku DESC LIMIT 1")->fetchColumn();
    $reject(fn()=>$writer->save(array_replace($input,['sku'=>$reserved])),422);
    $afterFailure=$db->query('SELECT dernier_numero FROM sku_sequence')->fetchColumn();
    $db->exec('SET @test_sku_fail=0');$retry=$writer->save($input+['sku_request'=>$failKey]);$q->execute([$retry['id']]);
    $assert($q->fetch()['sku']===$reserved && $db->query('SELECT dernier_numero FROM sku_sequence')->fetchColumn()===$afterFailure,'Nouvelle tentative reprend la réservation sans consommer un autre numéro');
    $db->exec('DROP TRIGGER test_sku_fail');
    // Variantes supplémentaires : ID/SKU/stock initial conservés, lot idempotent.
    $variants=new App\Services\AdminProductVariantService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Services\MoneyService(new App\Models\Setting($db)));
    $variantKey=bin2hex(random_bytes(16));$defs=[['nom'=>'Taille','valeurs'=>['Petit','Moyen','Grand']]];
    $rows=[['id_variante'=>(int)$v['id_variante'],'selection'=>[1=>1]],['selection'=>[1=>2],'prix'=>'10','devise'=>'USD','stock'=>0],['selection'=>[1=>3],'prix'=>'10','devise'=>'USD','stock'=>0]];
    $lot=$variants->initialize($first['id'],$defs,$rows,null,$variantKey);
    $assert($lot===$variants->initialize($first['id'],$defs,$rows,null,$variantKey),'Lot variantes idempotent après commit');
    $assert(count($lot['variantes'])===3 && $lot['variantes'][0]===(int)$v['id_variante'],'Conservation identifiant variante simple');
    // Remplacement manuel : ancienne référence reste interdite.
    $variants->saveVariants($first['id'],[['id_variante'=>(int)$v['id_variante'],'sku'=>'FOURNISSEUR-UNIQUE']]);
    $reject(fn()=>$writer->save(array_replace($input,['sku'=>$v['sku']])),422);
    $reject(fn()=>$db->exec("DELETE FROM sku_attribution WHERE sku='".$v['sku']."'"));
    $reject(fn()=>$db->exec("UPDATE sku_attribution SET sku='REFERENCE-RECYCLEE' WHERE sku='".$v['sku']."'"));
    $reject(fn()=>$db->exec('UPDATE sku_sequence SET dernier_numero=0 WHERE id=1'));
    // Six processus : deux rejouent exactement la même requête.
    $same=bin2hex(random_bytes(16));$workers=[];
    for($i=0;$i<6;$i++)$workers[]=$start(['sku_request'=>$i<2?$same:bin2hex(random_bytes(16))]);
    $results=array_map($finish,$workers);foreach($results as $r)$assert($r['ok'],'Création concurrente réussie : '.json_encode($r,JSON_THROW_ON_ERROR));
    $assert($results[0]['result']['id']===$results[1]['result']['id'],'Concurrence même requête sans doublon');
    $assert(count(array_unique(array_column(array_column($results,'result'),'id')))===5,'Cinq produits pour six requêtes dont un rejeu');
    // Allocation directe concurrente, sans le verrou catalogue : UNIQUE et compteur suffisent.
    $n=(int)$db->query('SELECT dernier_numero FROM sku_sequence')->fetchColumn()+1;$raceSku=App\Services\SkuService::format((string)$n);
    $a=$start(['allocate'=>true,'key'=>bin2hex(random_bytes(16)),'manual'=>$raceSku]);
    $b=$start(['allocate'=>true,'key'=>bin2hex(random_bytes(16)),'manual'=>'']);
    $ra=$finish($a);$rb=$finish($b);$assert($rb['ok'],'Allocation auto concurrente');
    $assert(!$ra['ok']||$ra['result']['sku']!==$rb['result']['sku'],'Collision concurrente auto/manuelle protégée');
    // Un lot qui échoue après sa première réservation la conserve pour sa reprise.
    $new=$writer->save($input);$q->execute([$new['id']]);$nv=$q->fetch();$batchKey=bin2hex(random_bytes(16));
    $batch=[['id_variante'=>(int)$nv['id_variante'],'selection'=>[1=>1]],['selection'=>[1=>2],'prix'=>'10','devise'=>'USD','stock'=>0],['selection'=>[1=>3],'prix'=>'10','devise'=>'USD','stock'=>0,'sku'=>'LEGACY-SECOND']];
    $reject(fn()=>$variants->initialize($new['id'],$defs,$batch,null,$batchKey),422);
    $assert((int)$db->query('SELECT COUNT(*) FROM option_produit WHERE id_produit='.$new['id'])->fetchColumn()===0,'Rollback options du lot');
    $counter=$db->query('SELECT dernier_numero FROM sku_sequence')->fetchColumn();
    $reject(fn()=>$variants->initialize($new['id'],$defs,$batch,null,$batchKey),422);
    $assert($counter===$db->query('SELECT dernier_numero FROM sku_sequence')->fetchColumn(),'Reprise lot échoué sans réallocation de sa première référence');
    // Dépassement : six chiffres minimum, aucune troncature ni retour à zéro.
    $db->exec('UPDATE sku_sequence SET dernier_numero=999998 WHERE id=1');
    $s=new App\Services\SkuService($db);$op=$s->operation('overflow',null,[]);
    $assert($s->reserve($op,'1')['sku']==='TYE-P999999'&&$s->reserve($op,'2')['sku']==='TYE-P1000000','Passage à sept chiffres');
    $assert(App\Services\SkuService::format('18446744073709551615')==='TYE-P18446744073709551615','BIGINT sans conversion PHP');
    $db->exec("UPDATE sku_sequence SET dernier_numero=18446744073709551615 WHERE id=1");$reject(fn()=>$s->reserve($op,'exhausted'),503);
    // Le verrou de maintenance est observé par une connexion distincte.
    $other=$connect($name);App\Services\CatalogueWriteLock::acquire($db);
    try{$reject(fn()=>App\Services\CatalogueWriteLock::acquire($other,0),503);}finally{App\Services\CatalogueWriteLock::release($db);}
    $stable=['panier','ligne_panier','commande','ligne_commande','numero_commande_sequence'];
    $assert(array_intersect_key($before,array_flip($stable))===$fingerprint($db,$stable),'Paniers, commandes, snapshots et numérotation inchangés');
    $assert($db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===12,'Stock historique conservé');
    $assert((int)$db->query('SELECT COUNT(*) FROM variante_produit')->fetchColumn()===(int)$db->query('SELECT COUNT(DISTINCT sku) FROM variante_produit')->fetchColumn(),'Unicité globale finale');
    echo "SKU_ALLOCATION_OK (migration/ambiguïté/rejeu, simple/variantes, concurrence/idempotence, manuel/collision, rollback/reprise, registre permanent, 7 chiffres/épuisement, maintenance, paniers/stocks/historiques).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');
    if(!preg_match('/^tout_y_est_sku_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé.');
    $db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
