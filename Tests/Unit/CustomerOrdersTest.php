<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';
    if(is_file($file))require_once $file;
});
if (($argv[1] ?? '') === 'guest') {
    App\Core\Session::start();App\Core\Session::set('user',null);
    register_shutdown_function(static fn () => print('STATUS='.http_response_code()));
    (new App\Controllers\CustomerOrderController(null,new App\Core\Request()))->index();exit;
}
if (in_array($argv[1] ?? '', ['page-list','page-detail'], true)) {
    $target=$argv[2] ?? '';
    if(!preg_match('/^tout_y_est_customer_orders_test_[a-f0-9]{16}$/D',$target))throw new RuntimeException('Base de rendu refusée');
    putenv('DB_DATABASE='.$target);
    App\Core\Session::start();App\Core\Session::set('user',['id'=>1,'name'=>'TEST Alice','email'=>'alice@example.invalid','role'=>'client']);
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']=$argv[1]==='page-list'?'/compte/commandes':'/compte/commande';$_GET=['commande'=>(int)($argv[3]??0)];
    http_response_code(200);require $root.'/Public/index.php';echo 'HTTP_STATUS='.http_response_code();exit;
}
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_customer_orders_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $fn)use($assert):void{try{$fn();}catch(DomainException){return;}$assert(false,'Accès refusé attendu');};
try {
    // Toutes les écritures sont confinées à cette base aléatoire, supprimée en fin de test.
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe) VALUES(1,'TEST','Alice','alice@example.invalid','unused'),(2,'TEST','Bob','bob@example.invalid','unused'),(3,'TEST','Empty','empty@example.invalid','unused')");
    $db->exec("INSERT INTO categorie VALUES(1,'TEST','test',NULL,'actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'Catalogue actuel','test','TEST','actif')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,stock,statut) VALUES(1,1,'CURRENT-SKU',20,20,'actif')");
    $model=new App\Models\Order($db);$items=new App\Models\OrderItem($db);$deliveries=new App\Models\Delivery($db);
    $money=new App\Services\MoneyService(new App\Models\Setting($db));
    $service=new App\Services\CustomerOrderService($model,$items,$deliveries,$money,new App\Services\ProductService(new App\Models\Product($db),null,null,$money));
    $make=static function(int $user,string $number,string $date,string $status)use($db,$model,$items,$deliveries):int {
        $id=$model->create(['user_id'=>$user,'number'=>$number,'subtotal'=>'40.00','shipping'=>'5.00','total'=>'45.00','rate'=>'2450','key_hash'=>hash('sha256',random_bytes(32))]);
        $q=$db->prepare('UPDATE commande SET date_commande=?,statut=? WHERE id_commande=?');$q->execute([$date,$status,$id]);
        $items->create(['order_id'=>$id,'product_id'=>1,'variant_id'=>1,'name'=>'Nom historique <test>','sku'=>'HISTORIC-SKU','description'=>'Variante historique','quantity'=>2,'price'=>'20.00','discount'=>'0.00','total'=>'40.00']);
        $deliveries->create($id,['nom_destinataire'=>'TEST','prenom_destinataire'=>'Destinataire historique','telephone'=>'+243812345678','commune'=>'Commune historique','adresse'=>'Adresse historique','instructions'=>'<script>TEST</script>'],['id_zone_livraison'=>null,'nom'=>'Zone historique','frais_reference'=>'5.00']);
        return $id;
    };
    $old=$make(1,'TYE-20261019-A713584AB8AC8BE0','2026-01-01 10:00:00','expiree');
    $cash=$make(1,'TYE-TEST-CASH','2026-02-01 10:00:00','confirmee');
    $foreign=$make(2,'TYE-BOB-PRIVATE','2026-03-01 10:00:00','confirmee');
    $cancelled=$make(1,'TYE-TEST-CANCELLED','2026-02-01 10:00:00','annulee');
    $mode=(new App\Models\PaymentMethod($db))->cash();
    $payment=new App\Models\Payment($db);$payment->createCash($model->find($cash),(int)$mode['id_mode_paiement']);
    $rows=$service->listing(1);
    $assert(array_column($rows['orders'],'id')===[$cancelled,$cash,$old],'Propriétaire, date décroissante et départage par ID');
    $assert($rows['orders'][1]['paymentStatus']==='À régler à la livraison','Cash non encaissé');
    $assert($rows['orders'][1]['paymentMode']==='Espèces à la livraison','Mode cash');
    $reject(fn()=>$service->listing(0));$reject(fn()=>$service->detail(1,$foreign));$reject(fn()=>$service->detail(0,$cash));$reject(fn()=>$service->detail(1,999999));
    $detail=$service->detail(1,$cash);
    $assert($detail['lines'][0]['image']===null,'Absence image sans invention');
    $db->exec("INSERT INTO image_produit(id_produit,chemin,image_principale) VALUES(1,'/assets/images/products/watch.jpg',1)");
    $detail=$service->detail(1,$cash);
    $assert($detail['lines'][0]['image']==='/assets/images/products/watch.jpg','Illustration actuelle associée au bon produit');
    $assert($service->detail(1,$old)['order']['number']==='TYE-20261019-A713584AB8AC8BE0','Ancienne référence intacte');
    $assert($detail['order']['total']==='$45.00' && $detail['order']['cdf']==='110 250 FC' && $detail['order']['rate']==='2450','Montants et taux historiques');
    $assert($detail['lines'][0]['unitPrice']==='$20.00' && $detail['lines'][0]['quantity']===2 && $detail['lines'][0]['name']==='Nom historique <test>','Ligne snapshot');
    $db->exec("UPDATE produit SET nom='Nouveau catalogue';UPDATE variante_produit SET prix_reference=999;UPDATE utilisateur SET nom='Profil modifié' WHERE id_utilisateur=1;UPDATE parametre SET valeur='{}' WHERE cle='monetary_config'");
    $db->exec("INSERT INTO adresse_utilisateur(id_utilisateur,nom_destinataire,prenom_destinataire,telephone,commune,adresse) VALUES(1,'Actuel','Actuel','+243899999999','Autre commune','Autre adresse')");
    $assert($service->detail(1,$cash)===$detail,'Catalogue, profil, adresse et configuration actuelle ne modifient pas les snapshots');
    $before=$db->query('SELECT statut,stock_restitue_at FROM commande ORDER BY id_commande')->fetchAll();
    $assert($service->detail(1,$old)['order']['status']==='Expirée' && $service->detail(1,$cancelled)['order']['status']==='Annulée','Commandes expirées et annulées consultables');
    $assert($before===$db->query('SELECT statut,stock_restitue_at FROM commande ORDER BY id_commande')->fetchAll(),'Consultation sans mutation ni expiration');
    foreach(['en_attente_paiement'=>'En attente de paiement','confirmee'=>'Commande confirmée','payee'=>'Paiement confirmé','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrée','annulee'=>'Annulée','expiree'=>'Expirée'] as $raw=>$label)$assert(App\Services\CustomerOrderService::orderStatus($raw)===$label,'Libellé statut '.$raw);
    $assert(!str_contains(App\Services\CustomerOrderService::orderStatus('statut_interne_inconnu'),'statut_interne_inconnu'),'Pas de statut brut');
    $q=$db->prepare("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut) VALUES(?,?,45,'paye'),(?,?,45,'echoue')");$q->execute([$cash,$mode['id_mode_paiement'],$cash,$mode['id_mode_paiement']]);
    $assert($service->detail(1,$cash)['order']['paymentStatus']==='Payé','Paiement encaissé prime sur une tentative échouée');
    $empty=$service->listing(3);extract($empty);
    ob_start();require $root.'/App/views/account/orders.php';$html=ob_get_clean();
    $assert(str_contains($html,'Aucune commande à afficher') && !str_contains($html,'TYE-BOB'),'État vide');
    extract($detail);ob_start();require $root.'/App/views/account/order-detail.php';$html=ob_get_clean();
    $assert(str_contains($html,'Nom historique &lt;test&gt;') && str_contains($html,'&lt;script&gt;TEST&lt;/script&gt;') && !str_contains($html,'<script>'),'Échappement des snapshots');
    $assert(!str_contains($html,'a_encaisser') && !str_contains($html,'confirmee') && !str_contains($html,'HISTORIC-SKU'),'Pas de statut technique ni SKU interne exposé');
    $assert(!str_contains($html,'Illustration actuelle') && str_contains($html,'/assets/images/products/watch.jpg') && str_contains($html,'Destinataire historique') && str_contains($html,'Téléphone') && !str_contains($html,'<dt>Repère</dt>'),'Livraison structurée, image seule, champs absents omis');
    $lines[]=$lines[0];$lines[1]['image']=null;
    ob_start();require $root.'/App/views/account/order-detail.php';$multiple=ob_get_clean();
    $assert(substr_count($multiple,'Total de ligne')===2 && str_contains($multiple,'Sans image'),'Plusieurs lignes et fallback propre');
    $delivery=null;ob_start();require $root.'/App/views/components/order-delivery.php';$noDelivery=ob_get_clean();
    $assert(str_contains($noDelivery,'ne sont pas disponibles'),'Snapshot livraison absent : message sans erreur');
    $db->exec("UPDATE image_produit SET chemin='/assets/images/products/image-absente-test.jpg'");
    $assert($service->detail(1,$cash)['lines'][0]['image']===null,'Image disparue : fallback sans casser historique');
    App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);$_SERVER['REQUEST_METHOD']='GET';$_GET=['commande'=>$foreign,'id_utilisateur'=>2];
    $controller=new App\Controllers\CustomerOrderController($service,new App\Core\Request());
    ob_start();$controller->detail();$denied=ob_get_clean();$assert(http_response_code()===404 && !str_contains($denied,'TYE-BOB'),'Controller session seule, refus 404 sans fuite');
    $_GET['commande']=999999;ob_start();$controller->detail();$missing=ob_get_clean();$assert($missing===$denied,'Même réponse pour absent et étranger');
    foreach(['page-list','page-detail'] as $action){
        $p=proc_open([PHP_BINARY,__FILE__,$action,$name,(string)$cash],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$pageHtml=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $assert(proc_close($p)===0 && $err==='' && str_contains($pageHtml,'HTTP_STATUS=200') && str_contains($pageHtml,'TYE-TEST-CASH') && !str_contains($pageHtml,'TYE-BOB-PRIVATE'),'Route complète et layout : '.$action.' '.$err);
        if($action==='page-list')$assert(str_contains($pageHtml,'/assets/css/select-control.css') && strpos($pageHtml,'/assets/js/select-control.js')!==false && strpos($pageHtml,'/assets/js/select-control.js')<strpos($pageHtml,'/assets/js/customer-orders-filter.js'),'Composant et style chargés avant le filtre');
    }
    for($i=0;$i<21;$i++)$make(3,'TYE-PAGE-'.$i,'2026-04-01 10:00:00','en_attente_paiement');
    $first=$service->listing(3);$second=$service->listing(3,2);
    $assert(count($first['orders'])===20 && $first['hasNext'] && count($second['orders'])===1 && !$second['hasNext'],'Lecture bornée et pagination');
    // Périodes calculées comme l'application : inclusives au début, exclusives au mois suivant.
    $thisMonth=new DateTimeImmutable('first day of this month 00:00:00');
    $lastMonth=$thisMonth->modify('-1 month');$nextMonth=$thisMonth->modify('+1 month');
    $currentId=$make(1,'TYE-CURRENT',$thisMonth->format('Y-m-d H:i:s'),'confirmee');
    $previousId=$make(1,'TYE-PREVIOUS',$lastMonth->format('Y-m-d H:i:s'),'confirmee');
    $nextId=$make(1,'TYE-NEXT',$nextMonth->format('Y-m-d H:i:s'),'confirmee');
    $otherId=$make(2,'TYE-PRIVATE-CURRENT',$thisMonth->format('Y-m-d H:i:s'),'confirmee');
    $current=$service->listing(1,1,['period'=>'current']);$previous=$service->listing(1,1,['period'=>'previous']);
    $assert(in_array($currentId,array_column($current['orders'],'id'),true) && !in_array($previousId,array_column($current['orders'],'id'),true) && !in_array($nextId,array_column($current['orders'],'id'),true) && !in_array($otherId,array_column($current['orders'],'id'),true),'Ce mois-ci, bornes et isolation');
    $assert(in_array($previousId,array_column($previous['orders'],'id'),true) && !in_array($currentId,array_column($previous['orders'],'id'),true),'Mois précédent, frontière de mois');
    $all=$service->listing(1,1,['period'=>'all']);
    $assert(in_array($nextId,array_column($all['orders'],'id'),true) && in_array($currentId,array_column($all['orders'],'id'),true) && !in_array($otherId,array_column($all['orders'],'id'),true),'Toutes les commandes restent personnelles');
    $chosen=$service->listing(3,1,['period'=>'month','month'=>'2026-04']);
    $chosenNext=$service->listing(3,2,['period'=>'month','month'=>'2026-04']);
    $assert(count($chosen['orders'])===20 && $chosen['hasNext'] && count($chosenNext['orders'])===1 && !$chosenNext['hasNext'],'Mois choisi et pagination de 20');
    $assert(array_keys($chosen['months'])===['2026-04'] && $chosen['months']['2026-04']==='Avril 2026','Uniquement les mois disponibles du compte');
    extract($chosen);ob_start();require $root.'/App/views/account/orders.php';$filteredHtml=ob_get_clean();
    $assert(str_contains($filteredHtml,'period=month&amp;month=2026-04&amp;page=2'),'Filtre conservé dans la pagination');
    $assert(str_contains($filteredHtml,'data-orders-month>') && !str_contains($filteredHtml,"'label'=>'Afficher'"),'Mois personnalisé visible pour la période choisie');
    $assert(str_contains($filteredHtml,'<noscript>') && str_contains($filteredHtml,'Appliquer la période'),'Fallback serveur sans JavaScript');
    $assert(!preg_match('/>\s*Afficher\s*<\/button>/', $filteredHtml),'Bouton Afficher absent');
    $assert(!str_contains($filteredHtml,'lorsque JavaScript est activé') && !str_contains($filteredHtml,'orders-period-help'),'Texte technique supprimé');
    $assert(str_contains($filteredHtml,'<select id="orders-period"') && str_contains($filteredHtml,'data-select-label'),'Vrais selects et libellés conservés pour le fallback');
    $none=$service->listing(3,1,['period'=>'month','month'=>'2001-01']);
    $assert($none['orders']===[] && $none['periodError']!==null,'Mois absent ou étranger refusé sans élargir les résultats');
    $none=$service->listing(3,1,['period'=>'previous']);
    if($lastMonth->format('Y-m')==='2026-04')$none=$service->listing(3,1,['period'=>'current']);
    extract($none);ob_start();require $root.'/App/views/account/orders.php';$noneHtml=ob_get_clean();
    $assert(str_contains($noneHtml,'Aucune commande pour cette période.'),'État vide contextualisé');
    $assert(str_contains($noneHtml,'data-orders-month hidden') && preg_match('/id="orders-month"[^>]*disabled/', $noneHtml),'Mois caché et désactivé quand inutile');
    $assert((bool)preg_match('/class="btn btn-primary[^>]+href="\/compte\/commandes"[^>]*>\s*Toutes les commandes/s', $noneHtml),'Bouton doré de retour sans filtre ni page conservé');
    $bad=$service->listing(1,1,['period'=>['month'],'month'=>['bad'],'id_utilisateur'=>2]);
    $assert($bad['period']==='all' && !in_array($otherId,array_column($bad['orders'],'id'),true),'Paramètres mal formés et identité client ignorés');
    $_GET=['period'=>'month','month'=>'2026-02','id_utilisateur'=>2];http_response_code(200);
    ob_start();$controller->index();$filteredRoute=ob_get_clean();
    $assert(http_response_code()===200 && str_contains($filteredRoute,'TYE-TEST-CASH') && !str_contains($filteredRoute,'TYE-BOB-PRIVATE'),'Controller transmet la période, jamais une identité cliente');
    $p=proc_open([PHP_BINARY,__FILE__,'guest'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$guest=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0 && str_contains($guest,'STATUS=303'),'Authentification obligatoire : '.$err);
    echo "CUSTOMER_ORDERS_OK (isolation, ordre, snapshots, statuts, cash, paiement, état vide, pagination, sécurité).\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_customer_orders_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
