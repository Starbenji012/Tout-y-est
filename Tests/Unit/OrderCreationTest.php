<?php
declare(strict_types=1);

// Base éphémère dédiée : aucun INSERT commande ni mouvement du stock réel.
$root = dirname(__DIR__, 2);
foreach (['core/Session','core/Controller','core/Request','core/Response','middleware/CsrfMiddleware','models/User','models/UserAddress','models/DeliveryZone','models/Setting','models/Cart','models/Product','models/ProductVariant','models/Order','models/OrderItem','models/Delivery','services/AuthService','services/ProductService','services/CartService','services/CheckoutService','services/OrderService','controllers/OrderController'] as $file) require_once $root . '/App/' . $file . '.php';
$config = require $root . '/Config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Tests autorisés uniquement sur le serveur local.');
$connect = static function (string $database) use ($config): PDO {
    return new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $database . ';charset=utf8mb4', $config['username'], $config['password'], $config['options']);
};
$build = static function (PDO $db): App\Services\OrderService {
    $users = new App\Models\User($db); $cart = new App\Models\Cart($db); $zones = new App\Models\DeliveryZone($db); $addresses = new App\Models\UserAddress($db);
    $money = new App\Services\MoneyService(new App\Models\Setting($db));
    $checkout = new App\Services\CheckoutService(new App\Services\CartService(new App\Services\ProductService(new App\Models\Product($db)), $cart, $money), new App\Services\AuthService($users), $users, $addresses, $zones, $money);
    return new App\Services\OrderService($db, $users, $cart, new App\Models\ProductVariant($db), $zones, $addresses, new App\Models\Order($db), new App\Models\OrderItem($db), new App\Models\Delivery($db), $checkout, $money);
};
$draft = ['address' => ['nom_destinataire'=>'TEST','prenom_destinataire'=>'Alice','telephone'=>'+243812345678','commune'=>'TEST','adresse'=>'TEST adresse','repere'=>'TEST repère','instructions'=>'TEST instructions','id_zone_livraison'=>1]];
if (($argv[1] ?? '') === 'worker') {
    $name = $argv[2] ?? '';
    if (!preg_match('/^tout_y_est_order_test_[a-f0-9]{16}$/D', $name)) throw new RuntimeException('Base worker refusée.');
    try { $result = $build($connect($name))->create((int) $argv[3], $draft, $argv[4]); echo json_encode(['ok'=>true]+$result); }
    catch (DomainException $error) { echo json_encode(['ok'=>false,'error'=>$error->getMessage()]); }
    exit;
}
$name = 'tout_y_est_order_test_' . bin2hex(random_bytes(8));
$db = $connect($config['database']);
$db->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$rejected = static function (callable $action) use ($assert): void {
    try { $action(); } catch (DomainException) { return; }
    $assert(false, 'Une opération invalide a été acceptée.');
};
try {
    $db->exec(str_replace('tout_y_est', $name, file_get_contents($root . '/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur (id_utilisateur,nom,prenom,email,mot_de_passe,statut,email_verified_at) VALUES (1,'TEST','Alice','alice@example.invalid','unused','actif',NOW()),(2,'TEST','Bob','bob@example.invalid','unused','actif',NOW()),(3,'TEST','Unverified','unverified@example.invalid','unused','actif',NULL)");
    $db->exec("INSERT INTO categorie (id_categorie,nom,slug,statut) VALUES (1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit (id_produit,id_categorie,nom,slug,description,statut) VALUES (1,1,'TEST produit','test-produit','TEST','actif')");
    $db->exec("INSERT INTO variante_produit (id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut,description_variante) VALUES (1,1,'TEST-USD',20,'USD',10,'actif','TEST variante')");
    $db->exec("INSERT INTO panier (id_panier,id_utilisateur) VALUES (1,1),(2,2),(3,3)");
    $db->exec("INSERT INTO zone_livraison (id_zone_livraison,nom,frais_reference,devise_reference,statut) VALUES (1,'TEST zone',5,'USD','actif')");
    $db->exec("INSERT INTO adresse_utilisateur (id_adresse,id_utilisateur,id_zone_livraison,nom_destinataire,prenom_destinataire,telephone,commune,adresse,repere,instructions,latitude,longitude) VALUES (1,1,1,'TEST','Alice','+243812345678','TEST','TEST adresse','TEST repère','TEST instructions',-4.3,15.3)");
    $service = $build($db); $key = bin2hex(random_bytes(32));
    $rejected(fn () => $service->create(0, $draft, $key));
    $rejected(fn () => $service->create(3, $draft, $key));
    $rejected(fn () => $service->create(1, $draft, $key));
    $db->exec('INSERT INTO ligne_panier (id_panier,id_variante,quantite) VALUES (1,1,1),(2,1,1)');
    $db->exec('UPDATE ligne_panier SET quantite=11 WHERE id_panier=1');
    $rejected(fn () => $service->create(1,$draft,$key));
    $assert((int)$db->query('SELECT quantite FROM ligne_panier WHERE id_panier=1')->fetchColumn()===11,'Quantité refusée, jamais plafonnée');
    $db->exec('UPDATE ligne_panier SET quantite=1 WHERE id_panier=1');
    foreach (["UPDATE variante_produit SET statut='inactif'", "UPDATE variante_produit SET stock=0", "UPDATE zone_livraison SET statut='inactif'", 'UPDATE variante_produit SET devise_reference=NULL'] as $sql) {
        $db->exec($sql); $rejected(fn () => $service->create(1, $draft, $key));
        $db->exec("UPDATE variante_produit SET statut='actif',stock=10,devise_reference='USD'"); $db->exec("UPDATE zone_livraison SET statut='actif'");
    }
    // Forcer une ligne orpheline uniquement dans cette base jetable.
    $db->exec('SET FOREIGN_KEY_CHECKS=0'); $db->exec('UPDATE ligne_panier SET id_variante=999 WHERE id_panier=1'); $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $rejected(fn () => $service->create(1, $draft, $key));
    $db->exec('UPDATE ligne_panier SET id_variante=1 WHERE id_panier=1');
    // Une erreur après le mouvement de stock doit annuler tous les inserts et ce mouvement.
    $db->exec("CREATE TRIGGER test_delivery_failure BEFORE INSERT ON livraison FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    try { $service->create(1, $draft, $key); throw new RuntimeException('Erreur attendue'); } catch (PDOException) {}
    $assert((int) $db->query('SELECT COUNT(*) FROM commande')->fetchColumn() === 0 && (int) $db->query('SELECT stock FROM variante_produit')->fetchColumn() === 10, 'Rollback intégral');
    $db->exec('DROP TRIGGER test_delivery_failure');
    App\Core\Session::start();
    $controller = new App\Controllers\OrderController($service, new App\Core\Request());
    $call = static function (int $user, array $input) use ($controller): array {
        App\Core\Session::set('user', ['id'=>$user]); $_SERVER['REQUEST_METHOD']='POST'; $_POST=$input;
        ob_start(); $controller->create(); return json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    };
    $input=['_creation_key'=>$key,'_token'=>App\Middleware\CsrfMiddleware::token(),'user_id'=>2,'total'=>'0','stock'=>'999','taux'=>'1','statut'=>'payee'];
    App\Core\Session::set('_checkout_creation_keys', [$key=>1]); App\Core\Session::set('_checkout_delivery', $draft+['userId'=>1,'addressId'=>1]);
    $call(0,$input); $assert(http_response_code()===401,'Authentification API');
    $call(1,array_replace($input,['_token'=>'bad'])); $assert(http_response_code()===419,'CSRF API');
    $call(1,array_replace($input,['_creation_key'=>str_repeat('a',64)])); $assert(http_response_code()===422,'Clé non émise refusée');
    $result=$call(1,$input); $assert($result['success'],'API création');
    $order=$db->query('SELECT * FROM commande')->fetch(); $id=(int)$order['id_commande'];
    $assert((bool)preg_match('/^TYE-[0-9]{3,}$/D',$order['numero_commande']),'Référence commerciale progressive générée côté serveur');
    $assert((int)$order['id_utilisateur']===1 && $order['statut']==='en_attente_paiement' && $order['total']==='25.00' && $order['taux_usd_cdf']==='2450.0000','Données navigateur ignorées');
    $assert((int)$db->query('SELECT TIMESTAMPDIFF(SECOND,date_commande,reservation_expire_at) FROM commande')->fetchColumn()===1800,'Réservation 30 minutes');
    $allocationsBefore=(int)$db->query('SELECT COUNT(*) FROM numero_commande_sequence')->fetchColumn();
    $retry=$call(1,$input); $assert($retry['reused'] && (int)$db->query('SELECT COUNT(*) FROM commande')->fetchColumn()===1 && (int)$db->query('SELECT stock FROM variante_produit')->fetchColumn()===9,'Retry sans double stock');
    $assert((int)$db->query('SELECT COUNT(*) FROM numero_commande_sequence')->fetchColumn()===$allocationsBefore,'Retry ne consomme pas de numéro commercial');
    $assert($db->query('SELECT numero_commande FROM commande')->fetchColumn()===$order['numero_commande'],'Retry conserve la référence');
    $assert((int)$db->query('SELECT COUNT(*) FROM ligne_panier')->fetchColumn()===2,'Panier conservé');
    $rejected(fn () => $service->forUser(2,$id));
    $db->exec("UPDATE produit SET nom='TEST changé'"); $db->exec("UPDATE variante_produit SET prix_reference=99,sku='TEST-CHANGE',description_variante='TEST changée'");
    $db->exec("UPDATE zone_livraison SET frais_reference=99,nom='TEST changée'");
    $db->exec("UPDATE adresse_utilisateur SET adresse='TEST adresse modifiée' WHERE id_adresse=1");
    $db->exec("UPDATE utilisateur SET nom='TEST profil modifié' WHERE id_utilisateur=1");
    (new App\Services\MoneyService(new App\Models\Setting($db)))->updateRate('2500');
    $snapshot=$build($db)->forUser(1,$id);
    $assert($snapshot['delivery']['adresse_snapshot']==='TEST adresse' && $snapshot['delivery']['nom_destinataire_snapshot']==='TEST' && $snapshot['delivery']['latitude_snapshot']==='-4.3000000','Adresse, profil et GPS figés');
    $assert($snapshot['order']['cdfFormatted']==='61 250 FC' && $snapshot['lines'][0]['nom_produit_snapshot']==='TEST produit' && $snapshot['lines'][0]['sku_snapshot']==='TEST-USD' && $snapshot['lines'][0]['description_variante_snapshot']==='TEST variante' && $snapshot['delivery']['nom_zone_snapshot']==='TEST zone' && $snapshot['delivery']['frais_snapshot']==='5.00','Snapshots historiques');
    $assert(!$service->releaseReservation($id),'Pas d’expiration anticipée');
    $db->exec("UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id_commande=$id");
    $assert($service->expireReservations()===1 && !$service->releaseReservation($id) && (int)$db->query('SELECT stock FROM variante_produit')->fetchColumn()===10,'Restitution exactement une fois');
    $db->exec('UPDATE variante_produit SET prix_reference=20');$db->exec('UPDATE zone_livraison SET frais_reference=5');
    $db->exec("INSERT INTO promotion (nom,pourcentage,date_debut,date_fin,statut) VALUES ('TEST remise',10,DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_ADD(NOW(),INTERVAL 1 DAY),'actif')");
    $db->exec('INSERT INTO beneficier VALUES (1,1)');
    $discounted=$build($db)->create(1,$draft,bin2hex(random_bytes(32)));
    $line=$db->query('SELECT * FROM ligne_commande WHERE id_commande='.(int)$discounted['id'])->fetch();
    $assert($line['prix_unitaire_applique']==='18.00' && $line['remise_unitaire_appliquee']==='2.00','Remise serveur figée');
    $service->releaseReservation($discounted['id'],'annulee');$db->exec('DELETE FROM beneficier');
    $db->exec("UPDATE variante_produit SET prix_reference=20,stock=1"); $db->exec('UPDATE zone_livraison SET frais_reference=5');
    // Deux processus attendent simultanément le dernier article verrouillé par le parent.
    $db->beginTransaction(); $db->query('SELECT * FROM variante_produit WHERE id_variante=1 FOR UPDATE')->fetch();
    $workers=[];
    foreach([1,2] as $actor){$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$actor,bin2hex(random_bytes(32))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    usleep(300000); $db->commit(); $wins=0; $winnerId=0;
    foreach($workers as [$process,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);$assert($exit===0,'Worker: '.$stderr);$worker=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);if($worker['ok']){$wins++;$winnerId=$worker['id'];}}
    $assert($wins===1 && (int)$db->query('SELECT stock FROM variante_produit')->fetchColumn()===0,'Concurrence dernier article : un seul gagnant');
    $assert($service->releaseReservation($winnerId,'annulee') && !$service->releaseReservation($winnerId,'annulee') && (int)$db->query('SELECT stock FROM variante_produit')->fetchColumn()===1,'Annulation idempotente');
    $sameKey=bin2hex(random_bytes(32)); $workers=[];
    $allocationsBefore=(int)$db->query('SELECT COUNT(*) FROM numero_commande_sequence')->fetchColumn();
    $db->beginTransaction(); $db->query('SELECT * FROM variante_produit WHERE id_variante=1 FOR UPDATE')->fetch();
    for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,'1',$sameKey],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    usleep(300000);$db->commit();$responses=[];
    foreach($workers as [$process,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,'Worker même clé: '.$stderr);$responses[]=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);}
    $assert($responses[0]['ok'] && $responses[1]['ok'] && $responses[0]['id']===$responses[1]['id'] && $responses[0]['reused']!==$responses[1]['reused'] && (int)$db->query('SELECT stock FROM variante_produit')->fetchColumn()===0,'Concurrence même clé : une seule commande');
    $assert((int)$db->query('SELECT COUNT(*) FROM numero_commande_sequence')->fetchColumn()===$allocationsBefore+1,'Retry concurrent : une seule allocation commerciale');
    $assert((int)$db->query('SELECT COUNT(*) FROM paiement')->fetchColumn()===0,'Aucun paiement créé');
    echo "ORDER_CREATION_OK (API, snapshots, rollback, idempotence, concurrence, expiration, panier conservé).\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    // Seule la base au nom aléatoire créée ci-dessus est supprimée.
    if(!preg_match('/^tout_y_est_order_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('USE `' . $config['database'] . '`');
    $db->exec('DROP DATABASE `' . $name . '`');
}
