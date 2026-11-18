<?php
declare(strict_types=1);
// Aucune fixture dans tout_y_est : toutes les écritures/DDL et les courses utilisent des bases gardées.
$root=dirname(__DIR__,2);require_once $root.'/vendor/autoload.php';
spl_autoload_register(static function(string $class)use($root):void{if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local uniquement.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
App\Core\Session::start();
if(($argv[1]??'')==='worker'){
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_tours_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base refusée.');
    $db=$connect($name);App\Core\Session::set('user',['id'=>(int)$argv[3]]);$tours=new App\Services\CourierTourService($db);
    try{
        if($argv[4]==='ready')$tours->ready((int)$argv[5],new App\Services\AdminAccessService(new App\Models\User($db)));
        elseif($argv[4]==='start')$tours->start((int)$argv[5],(int)$argv[6]);
        echo json_encode(['ok'=>true]);
    }catch(DomainException $e){echo json_encode(['ok'=>false,'status'=>$e->getCode()]);}exit;
}
$db=$connect($config['database']);$names=['tout_y_est_tours_test_'.bin2hex(random_bytes(8)),'tout_y_est_tours_test_'.bin2hex(random_bytes(8))];[$name,$fresh]=$names;
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $fn,int $code)use($assert):void{try{$fn();}catch(DomainException $e){$assert($e->getCode()===$code,$why='Refus attendu '.$code.', reçu '.$e->getCode());return;}throw new RuntimeException('Refus attendu.');};
$schema=file_get_contents($root.'/Database/schema.sql');$migration=file_get_contents($root.'/Database/migrations/S1.15_courier_tours.sql');
$schemaTail=substr($schema,strpos($schema,"-- S1.15 forward"));$assert(trim($schemaTail)===trim($migration),'schema.sql et migration forward identiques');
try{
    $db->exec(str_replace('tout_y_est',$name,explode('-- BEGIN S1.15',$schema,2)[0]));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut,email_verified_at) VALUES
      (1,'TEST','Admin','admin@example.invalid','unused','admin','actif',NOW()),(2,'TEST','Alice','alice@example.invalid','unused','client','actif',NOW()),
      (3,'TEST','Bob','bob@example.invalid','unused','client','actif',NOW()),(4,'TEST','Inactive','inactive@example.invalid','unused','admin','inactif',NOW()),(5,'TEST','Client','client@example.invalid','unused','client','actif',NOW())");
    $db->exec("INSERT INTO livreur(id_livreur,nom,statut) VALUES(1,'TEST Alice','actif'),(2,'TEST Bob','actif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif');INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST','test','TEST','actif');INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,stock,statut) VALUES(1,1,'TEST-TOURS',20,1000,'actif')");
    $make=static function(int $id,string $currency='USD')use($db):void{
        $q=$db->prepare("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,devise_paiement,total_paiement,taux_usd_cdf) VALUES(?,5,?,'confirmee',20,5,25,'USD',?,?,2450)");$q->execute([$id,'TYE-TEST-'.$id,$currency,$currency==='USD'?'25.00':'61250.00']);
        $q=$db->prepare("INSERT INTO livraison(id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,nom_zone_snapshot,frais_snapshot) VALUES(?,'TEST','Destinataire','TEST','TEST','Adresse historique','Zone historique',5)");$q->execute([$id]);
        $q=$db->prepare("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(?,1,1,'TEST historique','TEST-SNAPSHOT',1,20,20)");$q->execute([$id]);
        $q=$db->prepare("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(?,1,25,'a_encaisser',2450)");$q->execute([$id]);
    };
    $make(100);$db->exec("UPDATE commande SET statut='en_preparation' WHERE id_commande=100;UPDATE livraison SET statut='en_preparation',id_livreur=1,nom_livreur_snapshot='Ancien nom',date_preparation=NOW() WHERE id_commande=100");
    try{$db->exec($migration);throw new RuntimeException('Migration aurait dû refuser une affectation active legacy.');}catch(PDOException){}
    $assert((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tournee'")->fetchColumn()===0,'Précontrôle avant tout DDL durable');
    // Terminer uniquement cette fixture historique pour simuler un déploiement après retour legacy.
    $db->exec("UPDATE commande SET statut='livree' WHERE id_commande=100;UPDATE livraison SET statut='livree',date_expedition=NOW(),date_livraison=NOW() WHERE id_commande=100;UPDATE paiement SET statut='paye',date_paiement=NOW() WHERE id_commande=100");
    $before=$db->query('SELECT * FROM livraison WHERE id_commande=100')->fetch();unset($before['id_livreur_engage']);
    // Cas exact 1553 : en S1.14, seul l'UNIQUE de transition supporte cette FK.
    $indexes=$db->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colonnes FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' GROUP BY INDEX_NAME HAVING colonnes='id_commande' OR colonnes LIKE 'id_commande,%'")->fetchAll();
    $assert(count($indexes)===1&&$indexes[0]['INDEX_NAME']==='uq_journal_transition','Fixture S1.14 reproduit le seul index support de FK');
    $journalForeignKeys=static fn():array=>$db->query("SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' AND CONSTRAINT_NAME='fk_journal_commande'")->fetchAll();
    $foreignKeyBefore=$journalForeignKeys();$assert(count($foreignKeyBefore)===1,'FK commande présente avant migration');
    $db->exec($migration);
    $checkIndexes=static function()use($db,$assert,$journalForeignKeys,$foreignKeyBefore):void{
        $assert($journalForeignKeys()===$foreignKeyBefore,'FK conservée sans suppression/recréation');
        $q=$db->query("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colonnes FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' GROUP BY INDEX_NAME,NON_UNIQUE");$keys=[];foreach($q->fetchAll()as$key)$keys[$key['INDEX_NAME']]=$key;
        $assert(($keys['idx_journal_commande']['colonnes']??null)==='id_commande'&&(int)$keys['idx_journal_commande']['NON_UNIQUE']===1,'Index support explicite présent');
        $assert(($keys['uq_journal_transition']['colonnes']??null)==='id_commande,action_unique'&&(int)$keys['uq_journal_transition']['NON_UNIQUE']===0,'Unicité des transitions conservée');
        $assert(($keys['uq_journal_ressource']['colonnes']??null)==='id_commande,action,ressource'&&(int)$keys['uq_journal_ressource']['NON_UNIQUE']===0,'Unicité par ressource présente');
        $assert(!isset($keys['uq_journal_commande_action']),'Ancienne unicité globale absente');
    };
    $checkIndexes();$db->exec($migration);$checkIndexes();
    $assert($db->query('SELECT * FROM livraison WHERE id_commande=100')->fetch()===$before,'Ancienne livraison et nom inchangés après migration/rejeu');
    $create=static function(PDO $db):array{
        $out=[];
        foreach(['livreur','tournee','tentative_livraison','collecte_cash','journal_commande','livraison']as$table){
            $ddl=$db->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
            $ddl=preg_replace('/AUTO_INCREMENT=\d+ /','',$ddl);$definition=[];$constraints=[];
            foreach(explode("\n",$ddl)as$line){
                // Le rejeu peut changer l'ordre d'impression des index, pas leur définition.
                if(preg_match('/^\s*(?:PRIMARY KEY|UNIQUE KEY|KEY|CONSTRAINT)\b/',$line))$constraints[]=rtrim(trim($line),',');
                else $definition[]=$line; // L'ordre et les définitions des colonnes restent contrôlés.
            }
            sort($constraints,SORT_STRING);$out[$table]=['definition'=>$definition,'constraints'=>$constraints];
        }
        return $out;
    };
    $migrated=$create($db);$db->exec(str_replace('tout_y_est',$fresh,$schema));$assert($create($db)===$migrated,'Installation neuve et forward identiques');$db->exec('USE '.chr(96).$name.chr(96));
    $tours=new App\Services\CourierTourService($db);$access=new App\Services\AdminAccessService(new App\Models\User($db));$admin=new App\Services\AdminCourierService($db,$access);$orders=new App\Services\AdminOrderService($db,$access,new App\Services\MoneyService());
    App\Core\Session::set('user',['id'=>1]);$assert($tours->installed(),'S1.15 complète');
    $tours->link(1,2,$access);$tours->link(2,3,$access);$reject(fn()=>$tours->link(2,2,$access),422);
    $assert($admin->listing()['courierAccess']===false,'Pas de bouton espace Livreur pour un Admin non associé');
    foreach([0=>401,4=>403,5=>403]as$actor=>$code){App\Core\Session::set('user',['id'=>$actor]);$reject(fn()=>$orders->prepare(100),$code);}
    App\Core\Session::set('user',['id'=>1]);
    for($i=1;$i<=11;$i++){$make($i,$i===2?'CDF':'USD');$orders->prepare($i);}
    $assert((int)$db->query('SELECT COUNT(*) FROM tournee')->fetchColumn()===2,'Deux tournées de cinq, pas onze tournées');
    $assert((int)$db->query('SELECT COUNT(*) FROM tentative_livraison')->fetchColumn()===10,'Plafond cinq colis par tournée');
    $assert($db->query('SELECT id_livreur FROM livraison WHERE id_commande=11')->fetchColumn()===null,'Attente sûre quand complet');
    $fingerprint=static function()use($db):string{$out=[];foreach(['commande','livraison','paiement','tournee','tentative_livraison','collecte_cash','journal_commande','variante_produit']as$t)$out[$t]=$db->query('SELECT * FROM '.$t.' ORDER BY 1')->fetchAll();return serialize($out);};
    $before=$fingerprint();$assert($orders->prepare(1)['reused']&&$fingerprint()===$before,'Retry prêt sans aucun effet');
    $tour=$db->query('SELECT * FROM tournee WHERE id_livreur=1')->fetch();$tourId=(int)$tour['id_tournee'];$version=(int)$tour['version_manifeste'];
    App\Core\Session::set('user',['id'=>5]);$reject(fn()=>$tours->page(),403);App\Core\Session::set('user',['id'=>3]);$reject(fn()=>$tours->start($tourId,$version),404);
    App\Core\Session::set('user',['id'=>2]);$reject(fn()=>$access->requireCurrentAdmin(),403);$reject(fn()=>$tours->start($tourId,$version-1),409);
    $page=$tours->page();$assert(count($page['tours'])===1&&count($page['parcels'])===5,'Lecture limitée au compte du livreur');
    $qcount=static fn():int=>(int)$db->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_NUM)[1];$n=$qcount();$tours->page();$assert($qcount()-$n-1===5,'Lecture groupée : cinq requêtes pour cinq colis');
    $tours->start($tourId,$version);$before=$fingerprint();$tours->start($tourId,$version);$assert($fingerprint()===$before,'Départ idempotent');
    $reject(fn()=>$tours->returned($tourId),409);
    App\Core\Session::set('user',['id'=>1]);$admin->setAvailability(1,'inactif');$make(12);$orders->prepare(12);
    $assert((int)$db->query('SELECT COUNT(*) FROM tentative_livraison WHERE id_tournee='.$tourId)->fetchColumn()===5,'Aucun ajout après départ');
    $admin->save(1,'TEST Renommée');$assert($db->query('SELECT nom_livreur_snapshot FROM livraison WHERE id_commande=1')->fetchColumn()==='TEST Alice','Snapshot intact après renommage');
    App\Core\Session::set('user',['id'=>2]);$attempts=$db->query('SELECT * FROM tentative_livraison WHERE id_tournee='.$tourId.' ORDER BY emplacement')->fetchAll();
    $first=(int)$attempts[0]['id_tentative'];$reject(fn()=>$tours->outcome($first,'probleme','autre',''),422);
    $tours->outcome($first,'probleme','absent');$before=$fingerprint();$tours->outcome($first,'probleme','absent');$assert($fingerprint()===$before,'Incident idempotent');$reject(fn()=>$tours->outcome($first,'livree'),409);
    foreach(array_slice($attempts,1)as$a)$tours->outcome((int)$a['id_tentative'],'livree');
    $assert((int)$db->query("SELECT COUNT(*) FROM paiement WHERE statut='collecte_livreur'")->fetchColumn()===4,'Cash collecté, jamais payé implicitement');
    $assert((int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===1000,'Incident/livraison ne restituent pas le stock');
    $assert($db->query('SELECT statut FROM tournee WHERE id_tournee='.$tourId)->fetchColumn()==='en_cours','Dernière livraison ne libère pas le livreur');
    $page=$tours->page();$assert(($page['collectedTotals'][$tourId]??[])===['USD'=>'75.00','CDF'=>'61250.00']||($page['collectedTotals'][$tourId]??[])===['CDF'=>'61250.00','USD'=>'75.00'],'Résumé fondé sur les collectes réelles, devises séparées');
    $before=$fingerprint();$tours->outcome((int)$attempts[1]['id_tentative'],'livree');$assert($fingerprint()===$before,'Retry livraison sans deuxième collecte');
    $tours->returned($tourId);$before=$fingerprint();$tours->returned($tourId);$assert($fingerprint()===$before,'Retour idempotent');
    $assert((int)$db->query('SELECT COUNT(*) FROM tournee WHERE livreur_ouvert=1')->fetchColumn()===0,'Inactif ne reçoit rien après retour');
    App\Core\Session::set('user',['id'=>1]);$remits=$tours->remittances($access);$assert(count($remits)===2,'USD et CDF jamais additionnés');
    $reject(fn()=>$tours->remit($tourId,['USD'=>'0','CDF'=>'0'],$access),422);
    $reject(fn()=>$tours->remit($tourId,['USD'=>'0.01'],$access,true),422);
    $reject(fn()=>$tours->remit($tourId,['USD'=>'70.00','CDF'=>'61250.00'],$access,false,''),422);
    $tours->remit($tourId,['USD'=>'70.00','CDF'=>'61250.00'],$access,false,'TEST manque 5 USD');
    $assert((int)$db->query("SELECT COUNT(*) FROM paiement WHERE statut='collecte_livreur'")->fetchColumn()===4,'Écart signalé sans encaissement');
    $assert((int)$db->query("SELECT COUNT(*) FROM journal_commande WHERE action='incident' AND ressource LIKE 'ecart-cash:%'")->fetchColumn()===4,'Écart journalisé pour chaque commande concernée');
    $reader=new App\Services\AdminOrderReadService($access,new App\Models\AdminOrderRead($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),new App\Models\Payment($db),new App\Models\AdminOrderJournal($db),new App\Services\MoneyService());
    $detail=$reader->detail('TYE-TEST-2');
    $assert(count(array_filter($detail['events'],static fn(array $event):bool=>$event['label']==='Écart de caisse signalé — remise non validée'&&($event['details']['motif']??'')==='TEST manque 5 USD'))===1,'Écart lisible dans le journal, distinct des incidents de livraison');
    $before=$fingerprint();$tours->remit($tourId,['USD'=>'70.00','CDF'=>'61250.00'],$access,false,'TEST manque 5 USD');$assert($fingerprint()===$before,'Signalement idempotent');
    App\Core\Session::set('user',['id'=>2]);$reject(fn()=>$tours->remit($tourId,[],$access,true),403);App\Core\Session::set('user',['id'=>1]);
    $tours->remit($tourId,[],$access,true);$before=$fingerprint();$tours->remit($tourId,[],$access,true);$assert($fingerprint()===$before,'Remise normale serveur idempotente sans montant client');
    $assert((int)$db->query("SELECT COUNT(*) FROM collecte_cash WHERE date_remise IS NOT NULL AND id_confirmateur=1")->fetchColumn()===4,'Traçabilité des remises');
    $assert((int)$db->query("SELECT COUNT(*) FROM journal_commande WHERE action IN ('deliver','cash-collected')")->fetchColumn()===8,'Livraison et cash : événements distincts');
    $assert(App\Services\CustomerOrderService::paymentStatus('collecte_livreur','Espèces à la livraison')==='Réglé au livreur','Libellé client');
    $admin->setAvailability(1,'actif');$assert((int)$db->query('SELECT COUNT(*) FROM tournee WHERE livreur_ouvert=1')->fetchColumn()===1,'Retour/activation réaffectent les commandes prêtes en attente');
    $assert((int)$db->query('SELECT COUNT(*) FROM tentative_livraison WHERE id_livraison='.$attempts[0]['id_livraison'])->fetchColumn()===1,'Incident conservé, aucune nouvelle présentation silencieuse');
    // Course réelle : deux commandes prêtes et départ sur le même manifeste.
    $loading=$db->query('SELECT * FROM tournee WHERE livreur_ouvert=1')->fetch();$loadingId=(int)$loading['id_tournee'];$oldVersion=(int)$loading['version_manifeste'];$make(13);$make(14);
    $specs=[[1,'ready',13,0],[1,'ready',14,0],[2,'start',$loadingId,$oldVersion]];$workers=[];
    foreach($specs as[$actor,$action,$resource,$v]){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$actor,$action,(string)$resource,(string)$v],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
    foreach($workers as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);$r=json_decode($out,true,512,JSON_THROW_ON_ERROR);$assert($r['ok']||($r['status']??0)===409,'Course sans erreur SQL ni manifeste périmé accepté');}
    $assert((int)$db->query('SELECT MAX(n) FROM (SELECT COUNT(*) n FROM tentative_livraison GROUP BY id_tournee) x')->fetchColumn()<=5,'Capacité après concurrence');
    $assert((int)$db->query('SELECT COUNT(*) FROM (SELECT livraison_active FROM tentative_livraison WHERE livraison_active IS NOT NULL GROUP BY livraison_active HAVING COUNT(*)>1) x')->fetchColumn()===0,'Aucune double affectation');
    echo "COURIER_TOURS_OK (forward/rejeu, comptes, isolation, cinq colis, attente, manifeste/concurrence, incidents, retour, cash, retries, snapshots, stock, lectures groupées).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    $db->exec('USE '.chr(96).$config['database'].chr(96));
    foreach($names as$target){if(!preg_match('/^tout_y_est_tours_test_[a-f0-9]{16}$/D',$target))throw new RuntimeException('Nettoyage refusé.');$db->exec('DROP DATABASE IF EXISTS '.chr(96).$target.chr(96));}
}
