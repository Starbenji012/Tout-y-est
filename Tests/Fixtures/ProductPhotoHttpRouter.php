<?php
declare(strict_types=1);
// Authentification de fixture uniquement sur un serveur de test explicitement isolé.
$name=getenv('DB_DATABASE');
if(PHP_SAPI!=='cli-server'||!is_string($name)||!preg_match('/^tout_y_est_photo_http_[a-f0-9]{16}$/D',$name)||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
$root=dirname(__DIR__,2);
require_once $root.'/App/core/Session.php';
ini_set('session.save_path',getenv('TYE_PHOTO_TEST_SESSION'));
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/__fixture'){
    require_once $root.'/App/middleware/CsrfMiddleware.php';
    $c=require $root.'/Config/database.php';$db=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$name.';charset=utf8mb4',$c['username'],$c['password'],$c['options']);
    header('Content-Type: application/json');echo json_encode(['database'=>$name,'csrf'=>App\Middleware\CsrfMiddleware::token(),'products'=>(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn(),'images'=>(int)$db->query('SELECT COUNT(*) FROM image_produit')->fetchColumn()]);return;
}
return require $root.'/Public/index.php';
