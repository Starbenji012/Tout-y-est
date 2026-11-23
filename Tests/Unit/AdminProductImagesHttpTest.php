<?php
declare(strict_types=1);
// Véritable multipart HTTP -> is_uploaded_file() -> bootstrap complet -> PRG.
// Serveur éphémère loopback et base jetable ; aucun accès de test en production.
$root=dirname(__DIR__,2);$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname=tout_y_est;charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_image_http_test_'.bin2hex(random_bytes(8));
$temporary=sys_get_temp_dir().'/tye-image-http-'.bin2hex(random_bytes(8));mkdir($temporary,0700);
$server=null;$created=[];$key=bin2hex(random_bytes(24));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif'),(3,'TEST','Disabled','disabled@example.invalid','unused','admin','inactif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST HTTP photo','test-http-photo','','actif')");
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,'TEST-HTTP',20,'USD',10,'actif')");
    // Fixture exécutée seulement par ce serveur de test hors dépôt.
    $router='<?php $root='.var_export($root,true).'; putenv('.var_export('DB_DATABASE='.$name,true).'); ini_set("session.save_path",'.var_export($temporary,true).'); '. <<<'PHP'
spl_autoload_register(static function(string $class)use($root):void {if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
PHP;
    $router.='if(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)==="/__test/session" && hash_equals('.var_export($key,true).',$_GET["key"]??"")){App\Core\Session::start();App\Core\Session::set("user",["id"=>(int)($_GET["user"]??1)]);if(isset($_GET["preview"])){header("Location: /admin/produit?id=1",true,303);return;}header("Content-Type: application/json");echo json_encode(["token"=>App\Middleware\CsrfMiddleware::token()]);return;} if(is_file($root."/Public".parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)))return false;require $root."/Public/index.php";';
    file_put_contents($temporary.'/router.php',$router);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('Port test indisponible');
    $port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/Public',$temporary.'/router.php'],[0=>['pipe','r'],1=>['file',$temporary.'/server.log','a'],2=>['file',$temporary.'/server.log','a']],$pipes,$root);
    if(!is_resource($server))throw new RuntimeException('Serveur test indisponible');fclose($pipes[0]);
    $base='http://127.0.0.1:'.$port;$cookie='';
    $request=static function(string $url,string $method='GET',string $body='',array $headers=[])use($base,&$cookie):array {
        if($cookie!=='')$headers[]='Cookie: '.$cookie;
        $context=stream_context_create(['http'=>['method'=>$method,'content'=>$body,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);
        $text=@file_get_contents($base.$url,false,$context);$response=$http_response_header??[];
        foreach($response as $header)if(preg_match('/^Set-Cookie: ([^;]+)/i',$header,$match))$cookie=$match[1];
        preg_match('/\s(\d{3})\s/',$response[0]??'',$match);
        return [(int)($match[1]??0),$text===false?'':$text,$response];
    };
    $login=static function(int $user)use($request,$key):string {[$status,$body]=$request('/__test/session?key='.$key.'&user='.$user);if($status!==200)throw new RuntimeException('Session HTTP test indisponible');return json_decode($body,true,512,JSON_THROW_ON_ERROR)['token'];};
    for($attempt=0;$attempt<40;$attempt++){[$status]=$request('/__test/session?key='.$key);if($status===200)break;usleep(50000);}
    $token=$login(1);
    $upload=static function(string $token,?string $bytes)use($request):array {
        $boundary='----tye'.bin2hex(random_bytes(12));$body='';
        foreach(['_token'=>$token,'id_produit'=>'1'] as $field=>$value)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"$field\"\r\n\r\n$value\r\n";
        if($bytes!==null)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"untrusted.php\"\r\nContent-Type: text/plain\r\n\r\n".$bytes."\r\n";
        $body.='--'.$boundary."--\r\n";
        return $request('/admin/produits/photo','POST',$body,['Content-Type: multipart/form-data; boundary='.$boundary]);
    };
    [$status]=$request('/admin/produits/photo');$assert($status===405,'POST requis');
    [$status]=$upload('wrong',null);$assert($status===419,'CSRF invalide refusé');
    [$status,$html]=$upload($token,null);$assert($status===422&&str_contains($html,'Choisissez une photo'),'Upload manquant expliqué');
    [$status]=$upload($token,'<?php echo "invalid";');$assert($status===422,'Faux fichier refusé');
    foreach([2,3,0] as $user){$other=$login($user);[$status]=$upload($other,'invalid');$assert($status===($user===0?303:403),'Droits relus BDD : user='.$user.' HTTP='.$status);}
    $token=$login(1);[$status,$html]=$request('/admin/produit?id=1');$assert($status===200&&str_contains($html,'0 / 6 photos'),'Galerie vide via bootstrap');
    $canvas=imagecreatetruecolor(360,240);ob_start();imagejpeg($canvas);$jpeg=ob_get_clean();unset($canvas);
    for($i=0;$i<6;$i++){
        [$status,,$headers]=$upload($token,$jpeg);$assert($status===303,'Upload HTTP réel et PRG');
        $row=$db->query('SELECT chemin FROM image_produit ORDER BY id_image DESC LIMIT 1')->fetchColumn();
        foreach(['thumb','medium','large'] as $size)$created[]=$root.'/Public/'.str_replace('-large.webp','-'.$size.'.webp',$row);
        if($i===0){[$status,$html]=$request('/admin/produit?id=1');$assert($status===200&&str_contains($html,'1 / 6 photos')&&str_contains($html,'Principale')&&str_contains($html,'Photo ajoutée.'),'Persistance galerie et flash');
            [$status,$html]=$request('/produit?id=1');$assert($status===200&&str_contains($html,$row),'Lecture publique nouvelle principale');}
    }
    [$status,$html]=$upload($token,$jpeg);$assert($status===422&&str_contains($html,'limite de 6'),'Septième photo refusée HTTP');
    [$status,$html]=$request('/admin/produit?id=1');$assert($status===200&&str_contains($html,'6 / 6 photos')&&!str_contains($html,'type="file"'),'Galerie pleine sans action morte');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit')->fetchColumn()===6,'Six lignes seulement');
    foreach($created as $file)$assert(is_file($file)&&getimagesize($file)[2]===IMAGETYPE_WEBP,'Sorties publiques GD seulement');
    $manage=static function(string $token,int $image,string $action)use($request):array {
        return $request('/admin/produits/photo/modifier','POST',http_build_query(['_token'=>$token,'id_produit'=>1,'id_image'=>$image,'action'=>$action]),['Content-Type: application/x-www-form-urlencoded']);
    };
    [$status]=$request('/admin/produits/photo/modifier');$assert($status===405,'Gestion photo POST requis');
    $rows=$db->query('SELECT * FROM image_produit ORDER BY ordre_affichage,id_image')->fetchAll(PDO::FETCH_ASSOC);
    $last=(int)$rows[5]['id_image'];$lastPath=$rows[5]['chemin'];
    [$status]=$manage('wrong',$last,'primary');$assert($status===419,'Gestion photo CSRF requis');
    $other=$login(2);[$status]=$manage($other,$last,'primary');$assert($status===403,'Gestion photo non-admin refusé');$token=$login(1);
    [$status]=$manage($token,999999,'delete');$assert($status===404,'Gestion photo inconnue refusée');
    [$status]=$manage($token,$last,'primary');$assert($status===303,'Changement principale PRG');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit WHERE image_principale=1')->fetchColumn()===1&&(int)$db->query('SELECT id_image FROM image_produit WHERE image_principale=1')->fetchColumn()===$last,'Une seule nouvelle principale');
    [$status]=$manage($token,$last,'up');$assert($status===303,'Monter HTTP');
    $ids=array_map('intval',$db->query('SELECT id_image FROM image_produit ORDER BY ordre_affichage,id_image')->fetchAll(PDO::FETCH_COLUMN));$assert($ids[4]===$last,'Monter position réelle');
    [$status,$html]=$request('/produit?id=1');preg_match('~data-gallery-main src="([^"]+)"~',$html,$main);
    $assert($status===200&&($main[1]??null)==='/'.$lastPath,'Principale client indépendante de son ordre');
    [$status]=$manage($token,$last,'down');$assert($status===303,'Descendre HTTP');
    [$status]=$manage($token,$last,'delete');$assert($status===303,'Suppression principale HTTP');
    $assert((int)$db->query('SELECT COUNT(*) FROM image_produit WHERE image_principale=1')->fetchColumn()===1,'Promotion automatique HTTP');
    $ids=array_map('intval',$db->query('SELECT id_image FROM image_produit ORDER BY ordre_affichage,id_image')->fetchAll(PDO::FETCH_COLUMN));
    foreach(array_slice($ids,1) as $id){[$status]=$manage($token,$id,'delete');$assert($status===303,'Suppression secondaire HTTP');}
    [$status,$html]=$manage($token,$ids[0],'delete');$assert($status===422&&str_contains($html,'Un produit actif doit conserver au moins une photo.'),'Dernière active refusée HTTP');
    $db->exec("UPDATE produit SET statut='brouillon' WHERE id_produit=1");
    [$status]=$manage($token,$ids[0],'delete');$assert($status===303&&(int)$db->query('SELECT COUNT(*) FROM image_produit')->fetchColumn()===0,'Dernière brouillon supprimée HTTP');
    foreach($created as $file)$assert(!is_file($file),'Dérivés HTTP nettoyés après suppression');
    echo "ADMIN_PRODUCT_GALLERY_HTTP_OK (POST/CSRF/Admin, principale, ordre, rendu client, suppression, promotion, dernière active protégée).\n";
    echo "ADMIN_PRODUCT_IMAGES_HTTP_OK (vrai multipart, is_uploaded_file, MIME serveur, droits/CSRF/POST, PRG, galerie persistante 0/1/6, principale publique, limite 6).\n";
    if(($argv[1]??'')==='--browser') {
        // Démonstration manuelle facultative, toujours dans la même base jetable.
        $db->exec('DELETE FROM image_produit');
        foreach(array_unique($created) as $file)if(is_file($file))unlink($file);$created=[];
        file_put_contents($temporary.'/browser-test.jpg',$jpeg);
        file_put_contents($temporary.'/browser-invalid.txt','invalid image');
        echo 'BROWSER_URL='.$base.'/__test/session?key='.$key."&preview=1\n";
        echo 'BROWSER_IMAGE='.$temporary."/browser-test.jpg\n";
        echo 'BROWSER_INVALID='.$temporary."/browser-invalid.txt\n";
        echo "Appuyer sur Entrée après la validation navigateur pour nettoyer.\n";
        fgets(STDIN);
    }
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    // Inclut une éventuelle requête réussie avant qu'une assertion échoue.
    if($db->query('SELECT DATABASE()')->fetchColumn()===$name){foreach($db->query('SELECT chemin FROM image_produit')->fetchAll(PDO::FETCH_COLUMN) as $path)if(preg_match('~^uploads/products/[a-f0-9]{32}-large\.webp$~D',$path))foreach(['thumb','medium','large'] as $size)$created[]=$root.'/Public/'.str_replace('-large.webp','-'.$size.'.webp',$path);}
    foreach(array_unique($created) as $file)if(is_file($file))unlink($file);
    $db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    foreach(glob($temporary.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($temporary);
}
