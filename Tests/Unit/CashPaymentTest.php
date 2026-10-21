<?php
declare(strict_types=1);

// Base jetable : aucune commande ni aucun stock du catalogue réel n'est modifié.
$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) return;
    $parts = explode('\\', substr($class, 4)); $parts[0] = strtolower($parts[0]);
    $file = $root . '/App/' . implode('/', $parts) . '.php';
    if (is_file($file)) require_once $file;
});
$config = require $root . '/Config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Serveur non local refusé.');
$connect = static fn (string $name): PDO => new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4', $config['username'], $config['password'], $config['options']);
$orderService = static function (PDO $db): App\Services\OrderService {
    $users = new App\Models\User($db); $cart = new App\Models\Cart($db); $zones = new App\Models\DeliveryZone($db); $addresses = new App\Models\UserAddress($db);
    $money = new App\Services\MoneyService(new App\Models\Setting($db));
    $checkout = new App\Services\CheckoutService(new App\Services\CartService(new App\Services\ProductService(new App\Models\Product($db)), $cart, $money), new App\Services\AuthService($users), $users, $addresses, $zones, $money);
    return new App\Services\OrderService($db, $users, $cart, new App\Models\ProductVariant($db), $zones, $addresses, new App\Models\Order($db), new App\Models\OrderItem($db), new App\Models\Delivery($db), $checkout, $money);
};
if (($argv[1] ?? '') === 'worker') {
    $name = $argv[2];
    if (!preg_match('/^tout_y_est_cash_test_[a-f0-9]{16}$/D', $name)) throw new RuntimeException('Base refusée.');
    $db = $connect($name);
    try {
        if ($argv[3] === 'confirm') $result = (new App\Services\PaymentService($db))->confirmCash(1, (int) $argv[4]);
        else $result = ['released' => $orderService($db)->releaseReservation((int)$argv[4])];
        echo json_encode(['ok'=>true]+$result);
    } catch (DomainException) { echo json_encode(['ok'=>false]); }
    exit;
}
$db = $connect($config['database']);
$name = 'tout_y_est_cash_test_'.bin2hex(random_bytes(8));
$db->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$assert = static function (bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); };
$reject = static function (callable $fn) use ($assert): void { try {$fn();} catch (DomainException) {return;} $assert(false, 'Refus attendu'); };
try {
    $db->exec(str_replace('tout_y_est', $name, file_get_contents($root.'/Database/schema.sql')));
    $migration = str_replace('tout_y_est', $name, file_get_contents($root.'/Database/migrations/S1.9_cash_on_delivery.sql'));
    $db->exec($migration); $db->exec($migration);
    $assert((int)$db->query('SELECT COUNT(*) FROM mode_paiement')->fetchColumn()===1, 'Migration idempotente');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,email_verified_at) VALUES(1,'TEST','Alice','alice@example.invalid','unused',NOW()),(2,'TEST','Bob','bob@example.invalid','unused',NOW()),(3,'TEST','Unverified','u@example.invalid','unused',NULL)");
    $db->exec("INSERT INTO categorie VALUES(1,'TEST','test',NULL,'actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST','test','TEST','actif')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,stock,statut) VALUES(1,1,'TEST-1',20,18,'actif'),(2,1,'TEST-2',15,20,'actif')");
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,1),(2,2)');
    $service = new App\Services\PaymentService($db);
    $orders = new App\Models\Order($db);
    $make = static function (int $user = 1) use ($db, $orders): int {
        $id = $orders->create(['user_id'=>$user,'number'=>'TEST-'.bin2hex(random_bytes(8)),'subtotal'=>'40.00','shipping'=>'5.00','total'=>'45.00','rate'=>'2450','key_hash'=>hash('sha256',random_bytes(32))]);
        (new App\Models\OrderItem($db))->create(['order_id'=>$id,'product_id'=>1,'variant_id'=>1,'name'=>'TEST snapshot','sku'=>'TEST-SNAPSHOT','description'=>'TEST','quantity'=>2,'price'=>'20.00','discount'=>'0.00','total'=>'40.00']);
        (new App\Models\Delivery($db))->create($id, ['nom_destinataire'=>'TEST','prenom_destinataire'=>'Alice','telephone'=>'+243812345678','commune'=>'TEST','adresse'=>'TEST'], ['id_zone_livraison'=>null,'nom'=>'TEST','frais_reference'=>'5.00']);
        return $id;
    };
    $id=$make();
    $db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,1,5),(1,2,1),(2,1,4)');
    $before=$orders->find($id);
    $result=$service->confirmCash(1,$id); $after=$orders->find($id);
    $payment=(new App\Models\Payment($db))->forOrder($id)[0];
    $assert($after['statut']==='confirmee' && $after['reservation_expire_at']===null && $after['stock_restitue_at']===null, 'Confirmation et réservation');
    $assert($payment['statut']==='a_encaisser' && $payment['date_paiement']===null && $payment['reference_externe']===null && $payment['montant']==='45.00', 'Jamais encaissé');
    foreach(['total','sous_total','frais_livraison','taux_usd_cdf','devise_reference'] as $key) $assert($before[$key]===$after[$key], 'Snapshot '.$key);
    $cart=$db->query('SELECT id_panier,id_variante,quantite FROM ligne_panier ORDER BY id_panier,id_variante')->fetchAll();
    $assert(array_column($cart,'quantite')===[3,1,4], 'Quantités supplémentaires, autre article, autre utilisateur');
    $retry=$service->confirmCash(1,$id);
    $assert($retry['reused'] && $retry['paymentId']===$result['paymentId'] && $cart===$db->query('SELECT id_panier,id_variante,quantite FROM ligne_panier ORDER BY id_panier,id_variante')->fetchAll(), 'Retry sans effet');
    $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===18 && $orders->expiredIds(100)===[], 'Pas de second décrément ni expiration');
    $assert($orderService($db)->expireReservations()===0, 'ExpireReservations sans effet sur cash confirmé');
    $assert($service->confirmedForUser(1,$id)['order']['numero_commande']===$after['numero_commande'], 'Confirmation accessible au propriétaire');
    $reject(fn()=>$service->confirmedForUser(2,$id));
    $reject(fn()=>$service->confirmCash(2,$id)); $reject(fn()=>$service->confirmCash(0,$id));
    $unverified=$make(3); $reject(fn()=>$service->confirmCash(3,$unverified));
    $pending=$make();
    $db->exec("UPDATE utilisateur SET statut='inactif' WHERE id_utilisateur=1"); $reject(fn()=>$service->confirmCash(1,$pending)); $db->exec("UPDATE utilisateur SET statut='actif' WHERE id_utilisateur=1");
    $db->exec("UPDATE mode_paiement SET statut='inactif'"); $reject(fn()=>$service->confirmCash(1,$pending)); $db->exec("UPDATE mode_paiement SET statut='actif'");
    $db->exec("CREATE TRIGGER test_cash_rollback BEFORE UPDATE ON ligne_panier FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    try {$service->confirmCash(1,$pending); throw new RuntimeException('Erreur SQL attendue');} catch (PDOException) {}
    $assert($orders->find($pending)['statut']==='en_attente_paiement' && (new App\Models\Payment($db))->forOrder($pending)===[], 'Rollback paiement et commande');
    $db->exec('DROP TRIGGER test_cash_rollback');
    $db->exec("UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id_commande=$pending"); $reject(fn()=>$service->confirmCash(1,$pending));
    $db->exec("UPDATE livraison SET date_expedition=NOW() WHERE id_commande=$id"); $reject(fn()=>$service->cancelCash($id)); $db->exec("UPDATE livraison SET date_expedition=NULL WHERE id_commande=$id");
    $db->exec("UPDATE paiement SET date_paiement=NOW() WHERE id_commande=$id"); $reject(fn()=>$service->cancelCash($id)); $db->exec("UPDATE paiement SET date_paiement=NULL WHERE id_commande=$id");
    $assert($service->cancelCash($id) && !$service->cancelCash($id), 'Annulation unique');
    $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===20 && $orders->find($id)['stock_restitue_at']!==null, 'Restitution unique');
    $assert((new App\Models\Payment($db))->forOrder($id)[0]['statut']==='annule' && (new App\Models\Delivery($db))->forOrder($id)['statut']==='annulee', 'États annulation');
    App\Core\Session::start(); App\Core\Session::set('user',['id'=>1]); $_SERVER['REQUEST_METHOD']='POST'; $_POST=['commande'=>$pending,'_token'=>'invalid','user_id'=>2];
    $controller=new App\Controllers\PaymentController($service,new App\Core\Request(),new App\Services\MoneyService());
    ob_start();$controller->confirmCash();$json=json_decode(ob_get_clean(),true);$assert(http_response_code()===419 && isset($json['error']), 'CSRF invalide');
    App\Core\Session::set('user',[]);ob_start();$controller->confirmCash();ob_end_clean();$assert(http_response_code()===401, 'Session obligatoire');
    App\Core\Session::set('user',['id'=>1]);$_POST=['commande'=>$pending,'_token'=>App\Middleware\CsrfMiddleware::token(),'user_id'=>2,'total'=>'0'];
    ob_start();$controller->confirmCash();ob_end_clean();$assert(http_response_code()===422, 'API refuse réservation expirée avec CSRF valide');
    // Deux confirmations concurrentes doivent rendre le même paiement.
    $concurrent=$make();$workers=[];
    for($i=0;$i<2;$i++){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,'confirm',(string)$concurrent],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
    $responses=[];foreach($workers as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);$responses[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $assert($responses[0]['ok'] && $responses[1]['ok'] && $responses[0]['paymentId']===$responses[1]['paymentId'], 'Concurrence confirmation');
    $_POST=['commande'=>$concurrent,'_token'=>App\Middleware\CsrfMiddleware::token(),'user_id'=>2,'total'=>'0'];
    ob_start();$controller->confirmCash();$payload=json_decode(ob_get_clean(),true);$assert($payload['success']===true && $payload['reused']===true, 'API utilise session, ignore montants navigateur');
    $view=$service->confirmedForUser(1,$concurrent);extract($view);$totalFormatted='$45.00';$cdfFormatted='110 250 FC';
    ob_start();require $root.'/App/views/checkout/confirmation.php';$html=ob_get_clean();
    $assert(str_contains($html,'Votre commande est confirmée') && str_contains($html,'110 250 FC') && !str_contains($html,'a_encaisser'), 'Page confirmation non technique');
    $assert(str_contains($html,'N° de commande') && str_contains($html,'<strong>'.$order['numero_commande'].'</strong>') && str_contains($html,'Conservez ce numéro') && !str_contains($html,'Commande #'), 'Numéro complet et explication sans modifier sa valeur');
    $order=$orders->find($pending);$order['reservation_remaining_seconds']=120;$order['totalFormatted']='$45.00';$order['cdfFormatted']='110 250 FC';
    $lines=(new App\Models\OrderItem($db))->forOrder($pending);$delivery=(new App\Models\Delivery($db))->forOrder($pending);$cashAvailable=true;$csrfToken=App\Middleware\CsrfMiddleware::token();
    ob_start();require $root.'/App/views/checkout/payment.php';$html=ob_get_clean();
    $assert(substr_count($html,'type="radio"')===1 && substr_count($html,'aria-disabled="true"')===2 && str_contains($html,'data-cash-submit'), 'Cash seul sélectionnable, providers désactivés');
    $cashAvailable=false;ob_start();require $root.'/App/views/checkout/payment.php';$html=ob_get_clean();$assert(!str_contains($html,'type="radio"'), 'Mode inactif non sélectionnable');
    // Confirmation et expiration utilisent bien les services réels sur deux connexions.
    foreach ([false,true] as $expired) {
        $race=$make();
        if($expired)$db->exec("UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id_commande=$race");
        $stock=(int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn();
        $db->beginTransaction();$orders->find($race,true);$workers=[];
        foreach(['confirm','expire'] as $action){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,$action,(string)$race],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
        usleep(200000);$db->commit();
        foreach($workers as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);json_decode($out,true,512,JSON_THROW_ON_ERROR);}
        $final=$orders->find($race);
        $assert($final['statut']===($expired?'expiree':'confirmee'), 'Concurrence expiration / confirmation');
        $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===$stock+($expired?2:0), 'Stock concurrence');
        $assert(count((new App\Models\Payment($db))->forOrder($race))===($expired?0:1), 'Paiement concurrence');
    }
    echo "CASH_PAYMENT_OK (transaction, snapshots, panier ciblé, retry, concurrence, sécurité, annulation).\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_cash_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('USE `'.$config['database'].'`');$db->exec('DROP DATABASE `'.$name.'`');
}
