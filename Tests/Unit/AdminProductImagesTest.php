<?php
declare(strict_types=1);
// Fixtures exclusivement en base jetable et dossier temporaire, aucun produit réel.
$root=dirname(__DIR__,2);
ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis');
if(($argv[1]??'')==='worker') {
    $name=$argv[2]??'';
    if(!preg_match('/^tout_y_est_image_upload_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base worker refusée');
    $db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
    App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
    $service=new App\Services\AdminProductImageService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\ProductImage($db),new App\Services\UploadService(),$argv[4]);
    try{$service->add(1,$argv[3]);echo 'ADDED';}catch(DomainException $error){if($error->getCode()!==422)throw $error;echo 'LIMIT';}exit;
}
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname=tout_y_est;charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_image_upload_test_'.bin2hex(random_bytes(8));
$temporary=sys_get_temp_dir().'/tye-admin-images-'.bin2hex(random_bytes(8));mkdir($temporary,0700);
$storage=$temporary.'/public';mkdir($storage,0700);
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$rejected=static function(callable $call,int $code=422)use($assert):void {
    try{$call();}catch(DomainException $error){$assert($error->getCode()===$code,'Code refus : '.$error->getMessage());return;}
    throw new RuntimeException('Refus attendu');
};
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif'),(3,'TEST','Inactif','inactive@example.invalid','unused','admin','inactif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST photos','test-photos','','brouillon'),(2,1,'TEST anciennes','test-anciennes','','brouillon')");
    $access=new App\Services\AdminAccessService(new App\Models\User($db));
    $images=new App\Models\ProductImage($db);
    $service=new App\Services\AdminProductImageService($db,$access,$images,new App\Services\UploadService(),$storage);
    $source=$temporary.'/original.jpg';$canvas=imagecreatetruecolor(360,240);imagejpeg($canvas,$source,90);unset($canvas);
    $hash=hash_file('sha256',$source);
    foreach([[],['id'=>2],['id'=>3]] as $user){App\Core\Session::set('user',$user);$rejected(fn()=>$service->add(1,$source),$user===[]?401:403);}
    App\Core\Session::set('user',['id'=>1]);
    $rejected(fn()=>$service->add(999,$source),404);
    foreach([null,[],['error'=>[0]],['error'=>0,'tmp_name'=>$source]] as $file)$rejected(fn()=>App\Services\HttpImageUpload::source($file));
    foreach([1,2,3,4,6,7,8] as $error)$rejected(fn()=>App\Services\HttpImageUpload::source(['error'=>$error]));
    $bad=$temporary.'/invalid.jpg';file_put_contents($bad,'<?php echo "invalid";');
    $rejected(fn()=>$service->add(1,$bad));$assert($images->forProduct(1)===[]&&glob($storage.'/*')===[],'Traitement refusé sans résidu');
    $service->add(1,$source);$rows=$images->forProduct(1);
    $assert(count($rows)===1&&(int)$rows[0]['image_principale']===1&&(int)$rows[0]['ordre_affichage']===0,'Première principale ordre zéro');
    $assert((bool)preg_match('~^uploads/products/[a-f0-9]{32}-large\.webp$~D',$rows[0]['chemin']),'Chemin opaque');
    foreach(['thumb','medium','large'] as $size){$file=$storage.'/'.basename(str_replace('-large.webp','-'.$size.'.webp',$rows[0]['chemin']));$assert(is_file($file)&&getimagesize($file)[2]===IMAGETYPE_WEBP,'WebP valide '.$size);}
    $assert(count(glob($storage.'/*'))===3&&hash_file('sha256',$source)===$hash,'Source non publiée et inchangée');
    $service->add(1,$source);$rows=$images->forProduct(1);
    $assert((int)$rows[1]['image_principale']===0&&(int)$rows[1]['ordre_affichage']===1,'Seconde non principale et ordre suivant');
    $before=glob($storage.'/*');
    $db->exec("CREATE TRIGGER test_image_failure BEFORE INSERT ON image_produit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    try{$service->add(1,$source);throw new RuntimeException('Trigger attendu');}catch(PDOException){}
    $assert(glob($storage.'/*')===$before&&count($images->forProduct(1))===2&&!$db->inTransaction(),'Échec insertion nettoyé et rollback');
    $db->exec('DROP TRIGGER test_image_failure');
    for($i=2;$i<5;$i++)$service->add(1,$source);
    // Deux processus indépendants se disputent la sixième place.
    $workers=[];
    for($i=0;$i<2;$i++){
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,$source,$storage],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Worker indisponible');$workers[]=[$process,$pipes];
    }
    $results=[];
    foreach($workers as [$process,$pipes]){$results[]=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,'Worker : '.$errors);}
    sort($results);$assert($results===['ADDED','LIMIT'],'Concurrence : exactement une sixième photo');
    $before=glob($storage.'/*');$rejected(fn()=>$service->add(1,$source));
    $assert(count($images->forProduct(1))===6&&glob($storage.'/*')===$before,'Limite six sans fichier supplémentaire');
    $legacy='assets/images/products/montre.jpg';
    $insert=$db->prepare('INSERT INTO image_produit(id_produit,chemin,ordre_affichage,image_principale) VALUES(2,?,4,1)');$insert->execute([$legacy]);
    $gallery=$service->gallery(2);$assert($gallery[0]['thumbnail']==='/'.$legacy,'Ancienne image inchangée et lisible');
    $service->add(2,$source);$rows=$images->forProduct(2);$assert((int)$rows[1]['ordre_affichage']===5&&(int)$rows[1]['image_principale']===0,'Ordre MAX existant conservé');
    $render=static function(array $photos)use($root):string {
        $product=['id_produit'=>1,'nom'=>'TEST','statut'=>'brouillon'];$categories=[];$variants=[];$old=[];$canPublish=true;$flash=null;$formError=null;$csrfToken='test';
        ob_start();require $root.'/App/views/admin/product-form.php';return ob_get_clean();
    };
    foreach([0,1,6] as $count){$photos=array_fill(0,$count,['thumbnail'=>null,'image_principale'=>1]);$html=$render($photos);$assert(str_contains($html,$count.' / 6 photos'),'Compteur galerie');$assert(str_contains($html,'type="file"')===($count<6),'Ajout présent seulement sous six');}
    $controller=file_get_contents($root.'/App/controllers/Admin/ProductController.php');
    $assert(str_contains($controller,'CsrfMiddleware::isValid')&&str_contains($controller,'HttpImageUpload::source')&&str_contains($controller,'isPost()'),'Frontière HTTP et sécurité branchées');
    echo "ADMIN_PRODUCT_IMAGES_OK (droits, upload HTTP refusé hors HTTP, erreurs PHP, principale/ordre, limite 6, rollback fichiers/BDD, WebP opaques, anciennes images, galeries 0/1/6).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    foreach(glob($storage.'/*')?:[] as $file)unlink($file);rmdir($storage);
    foreach(glob($temporary.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($temporary);
}
