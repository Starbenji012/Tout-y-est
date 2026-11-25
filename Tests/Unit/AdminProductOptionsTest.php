<?php
declare(strict_types=1);
// Schéma S1.16, HTTP réel et données exclusivement dans une base jetable.
$root=dirname(__DIR__,2);ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Cible locale requise');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
if(($argv[1]??'')==='http') {
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_options_ui_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base HTTP refusée');
    putenv('DB_DATABASE='.$name);App\Core\Session::start();App\Core\Session::set('user',['id'=>(int)$argv[3]]);
    $_SERVER['REQUEST_METHOD']=$argv[4];$_SERVER['REQUEST_URI']=$argv[5];$input=json_decode(base64_decode($argv[6]),true,512,JSON_THROW_ON_ERROR);
    $_GET=$_SERVER['REQUEST_METHOD']==='GET'?$input:[];$_POST=$_SERVER['REQUEST_METHOD']==='POST'?$input:[];
    if($_SERVER['REQUEST_METHOD']==='POST')$_POST['_token']=($argv[7]??'')==='bad'?'bad':App\Middleware\CsrfMiddleware::token();
    http_response_code(200);register_shutdown_function(static function():void {echo '\n__HTTP='.http_response_code();});require $root.'/Public/index.php';exit;
}
$name='tout_y_est_options_ui_test_'.bin2hex(random_bytes(8));$db=$connect('tout_y_est');
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $fn,int $code=422)use($assert):void {try{$fn();}catch(DomainException $e){$assert($e->getCode()===$code,$e->getMessage());return;}throw new RuntimeException('Refus attendu');};
$http=static function(int $user,string $method,string $url,array $input,int $status,string $csrf='good')use($name,$assert):string {
    $process=proc_open([PHP_BINARY,__FILE__,'http',$name,(string)$user,$method,$url,base64_encode(json_encode($input,JSON_THROW_ON_ERROR)),$csrf],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$log=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $assert($exit===0&&str_ends_with($out,'__HTTP='.$status),'HTTP '.$status.' '.substr($out,-300).' '.$log);
    $assert(!preg_match('/(?:Warning|Notice|Fatal error):/',$out.$log),'Aucun diagnostic PHP');return $out;
};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Écritures exclusivement en base jetable');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut,email_verified_at) VALUES(1,'TEST','Admin','options-admin@example.invalid','unused','admin','actif',NOW()),(2,'TEST','Client','options-client@example.invalid','unused','client','actif',NOW()),(3,'TEST','Disabled','options-disabled@example.invalid','unused','admin','inactif',NOW())");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST simple','test-simple','','actif'),(2,1,'TEST libre','test-libre','','actif'),(3,1,'TEST zéro','test-zero','','brouillon')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,1,'TEST-ORIGINAL',25,'USD',8,'actif'),(2,2,'TEST-LIBRE',15,'USD',20,'actif')");
    App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);session_write_close();
    $service=new App\Services\AdminProductVariantService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Services\MoneyService(new App\Models\Setting($db)));
    $definitions=[['nom'=>'Matière','valeurs'=>['Coton','Lin']],['nom'=>'Contenance','valeurs'=>['Petit','Grand']]];
    foreach ([1,2,3] as $n) {
        $defs=array_slice([['nom'=>'Finition','valeurs'=>['Mate','Brillante']],['nom'=>'Volume','valeurs'=>['Petit','Grand']],['nom'=>'Stockage','valeurs'=>['128 Go','256 Go']]],0,$n);
        $preview=$service->candidates($defs);$assert($preview['count']===2**$n&&count($preview['candidates'])===2**$n,'Une/deux/trois options personnalisées');
    }
    foreach ([array_fill(0,4,['nom'=>'A','valeurs'=>['X']]),[['nom'=>'','valeurs'=>['X']]],[['nom'=>'A','valeurs'=>['']]],[['nom'=>'État','valeurs'=>['X']],['nom'=>' etat ','valeurs'=>['Y']]],[['nom'=>'A','valeurs'=>['Écru','ecru']]]] as $bad) $reject(fn()=>$service->candidates($bad));
    $large=[['nom'=>'A','valeurs'=>array_map('strval',range(1,11))],['nom'=>'B','valeurs'=>array_map('strval',range(1,10))]];
    $reject(fn()=>$service->candidates($large));
    $html=$http(1,'GET','/admin/produit',['id'=>1],200);
    $assert(str_contains($html,'Ce produit existe-t-il en plusieurs versions ?')&&str_contains($html,'data-options-panel hidden'),'Simple : complexité masquée');
    $assert(!str_contains($html,'clé canonique')&&!str_contains($html,'id_option'),'Pas de jargon commercial');
    $base=['id_produit'=>'1','mode'=>'yes','options'=>$definitions];
    $preview=$http(1,'POST','/admin/produits/versions',$base+['action'=>'preview'],200);
    $assert(str_contains($preview,'Coton / Petit')&&str_contains($preview,'Lin / Grand'),'Prévisualisation serveur réelle');
    $four=$http(1,'POST','/admin/produits/versions',array_replace($base,['options'=>array_fill(0,4,['nom'=>'A','valeurs'=>['X']]),'action'=>'preview']),422);$assert(str_contains($four,'Trois options maximum'),'Quatrième option refusée');
    $html=$http(1,'POST','/admin/produits/versions',array_replace($base,['options'=>$large,'action'=>'preview']),422);$assert(str_contains($html,'plus de 100 combinaisons')&&!str_contains($html,'class="admin-product-options__candidate"'),'Pas de rendu massif');
    $http(1,'POST','/admin/produits/versions',$base+['action'=>'preview'],419,'bad');
    $http(2,'POST','/admin/produits/versions',$base+['action'=>'preview'],403);
    $http(3,'POST','/admin/produits/versions',$base+['action'=>'preview'],403);
    $http(1,'GET','/admin/produits/versions',[],405);
    $http(1,'POST','/admin/produits/versions',$base+['action'=>'configure','selected'=>['1:1|2:1'],'reuse'=>'1:1|2:1','stock'=>'100'],422);
    $http(1,'POST','/admin/produits/versions',$base+['action'=>'configure','selected'=>['99:1'],'reuse'=>'99:1'],422);
    $http(1,'POST','/admin/produits/versions',$base+['action'=>'configure','selected'=>['1:1|2:1']],422);
    $assert((int)$db->query('SELECT COUNT(*) FROM option_produit')->fetchColumn()===0,'Aucune écriture sur validation refusée');
    // Fallback : agir sur la deuxième option, pas systématiquement la première.
    $html=$http(1,'POST','/admin/produits/versions',$base+['action'=>'add-value-draft:1'],200);
    $assert(str_contains($html,'options[1][valeurs][2]'),'Ajout valeur sans JS ciblé');
    $html=$http(1,'POST','/admin/produits/versions',$base+['action'=>'remove-option:1'],200);
    $assert(!str_contains($html,'name="options[1][nom]"'),'Retrait option sans JS ciblé');
    $old=$db->query('SELECT * FROM variante_produit WHERE id_variante=1')->fetch(PDO::FETCH_ASSOC);
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,2)');$db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,1,2)');
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,2,'TYE-TEST-OPTIONS-UI','confirmee',50,5,55,'USD',2450)");
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,1,1,'TEST historique','TEST-ORIGINAL',2,25,50)");
    $history=$db->query('SELECT * FROM ligne_commande')->fetchAll();
    $race=$connect($name);$race->beginTransaction();$race->query('SELECT COUNT(*) FROM option_produit')->fetchColumn();
    $configure=$base+['action'=>'configure','selected'=>['1:1|2:1','1:2|2:2'],'reuse'=>'1:2|2:2','sku_request'=>bin2hex(random_bytes(16))];
    $http(1,'POST','/admin/produits/versions',$configure,303);
    $current=(new App\Models\ProductVariant($race))->lock(1);
    $assert($current['options_pending_selection']===true,'Conversion détectée même dans une transaction avec ancienne snapshot SQL');$race->rollBack();
    $configuredRows=$db->query('SELECT * FROM variante_produit ORDER BY id_variante')->fetchAll();
    $http(1,'POST','/admin/produits/versions',$configure,303);
    $assert($db->query('SELECT * FROM variante_produit ORDER BY id_variante')->fetchAll()===$configuredRows,'Rejeu HTTP configuration sans nouvelle variante ni référence');
    $all=$db->query('SELECT * FROM variante_produit WHERE id_produit=1 ORDER BY id_variante')->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($all)===2&&$all[0]['id_variante']===$old['id_variante'],'Seulement deux combinaisons choisies, ID conservé');
    foreach (['sku','prix_reference','devise_reference','stock','statut'] as $field)$assert($all[0][$field]===$old[$field],'Ancienne valeur conservée : '.$field);
    $assert($all[0]['description_variante']==='Lin / Grand','Choix explicite ancienne combinaison');
    $assert($all[1]['statut']==='inactif'&&$all[1]['stock']===0&&$all[1]['prix_reference']==='25.00'&&(bool)preg_match('/^TYE-P[0-9]{6,}$/D',$all[1]['sku']),'Nouvelles versions jamais activées ni stock inventé');
    $catalog=new App\Models\Product($db);
    $assert($catalog->findById(1)===null&&$catalog->findByVariantIds([1])===[],'Fiche, aperçu et lecture panier bloqués après conversion');
    $assert($catalog->countCatalog(['priceMin'=>null,'priceMax'=>null])===1&&$catalog->findSuggestionCandidates('TEST')!==[],'Autre simple reste visible');
    foreach($catalog->findSuggestionCandidates('TEST') as $candidate)$assert((int)$candidate['id_produit']!==1,'Suggestions sans produit à options');
    $assert($catalog->findByIds([1])===[],'Favoris et lecture par IDs cohérents');
    // Prix/stock actifs conservés ne sont pas une autorisation d’achat via ancien panier.
    $variant=(new App\Models\ProductVariant($db))->lock(1);$assert($variant['options_pending_selection']===true,'Garde serveur indépendante des lectures catalogue');
    $users=new App\Models\User($db);$cartModel=new App\Models\Cart($db);$zones=new App\Models\DeliveryZone($db);$addresses=new App\Models\UserAddress($db);$money=new App\Services\MoneyService(new App\Models\Setting($db));
    $checkout=new App\Services\CheckoutService(new App\Services\CartService(new App\Services\ProductService(new App\Models\Product($db)),$cartModel,$money),new App\Services\AuthService($users),$users,$addresses,$zones,$money);
    $orders=new App\Services\OrderService($db,$users,$cartModel,new App\Models\ProductVariant($db),$zones,$addresses,new App\Models\Order($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),$checkout,$money);
    $reject(fn()=>$orders->create(2,[],bin2hex(random_bytes(32))),0);
    $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===8&&(int)$db->query('SELECT COUNT(*) FROM commande')->fetchColumn()===1,'Ancien panier ne permet ni achat silencieux ni réservation');
    $defs=$service->form(1)['definitions'];$option=(int)$defs[0]['id_option'];$value=(int)$defs[0]['valeurs'][0]['id_valeur'];
    $foreignValue=(int)$defs[1]['valeurs'][0]['id_valeur'];
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'remove-value','option'=>(string)$option,'value'=>(string)$foreignValue],422);
    $key=$all[0]['cle_combinaison'];
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'rename-option','option'=>(string)$option,'label'=>'Matière du produit'],303);
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'rename-value','option'=>(string)$option,'value'=>(string)$value,'label'=>'Coton doux'],303);
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'add-value','option'=>(string)$option,'label'=>'Chanvre'],303);
    $unused=(int)$db->query("SELECT id_valeur FROM valeur_option_produit WHERE id_option=$option AND libelle='Chanvre'")->fetchColumn();
    $assert($unused>0,'Ajout de valeur existante via route sécurisée');
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'remove-value','option'=>(string)$option,'value'=>(string)$unused],303);
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'remove-value','option'=>(string)$option,'value'=>(string)$value],422);
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'delete-option','option'=>(string)$option],422);
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'2','action'=>'rename-option','option'=>(string)$option,'label'=>'Étranger'],422);
    $assert($db->query('SELECT cle_combinaison FROM variante_produit WHERE id_variante=1')->fetchColumn()===$key,'Renommage sans identité modifiée');
    $http(1,'POST','/admin/produits/versions',['id_produit'=>'1','action'=>'extend','selected'=>['1:1|2:2']],303);
    $assert((int)$db->query('SELECT COUNT(*) FROM variante_produit WHERE id_produit=1')->fetchColumn()===3,'Ajout seulement de la combinaison demandée');
    $assert((int)$db->query("SELECT COUNT(*) FROM variante_produit WHERE id_produit=1 AND statut='actif'")->fetchColumn()===1,'Aucune nouvelle activation');
    $assert($db->query('SELECT * FROM ligne_commande')->fetchAll()===$history,'Snapshots historiques intacts');
    // Simuler le schéma non déployé uniquement dans cette base jetable, après nettoyage des fixtures modernes.
    $db->exec('DELETE FROM variante_valeur_option');$db->exec('DELETE FROM valeur_option_produit');$db->exec('DELETE FROM option_produit');$db->exec('UPDATE variante_produit SET cle_combinaison=NULL');
    $db->exec(file_get_contents($root.'/Database/migrations/S1.16_product_options_rollback.sql'));
    $html=$http(1,'GET','/admin/produit',['id'=>2],200);$assert(str_contains($html,'gestion des versions n’est pas encore disponible'),'Ancienne base sans panne ni migration implicite');
    $assert((new App\Models\Product($db))->findById(2)!==null,'Simple non migré toujours public');
    echo 'ADMIN_PRODUCT_OPTIONS_OK PHP='.PHP_VERSION." (HTTP, CSRF, droits, options 1..3, validation, plafond, choix, conversion, politique commerciale, garde publique, historique, fallback, renommage, suppressions).\n";
} finally {if(isset($race)&&$race->inTransaction())$race->rollBack();if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
