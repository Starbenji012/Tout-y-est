<?php
declare(strict_types=1);
// Fixtures exclusivement dans une base jetable ; aucune écriture dans tout_y_est.
$root=dirname(__DIR__,2);
// Les sessions du test utilisent le répertoire temporaire du processus, pas celui d'Apache.
ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if (!str_starts_with($class,'App\\')) return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
if (($argv[1]??'')==='http') {
    if (!preg_match('/^tout_y_est_products_test_[a-f0-9]{16}$/D',$argv[2]??'')) throw new RuntimeException('Base isolée requise.');
    putenv('DB_DATABASE='.$argv[2]);
    App\Core\Session::start();App\Core\Session::set('user',['id'=>(int)$argv[3],'name'=>'TEST Admin']);
    $_SERVER['REQUEST_METHOD']=$argv[4]??'GET';$_SERVER['REQUEST_URI']='/admin/produits';$_GET=[];
    http_response_code(200);
    require $root.'/Public/index.php';echo '\n__HTTP='.http_response_code();exit;
}
$config=require $root.'/Config/database.php';
if ($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Serveur local requis.');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_products_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $call,int $code)use($assert):void {try{$call();}catch(DomainException $e){$assert($e->getCode()===$code,'Code refus');return;}throw new RuntimeException('Requête invalide acceptée');};
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Base jetable');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif'),(3,'TEST','Disabled','disabled@example.invalid','unused','admin','inactif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST catégorie','test','actif'),(2,'TEST inactive','test-inactive','inactif')");
    $insert=$db->prepare('INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(?,?,?,?,?,?)');
    $service=new App\Services\AdminProductReadService(new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminProductRead($db),new App\Services\MoneyService());
    $questions=static fn():int=>(int)$db->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1];
    $inserted=0;
    foreach ([1,20,21] as $count) {
        while($inserted<$count){$inserted++;$insert->execute([$inserted,$inserted===2?2:1,$inserted===1?'<script>TEST</script>':'TEST '.$inserted,'test-'.$inserted,'TEST',['actif','brouillon','archive'][($inserted-1)%3]]);}
        $before=$questions();$data=$service->listing([]);$queries=$questions()-$before-1;
        $assert($queries===6,'N+1 : attendu 6, obtenu '.$queries);
        $assert(count($data['products'])===min(20,$count)&&$data['total']===$count,'Pagination');
    }
    $empty=$service->listing(['q'=>'absent']);$assert($empty['products']===[]&&$empty['total']===0,'État vide');
    $first=$service->listing(['page'=>'1']);$second=$service->listing(['page'=>'2']);
    $assert($first['products'][0]['id_produit']===21&&count($second['products'])===1,'Ordre et deuxième page');
    foreach (['actif'=>'Actif','brouillon'=>'Brouillon','archive'=>'Archivé'] as $status=>$label) {
        $data=$service->listing(['statut'=>$status]);$assert($data['total']===7&&$data['products'][0]['statusLabel']===$label,'Statut Admin visible : '.$status);
    }
    $data=$service->listing(['categorie'=>'2','q'=>'TEST']);$assert($data['total']===1&&$data['products'][0]['id_produit']===2,'Catégorie inactive consultable par Admin');
    $assert(str_contains($data['paginationUrl'],'categorie=2')&&str_contains($data['paginationUrl'],'q=TEST'),'Filtres dans liens pagination');
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,'TEST-SKU',25,'USD',3,'actif'),(1,'TEST-OTHER',15,'USD',4,'inactif'),(2,'TEST-ONE',20,'USD',2,'actif'),(3,'TEST-UNKNOWN',99,NULL,1,'actif'),(3,'TEST-KNOWN',10,'USD',2,'actif')");
    $row=$service->listing(['q'=>'TEST-SKU'])['products'][0];
    $assert($row['price']==='$25.00'&&$row['variantCount']===2&&$row['stock']===3&&$row['inactiveStock']===4,'Prix/stock actifs seuls, total variantes conservé');
    $data=$service->listing(['q'=>'TEST-SKU']);extract($data);ob_start();require $root.'/App/views/admin/products.php';$html=ob_get_clean();
    $assert(str_contains($html,'+ 4 sur variantes inactives')&&str_contains($html,'<strong>3</strong>')&&!str_contains($html,'$15.00'),'Stocks séparés et prix inactif absent du rendu');
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,'TEST-SECOND-ACTIVE',30,'USD',2,'active'),(4,'TEST-ONLY-INACTIVE',999,NULL,6,'inactif')");
    $row=$service->listing(['q'=>'TEST-SKU'])['products'][0];$assert($row['price']==='$25.00 – $30.00'&&$row['stock']===5&&$row['inactiveStock']===4&&$row['variantCount']===3,'Plage active et alias active');
    $row=$service->listing(['q'=>'TEST-ONLY-INACTIVE'])['products'][0];$assert($row['price']==='Aucune variante active'&&$row['stock']===0&&$row['inactiveStock']===6&&$row['variantCount']===1,'Variante inactive seule sans faux prix');
    $row=$service->listing(['categorie'=>'2'])['products'][0];$assert($row['price']==='$20.00'&&$row['variantCount']===1,'Mono-variante');
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(2,'TEST-SAME-PRICE',20,'USD',1,'actif')");
    $row=$service->listing(['categorie'=>'2'])['products'][0];$assert($row['price']==='$20.00'&&$row['variantCount']===2&&$row['stock']===3&&$row['inactiveStock']===0,'Prix identiques sans plage ni stock inactif inventé');
    $row=$service->listing(['q'=>'TEST 3'])['products'][0];$assert(str_contains($row['price'],'Devise à préciser')&&str_contains($row['price'],'$10.00'),'Devise absente non mélangée');
    // Robustesse à une future devise : assouplissement UNIQUEMENT de la fixture jetable.
    $db->exec('ALTER TABLE variante_produit DROP CONSTRAINT chk_variante_devise');
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,'TEST-CDF',49000,'CDF',1,'actif')");
    $row=$service->listing(['q'=>'TEST-SKU'])['products'][0];$assert(str_contains($row['price'],'49 000 FC')&&str_contains($row['price'],'$25.00 – $30.00')&&$row['stock']===6&&$row['inactiveStock']===4&&$row['variantCount']===4,'Devises actives séparées, variantes toujours comptées');
    $row=$service->listing(['q'=>'TEST 21'])['products'][0];$assert($row['image']===null&&$row['price']==='Aucune variante active'&&$row['stock']===0&&$row['inactiveStock']===0,'Sans image/variante');
    $db->exec("UPDATE variante_produit SET stock=0 WHERE id_produit=2");
    $row=$service->listing(['categorie'=>'2'])['products'][0];$assert($row['price']==='$20.00'&&$row['stock']===0,'Prix actif conservé en rupture');
    // Le nombre de requêtes reste constant avec les groupes actif/inactif et devises réels.
    $before=$questions();$service->listing([]);$assert($questions()-$before-1===6,'N+1 avec variantes actives/inactives et images');
    $db->exec("INSERT INTO image_produit(id_produit,chemin,image_principale) VALUES(1,'/assets/images/products/watch.jpg',1)");
    $assert($service->listing(['q'=>'TEST-SKU'])['products'][0]['image']==='/assets/images/products/watch.jpg','Image groupée');
    foreach ([[],['id'=>2],['id'=>3]] as $user) {App\Core\Session::set('user',$user);$reject(fn()=>$service->listing([]),$user===[]?401:403);}
    App\Core\Session::set('user',['id'=>1]);
    foreach ([['statut'=>'inconnu'],['categorie'=>'-1'],['page'=>'0'],['q'=>[]],['page'=>str_repeat('9',25)]] as $input) $reject(fn()=>$service->listing($input),422);
    $data=$service->listing(['q'=>'TEST-SKU']);extract($data);ob_start();require $root.'/App/views/admin/products.php';$html=ob_get_clean();
    $assert(str_contains($html,'&lt;script&gt;TEST&lt;/script&gt;')&&!str_contains($html,'<script>TEST</script>'),'HTML échappé');
    $assert(str_contains($html,'/admin/produit?id=1')&&str_contains($html,'/admin/produit/ajouter')&&str_contains($html,'method="get"'),'Actions vers de vrais formulaires, liste toujours GET');
    $data=$service->listing(['q'=>'absent']);extract($data);ob_start();require $root.'/App/views/admin/products.php';$html=ob_get_clean();$assert(str_contains($html,'Aucun produit trouvé'),'État vide rendu');
    $layout=file_get_contents($root.'/App/views/layouts/admin.php');
    $assert(str_contains($layout,"=== 'zones'")&&str_contains($layout,'/admin/produits')&&!str_contains($layout,'!in_array'),'Navigation explicite');
    foreach (['orders'=>'/admin/commandes','products'=>'/admin/produits','zones'=>'/admin/zones-livraison','couriers'=>'/admin/livreurs'] as $adminPage=>$destination) {
        $content='';ob_start();require $root.'/App/views/layouts/admin.php';$navigation=ob_get_clean();
        $assert(substr_count($navigation,'aria-current="page"')===1&&str_contains($navigation,'href="'.$destination.'" aria-current="page"'),'Un seul onglet actif : '.$adminPage);
    }
    // Vrai bootstrap et route : aucune dépendance implicite oubliée.
    foreach ([[1,'GET',200],[2,'GET',403],[3,'GET',403],[1,'POST',405]] as [$id,$method,$expected]) {
        $process=proc_open([PHP_BINARY,__FILE__,'http',$name,(string)$id,$method],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$log=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $assert(proc_close($process)===0&&str_ends_with($output,'__HTTP='.$expected),'Bootstrap HTTP '.$expected.' '.substr($output,-100).' '.$log);
        if($expected===200)$assert(str_contains($output,'admin-products')&&str_contains($output,'Sans image'),'Liste réelle rendue');
    }
    echo "ADMIN_PRODUCTS_READ_OK (droits, statuts, recherche/SKU, filtres, pagination, prix/devises, stock, images, XSS, bootstrap, N+1 : 6 requêtes pour 1/20/21 produits).\n";
} finally {
    if ($db->inTransaction())$db->rollBack();
    $db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
