<?php
declare(strict_types=1);
// Tout est jetable : base, sources et destinations publiques injectées.
$root=dirname(__DIR__,2);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'App\\'))return;
    $p=explode('\\',substr($class,4));$p[0]=strtolower($p[0]);$file=$root.'/App/'.implode('/',$p).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Serveur local obligatoire.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $call,int $status=422)use($assert):void{try{$call();}catch(DomainException $e){$assert($e->getCode()===$status,'Refus attendu '.$status.' reçu '.$e->getCode().' '.$e->getMessage());return;}throw new RuntimeException('Refus absent.');};
$token=static fn():string=>bin2hex(random_bytes(16));
$build=static function(PDO $db,string $files):array{
    $access=new App\Services\AdminAccessService(new App\Models\User($db));
    $photos=new App\Services\ProductPhotoPreparation($db,$access,new App\Services\UploadService(),$files.'/private',$files.'/public');
    $writer=new App\Services\AdminProductWriteService($db,$access,new App\Models\AdminProductWrite($db),new App\Services\MoneyService(new App\Models\Setting($db)),$photos);
    return [$photos,$writer];
};
$input=['nom'=>'TEST Photos','description'=>'','id_categorie'=>'1','sku'=>'','prix'=>'12.50','devise'=>'USD','stock_initial'=>'3','statut'=>'actif'];
App\Core\Session::set('user',['id'=>1]);
if(($argv[1]??'')==='worker'){
    $name=$argv[2];if(!preg_match('/^tout_y_est_photo_test_[a-f0-9]{32}$/D',$name))throw new RuntimeException('Base refusée.');
    [$photos,$writer]=$build($connect($name),$argv[3]);
    $payload=json_decode(base64_decode($argv[4]),true,512,JSON_THROW_ON_ERROR);
    echo json_encode($writer->save($payload),JSON_THROW_ON_ERROR);exit;
}
$db=$connect($config['database']);$name='tout_y_est_photo_test_'.$token();$files=sys_get_temp_dir().'/'.$name;mkdir($files,0700);
$remove=static function(string $directory)use(&$remove,$files):void{
    if(!str_starts_with($directory,$files)||!is_dir($directory))return;
    foreach(new DirectoryIterator($directory) as $file){if($file->isDot())continue;if($file->isDir())$remove($file->getPathname());else unlink($file->getPathname());}rmdir($directory);
};
try{
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','a@example.invalid','unused','admin','actif'),(2,'TEST','Admin','b@example.invalid','unused','admin','actif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    [$photos,$writer]=$build($db,$files);
    $source=static function(int $i)use($files):string{$im=imagecreatetruecolor(32+$i,32);imagefill($im,0,0,imagecolorallocate($im,$i*19%255,20,30));$path=$files.'/source-'.$i.'.png';imagepng($im,$path);return $path;};
    $batch=$token();$ids=[];
    for($i=1;$i<=6;$i++)$ids[]=$photos->prepare($batch,$token(),$source($i));
    $assert(count($photos->listing($batch))===6,'Six préparations');
    $assert($photos->prepare($batch,$token(),$source(1))===$ids[0],'Hash identique dédoublonné');
    $reject(fn()=>$photos->prepare($batch,$token(),$source(7)));
    $photos->remove($batch,$ids[5]);$assert(count($photos->listing($batch))===5,'Retrait');
    $ids[5]=$photos->prepare($batch,$token(),$source(7));
    App\Core\Session::set('user',['id'=>2]);$reject(fn()=>$photos->listing($batch),404);$reject(fn()=>$photos->preview($batch,$ids[0]),404);App\Core\Session::set('user',['id'=>1]);
    $before=(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn();
    $reject(fn()=>$writer->save($input+['sku_request'=>$token()]));
    $assert((int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn()===$before,'Actif sans photo jamais créé');
    $payload=$input+['sku_request'=>$token(),'photo_batch'=>$batch,'photo_ids'=>array_reverse($ids),'primary_photo'=>$ids[2]];
    $result=$writer->save($payload);$assert($writer->save($payload)===$result,'Replay après consommation sans recopie');
    $id=$result['id'];$assert($db->query('SELECT statut FROM produit WHERE id_produit='.$id)->fetchColumn()==='actif','Publication atomique');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit WHERE id_produit='.$id)->fetchColumn()===6,'Six photos attachées');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit WHERE image_principale=1 AND id_produit='.$id)->fetchColumn()===1,'Une principale');
    $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_produit='.$id)->fetchColumn()===3,'Variante et stock créés');
    $draft=$writer->save(array_replace($input,['statut'=>'brouillon','sku_request'=>$token()]));
    $assert($db->query('SELECT statut FROM produit WHERE id_produit='.$draft['id'])->fetchColumn()==='brouillon','Brouillon sans photo');
    // Échec SQL après copies : rollback complet, réservation SKU durable, reprise même clé.
    $retryBatch=$token();$retryId=$photos->prepare($retryBatch,$token(),$source(8));
    $retry=$input+['sku_request'=>$token(),'photo_batch'=>$retryBatch,'photo_ids'=>[$retryId],'primary_photo'=>$retryId];
    $db->exec("CREATE TRIGGER test_photo_failure BEFORE UPDATE ON produit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST failure'");
    $count=(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn();
    try{$writer->save($retry);throw new RuntimeException('Échec SQL absent');}catch(PDOException){}
    $assert((int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn()===$count,'Rollback sans produit partiel');
    $stem=substr(hash('sha256',$retryBatch.':'.$retryId),0,32);
    file_put_contents($files.'/public/'.$stem.'-large.webp','partial-copy');
    $db->exec('DROP TRIGGER test_photo_failure');$recovered=$writer->save($retry);$assert($writer->save($retry)===$recovered,'Reprise après copies et rollback');
    // Source corrompue, dépassement, expiration, propriété et ticket invalide.
    $bad=$files.'/bad.txt';file_put_contents($bad,'not an image');$badBatch=$token();$badTicket=$token();
    $reject(fn()=>$photos->prepare($badBatch,$badTicket,$bad));
    $assert($photos->prepare($badBatch,$token(),$source(9))!=='','Nouvelle tentative après erreur de traitement');
    $staleBatch=$token();$staleTicket=$photos->prepare($staleBatch,$token(),$source(11));
    $q=$db->prepare("UPDATE product_photo_item SET state='processing' WHERE batch_ticket=?");$q->execute([$staleBatch]);
    $held=$connect($name);$lock=substr('tye:photo:'.hash('sha256',$files.'/private:'.$staleBatch.':'.hash_file('sha256',$source(11))),0,64);
    $q=$held->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
    $reject(fn()=>$photos->prepare($staleBatch,$staleTicket,$source(11)),409);
    $q=$held->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$held=null;
    $assert($photos->prepare($staleBatch,$staleTicket,$source(11))===$staleTicket,'Reprise sûre après arrêt d’un processus de préparation');
    // Quota par propriétaire, y compris les réservations de traitements en vol.
    for($b=0;$b<4;$b++){
        $quotaBatch=$token();$q=$db->prepare('INSERT INTO product_photo_batch(ticket,owner_id,expires_at) VALUES(?,2,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))');$q->execute([$quotaBatch]);
        for($j=0;$j<6;$j++){$q=$db->prepare("INSERT INTO product_photo_item(batch_ticket,ticket,state,source_hash) VALUES(?,?,'processing',?)");$q->execute([$quotaBatch,$token(),hash('sha256',$b.':'.$j)]);}
    }
    App\Core\Session::set('user',['id'=>2]);$reject(fn()=>$photos->prepare($token(),$token(),$source(12)),429);App\Core\Session::set('user',['id'=>1]);
    $large=$files.'/large.bin';$fp=fopen($large,'wb');ftruncate($fp,10*1048576+1);fclose($fp);$reject(fn()=>$photos->prepare($token(),$token(),$large));
    $reject(fn()=>App\Services\ProductPhotoPreparation::token('../escape'));
    $_SERVER['CONTENT_LENGTH']=13*1048576;$reject(fn()=>App\Services\ProductPhotoPreparation::guardPostSize(),413);unset($_SERVER['CONTENT_LENGTH']);
    // Deux créations simultanées identiques : un résultat, une variante, une seule galerie.
    $parallelBatch=$token();$parallelId=$photos->prepare($parallelBatch,$token(),$source(10));
    $parallel=$input+['sku_request'=>$token(),'photo_batch'=>$parallelBatch,'photo_ids'=>[$parallelId],'primary_photo'=>$parallelId];
    $jobs=[];
    for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'worker',$name,$files,base64_encode(json_encode($parallel))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$proc,$pipes];}
    $results=[];foreach($jobs as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($proc)===0,'Worker : '.$err);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $assert($results[0]===$results[1],'Concurrence idempotente');
    // L'expiration réconcilie seulement les manifestes connus, jamais les photos référencées.
    $db->exec('UPDATE product_photo_batch SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)');
    $reject(fn()=>$photos->listing($badBatch),409);$assert($photos->cleanup()>0,'Nettoyage expiré');
    $assert(count(glob($files.'/public/*.webp'))===24,'24 dérivés publiés conservés après nettoyage');
    $assert((int)$db->query('SELECT COUNT(*) FROM product_photo_item')->fetchColumn()===0,'Métadonnées des fichiers expirés nettoyées');
    $reject(fn()=>$photos->prepare($batch,$token(),$source(1)),409);
    echo "PRODUCT_PHOTO_PREPARATION_OK (6 photos, dédoublonnage, retrait, droits, publication, brouillon, rollback, reprise, concurrence, expiration, nettoyage ciblé).\n";
}finally{
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');if(preg_match('/^tout_y_est_photo_test_[a-f0-9]{32}$/D',$name))$db->exec('DROP DATABASE `'.$name.'`');$remove($files);
}
