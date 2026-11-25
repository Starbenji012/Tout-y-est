<?php
declare(strict_types=1);
// Exclusivement appelé par le test HTTP, jamais chargé par l'application.
$root=dirname(__DIR__,2);$name=$argv[2]??'';
if(PHP_SAPI!=='cli'||!preg_match('/^tout_y_est_photo_http_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Fixture refusée.');
spl_autoload_register(static function(string $class)use($root):void{$p=explode('\\',substr($class,4));$p[0]=strtolower($p[0]);$f=$root.'/App/'.implode('/',$p).'.php';if(str_starts_with($class,'App\\')&&is_file($f))require_once $f;});
$c=require $root.'/Config/database.php';
if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Serveur local requis.');
$db=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['username'],$c['password'],$c['options']);
if(($argv[1]??'')==='setup'){
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','photo@example.invalid','unused','admin','actif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST Photos','test-photos','actif')");
    $im=imagecreatetruecolor(48,48);imagefill($im,0,0,imagecolorallocate($im,170,90,30));imagepng($im,$argv[3]);
    echo 'FIXTURE_READY';
}elseif(($argv[1]??'')==='cleanup'){
    $db->exec('USE `'.$name.'`');$db->exec('UPDATE product_photo_batch SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)');
    // Supprime uniquement les lignes de la base jetable, puis ses dérivés connus.
    $db->exec('DELETE FROM image_produit');
    $photos=new App\Services\ProductPhotoPreparation($db,new App\Services\AdminAccessService(null),new App\Services\UploadService());$photos->cleanup();
    $db->exec('DROP DATABASE `'.$name.'`');echo 'FIXTURE_REMOVED';
}else throw new RuntimeException('Action refusée.');
