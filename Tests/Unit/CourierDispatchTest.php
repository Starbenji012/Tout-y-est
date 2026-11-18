<?php
declare(strict_types=1);
// Toutes les écritures, migrations et courses concurrentes sont dans une base jetable.
$root=dirname(__DIR__,2);require_once $root.'/vendor/autoload.php';
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local requis.');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$services=static function(PDO $db):array {
    $access=new App\Services\AdminAccessService(new App\Models\User($db));
    return [new App\Services\AdminOrderService($db,$access,new App\Services\MoneyService()),new App\Services\AdminCourierService($db,$access)];
};
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
if(($argv[1]??'')==='worker'){
    $name=$argv[2];if(!preg_match('/^tout_y_est_dispatch_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base refusée.');
    [$orders]=$services($connect($name));
    try{$result=($argv[4]??'prepare')==='assign'?$orders->assign((int)$argv[3],(int)$argv[5],(int)$argv[6]):$orders->prepare((int)$argv[3]);echo json_encode(['ok'=>true]+$result);}
    catch(DomainException $e){echo json_encode(['ok'=>false,'code'=>$e->getCode()]);}exit;
}
$db=$connect($config['database']);$name='tout_y_est_dispatch_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $fn,int $code)use($assert):void{try{$fn();}catch(DomainException $e){$assert($e->getCode()===$code,'Refus '.$code.' attendu, reçu '.$e->getCode());return;}throw new RuntimeException('Refus attendu.');};
try{
    // Contrat S1.14 pré-migration ; CourierToursTest couvre son remplacement S1.15.
    $db->exec(str_replace('tout_y_est',$name,explode('-- BEGIN S1.15',file_get_contents($root.'/Database/schema.sql'),2)[0]));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'Admin','TEST','admin@example.invalid','unused','admin','actif'),(2,'Client','TEST','client@example.invalid','unused','client','actif')");
    // Partir de S1.13 pour vérifier le véritable ajout et le rejeu S1.14.
    $db->exec('ALTER TABLE livraison DROP INDEX uq_livraison_livreur_engage, DROP COLUMN id_livreur_engage');
    $db->exec('ALTER TABLE journal_commande ADD UNIQUE KEY uq_journal_commande_action(id_commande,action)');
    $db->exec('ALTER TABLE journal_commande DROP INDEX uq_journal_transition, DROP COLUMN action_unique');
    $db->exec("ALTER TABLE journal_commande DROP CONSTRAINT chk_journal_action, ADD CONSTRAINT chk_journal_action CHECK(action IN ('prepare','ship','deliver','collect-cash','cancel'))");
    $migration=file_get_contents($root.'/Database/migrations/S1.14_courier_dispatch.sql');
    $db->exec($migration);$db->exec($migration);
    [$orders,$couriers]=$services($db);$model=new App\Models\Courier($db);
    $renderCouriers=static function()use($couriers,$root):string{
        $data=$couriers->listing();extract($data);$old=[];$courierFlash=null;$courierError=null;$csrfToken='TEST';
        ob_start();require $root.'/App/views/admin/couriers.php';return ob_get_clean();
    };
    $make=static function(int $id)use($db):void {
        $q=$db->prepare("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf,date_commande) VALUES(?,2,?,'confirmee',20,5,25,'USD',2450,DATE_ADD('2026-01-01',INTERVAL ? SECOND))");
        $q->execute([$id,'TYE-TEST-DISPATCH-'.$id,$id]);
        $q=$db->prepare("INSERT INTO livraison(id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,frais_snapshot) VALUES(?,'TEST','Client','TEST','TEST','TEST',5)");$q->execute([$id]);
        $q=$db->prepare("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(?,1,25,'a_encaisser',2450)");$q->execute([$id]);
    };
    $delivery=static fn(int $id):array=>(new App\Models\Delivery($db))->forOrder($id);
    $events=static function(int $id)use($db):array{$q=$db->prepare('SELECT * FROM journal_commande WHERE id_commande=? ORDER BY id_journal');$q->execute([$id]);return $q->fetchAll();};
    $one=$couriers->save(null,'TEST Un')['id'];$two=$couriers->save(null,'TEST Deux')['id'];$three=$couriers->save(null,'TEST Trois')['id'];
    $make(1);$orders->prepare(1);$assert($delivery(1)['id_livreur']===null&&$delivery(1)['date_expedition']===null,'Aucun libre : préparation sans départ');
    $couriers->setAvailability($one,'actif');
    $assert((int)$delivery(1)['id_livreur']===$one&&$delivery(1)['statut']==='en_preparation','Activation affecte la plus ancienne en attente');
    $assert($model->active()===[]&&$model->listing()[0]['en_course']==0,'Préaffecté non libre, pas encore en course');
    $assert(str_contains($renderCouriers(),'</span>Affecté — départ en attente</span>'),'État affecté réellement rendu');
    $before=$events(1);$assert($orders->prepare(1)['reused']&&$events(1)===$before,'Retry préparation sans réaffectation');
    $make(2);$orders->prepare(2);$assert($delivery(2)['id_livreur']===null,'Aucune double affectation');
    $couriers->setAvailability($two,'actif');$couriers->setAvailability($three,'actif');
    $assert((int)$delivery(2)['id_livreur']===$two,'Choix stable premier ID libre');
    $preassigned=$delivery(2);$couriers->setAvailability($two,'inactif');
    $assert($delivery(2)===$preassigned,'Indisponibilité avant départ ne remplace pas silencieusement');
    $reject(fn()=>$orders->ship(2,$two),422);$couriers->setAvailability($two,'actif');
    $reject(fn()=>$orders->assign(1,$two,$one),422);
    $initialAssignment=$delivery(1);$initialEvents=$events(1);
    $db->exec("CREATE TRIGGER fail_dispatch_journal BEFORE INSERT ON journal_commande FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST journal failure'");
    try{$orders->assign(1,$three,$one);throw new RuntimeException('Journal attendu');}
    catch(PDOException){}finally{$db->exec('DROP TRIGGER fail_dispatch_journal');}
    $assert($delivery(1)===$initialAssignment&&$events(1)===$initialEvents,'Affectation et journal atomiques');
    $snapshot=$delivery(1)['nom_livreur_snapshot'];$couriers->save($one,'TEST Renommé');
    $assert($delivery(1)['nom_livreur_snapshot']===$snapshot,'Renommage sans réécriture');
    $orders->assign(1,$three,$one);$before=$events(1);
    $assert($orders->assign(1,$three,$one)['reused']&&$events(1)===$before,'Retry affectation sans nouvel événement');
    $reject(fn()=>$orders->assign(1,$one,$one),409);
    $orders->assign(1,$one,$three);
    $assignments=array_values(array_filter($events(1),fn($e)=>$e['action']==='assign'));
    $assert(count($assignments)===3,'Plusieurs réaffectations tracées');
    $data=json_decode($assignments[1]['donnees_complementaires'],true);
    $assert((int)$data['ancien_id_livreur']===$one&&(int)$data['id_livreur']===$three&&$data['origine']==='administrateur','Ancien/nouveau/origine');
    $reject(fn()=>$orders->ship(1,$three),409);
    $orders->ship(1,$one);$before=$delivery(1);$assert($before['statut']==='en_livraison','Départ réel explicite');
    $inCourse=array_values(array_filter($model->listing(),fn($r)=>(int)$r['id_livreur']===$one))[0];
    $assert((bool)$inCourse['en_course'],'En course dérivé');
    $assert(str_contains($renderCouriers(),'</span>En tournée</span>'),'État en tournée réellement rendu');
    $couriers->setAvailability($one,'inactif');$assert($delivery(1)===$before,'Désactivation ne coupe pas la course');
    $assert(str_contains($renderCouriers(),'La livraison actuelle est conservée.'),'Restriction administrative visible pendant la course');
    $make(3);$orders->prepare(3);$assert((int)$delivery(3)['id_livreur']===$three,'ID actif et libre seulement');
    $make(4);$orders->prepare(4);$assert($delivery(4)['id_livreur']===null,'File en attente');
    $orders->deliver(1);$assert($delivery(4)['id_livreur']===null,'Inactif après course : pas de nouvelle affectation');
    $couriers->setAvailability($one,'actif');$assert((int)$delivery(4)['id_livreur']===$one,'Réactivation affecte en attente');
    $finished=$delivery(1);$orders->collectCash(1);$couriers->save($one,'TEST Autre nom');$assert($delivery(1)===$finished,'Livraison terminée et snapshot intacts');
    foreach([[2,$two],[3,$three],[4,$one]]as[$id,$courier]){$orders->ship($id,$courier);$orders->deliver($id);}
    $assert(count($model->active())===3,'Retour libre après livraison');
    $make(7);$orders->prepare(7);$assert((int)$delivery(7)['id_livreur']===$one,'Plusieurs libres : ID croissant');
    $orders->ship(7,$one);$orders->deliver(7);
    // La BDD elle-même refuse une seconde occupation, même hors du service.
    $make(5);$make(6);$couriers->setAvailability($two,'inactif');$couriers->setAvailability($three,'inactif');
    $couriers->setAvailability($one,'inactif');$make(8);$make(9);$orders->prepare(8);$orders->prepare(9);
    $db->exec("UPDATE livraison SET date_preparation=CASE id_commande WHEN 8 THEN '2026-01-02' ELSE '2026-01-01' END WHERE id_commande IN (8,9)");
    $couriers->setAvailability($one,'actif');
    $assert((int)$delivery(9)['id_livreur']===$one&&$delivery(8)['id_livreur']===null,'Plus ancienne préparation en attente prioritaire');
    $orders->ship(9,$one);$orders->deliver(9);$assert((int)$delivery(8)['id_livreur']===$one,'Livraison libère puis affecte la suivante dans la même transaction');
    $orders->ship(8,$one);$orders->deliver(8);
    $db->beginTransaction();$db->query('SELECT id_livreur FROM livreur ORDER BY id_livreur FOR UPDATE')->fetchAll();
    $workers=[];foreach([5,6]as$id){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
    usleep(200000);$db->commit();
    foreach($workers as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);$assert(json_decode($out,true)['ok'],'Préparations concurrentes réussies');}
    $rows=$db->query("SELECT id_livreur,COUNT(*) n FROM livraison WHERE statut IN ('en_preparation','en_livraison') AND id_livreur IS NOT NULL GROUP BY id_livreur")->fetchAll();
    $assert(count($rows)===1&&(int)$rows[0]['n']===1,'Deux processus : une seule occupation');
    $freeOrder=$delivery(5)['id_livreur']===null?5:6;
    try{$db->exec("UPDATE livraison SET id_livreur=$one WHERE id_commande=$freeOrder");throw new RuntimeException('Doublon BDD accepté');}catch(PDOException $e){$assert((int)$e->errorInfo[1]===1062,'UNIQUE occupation');}
    $count=(int)$db->query('SELECT COUNT(*) FROM journal_commande')->fetchColumn();$db->exec($migration);
    $assert((int)$db->query('SELECT COUNT(*) FROM journal_commande')->fetchColumn()===$count,'Rejeu migration après réaffectations sans perte');
    $controller=new App\Controllers\Admin\OrderController($orders,new App\Core\Request());
    $api=static function(int $actor,array $extra=[],string $method='POST')use($controller):int {
        App\Core\Session::set('user',['id'=>$actor]);$_SERVER['REQUEST_METHOD']=$method;
        $_POST=array_replace(['commande'=>5,'id_livreur'=>1,'ancien_id_livreur'=>0,'_token'=>App\Middleware\CsrfMiddleware::token()],$extra);
        ob_start();$controller->assign();ob_end_clean();return http_response_code();
    };
    $assert($api(2)===403&&$api(1,['_token'=>'invalid'])===419&&$api(1,[],'GET')===405,'Affectation : Admin, CSRF et POST');
    $assert($api(1,['user_id'=>2])===422&&$api(1,['ancien_id_livreur'=>'invalide'])===422,'Affectation : champs falsifiés refusés');
    try{$q=$db->prepare("INSERT INTO journal_commande(id_commande,id_administrateur,action,ancien_etat,nouvel_etat,donnees_complementaires) VALUES(1,1,'prepare','{}','{}','{}')");$q->execute();throw new RuntimeException('Transition dupliquée');}catch(PDOException $e){$assert((int)$e->errorInfo[1]===1062,'Unicité transitions conservée');}
    $couriers->setAvailability($two,'actif');$couriers->setAvailability($three,'actif');
    foreach([5,6]as$id){$assigned=(int)$delivery($id)['id_livreur'];$orders->ship($id,$assigned);$orders->deliver($id);}
    $make(10);$orders->prepare(10);$assert((int)$delivery(10)['id_livreur']===$one,'Fixture concurrence réaffectation');
    $db->beginTransaction();$db->query('SELECT id_livreur FROM livreur ORDER BY id_livreur FOR UPDATE')->fetchAll();$workers=[];
    foreach([$two,$three]as$next){$p=proc_open([PHP_BINARY,__FILE__,'worker',$name,'10','assign',(string)$next,(string)$one],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
    usleep(200000);$db->commit();$results=[];
    foreach($workers as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($p)===0,$err);$results[]=json_decode($out,true);}
    $assert(count(array_filter($results,fn($r)=>$r['ok']))===1,'Deux réaffectations concurrentes : un seul gagnant, aucun écrasement');
    $loser=array_values(array_filter($results,fn($r)=>!$r['ok']))[0];$assert($loser['code']===409,'Choix obsolète refusé');
    $winner=(int)$delivery(10)['id_livreur'];$before=$events(10);
    $assert($orders->assign(10,$winner,$one)['reused']&&$events(10)===$before,'Retry du gagnant concurrent sans doublon');
    echo "COURIER_DISPATCH_OK (S1.14/rejeu, libre/affecté/course/inactif, file, réaffectation, snapshots, cash, concurrence et UNIQUE).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_dispatch_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé.');
    $db->exec('USE `'.$config['database'].'`');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
