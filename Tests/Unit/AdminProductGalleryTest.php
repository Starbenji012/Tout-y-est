<?php
declare(strict_types=1);
namespace App\Services {
    // Injection d'un échec filesystem uniquement dans ce processus de test.
    function unlink(string $path): bool {
        $failure=$GLOBALS['gallery_unlink_failure']??null;
        if(is_string($failure)&&\realpath($failure)===\realpath($path))return false;
        return \unlink($path);
    }
}
namespace {
$root=dirname(__DIR__,2);ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new \RuntimeException('Serveur local requis');
$db=new \PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname=tout_y_est;charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_gallery_test_'.bin2hex(random_bytes(8));
$temporary=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tye-gallery-'.bin2hex(random_bytes(8));mkdir($temporary,0700);
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new \RuntimeException($message);};
$reject=static function(callable $call,int $code=422)use($assert):void {try{$call();}catch(\DomainException $e){$assert($e->getCode()===$code,'Refus : '.$e->getMessage());return;}throw new \RuntimeException('Refus attendu');};
\App\Core\Session::start();\App\Core\Session::set('user',['id'=>1]);
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif'),(3,'TEST','Inactif','inactive@example.invalid','unused','admin','inactif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    foreach([1=>'actif',2=>'brouillon',3=>'archive',4=>'brouillon'] as $id=>$status)$db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES($id,1,'TEST galerie $id','test-galerie-$id','','$status')");
    $model=new \App\Models\ProductImage($db);
    $service=new \App\Services\AdminProductImageService($db,new \App\Services\AdminAccessService(new \App\Models\User($db)),$model,new \App\Services\UploadService(),$temporary);
    $insert=static function(int $product,string $path,int $order=0,bool $primary=false)use($model,$db):int {$model->insert($product,$path,$order,$primary);return (int)$db->lastInsertId();};
    $legacy='assets/images/products/montre.jpg';$legacyHash=hash_file('sha256',$root.'/Public/'.$legacy);
    $a=$insert(1,$legacy,3,true);$b=$insert(1,'assets/images/products/watch.jpg',8);$c=$insert(1,'assets/images/products/laptop.jpg',8);
    $foreign=$insert(4,$legacy,0,true);
    foreach([[],['id'=>2],['id'=>3]] as $user){\App\Core\Session::set('user',$user);$reject(fn()=>$service->change(1,$a,'primary'),$user===[]?401:403);}
    \App\Core\Session::set('user',['id'=>1]);
    $before=$model->forProduct(1);$reject(fn()=>$service->change(1,$foreign,'delete'),404);$reject(fn()=>$service->change(1,$foreign,'primary'),404);
    $assert($model->forProduct(1)===$before,'Appartenance stricte');$reject(fn()=>$service->change(1,$a,'other'));
    $service->change(1,$b,'primary');$rows=$model->forProduct(1);
    $assert(array_map('intval',array_column($rows,'ordre_affichage'))===[0,1,2]&&array_sum(array_column($rows,'image_principale'))===1&&(int)$rows[1]['image_principale']===1,'Changement principale et normalisation');
    $service->change(1,$b,'up');$rows=$model->forProduct(1);$assert((int)$rows[0]['id_image']===$b&&(int)$rows[0]['image_principale']===1,'Monter conserve principale');
    $reject(fn()=>$service->change(1,$b,'up'));$reject(fn()=>$service->change(1,$c,'down'));
    $service->change(1,$b,'down');$rows=$model->forProduct(1);$assert((int)$rows[1]['id_image']===$b&&(int)$rows[1]['image_principale']===1,'Descendre conserve principale');
    $paths=(new \App\Models\Product($db))->imagesForProducts([1]);$assert(array_column($paths,'chemin')===array_column($rows,'chemin'),'Lecture client suit ordre Admin sans déplacer principale');
    $service->change(1,$c,'delete');$assert(count($model->forProduct(1))===2,'Suppression secondaire');
    $service->change(1,$b,'delete');$rows=$model->forProduct(1);$assert(count($rows)===1&&(int)$rows[0]['id_image']===$a&&(int)$rows[0]['image_principale']===1&&(int)$rows[0]['ordre_affichage']===0,'Promotion première après suppression principale');
    $reject(fn()=>$service->change(1,$a,'delete'));$assert(count($model->forProduct(1))===1,'Dernière photo active conservée');
    foreach([2,3] as $id){$image=$insert($id,$legacy,4,true);$service->change($id,$image,'delete');$assert($model->forProduct($id)===[],'Dernière photo brouillon/archivé autorisée');}
    $assert(hash_file('sha256',$root.'/Public/'.$legacy)===$legacyHash,'Fichier historique préservé');
    // Une galerie ancienne sans principale est réparée sur mutation.
    $x=$insert(2,$legacy,5);$y=$insert(2,'assets/images/products/watch.jpg',9);$service->change(2,$y,'up');
    $rows=$model->forProduct(2);$assert((int)$rows[0]['id_image']===$y&&(int)$rows[0]['image_principale']===1,'Galerie non vide réparée');
    $createFiles=static function()use($temporary):array {
        $opaque=bin2hex(random_bytes(16));$paths=[];$canvas=imagecreatetruecolor(12,12);
        foreach(['thumb','medium','large'] as $size){$file=$temporary.DIRECTORY_SEPARATOR.$opaque.'-'.$size.'.webp';imagewebp($canvas,$file);$paths[]=$file;}unset($canvas);
        return ['uploads/products/'.$opaque.'-large.webp',$paths];
    };
    [$path,$files]=$createFiles();$image=$insert(3,$path,0,true);
    $before=$model->forProduct(3);
    $db->exec("CREATE TRIGGER test_gallery_delete_failure AFTER DELETE ON image_produit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    try{$service->change(3,$image,'delete');throw new \RuntimeException('Échec SQL attendu');}catch(\PDOException){}
    $assert($model->forProduct(3)===$before&&count(array_filter($files,'is_file'))===3&&!$db->inTransaction(),'Rollback SQL conserve tous les dérivés');
    $db->exec('DROP TRIGGER test_gallery_delete_failure');
    $service->change(3,$image,'delete');$assert($model->forProduct(3)===[]&&count(array_filter($files,'is_file'))===0,'Trois dérivés supprimés');
    [$path,$files]=$createFiles();$image=$insert(3,$path,0,true);$shared=$insert(4,'/'.$path,1);
    $service->change(3,$image,'delete');$assert(count(array_filter($files,'is_file'))===3,'Dérivés partagés préservés');$service->change(4,$shared,'delete');
    $assert(count(array_filter($files,'is_file'))===0,'Dernière référence partagée nettoyée');
    $danger=$insert(3,'uploads/products/../../outside.webp');$reject(fn()=>$service->change(3,$danger,'delete'));$assert(count($model->forProduct(3))===1,'Chemin hors convention refusé');$model->delete(3,$danger);
    [$path,$files]=$createFiles();$image=$insert(3,$path,0,true);$GLOBALS['gallery_unlink_failure']=$files[2];
    $message=$service->change(3,$image,'delete');unset($GLOBALS['gallery_unlink_failure']);
    $assert(str_contains($message,'maintenance')&&$model->forProduct(3)===[]&&is_file($files[2]),'Échec nettoyage signalé sans ligne cassée');
    // UI générée : actions limites, principale, produit actif et échappement.
    $render=static function(array $photos,string $status='brouillon')use($root):string {
        $product=['id_produit'=>2,'nom'=>'TEST','statut'=>$status];$categories=[];$variants=[];$old=[];$canPublish=true;$flash=null;$formError=null;$csrfToken='test';
        foreach($photos as &$photo)$photo['thumbnail']=null;unset($photo);ob_start();require $root.'/App/views/admin/product-form.php';return ob_get_clean();
    };
    $html=$render($model->forProduct(2));$document=new \DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($document);
    $forms=$xpath->query('//form[@data-gallery-actions]');$assert($forms->length===2,'Deux formulaires photo');
    $assert($xpath->query('.//button[@value="up"]',$forms->item(0))->length===0&&$xpath->query('.//button[@value="down"]',$forms->item(1))->length===0,'Pas actions de limite');
    $assert($xpath->query('.//button[@value="primary"]',$forms->item(0))->length===0&&$xpath->query('.//button[@value="primary"]',$forms->item(1))->length===1,'Principale contextualisée');
    $assert(str_contains($html,'data-confirm-delete')&&str_contains($html,'action="/admin/produits/photo/modifier"'),'Confirmation et vraie route');
    $html=$render($model->forProduct(1),'actif');$assert(!str_contains($html,'value="delete"'),'Suppression dernière active masquée');
    $assert($db->query('SELECT statut FROM produit WHERE id_produit=1')->fetchColumn()==='actif','Aucun changement statut produit');
    echo "ADMIN_PRODUCT_GALLERY_OK (principale, ordre, limites, suppression, fichiers, anciennes images, rollback, droits, appartenance, UI).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    foreach(glob($temporary.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($temporary);
}
}
