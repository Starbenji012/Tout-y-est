<?php
declare(strict_types=1);

// Fixtures et collisions uniquement dans une base jetable locale, jamais tout_y_est.
$root = dirname(__DIR__,2);
require_once $root . '/vendor/autoload.php';
spl_autoload_register(static function(string $class) use($root): void {
    if (!str_starts_with($class,'App\\')) return;
    $parts=explode('\\',substr($class,4)); $parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php'; if(is_file($file)) require_once $file;
});
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Serveur local requis.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$build=static fn(PDO $db):App\Services\AdminOrderService=>new App\Services\AdminOrderService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Services\MoneyService(new App\Models\Setting($db)));
$orderService=static function(PDO $db):App\Services\OrderService {
    $users=new App\Models\User($db); $cart=new App\Models\Cart($db); $zones=new App\Models\DeliveryZone($db); $addresses=new App\Models\UserAddress($db);
    $money=new App\Services\MoneyService(new App\Models\Setting($db));
    $checkout=new App\Services\CheckoutService(new App\Services\CartService(new App\Services\ProductService(new App\Models\Product($db)),$cart,$money),new App\Services\AuthService($users),$users,$addresses,$zones,$money);
    return new App\Services\OrderService($db,$users,$cart,new App\Models\ProductVariant($db),$zones,$addresses,new App\Models\Order($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),$checkout,$money);
};
App\Core\Session::start();
if(($argv[1]??'')==='worker') {
    $name=$argv[2]; if(!preg_match('/^tout_y_est_admin_orders_test_[a-f0-9]{16}$/D',$name)) throw new RuntimeException('Base refusée.');
    $db=$connect($name); $action=$argv[3]; $id=(int)$argv[4];
    App\Core\Session::set('user',['id'=>3,'role'=>'client']);
    try {
        $result=match($action) {
            'confirm'=>(new App\Services\PaymentService($db))->confirmCash(1,$id),
            'expire'=>['released'=>$orderService($db)->releaseReservation($id)],
            'cancel'=>$build($db)->cancel($id,'TEST annulation'),
            'ship'=>$build($db)->ship($id,'TEST Livreur'),
            default=>$build($db)->{$action}($id),
        };
        echo json_encode(['ok'=>true]+$result);
    } catch(DomainException $error) {echo json_encode(['ok'=>false,'code'=>$error->getCode()]);}
    exit;
}
$db=$connect($config['database']); $name='tout_y_est_admin_orders_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $fn,int $code=409)use($assert):void {
    try{$fn();}catch(DomainException $error){$assert($error->getCode()===$code,'Code de refus '.$error->getCode().' attendu '.$code);return;}
    throw new RuntimeException('Action interdite acceptée.');
};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    // S1.12 uniquement dans cette base jetable : ajout réel puis rejeu sans perte.
    $courierMigration=file_get_contents($root.'/Database/migrations/S1.12_delivery_courier.sql');
    $db->exec('ALTER TABLE livraison DROP COLUMN nom_livreur_snapshot');
    $db->exec($courierMigration);$db->exec($courierMigration);
    $migration=file_get_contents($root.'/Database/migrations/S1.11_admin_order_actions.sql');
    $db->exec('DROP TABLE journal_commande'); $db->exec($migration); $db->exec($migration);
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut,email_verified_at) VALUES
        (1,'TEST','Client','client@example.invalid','unused','client','actif',NOW()),
        (2,'TEST','Autre','other@example.invalid','unused','client','actif',NOW()),
        (3,'TEST','Admin','admin@example.invalid','unused','admin','actif',NOW()),
        (4,'TEST','Inactive','inactive@example.invalid','unused','admin','inactif',NOW()),
        (5,'TEST','Demoted','demoted@example.invalid','unused','client','actif',NOW())");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST snapshot','test','TEST','actif')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,stock,statut) VALUES(1,1,'TEST-ADMIN',20,1000,'actif')");
    $db->exec("INSERT INTO zone_livraison(id_zone_livraison,nom,frais_reference,statut) VALUES(1,'TEST zone',5,'actif')");
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,1)');
    App\Core\Session::set('user',['id'=>3,'role'=>'client']); // La BDD prime même sur un rôle session périmé.
    $service=$build($db); $payments=new App\Services\PaymentService($db);
    $make=static function(bool $confirm=true)use($db,$orderService,$payments):int {
        $db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,1,2) ON DUPLICATE KEY UPDATE quantite=2');
        $draft=['address'=>['nom_destinataire'=>'TEST','prenom_destinataire'=>'Client','telephone'=>'+243812345678','commune'=>'TEST','adresse'=>'TEST adresse','id_zone_livraison'=>1]];
        $id=$orderService($db)->create(1,$draft,bin2hex(random_bytes(32)))['id'];
        if($confirm)$payments->confirmCash(1,$id);
        return $id;
    };
    $order=static fn(int $id):array=>(new App\Models\Order($db))->find($id);
    $payment=static fn(int $id):array=>(new App\Models\Payment($db))->forOrder($id)[0];
    $shipping=static fn(int $id):array=>(new App\Models\Delivery($db))->forOrder($id);
    $stock=static fn():int=>(int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn();
    $events=static function(int $id)use($db):array {$q=$db->prepare('SELECT * FROM journal_commande WHERE id_commande=? ORDER BY id_journal');$q->execute([$id]);return $q->fetchAll();};
    $allState=static fn(int $id):array=>[$order($id),$payment($id),$shipping($id),$stock(),$events($id)];
    $actionHtml=static function(int $id)use($service,$order,$payment,$shipping,$root):string {
        $row=$order($id);$actions=$service->availableActions($row,[$payment($id)],$shipping($id));$order=['number'=>$row['numero_commande']];
        $escape=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        ob_start();require $root.'/App/views/admin/order-actions.php';return ob_get_clean();
    };
    $finish=static function(int $id)use($service):void {$service->prepare($id);$service->ship($id,'TEST Livreur');$service->deliver($id);};
    $id=$make(); $initial=$allState($id);
    $html=$actionHtml($id);$assert(str_contains($html,'data-order-action="prepare"')&&str_contains($html,'data-order-action="cancel"')&&!str_contains($html,'data-order-action="ship"'),'UI confirmée');
    $reject(fn()=>$service->ship($id,'TEST Livreur')); $reject(fn()=>$service->deliver($id)); $reject(fn()=>$service->collectCash($id));
    $assert($allState($id)===$initial,'Refus sans effet');
    $assert(!$service->prepare($id)['reused'],'Préparation'); $prepared=$allState($id);
    $assert($order($id)['statut']==='en_preparation' && $shipping($id)['date_preparation']!==null && $stock()===$initial[3],'Préparation sans stock');
    $assert($service->prepare($id)['reused'] && $allState($id)===$prepared,'Retry préparation sans date/journal supplémentaire');
    $html=$actionHtml($id);$assert(str_contains($html,'name="livreur"')&&str_contains($html,'data-order-action="ship"')&&!str_contains($html,'data-order-action="prepare"'),'UI préparation et affectation');
    $assert(array_keys($service->availableActions($order($id),[$payment($id)],$shipping($id)))===['ship','cancel'],'Actions préparation');
    foreach(['','A',str_repeat('A',121),"Livreur\nAutre"] as $invalid)$reject(fn()=>$service->ship($id,$invalid),422);
    $assert($allState($id)===$prepared,'Livreur invalide : aucune mutation');
    $service->ship($id,'TEST Livreur'); $shipped=$allState($id);
    $html=$actionHtml($id);$assert(str_contains($html,'data-order-action="deliver"')&&!str_contains($html,'data-order-action="cancel"'),'UI en livraison');
    $assert($shipping($id)['nom_livreur_snapshot']==='TEST Livreur','Livreur enregistré');
    $shipLog=$events($id)[1];$assert(json_decode($shipLog['donnees_complementaires'],true)['livreur']==='TEST Livreur'&&(int)$shipLog['id_administrateur']===3,'Livreur distinct de l’acteur');
    $reject(fn()=>$service->ship($id,'Autre livreur'));
    $assert($order($id)['statut']==='en_livraison' && $shipping($id)['date_expedition']!==null,'Expédition');
    $reject(fn()=>$service->prepare($id)); $reject(fn()=>$service->cancel($id,'TEST annulation'));
    $assert($service->ship($id,'TEST Livreur')['reused'] && $allState($id)===$shipped,'Retry expédition');
    $service->deliver($id); $delivered=$allState($id);
    $assert($order($id)['statut']==='livree' && $payment($id)['statut']==='a_encaisser' && $shipping($id)['date_livraison']!==null && $stock()===$initial[3],'Livrée ne signifie pas payée');
    $assert($shipping($id)['nom_livreur_snapshot']==='TEST Livreur'&&json_decode($events($id)[2]['donnees_complementaires'],true)['livreur']==='TEST Livreur','Livreur conservé après livraison');
    $assert(isset($service->availableActions($order($id),[$payment($id)],$shipping($id))['collectCash']),'Encaissement seul après livraison');
    $html=$actionHtml($id);$assert(str_contains($html,'data-order-action="collectCash"')&&!str_contains($html,'name="montant"'),'UI encaissement sans montant client');
    $assert($service->deliver($id)['reused'] && $allState($id)===$delivered,'Retry livraison');
    $reject(fn()=>$service->cancel($id,'TEST annulation'));
    // Le taux actuel et la désactivation du cash n'altèrent pas les commandes historiques.
    $db->exec("UPDATE parametre SET valeur='{\"reference\":\"USD\",\"alternative\":\"CDF\",\"usd_cdf_rate\":\"9999\"}' WHERE cle='monetary_config'");
    $db->exec("UPDATE mode_paiement SET statut='inactif'");
    $service->collectCash($id); $paid=$allState($id);
    $assert($order($id)['statut']==='livree' && $payment($id)['statut']==='paye' && $payment($id)['montant_reglement']==='45.00' && $payment($id)['devise_reglement']==='USD' && $payment($id)['date_paiement']!==null,'Encaissement USD historique');
    $assert($service->collectCash($id)['reused'] && $allState($id)===$paid,'Encaissement unique');
    $assert($service->availableActions($order($id),[$payment($id)],$shipping($id))===[],'Aucune action après encaissement');
    $assert(!str_contains($actionHtml($id),'data-order-action="'),'Aucune action UI après encaissement');
    $assert($service->deliver($id)['reused'] && $allState($id)===$paid,'Livraison déjà faite après encaissement');
    $reject(fn()=>$service->cancel($id,'TEST annulation'));
    $assert(count((new App\Models\Payment($db))->forOrder($id))===1 && $stock()===$initial[3],'Paiement unique, stock engagé');
    foreach(['numero_commande','sous_total','frais_livraison','total','devise_reference','taux_usd_cdf','cle_idempotence'] as $key)$assert($initial[0][$key]===$order($id)[$key],'Snapshot préservé : '.$key);
    $log=$events($id); $before=json_decode($log[0]['ancien_etat'],true);$after=json_decode($log[0]['nouvel_etat'],true);
    $assert(count($log)===4 && $before['commande']==='confirmee' && $after['commande']==='en_preparation' && (int)$log[0]['id_administrateur']===3,'Journal acteur/états');
    $data=json_decode($log[3]['donnees_complementaires'],true);
    $assert($data['devise']==='USD' && $data['montant']==='45.00' && $data['taux_usd_cdf']==='2450.0000' && (int)$log[3]['id_paiement']===(int)$payment($id)['id_paiement'],'Journal financier historique');
    $assert($payments->confirmCash(1,$id)['reused'] && $payments->confirmedForUser(1,$id)['order']['statut']==='livree','Anciennes requêtes cash restent sans double effet');
    $history=new App\Services\CustomerOrderService(new App\Models\Order($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),new App\Services\MoneyService());
    $assert($history->detail(1,$id)['order']['paymentStatus']==='Payé','Historique client compatible');
    $db->exec("UPDATE mode_paiement SET statut='actif'");
    $db->exec("UPDATE parametre SET valeur='{\"reference\":\"USD\",\"alternative\":\"CDF\",\"usd_cdf_rate\":\"2450\"}' WHERE cle='monetary_config'");
    $cdf=$make(); $db->exec("UPDATE commande SET devise_paiement='CDF',total_paiement=110250 WHERE id_commande=$cdf");
    $db->exec("UPDATE parametre SET valeur='{\"reference\":\"USD\",\"alternative\":\"CDF\",\"usd_cdf_rate\":\"9999\"}' WHERE cle='monetary_config'");
    $finish($cdf);$service->collectCash($cdf);
    $assert($payment($cdf)['devise_reglement']==='CDF' && $payment($cdf)['montant_reglement']==='110250.00' && $order($cdf)['total']==='45.00','CDF en francs entiers au taux snapshot');
    $inactiveMode=$make();$db->exec("UPDATE mode_paiement SET statut='inactif'");
    $finish($inactiveMode);$service->collectCash($inactiveMode);
    $assert($payment($inactiveMode)['statut']==='paye','Ancien cash gérable même après désactivation du mode');
    $db->exec("UPDATE mode_paiement SET statut='actif'");
    $badMoney=$make();$finish($badMoney);$before=$allState($badMoney);
    $db->exec("UPDATE paiement SET taux_usd_cdf=1 WHERE id_commande=$badMoney");
    $reject(fn()=>$service->collectCash($badMoney));
    $assert($payment($badMoney)['statut']==='a_encaisser' && count($events($badMoney))===3,'Snapshot financier incohérent refusé');
    foreach([false,true] as $prep) {
        $cancel=$make(); if($prep)$service->prepare($cancel); $reserved=$stock();
        $reject(fn()=>$service->cancel($cancel,''),422); $reject(fn()=>$service->cancel($cancel,str_repeat('x',501)),422);
        $service->cancel($cancel,'TEST annulation'); $cancelled=$allState($cancel);
        $assert($order($cancel)['statut']==='annulee' && $payment($cancel)['statut']==='annule' && $shipping($cancel)['statut']==='annulee' && $order($cancel)['stock_restitue_at']!==null && $stock()===$reserved+2,'Annulation et restitution');
        $assert($service->cancel($cancel,'TEST annulation')['reused'] && $allState($cancel)===$cancelled,'Retry annulation');
        $assert(json_decode(end($cancelled[4])['donnees_complementaires'],true)['motif']==='TEST annulation','Motif conservé');
        $reject(fn()=>$service->cancel($cancel,'Autre motif')); $reject(fn()=>$service->collectCash($cancel));
        $assert(!$payments->cancelCash($cancel) && $stock()===$reserved+2,'Entrée annulation existante conservée');
    }
    $bad=$make();$db->exec("UPDATE livraison SET date_expedition=NOW() WHERE id_commande=$bad");$reject(fn()=>$service->prepare($bad));
    $restored=$make();$db->exec("UPDATE commande SET stock_restitue_at=NOW() WHERE id_commande=$restored");
    $reject(fn()=>$service->prepare($restored));$reject(fn()=>$service->cancel($restored,'TEST annulation'));
    $duplicate=$make();(new App\Models\Payment($db))->createCash($order($duplicate),(int)$payment($duplicate)['id_mode_paiement']);
    $reject(fn()=>$service->prepare($duplicate));$assert($events($duplicate)===[],'Plusieurs paiements ambigus refusés');
    $pending=$make(false);$reject(fn()=>$service->prepare($pending));
    $db->exec("UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id_commande=$pending");
    $orderService($db)->releaseReservation($pending);$reject(fn()=>$service->prepare($pending));
    // Toute panne du journal annule aussi les dates, le paiement et la restitution.
    foreach(['prepare','ship','deliver','cancel','collectCash'] as $action) {
        $rollback=$make();
        if(in_array($action,['ship','deliver'],true))$service->prepare($rollback);
        if($action==='deliver')$service->ship($rollback,'TEST Livreur');
        if($action==='collectCash')$finish($rollback);
        $before=$allState($rollback);
        $db->exec("CREATE TRIGGER fail_admin_journal BEFORE INSERT ON journal_commande FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST journal failure'");
        try {
            if($action==='cancel')$service->cancel($rollback,'TEST annulation');elseif($action==='ship')$service->ship($rollback,'TEST Livreur');else $service->{$action}($rollback);
            throw new RuntimeException('Erreur journal attendue.');
        } catch(PDOException) {} finally {$db->exec('DROP TRIGGER fail_admin_journal');}
        $assert($allState($rollback)===$before && !$db->inTransaction(),'Rollback complet : '.$action);
    }
    // Le contrôleur et le service contrôlent chacun l'acteur réel, jamais les champs clients.
    $controller=new App\Controllers\Admin\OrderController($service,new App\Core\Request());
    $api=static function(int $actor,string $action,int $id,array $extra=[],string $method='POST')use($controller):array {
        App\Core\Session::set('user',['id'=>$actor,'role'=>'admin']); $_SERVER['REQUEST_METHOD']=$method;
        $_POST=array_replace(['commande'=>$id,'_token'=>App\Middleware\CsrfMiddleware::token()],$extra);
        ob_start();$controller->{$action}();$json=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);return [http_response_code(),$json];
    };
    $secure=$make();
    foreach([0=>401,2=>403,4=>403,5=>403,99999=>403] as $actor=>$status)$assert($api($actor,'prepare',$secure)[0]===$status,'Accès '.$actor);
    $assert($api(3,'prepare',$secure,['_token'=>'invalid'])[0]===419,'CSRF');
    $assert($api(3,'prepare',$secure,[],'GET')[0]===405,'GET interdit');
    foreach(['user_id','admin_id','statut','montant','devise','id_utilisateur','etat_paiement'] as $field)$assert($api(3,'prepare',$secure,[$field=>'1'])[0]===422,'Champ client interdit : '.$field);
    $assert($api(3,'prepare',0)[0]===422 && $api(3,'prepare',999999)[0]===404,'ID validé');
    $db->exec("UPDATE utilisateur SET role='client' WHERE id_utilisateur=3");$assert($api(3,'prepare',$secure)[0]===403,'Révocation BDD immédiate');
    $db->exec("UPDATE utilisateur SET role='admin' WHERE id_utilisateur=3");
    $assert($api(3,'prepare',$secure)[0]===200 && $api(3,'prepare',$secure)[1]['reused'],'API + retry');
    $assert($api(3,'cancel',$secure,['motif'=>'TEST annulation'])[0]===200,'API annulation');
    // Deux processus PHP distincts entrent en concurrence sur le même verrou de commande.
    $race=static function(int $id,array $actions)use($db,$name,$assert):array {
        $db->beginTransaction(); (new App\Models\Order($db))->find($id,true); $workers=[];
        foreach($actions as $action){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,$action,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
        usleep(200000);$db->commit();$results=[];
        foreach($workers as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
        return $results;
    };
    $double=$make();$results=$race($double,['prepare','prepare']);
    $assert($results[0]['ok'] && $results[1]['ok'] && $results[0]['reused']!==$results[1]['reused'] && count($events($double))===1,'Préparation concurrente unique');
    $service->ship($double,'TEST Livreur');$service->deliver($double);$results=$race($double,['collectCash','collectCash']);
    $assert($results[0]['ok'] && $results[1]['ok'] && count($events($double))===4 && count((new App\Models\Payment($db))->forOrder($double))===1,'Encaissement concurrent unique');
    $collectRace=$make();$finish($collectRace);$reserved=$stock();$results=$race($collectRace,['collectCash','cancel']);
    $assert($payment($collectRace)['statut']==='paye' && !$results[1]['ok'] && $stock()===$reserved,'Encaissement/annulation : aucune restitution après livraison');
    $opposite=$make();$service->prepare($opposite);$reserved=$stock();$results=$race($opposite,['ship','cancel']);
    $assert(count(array_filter($results,fn($r)=>$r['ok']))===1,'Expédition/annulation : un seul gagnant');
    $assert($stock()===$reserved+($order($opposite)['statut']==='annulee'?2:0),'Stock collision');
    $doubleCancel=$make();$reserved=$stock();$results=$race($doubleCancel,['cancel','cancel']);
    $assert($results[0]['ok'] && $results[1]['ok'] && $stock()===$reserved+2 && count($events($doubleCancel))===1,'Double annulation concurrente');
    $cashRace=$make(false);$reserved=$stock();$race($cashRace,['confirm','prepare']);
    $assert(in_array($order($cashRace)['statut'],['confirmee','en_preparation'],true) && $stock()===$reserved && count((new App\Models\Payment($db))->forOrder($cashRace))===1,'Collision confirmation cash/Admin');
    $expiry=$make(false);$db->exec("UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id_commande=$expiry");$reserved=$stock();$race($expiry,['expire','prepare']);
    $assert($order($expiry)['statut']==='expiree' && $stock()===$reserved+2 && $events($expiry)===[],'Collision expiration/Admin');
    $count=(int)$db->query('SELECT COUNT(*) FROM journal_commande')->fetchColumn();$db->exec($migration);
    $assert((int)$db->query('SELECT COUNT(*) FROM journal_commande')->fetchColumn()===$count,'Migration rejouable sans perte');
    $routes=file_get_contents($root.'/Routes/web.php');
    foreach(['preparer','mettre-en-livraison','livrer','encaisser-cash','annuler'] as $path)$assert(str_contains($routes,'/api/admin/commandes/'.$path),'Route explicite '.$path);
    echo "ADMIN_ORDER_ACTIONS_OK (droits BDD, CSRF, transitions, USD/CDF historique, annulation unique, journal atomique, rollback, concurrence, compatibilité).\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_admin_orders_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé.');
    $db->exec('USE `'.$config['database'].'`');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
