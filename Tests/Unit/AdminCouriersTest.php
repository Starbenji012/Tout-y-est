<?php
declare(strict_types=1);
// Toutes les créations et modifications restent dans une base jetable locale.
$root=dirname(__DIR__,2);
require_once $root.'/vendor/autoload.php';
spl_autoload_register(static function(string $class)use($root):void {if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$build=static fn(PDO $db):App\Services\AdminCourierService=>new App\Services\AdminCourierService($db,new App\Services\AdminAccessService(new App\Models\User($db)));
App\Core\Session::start();
if(($argv[1]??'')==='worker') {
    $name=$argv[2];if(!preg_match('/^tout_y_est_couriers_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base refusée.');
    $db=$connect($name);App\Core\Session::set('user',['id'=>(int)$argv[3],'role'=>'admin']);
    $_SERVER['REQUEST_METHOD']=$argv[5]??'POST';
    $_POST=json_decode(base64_decode($argv[6]),true,512,JSON_THROW_ON_ERROR);
    $_POST+=['_token'=>App\Middleware\CsrfMiddleware::token()];
    ob_start();register_shutdown_function(static function():void {echo json_encode(['status'=>http_response_code(),'html'=>ob_get_clean(),'flash'=>App\Core\Session::get('_admin_courier_flash')],JSON_THROW_ON_ERROR);});
    $controller=new App\Controllers\Admin\CourierController($build($db),new App\Core\Request());
    $controller->{$argv[4]}();exit;
}
$db=$connect($config['database']);$name='tout_y_est_couriers_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $fn,int $code=422)use($assert):void {try{$fn();}catch(DomainException $e){$assert($e->getCode()===$code,'Code de refus '.$e->getCode().' attendu '.$code);return;}throw new RuntimeException('Opération refusée attendue.');};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'Admin','TEST','admin@example.invalid','unused','admin','actif'),(2,'Client','TEST','client@example.invalid','unused','client','actif'),(3,'Inactive','TEST','inactive@example.invalid','unused','admin','inactif')");
    $service=$build($db);$model=new App\Models\Courier($db);
    foreach([0=>401,2=>403,3=>403,999=>403]as$actor=>$code){App\Core\Session::set('user',['id'=>$actor,'role'=>'admin']);$reject(fn()=>$service->listing(),$code);$reject(fn()=>$service->save(null,'TEST Interdit'),$code);$reject(fn()=>$service->setAvailability(1,'actif'),$code);}
    App\Core\Session::set('user',['id'=>1]);
    $assert($service->listing()['installed']&&$service->listing()['couriers']===[],'État vide');
    foreach(['','A',str_repeat('A',121),"Nom\0Interdit","Nom\nInterdit","\xff"]as$invalid)$reject(fn()=>$service->save(null,$invalid));
    $created=$service->save(null,'  TEST   Gérant  ');$id=$created['id'];$row=$model->listing()[0];
    $assert($row['nom']==='TEST Gérant'&&$row['statut']==='inactif','Nom normalisé, création indisponible');
    $service->setAvailability($id,'actif');$assert(count($model->active())===1,'Disponible proposé');
    $service->setAvailability($id,'actif');$assert(count($model->listing())===1,'Cible explicite idempotente');
    $service->setAvailability($id,'inactif');$assert($model->active()===[],'Indisponible non proposé');
    $service->setAvailability($id,'actif');$assert(count($model->active())===1,'Retour disponible');
    $reject(fn()=>$service->setAvailability($id,'paye'));$reject(fn()=>$service->save(999,'TEST absent'),404);
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,2,'TYE-TEST-COURSIER','confirmee',20,5,25,'USD',2450)");
    $db->exec("INSERT INTO livraison(id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,frais_snapshot) VALUES(1,'Destinataire','TEST','TEST','TEST','Adresse historique',5)");
    $db->exec("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(1,1,25,'a_encaisser',2450)");
    $orders=new App\Services\AdminOrderService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Services\MoneyService());
    $orders->prepare(1);$service->setAvailability($id,'inactif');$reject(fn()=>$orders->ship(1,$id));
    $service->setAvailability($id,'actif');$orders->ship(1,$id);
    $snapshots=static function()use($db):string {$r=[];foreach(['commande','livraison','paiement','journal_commande']as$t)$r[$t]=$db->query('SELECT * FROM '.$t)->fetchAll();return serialize($r);};
    $before=$snapshots();$service->save($id,'TEST Collaborateur');$service->setAvailability($id,'inactif');
    $assert($before===$snapshots(),'Renommage et indisponibilité ne modifient aucun historique');
    $orders->deliver(1);$delivery=(new App\Models\Delivery($db))->forOrder(1);
    $assert($delivery['nom_livreur_snapshot']==='TEST Gérant'&&(int)$delivery['id_livreur']===$id,'Livreur historique conservé après livraison');
    $call=static function(int $actor,string $action,array $input,string $method='POST')use($name,$assert):array {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$actor,$action,$method,base64_encode(json_encode($input,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,$err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);
    };
    $assert($call(1,'save',['nom'=>'TEST CSRF','_token'=>'invalide'])['status']===419,'CSRF');
    $assert($call(2,'save',['nom'=>'TEST Interdit'])['status']===403,'Client refusé contrôleur');
    $assert($call(3,'save',['nom'=>'TEST Interdit'])['status']===403,'Admin inactif refusé contrôleur');
    $assert($call(0,'index',[],'GET')['status']===303,'Visiteur redirigé connexion');
    $assert($call(1,'save',['nom'=>'TEST GET'],'GET')['status']===405,'Mutation GET interdite');
    $assert($call(1,'save',['nom'=>['invalide']])['status']===422,'Nom tableau refusé');
    $assert($call(1,'save',['nom'=>'TEST','user_id'=>1])['status']===422,'Identité client refusée');
    $prg=$call(1,'save',['nom'=>'TEST <script>alert(1)</script>']);
    $assert($prg['status']===303&&$prg['html']===''&&is_string($prg['flash']),'Création PRG et succès');
    $newId=(int)$db->query('SELECT MAX(id_livreur) FROM livreur')->fetchColumn();
    $assert($call(1,'availability',['id_livreur'=>$newId,'statut'=>'actif'])['status']===303,'Disponibilité PRG');
    $view=$call(1,'index',[],'GET');$assert($view['status']===false||$view['status']===200,'Page GET');
    $assert(!str_contains($view['html'],'<script>alert(1)</script>')&&str_contains($view['html'],'&lt;script&gt;'),'Échappement nom');
    $assert(str_contains($view['html'],'Disponible')&&str_contains($view['html'],'Indisponible')&&str_contains($view['html'],'Rendre disponible')&&str_contains($view['html'],'Rendre indisponible'),'États et actions lisibles');
    $assert(str_contains($view['html'],'href="/admin/livreurs" aria-current="page"')&&!str_contains($view['html'],'href="/admin/zones-livraison" aria-current="page"'),'Navigation active');
    $db->exec('RENAME TABLE livreur TO livreur_pending');
    $pending=$call(1,'index',[],'GET');$assert(str_contains($pending['html'],'mise à jour prévue')&&!str_contains($pending['html'],'data-courier-form'),'Avant migration : pas de bouton mort');
    $db->exec('RENAME TABLE livreur_pending TO livreur');
    $db->exec("UPDATE utilisateur SET role='client' WHERE id_utilisateur=1");$reject(fn()=>$service->save($id,'TEST Interdit'),403);
    echo "ADMIN_COURIERS_OK (création, noms, disponibilité, snapshots, affectation, CSRF, droits BDD, PRG, XSS, navigation, schéma en attente).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_couriers_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('USE `'.$config['database'].'`');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
