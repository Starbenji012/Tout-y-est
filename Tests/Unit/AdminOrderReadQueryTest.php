<?php
declare(strict_types=1);
// PDO natif + compteur MariaDB : aucune classe PDO/PDOStatement redéfinie.
// Les fixtures et leurs mutations restent exclusivement dans une base jetable.
$root=dirname(__DIR__,2);
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Base locale attendue.');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_admin_query_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Fixtures hors base réelle');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','query-test@example.invalid','unused','admin','actif')");
    $mode=$db->prepare('SELECT id_mode_paiement FROM mode_paiement WHERE nom=?');
    $mode->execute([App\Models\PaymentMethod::CASH]);$modeId=(int)$mode->fetchColumn();
    $assert($modeId>0,'Mode cash des fixtures disponible');
    $order=$db->prepare("INSERT INTO commande(id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,?,'confirmee',40,5,45,'USD',2450)");
    $payment=$db->prepare("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(?,?,45,'a_encaisser',2450)");
    $delivery=$db->prepare("INSERT INTO livraison(id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,frais_snapshot) VALUES(?,'TEST','Destinataire','TEST','TEST','TEST',5)");
    $service=new App\Services\AdminOrderReadService(new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminOrderRead($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),new App\Models\Payment($db),new App\Models\AdminOrderJournal($db),new App\Services\MoneyService());
    // Questions compte les exécutions de CETTE connexion, pas les autres clients.
    $questions=static fn():int=>(int)$db->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1];
    $before=$questions();$db->query('SELECT 1')->fetchColumn();$after=$questions();
    $assert($after-$before===2,'Compteur SQL vérifié : une lecture + la sonde finale');
    $inserted=0;
    foreach([1,20,21]as$count) {
        while($inserted<$count) {
            $inserted++;$order->execute(['TYE-TEST-QUERY-'.$inserted]);$id=(int)$db->lastInsertId();
            $payment->execute([$id,$modeId]);$delivery->execute([$id]);
        }
        $before=$questions();
        $data=$service->listing(['etat'=>'confirmee','encaisser'=>'1']);
        $after=$questions();$queryCount=$after-$before-1; // La sonde finale n'appartient pas au service.
        if($queryCount!==4)throw new RuntimeException('Régression N+1 : '.$queryCount.' requêtes');
        if(count($data['orders'])!==min($count,20)||$data['orders'][0]['paymentStatus']!=='À encaisser'||$data['orders'][0]['cdf']!=='110 250 FC')throw new RuntimeException('Présentation modifiée');
        $assert($data['hasNext']===($count>20),'Pagination 20 conservée');
    }
    echo "ADMIN_ORDER_READ_QUERIES_OK (PDO natif, 4 exécutions SQL pour 1/20 cartes et 21 résultats, snapshots conservés).\n";
}finally {
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_admin_query_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS '.$name);
}
