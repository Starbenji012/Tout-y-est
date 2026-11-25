<?php
declare(strict_types=1);
// Toutes les écritures, y compris workers et réservations, ciblent une base jetable.
$root=dirname(__DIR__,2);
ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$build=static fn(PDO $db):App\Services\AdminProductVariantService=>new App\Services\AdminProductVariantService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Services\MoneyService(new App\Models\Setting($db)));
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);session_write_close();
if(($argv[1]??'')==='worker') {
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_variant_engine_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base worker refusée');
    $db=$connect($name);$payload=json_decode(base64_decode($argv[3]),true,512,JSON_THROW_ON_ERROR);
    try {
        if($payload['action']==='reserve') {
            $db->beginTransaction();$model=new App\Models\ProductVariant($db);$model->lock($payload['id']);
            if(!$model->reserve($payload['id'],2))throw new RuntimeException('Réservation refusée');
            echo "LOCKED\n";flush();usleep(350000);$db->commit();$result=true;
        } else $result=$build($db)->saveVariants($payload['product'],$payload['variants']);
        echo json_encode(['ok'=>true,'result'=>$result]);
    } catch(DomainException $error) {echo json_encode(['ok'=>false,'code'=>$error->getCode(),'message'=>$error->getMessage()]);}
    exit;
}
$db=$connect('tout_y_est');$name='tout_y_est_variant_engine_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(callable $call,int $code=422)use($assert):void {try{$call();}catch(DomainException $e){$assert($e->getCode()===$code,'Code incorrect : '.$e->getMessage());return;}throw new RuntimeException('Refus attendu');};
$start=static function(array $payload)use($name):array {
    $process=proc_open([PHP_BINARY,__FILE__,'worker',$name,base64_encode(json_encode($payload,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Worker non démarré');fclose($pipes[0]);return [$process,$pipes];
};
$finish=static function(array $worker)use($assert):array {
    [$process,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $assert(proc_close($process)===0,'Worker : '.$err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);
};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Base éphémère');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut,email_verified_at) VALUES(1,'TEST','Admin','engine@example.invalid','unused','admin','actif',NOW()),(2,'TEST','Client','client@example.invalid','unused','client','actif',NOW()),(3,'TEST','Admin2','admin2@example.invalid','unused','admin','inactif',NOW())");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $product=static function()use($db):int {$slug='test-'.bin2hex(random_bytes(5));$q=$db->prepare("INSERT INTO produit(id_categorie,nom,slug,description,statut) VALUES(1,'TEST',?,'','actif')");$q->execute([$slug]);return (int)$db->lastInsertId();};
    $row=static function(int $id)use($db):array {$q=$db->prepare('SELECT * FROM variante_produit WHERE id_variante=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);};
    $service=$build($db);$p=$product();
    $v=$service->saveVariants($p,[['prix'=>'25','devise'=>'USD','stock'=>8,'sku'=>'test-simple']])[0];
    $simple=$row($v);$assert($simple['cle_combinaison']==='simple'&&$simple['sku']==='TEST-SIMPLE'&&$simple['prix_reference']==='25.00','Simple sans option fictive');
    $reject(fn()=>$service->saveVariants($p,[['prix'=>'1','devise'=>'USD','stock'=>1]]));
    $assert((int)$db->query('SELECT COUNT(*) FROM option_produit')->fetchColumn()===0,'Simple sans option');
    // Vrais liens panier / snapshots existants avant conversion ; aucun ID recréé.
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,2)');
    $db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,'.$v.',2)');
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,2,'TYE-TEST-ENGINE','confirmee',50,5,55,'USD',2450)");
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,description_variante_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,$p,$v,'TEST figé','TEST-SIMPLE','Ancien libellé',2,25,50)");
    $snapshots=$db->query('SELECT * FROM ligne_commande')->fetchAll();$cart=$db->query('SELECT * FROM ligne_panier')->fetchAll();
    $definitions=[['nom'=>'Couleur','valeurs'=>['Noir','Blanc']],['nom'=>'Taille','valeurs'=>['S','M','L']]];
    $reject(fn()=>$service->initialize($p,$definitions,[['selection'=>[1=>1,2=>1],'prix'=>'25','devise'=>'USD','stock'=>1]]));
    $assert((int)$db->query('SELECT COUNT(*) FROM option_produit')->fetchColumn()===0,'Conversion incomplète rollback');
    $result=$service->initialize($p,$definitions,[['id_variante'=>$v,'selection'=>[1=>1,2=>1]],['selection'=>[1=>1,2=>2],'prix'=>'25','devise'=>'USD','stock'=>5],['selection'=>[1=>2,2=>2],'prix'=>'30','devise'=>'USD','stock'=>0,'statut'=>'inactif']]);
    [$o1,$noir]=$result['options'][1][1];[, $blanc]=$result['options'][1][2];[$o2,$s]=$result['options'][2][1];[, $m]=$result['options'][2][2];[, $l]=$result['options'][2][3];
    [$same,$second,$third]=$result['variantes'];$converted=$row($v);
    $assert($same===$v&&$converted['sku']===$simple['sku']&&$converted['stock']===$simple['stock']&&$converted['prix_reference']===$simple['prix_reference'],'Conversion conserve ID, SKU, prix, stock');
    $assert($converted['cle_combinaison']==="$o1:$noir|$o2:$s"&&$converted['description_variante']==='Noir / S','Clé calculée / libellé générique');
    $assert((int)$db->query("SELECT COUNT(*) FROM variante_produit WHERE id_produit=$p")->fetchColumn()===3,'Trois combinaisons choisies, pas six');
    $assert($row($third)['stock']===0&&$row($third)['statut']==='inactif','Stock/statut indépendants');
    $payload=['valeurs'=>[$o2=>$l,$o1=>$blanc],'prix'=>'49000','devise'=>'CDF','stock'=>3];
    $fourth=$service->saveVariants($p,[$payload])[0];
    $assert($row($fourth)['cle_combinaison']==="$o1:$blanc|$o2:$l"&&$row($fourth)['prix_reference']==='20.00'&&$row($fourth)['devise_reference']==='USD','Tri numérique / CDF serveur');
    $reject(fn()=>$service->saveVariants($p,[$payload])); // Retry création : aucun doublon.
    foreach ([['valeurs'=>[$o1=>$blanc]],['valeurs'=>[$o1=>$s,$o2=>$m]],['valeurs'=>[$o1=>$noir,$o2=>$m,999=>1]],['cle_combinaison'=>'simple'],['taux'=>'1'],['prix_usd'=>'1']] as $bad) $reject(fn()=>$service->saveVariants($p,[array_replace($payload,$bad)]));
    $other=$product();$otherResult=$service->initialize($other,[['nom'=>'Stockage','valeurs'=>['128 Go','256 Go']]],[['selection'=>[1=>1],'prix'=>'300','devise'=>'USD','stock'=>1],['selection'=>[1=>2],'prix'=>'350','devise'=>'USD','stock'=>2]]);
    [$foreignOption,$foreignValue]=$otherResult['options'][1][1];
    $reject(fn()=>$service->saveVariants($p,[array_replace($payload,['valeurs'=>[$foreignOption=>$foreignValue]])]));
    $reject(fn()=>$service->saveVariants($p,[array_replace($payload,['valeurs'=>[$o1=>$foreignValue,$o2=>$l]])]));
    $reject(fn()=>$service->saveVariants($p,[['id_variante'=>$otherResult['variantes'][0]]]));
    $assert($row($otherResult['variantes'][0])['prix_reference']==='300.00'&&$row($otherResult['variantes'][1])['prix_reference']==='350.00','Prix individuels');
    $reject(fn()=>$service->saveVariants($p,[['id_variante'=>$v,'valeurs'=>[$o1=>$blanc,$o2=>$s]]]));
    $reject(fn()=>$service->saveVariants($p,[array_replace($payload,['valeurs'=>[$o1=>$noir,$o2=>$l],'sku'=>'TEST-SIMPLE'])]));
    $service->saveVariants($p,[['id_variante'=>$v],['id_variante'=>$second]],['prix'=>'73500','devise'=>'CDF']);
    $assert($row($v)['prix_reference']==='30.00'&&$row($second)['prix_reference']==='30.00'&&$row($fourth)['prix_reference']==='20.00','Prix commun transactionnel sur sélection, autres variantes intactes');
    $before=$db->query("SELECT * FROM variante_produit WHERE id_produit=$p ORDER BY id_variante")->fetchAll();
    $reject(fn()=>$service->saveVariants($p,[['id_variante'=>$v],['id_variante'=>$second,'stock'=>2,'stock_attendu'=>999]],['prix'=>'99','devise'=>'USD']),409);
    $assert($db->query("SELECT * FROM variante_produit WHERE id_produit=$p ORDER BY id_variante")->fetchAll()===$before,'Rollback intégral prix commun + stock périmé');
    $json=$db->query("SELECT valeur FROM parametre WHERE cle='monetary_config'")->fetchColumn();
    $db->exec("UPDATE parametre SET valeur='{}' WHERE cle='monetary_config'");
    $reject(fn()=>$build($db)->saveVariants($p,[['id_variante'=>$v,'prix'=>'2450','devise'=>'CDF']]));
    $q=$db->prepare("UPDATE parametre SET valeur=? WHERE cle='monetary_config'");$q->execute([$json]);
    $key=$row($v)['cle_combinaison'];$service->renameOption($p,$o1,'Teinte');$service->renameValue($p,$o1,$noir,'Noir profond');
    $assert($row($v)['cle_combinaison']===$key&&$row($v)['description_variante']==='Noir profond / S','Renommage sans identité modifiée');
    $service->saveVariants($p,[['id_variante'=>$v,'statut'=>'inactif']]);
    $assert($row($v)['stock']===$simple['stock']&&$row($second)['statut']==='actif','Désactivation indépendante sans suppression');
    $reject(fn()=>$service->removeValue($p,$o1,$noir));$reject(fn()=>$service->removeOption($p,$o1));
    $unused=$service->addValue($p,$o1,"  Gris\u{00A0}  clair  ");
    $assert($db->query('SELECT libelle FROM valeur_option_produit WHERE id_valeur='.$unused)->fetchColumn()==='Gris clair','Espaces Unicode normalisés');
    $service->removeValue($p,$o1,$unused);
    $reject(fn()=>$service->addValue($p,$o1,' BLANC '));
    $service->saveVariants($p,[['id_variante'=>$v,'stock'=>0,'stock_attendu'=>8,'statut'=>'actif']]);
    $assert($row($v)['statut']==='actif'&&$row($v)['stock']===0,'Rupture distincte inactive/inexistante');
    $assert((int)$db->query("SELECT COUNT(*) FROM variante_produit WHERE id_produit=$p AND cle_combinaison='$o1:$blanc|$o2:$s'")->fetchColumn()===0,'Combinaison non vendue absente');
    $assert($db->query('SELECT * FROM ligne_commande')->fetchAll()===$snapshots&&$db->query('SELECT * FROM ligne_panier')->fetchAll()===$cart,'Historiques et panier préservés');
    foreach ([[],['id'=>2],['id'=>3]] as $user) {App\Core\Session::set('user',$user);$reject(fn()=>$service->saveVariants($p,[['id_variante'=>$v]]),$user===[]?401:403);}
    App\Core\Session::set('user',['id'=>1]);
    // Migration historique : plusieurs NULL ne sont pas arbitrairement renommés "simple".
    $legacy=$product();$db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES($legacy,'LEGACY-A',10,'USD',4,'actif'),($legacy,'LEGACY-B',11,'USD',5,'inactif')");
    $legacyIds=array_map('intval',$db->query("SELECT id_variante FROM variante_produit WHERE id_produit=$legacy ORDER BY id_variante")->fetchAll(PDO::FETCH_COLUMN));
    $reject(fn()=>$service->saveVariants($legacy,[['id_variante'=>$legacyIds[0]]]));
    $legacyResult=$service->initialize($legacy,[['nom'=>'Contenance','valeurs'=>['Petit','Grand']]],[['id_variante'=>$legacyIds[0],'selection'=>[1=>1]],['id_variante'=>$legacyIds[1],'selection'=>[1=>2]]]);
    $assert($legacyResult['variantes']===$legacyIds&&$row($legacyIds[1])['stock']===5,'Classement explicite anciennes variantes sans perte');
    $three=$product();
    $reject(fn()=>$service->initialize($three,array_fill(0,4,['nom'=>'Option','valeurs'=>['A']]),[]));
    $reject(fn()=>$service->initialize($three,[['nom'=>' Couleur ','valeurs'=>['A']],['nom'=>'couleur','valeurs'=>['B']]],[['selection'=>[1=>1,2=>1],'prix'=>'1','devise'=>'USD','stock'=>1]]));
    $reject(fn()=>$service->initialize($three,[['nom'=>'Option','valeurs'=>[' A ','a']]],[['selection'=>[1=>1],'prix'=>'1','devise'=>'USD','stock'=>1]]));
    $assert((int)$db->query("SELECT COUNT(*) FROM option_produit WHERE id_produit=$three")->fetchColumn()===0,'Définitions invalides sans résidu');
    $triple=$service->initialize($three,[['nom'=>'Matière','valeurs'=>['Coton']],['nom'=>'Volume','valeurs'=>['Petit']],['nom'=>'Finition','valeurs'=>['Mate']]],[['selection'=>[3=>1,1=>1,2=>1],'prix'=>'1','devise'=>'USD','stock'=>1]]);
    $assert($row($triple['variantes'][0])['description_variante']==='Coton / Petit / Mate','Trois options personnalisées, ordre métier stable');
    // Plafond concurrent : 99 existantes, deux demandes distinctes pour la dernière place.
    $limit=$product();$labels=[];$lines=[];
    for($i=1;$i<=101;$i++)$labels[]='Valeur '.$i;
    for($i=1;$i<=99;$i++)$lines[]=['selection'=>[1=>$i],'prix'=>'1','devise'=>'USD','stock'=>1,'statut'=>$i===1?'inactif':'actif'];
    $limited=$service->initialize($limit,[['nom'=>'Série','valeurs'=>$labels]],$lines);
    [$limitOption,$value100]=$limited['options'][1][100];[, $value101]=$limited['options'][1][101];
    $w1=$start(['action'=>'save','product'=>$limit,'variants'=>[['valeurs'=>[$limitOption=>$value100],'prix'=>'1','devise'=>'USD','stock'=>1]]]);
    $w2=$start(['action'=>'save','product'=>$limit,'variants'=>[['valeurs'=>[$limitOption=>$value101],'prix'=>'1','devise'=>'USD','stock'=>1]]]);
    $r1=$finish($w1);$r2=$finish($w2);
    $assert((int)$r1['ok']+(int)$r2['ok']===1&&(int)$db->query("SELECT COUNT(*) FROM variante_produit WHERE id_produit=$limit")->fetchColumn()===100,'Concurrence plafond 100 inclut inactives');
    $loser=$r1['ok']?$r2:$r1;$assert($loser['code']===422&&str_contains($loser['message'],'100 variantes'),'Second créateur refusé précisément par le plafond');
    // Deux Admins avec le même stock lu : une seule modification acceptée.
    $service->saveVariants($p,[['id_variante'=>$v,'stock'=>20,'stock_attendu'=>0]]);
    $w1=$start(['action'=>'save','product'=>$p,'variants'=>[['id_variante'=>$v,'stock'=>19,'stock_attendu'=>20]]]);
    $w2=$start(['action'=>'save','product'=>$p,'variants'=>[['id_variante'=>$v,'stock'=>18,'stock_attendu'=>20]]]);
    $r1=$finish($w1);$r2=$finish($w2);$assert((int)$r1['ok']+(int)$r2['ok']===1,'Stock concurrent un seul gagnant');
    $loser=$r1['ok']?$r2:$r1;$assert($loser['code']===409,'Second remplacement de stock refusé comme conflit');
    $stock=$row($v)['stock'];$service->saveVariants($p,[['id_variante'=>$v,'stock'=>20,'stock_attendu'=>$stock]]);
    // Réservation réelle ProductVariant : garder le verrou assez longtemps pour faire attendre l'Admin.
    $reserve=$start(['action'=>'reserve','id'=>$v]);$assert(trim((string)fgets($reserve[1][1]))==='LOCKED','Réservation verrouillée');
    $admin=$start(['action'=>'save','product'=>$p,'variants'=>[['id_variante'=>$v,'stock'=>25,'stock_attendu'=>20]]]);
    $assert($finish($reserve)['ok']===true,'Réservation validée');$adminResult=$finish($admin);
    $assert(!$adminResult['ok']&&$adminResult['code']===409&&$row($v)['stock']===18,'Admin ne remplace pas le stock réservé');
    $db->beginTransaction();$model=new App\Models\ProductVariant($db);$model->lock($v);$model->restore($v,2);$db->commit();
    $assert($row($v)['stock']===20,'Restitution existante compatible');
    // Échec SQL après une première écriture : tout le lot doit être annulé.
    $db->exec("CREATE TRIGGER test_engine_failure BEFORE UPDATE ON variante_produit FOR EACH ROW BEGIN IF NEW.id_variante=$second THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'; END IF; END");
    $before=$row($v);try{$service->saveVariants($p,[['id_variante'=>$v],['id_variante'=>$second]],['prix'=>'99','devise'=>'USD']);throw new RuntimeException('Trigger attendu');}catch(PDOException){}
    $assert($row($v)===$before&&!$db->inTransaction(),'Rollback après écriture effective');$db->exec('DROP TRIGGER test_engine_failure');
    // Retry d'une mise à jour identifiée : stable, pas de nouvelle variante ni nouveau lien.
    $updates=[['id_variante'=>$v,'statut'=>'actif']];$service->saveVariants($p,$updates);$before=$row($v);$service->saveVariants($p,$updates);
    $assert($row($v)===$before,'Retry update idempotent');
    $assert($db->query('SELECT * FROM ligne_commande')->fetchAll()===$snapshots&&$db->query('SELECT * FROM ligne_panier')->fetchAll()===$cart,'Snapshots et références finaux intacts');
    // Avant Phase 5, seul le simple est achetable ; une conversion préserve la commande déjà créée.
    $saleProduct=$product();$saleVariant=$service->saveVariants($saleProduct,[['prix'=>'30','devise'=>'USD','stock'=>20]])[0];
    $db->exec('UPDATE ligne_panier SET id_variante='.$saleVariant.' WHERE id_panier=1');$saleCart=$db->query('SELECT * FROM ligne_panier')->fetchAll();
    $db->exec("INSERT INTO zone_livraison(id_zone_livraison,nom,frais_reference,devise_reference,statut) VALUES(1,'TEST zone',5,'USD','actif')");
    $users=new App\Models\User($db);$cartModel=new App\Models\Cart($db);$zones=new App\Models\DeliveryZone($db);$addresses=new App\Models\UserAddress($db);$money=new App\Services\MoneyService(new App\Models\Setting($db));
    $checkout=new App\Services\CheckoutService(new App\Services\CartService(new App\Services\ProductService(new App\Models\Product($db)),$cartModel,$money),new App\Services\AuthService($users),$users,$addresses,$zones,$money);
    $orders=new App\Services\OrderService($db,$users,$cartModel,new App\Models\ProductVariant($db),$zones,$addresses,new App\Models\Order($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),$checkout,$money);
    $draft=['address'=>['nom_destinataire'=>'TEST','prenom_destinataire'=>'Client','telephone'=>'+243812345678','commune'=>'TEST','adresse'=>'TEST adresse','repere'=>'','instructions'=>'','id_zone_livraison'=>1]];
    $creationKey=bin2hex(random_bytes(32));$order=$orders->create(2,$draft,$creationKey);$retry=$orders->create(2,$draft,$creationKey);
    $assert($retry['id']===$order['id']&&$retry['reused']&&$row($saleVariant)['stock']===18,'S1.7 réserve une seule fois le simple');
    $reject(fn()=>$service->saveVariants($saleProduct,[['id_variante'=>$saleVariant,'stock'=>4294967295,'stock_attendu'=>18]]));
    $assert($row($saleVariant)['stock']===18,'Remplacement extrême refusé pour préserver les restitutions');
    $q=$db->prepare('SELECT * FROM ligne_commande WHERE id_commande=?');$q->execute([$order['id']]);$historical=$q->fetchAll();
    $assert($historical[0]['description_variante_snapshot']===null&&$historical[0]['prix_unitaire_applique']==='30.00','Snapshots du simple avant conversion');
    $convertedSale=$service->initialize($saleProduct,[['nom'=>'Teinte','valeurs'=>['Noir']]],[['id_variante'=>$saleVariant,'selection'=>[1=>1]]]);
    [$saleOption,$saleValue]=$convertedSale['options'][1][1];
    $service->renameValue($saleProduct,$saleOption,$saleValue,'Noir futur');$service->saveVariants($saleProduct,[['id_variante'=>$saleVariant,'prix'=>'99','devise'=>'USD']]);
    $q->execute([$order['id']]);$assert($q->fetchAll()===$historical,'Renommage/prix ne recalculent pas les anciennes commandes');
    $db->exec('UPDATE commande SET reservation_expire_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id_commande='.(int)$order['id']);
    $assert($orders->expireReservations()===1&&$row($saleVariant)['stock']===20,'Expiration S1.7 restitue deux unités même après conversion');
    $assert($orders->expireReservations()===0&&$row($saleVariant)['stock']===20,'Deuxième expiration neutre');
    $assert($db->query('SELECT * FROM ligne_panier')->fetchAll()===$saleCart,'Panier toujours basé sur le même ID');
    echo "PRODUCT_VARIANT_ENGINE_OK PHP=".PHP_VERSION." MariaDB=".$db->query('SELECT VERSION()')->fetchColumn()." (simple, options, combinaisons, héritage, prix, droits, rollback, plafond concurrent, stock/réservation concurrente, retry, snapshots).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
