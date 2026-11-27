<?php
declare(strict_types=1);
// All writes, workers, photo files and triggers are isolated from the catalogue.
$root=dirname(__DIR__,2);ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {if(!str_starts_with($class,'App\\'))return;$p=explode('\\',substr($class,4));$p[0]=strtolower($p[0]);$f=$root.'/App/'.implode('/',$p).'.php';if(is_file($f))require_once $f;});
$c=require $root.'/Config/database.php';
if(!in_array($c['host'],['localhost','127.0.0.1','::1'],true)||$c['database']!=='tout_y_est')throw new RuntimeException('Local test server required');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$name.';charset=utf8mb4',$c['username'],$c['password'],$c['options']);
$build=static function(PDO $db,string $files):array {$access=new App\Services\AdminAccessService(new App\Models\User($db));$photos=new App\Services\ProductPhotoPreparation($db,$access,new App\Services\UploadService(),$files.'/private',$files.'/public');return [new App\Services\AdminProductWriteService($db,$access,new App\Models\AdminProductWrite($db),new App\Services\MoneyService(new App\Models\Setting($db)),$photos),$photos];};
App\Core\Session::set('user',['id'=>1]);
if(($argv[1]??'')==='worker') {
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_variable_create_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Worker database refused');
    [$writer]=$build($connect($name),$argv[3]);$payload=json_decode(base64_decode($argv[4]),true,512,JSON_THROW_ON_ERROR);
    echo json_encode($writer->save($payload),JSON_THROW_ON_ERROR);exit;
}
$name='tout_y_est_variable_create_test_'.bin2hex(random_bytes(8));$files=sys_get_temp_dir().'/'.$name;mkdir($files,0700);$db=$connect('tout_y_est');
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $call,int $code=422)use($assert):void {try{$call();}catch(DomainException $e){$assert($e->getCode()===$code,$e->getMessage());return;}throw new RuntimeException('Expected rejection');};
$remove=static function(string $dir)use(&$remove,$files):void {if(!str_starts_with($dir,$files)||!is_dir($dir))return;foreach(new DirectoryIterator($dir) as $f){if($f->isDot())continue;if($f->isDir())$remove($f->getPathname());else unlink($f->getPathname());}rmdir($dir);};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','variable@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    [$writer,$photos]=$build($db,$files);
    $make=static function(array $options)use($writer):array {
        $input=['nom'=>'TEST Variable','description'=>'','id_categorie'=>'1','statut'=>'brouillon','product_type'=>'variable','creation_options'=>$options,'creation_preview'=>'1','creation_end'=>'v1','sku_request'=>bin2hex(random_bytes(16))];
        $preview=$writer->creationPreview($input);$input['creation_signature']=$preview['signature'];
        foreach($preview['candidates'] as $token=>$candidate)$input['creation_variants'][$token]=['prix'=>'','devise'=>'USD','stock'=>'0','statut'=>'actif','sku_mode'=>'auto','sku'=>''];
        return $input;
    };
    $options=[['nom'=>'Couleur','valeurs'=>"Noir\nBlanc"],['nom'=>'Taille','valeurs'=>"S\nM"]];
    $input=$make($options);$tokens=array_keys($input['creation_variants']);
    $input['creation_variants'][$tokens[0]]=array_replace($input['creation_variants'][$tokens[0]],['selected'=>'1','prix'=>'49000','devise'=>'CDF']);
    $input['creation_variants'][$tokens[3]]=array_replace($input['creation_variants'][$tokens[3]],['selected'=>'1','prix'=>'30','stock'=>'5','statut'=>'inactif','sku_mode'=>'manual','sku'=>' variant-test ']);
    $result=$writer->save($input);$id=$result['id'];
    $rows=$db->query('SELECT * FROM variante_produit WHERE id_produit='.$id.' ORDER BY id_variante')->fetchAll();
    $assert(count($rows)===2&&$rows[0]['cle_combinaison']!==null&&$rows[1]['cle_combinaison']!==null,'Only selected variants, no technical intermediate');
    $assert($rows[0]['prix_reference']==='20.00'&&$rows[0]['devise_reference']==='USD'&&(int)$rows[0]['stock']===0&&$rows[0]['statut']==='actif','CDF, zero stock and active status');
    $assert($rows[1]['sku']==='VARIANT-TEST'&&(int)$rows[1]['stock']===5&&$rows[1]['statut']==='inactif','Manual SKU, independent stock/status');
    $assert($writer->save($input)===$result,'Identical replay');
    $counts=$db->query('SELECT (SELECT COUNT(*) FROM produit),(SELECT COUNT(*) FROM variante_produit),(SELECT COUNT(*) FROM option_produit)')->fetch(PDO::FETCH_NUM);
    $bad=$input;unset($bad['creation_end']);$reject(fn()=>$writer->save($bad));
    $bad=$input;unset($bad['creation_variants'][$tokens[2]]['stock']);$reject(fn()=>$writer->save($bad));
    $bad=$input;$bad['creation_variants']['forged']=$bad['creation_variants'][$tokens[0]];$reject(fn()=>$writer->save($bad));
    $bad=$input;$bad['creation_options'][0]['valeurs']="Vert\nBlanc";$reject(fn()=>$writer->save($bad));
    $bad=$make($options);$reject(fn()=>$writer->save($bad));
    $bad=$input;$bad['sku_request']=bin2hex(random_bytes(16));$bad['creation_variants'][$tokens[0]]['sku_mode']='manual';$bad['creation_variants'][$tokens[0]]['sku']='';$reject(fn()=>$writer->save($bad));
    $bad=$input;$bad['sku_request']=bin2hex(random_bytes(16));$reject(fn()=>$writer->save($bad)); // Permanent manual collision, including registry.
    $bad=$input;$bad['creation_variants'][$tokens[0]]['prix']='-1';$reject(fn()=>$writer->save($bad));
    $reject(fn()=>$writer->creationPreview(['creation_options'=>array_fill(0,4,['nom'=>'A','valeurs'=>'B'])]));
    $reject(fn()=>$writer->creationPreview(['creation_options'=>[['nom'=>'A','valeurs'=>implode("\n",range(1,11))],['nom'=>'B','valeurs'=>implode("\n",range(1,10))]]]));
    $assert($db->query('SELECT (SELECT COUNT(*) FROM produit),(SELECT COUNT(*) FROM variante_produit),(SELECT COUNT(*) FROM option_produit)')->fetch(PDO::FETCH_NUM)===$counts,'Failures leave no partial product/options/variants');
    App\Core\Session::set('user',['id'=>2]);$reject(fn()=>$writer->save($input),403);App\Core\Session::set('user',['id'=>1]);
    $hundred=$make([['nom'=>'Numéro','valeurs'=>implode("\n",range(1,100))]]);
    foreach($hundred['creation_variants'] as &$row)$row+=['selected'=>'1'];unset($row);
    foreach($hundred['creation_variants'] as &$row)$row['prix']='1';unset($row);
    $leaves=0;array_walk_recursive($hundred,static function()use(&$leaves):void {$leaves++;});$assert($leaves<800,'100 variants below max_input_vars=1000');
    $maximum=$writer->save($hundred);$assert((int)$db->query('SELECT COUNT(*) FROM variante_produit WHERE id_produit='.$maximum['id'])->fetchColumn()===100,'100 selected variants accepted');
    $overflow=$hundred;$overflow['ignored']=array_fill(0,1000,'x');$reject(fn()=>$writer->save($overflow));
    // Atomic photo/publication failure, durable SKU reservations, retry with same intent.
    $source=$files.'/source.png';$im=imagecreatetruecolor(40,40);imagepng($im,$source);unset($im);
    $batch=bin2hex(random_bytes(16));$ticket=$photos->prepare($batch,bin2hex(random_bytes(16)),$source);
    $publish=$make([['nom'=>'Edition','valeurs'=>"A\nB"]]);foreach($publish['creation_variants'] as &$row){$row['selected']='1';$row['prix']='5';}unset($row);
    $publish+=['photo_batch'=>$batch,'photo_ids'=>[$ticket],'primary_photo'=>$ticket];$publish['statut']='actif';
    $before=(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn();
    $db->exec("CREATE TRIGGER variable_creation_failure BEFORE UPDATE ON produit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    try {$writer->save($publish);throw new RuntimeException('Trigger required');}catch(PDOException){}
    $assert((int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn()===$before&&count($photos->listing($batch))===1,'Full rollback and reusable photo');
    $db->exec('DROP TRIGGER variable_creation_failure');
    $published=$writer->save($publish);$assert($writer->save($publish)===$published,'Replay after photo consumption');
    $assert((new App\Models\Product($db))->findById($published['id'])===null,'Active variable product remains publicly hidden');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit WHERE id_produit='.$published['id'])->fetchColumn()===1,'Shared product photo only');
    // Two independent connections submit the same automatic-only intent.
    $concurrent=$make([['nom'=>'Test','valeurs'=>"X\nY"]]);foreach($concurrent['creation_variants'] as &$row){$row['selected']='1';$row['prix']='2';}unset($row);
    $workers=[];for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,$files,base64_encode(json_encode($concurrent,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Worker unavailable');fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,$err);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $assert($results[0]===$results[1]&&(int)$db->query('SELECT COUNT(*) FROM variante_produit WHERE id_produit='.$results[0]['id'])->fetchColumn()===2,'Concurrent replay creates exactly two variants once');
    $workers=[];for($i=0;$i<2;$i++){$payload=$concurrent;$payload['sku_request']=bin2hex(random_bytes(16));$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,$files,base64_encode(json_encode($payload,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Worker unavailable');fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,$err);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $assert($results[0]['id']!==$results[1]['id'],'Distinct simultaneous intentions create distinct products');
    $q=$db->prepare('SELECT COUNT(*),COUNT(DISTINCT sku) FROM variante_produit WHERE id_produit IN (?,?)');$q->execute([$results[0]['id'],$results[1]['id']]);$assert(array_map('intval',$q->fetch(PDO::FETCH_NUM))===[4,4],'Concurrent variable products have four unique SKUs');
    echo "PRODUCT_VARIABLE_CREATION_OK (selected combinations, USD/CDF, individual stock/status, manual/auto SKU, 100 variants, truncation, validation, rights, photos, rollback/retry, concurrency, public guard).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');$remove($files);
}
