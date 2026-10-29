<?php
declare(strict_types=1);
// Base jetable : aucune commande du navigateur n'est utilisée comme fixture.
$root=dirname(__DIR__,2);require_once $root.'/vendor/autoload.php';
spl_autoload_register(static function($class)use($root){if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});

/** Page de test nommée pour rendre show() identifiable par l'analyse statique. */
final class AdminOrderReadTestPage extends App\Core\Controller
{
    public function show(): void
    {
        $this->render('admin/access-error',['accessStatus'=>403]);
    }
}

$c=require $root.'/Config/database.php';if(!in_array($c['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local uniquement');
$db=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['database'].';charset=utf8mb4',$c['username'],$c['password'],$c['options']);
$name='tout_y_est_admin_read_test_'.bin2hex(random_bytes(8));
$assert=static function($ok,$why){if(!$ok)throw new RuntimeException($why);};
App\Core\Session::start();
try {
 $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
 $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES (1,'<script>client</script>','Actuel','client@example.invalid','unused','client','actif'),(2,'Admin','Test','admin@example.invalid','unused','admin','actif'),(3,'Inactif','Test','inactive@example.invalid','unused','admin','inactif')");
 $insert=$db->prepare("INSERT INTO commande(id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf,date_commande,reservation_expire_at) VALUES(1,?,?,40,5,45,'USD',2450,?, '2000-01-01')");
 $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif'); INSERT INTO produit(id_produit,id_categorie,nom,slug,statut) VALUES(1,1,'Nom actuel','test','actif'); INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,stock,statut) VALUES(1,1,'ACTUEL',999,10,'actif')");
 $states=['confirmee','en_preparation','en_livraison','livree','annulee','expiree','en_attente_paiement'];
 for($i=1;$i<=28;$i++){$insert->execute(['TYE-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),$states[($i-1)%7],'2026-01-01 12:00:00']);}
 $db->exec("INSERT INTO livraison(id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,nom_zone_snapshot,frais_snapshot) VALUES(1,'Historique','Destinataire','TEST','Commune','<script>adresse</script>','Zone historique',5)");
 $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,description_variante_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,1,1,'Nom historique','SKU-HISTORIQUE','Variante historique',2,20,40)");
 $db->exec("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(1,1,45,'a_encaisser',2450),(5,1,45,'a_encaisser',2450)");
 $db->exec("INSERT INTO journal_commande(id_commande,id_administrateur,id_paiement,action,ancien_etat,nouvel_etat,donnees_complementaires) VALUES(1,2,1,'cancel','{}','{}',JSON_OBJECT('motif','<script>motif</script>'))");
 $service=new App\Services\AdminOrderReadService(new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminOrderRead($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),new App\Models\Payment($db),new App\Models\AdminOrderJournal($db),new App\Services\MoneyService(new App\Models\Setting($db)));
 foreach([0=>401,1=>403,3=>403,999=>403] as $user=>$code){App\Core\Session::set('user',['id'=>$user,'role'=>'admin']);foreach([fn()=>$service->listing([]),fn()=>$service->detail('TYE-001')] as $read){try{$read();throw new RuntimeException('Accès accepté');}catch(DomainException $e){$assert($e->getCode()===$code,'Droits relus');}}}
 App\Core\Session::set('user',['id'=>2]);
 $fingerprint=static function()use($db){$out=[];foreach(['commande','paiement','livraison','journal_commande']as$t)$out[$t]=$db->query('SELECT * FROM '.$t)->fetchAll();return serialize($out);};$before=$fingerprint();
 $list=$service->listing([]);$assert(count($list['orders'])===20&&$list['hasNext'],'20 par page');$assert($list['orders'][0]['number']==='TYE-028','Ordre déterministe');$assert(count($service->listing(['page'=>2])['orders'])===8,'Page 2');
 foreach($states as$state){if($state==='expiree'||$state==='en_attente_paiement')continue;$filtered=$service->listing(['etat'=>$state]);foreach($filtered['orders']as$o)$assert($o['row']['statut']===$state,'Filtre '.$state);$assert($filtered['filters']['etat']===$state,'Conservation filtre');}
 $assert(count($service->listing(['encaisser'=>'1'])['orders'])===2,'À encaisser indépendant, même commande annulée');
 $assert(count($service->listing(['etat'=>'confirmee','encaisser'=>'1'])['orders'])===1,'Intersection filtres');
 $assert($service->listing(['etat'=>['invalide'],'page'=>-1])['state']==='','Filtre invalide');
 $data=$service->detail('TYE-001');$assert($data['order']['cdf']==='110 250 FC','Taux historique');$assert($data['order']['delivery']['nom_destinataire_snapshot']==='Historique','Snapshot distinct');$assert($data['order']['paymentStatus']==='À encaisser','Cash');
 try{$service->detail('inconnue');throw new RuntimeException('404 absent');}catch(DomainException$e){$assert($e->getCode()===404,'404');}
 extract($data);ob_start();require $root.'/App/views/admin/order-detail.php';$html=ob_get_clean();
 $assert(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;motif'),'XSS');$assert(str_contains($html,'Compte client actuel')&&str_contains($html,'Destinataire et livraison historiques'),'Deux identités');$assert(!str_contains($html,'ancien_etat')&&!str_contains($html,'type="submit"'),'Lecture sans JSON ni mutation');
 $assert(str_contains($html,'Nom historique')&&str_contains($html,'Variante historique')&&!str_contains($html,'999'),'Prix et libellés snapshot');
 extract($service->listing(['page'=>999]));ob_start();require $root.'/App/views/admin/orders.php';$empty=ob_get_clean();$assert(str_contains($empty,'Aucune commande pour cette sélection'),'État vide');
 $_SERVER['REQUEST_METHOD']='GET';$_GET=['reference'=>'TYE-001'];$controller=new App\Controllers\Admin\OrderReadController($service,new App\Core\Request());ob_start();$controller->detail();$rendered=ob_get_clean();$assert(str_contains($rendered,'Nom historique')&&str_contains($rendered,'/admin/zones-livraison'),'Contrôleur et layout');
 $_GET=['reference'=>'absente'];ob_start();$controller->detail();$error=ob_get_clean();$assert(http_response_code()===404&&str_contains($error,'introuvable'),'404 contrôleur');
 $assert(str_contains($rendered,'1 USD = 2 450 FC')&&!str_contains($rendered,'2450.0000'),'Taux lisible');
 $assert(str_contains($rendered,'Par :')&&str_contains($rendered,'Admin'),'Auteur journal');
 $assert($before===$fingerprint(),'Aucune expiration ni écriture');
 App\Core\Controller::configureAdminAccess(new App\Services\AdminAccessService(new App\Models\User($db)));
 $page=new AdminOrderReadTestPage();
 ob_start();$page->show();$adminHtml=ob_get_clean();$assert(str_contains($adminHtml,'href="/admin/commandes"'),'Pont Admin actif');
 $db->exec("UPDATE utilisateur SET role='client' WHERE id_utilisateur=2");try{$service->listing([]);throw new RuntimeException('Admin rétrogradé accepté');}catch(DomainException$e){$assert($e->getCode()===403,'Rétrogradation');}
 ob_start();$page->show();$clientHtml=ob_get_clean();$assert(!str_contains($clientHtml,'href="/admin/commandes"'),'Pont retiré après rétrogradation');
 echo "ADMIN_ORDER_READ_OK (droits, 20/page, ordre, filtres, snapshots, journal, XSS, lecture sans effet).\n";
}finally{if(!preg_match('/^tout_y_est_admin_read_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');$db->exec('USE `'.$c['database'].'`');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
