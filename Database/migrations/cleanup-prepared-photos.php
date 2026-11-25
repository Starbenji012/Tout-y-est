<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--expired')throw new RuntimeException('Usage : php Database/migrations/cleanup-prepared-photos.php --expired');
$root=dirname(__DIR__,2);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$service=new App\Services\ProductPhotoPreparation($db,new App\Services\AdminAccessService(null),new App\Services\UploadService());
echo 'EXPIRED_BATCHES_CLEANED='.$service->cleanup().PHP_EOL;
