<?php
declare(strict_types=1);
// Aucun autoloader App de test : le POST traverse le vrai point d'entrée du navigateur.
$root=dirname(__DIR__,2);
$config=require $root.'/Config/database.php';
if(!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis.');
// Le parent et ses processus enfants utilisent le répertoire temporaire de test.
ini_set('session.save_path',sys_get_temp_dir());
if(($argv[1]??'')==='worker'){
    $name=$argv[2]??'';
    if(!preg_match('/^tout_y_est_boot_test_[a-f0-9]{16}$/D',$name)||$config['database']!==$name)throw new RuntimeException('Base jetable attendue.');
    require $root.'/App/core/Session.php';
    require $root.'/App/middleware/CsrfMiddleware.php';
    App\Core\Session::start();
    $case=$argv[3]??'valid';
    App\Core\Session::set('user',['id'=>in_array($case,['client','tour-client'],true)?2:1]);
    $_SERVER['REQUEST_METHOD']='POST';
    $_SERVER['REQUEST_URI']='/api/admin/commandes/preparer';
    $_SERVER['HTTP_ACCEPT']='application/json';
    $_POST=['commande'=>'128','_token'=>($argv[3]??'')==='csrf'?'invalid':App\Middleware\CsrfMiddleware::token()];
    if(str_starts_with($case,'tour-')) {
        $_SERVER['REQUEST_URI']='/livreur/livraisons';$_SERVER['REQUEST_METHOD']='GET';$_POST=[];
        $_GET=[];
        if(in_array($case,['tour-detail','tour-foreign','tour-invalid','tour-finished'],true))$_GET=['livraison'=>$case==='tour-foreign'?'999999':($case==='tour-invalid'?['1']:'1')];
        if(in_array($case,['tour-csrf','tour-start'],true)){
            $_SERVER['REQUEST_URI']='/livreur/tournee/demarrer';$_SERVER['REQUEST_METHOD']='POST';
            $_POST=['tournee'=>'1','version'=>'1','_token'=>$case==='tour-csrf'?'invalid':App\Middleware\CsrfMiddleware::token()];
        }
        if($case==='tour-success'){
            $_SERVER['REQUEST_URI']='/livreur/livraison/resultat';$_SERVER['REQUEST_METHOD']='POST';
            $_POST=['tentative'=>'1','resultat'=>'livree','_token'=>App\Middleware\CsrfMiddleware::token()];
        }
    }
    if(str_starts_with($case,'remit-')){
        App\Core\Session::set('user',['id'=>$case==='remit-client'?2:1]);
        $_SERVER['REQUEST_URI']='/admin/remises-cash/confirmer';$_SERVER['REQUEST_METHOD']='POST';
        $_POST=['tournee'=>'1','mode'=>'exact','_token'=>$case==='remit-csrf'?'invalid':App\Middleware\CsrfMiddleware::token()];
        if($case==='remit-forged')$_POST['montants']=['USD'=>'0.01'];
        if($case==='remit-gap')$_POST+=['montants'=>['USD'=>'19.00'],'motif'=>'TEST manque 1 USD'];
        if($case==='remit-gap')$_POST['mode']='ecart';
    }
    http_response_code(200);ob_start();
    register_shutdown_function(static function():void {
        $body=ob_get_clean();$json=str_starts_with($_SERVER['REQUEST_URI'],'/api/');
        $flash=App\Core\Session::get('_tour_flash');App\Core\Session::destroy();
        echo json_encode(['status'=>http_response_code(),'body'=>$json?json_decode($body,true,512,JSON_THROW_ON_ERROR):$body,'flash'=>$flash,
            'dispatch_loaded'=>class_exists('App\\Services\\CourierAssignmentService',false),
            'tours_loaded'=>class_exists('App\\Services\\CourierTourService',false)],JSON_THROW_ON_ERROR);
    });
    require $root.'/Public/index.php';exit;
}
if($config['database']!=='tout_y_est')throw new RuntimeException('Configuration locale attendue.');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_boot_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
try{
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif')");
    $db->exec("INSERT INTO livreur(id_livreur,nom,statut) VALUES(1,'TEST Libre','actif'),(2,'TEST Inactif','inactif')");
    $db->exec('UPDATE livreur SET id_utilisateur=1 WHERE id_livreur=1');
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','boot-test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,statut) VALUES(1,1,'TEST Produit','boot-test','actif')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES(1,1,'TEST-BOOT',15,'USD',20,'actif')");
    // Même état que TYE-004 / 128 : cash confirmé, pas encore préparé, un livreur libre.
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(128,2,'TYE-004','confirmee',15,5,20,'USD',2450)");
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(128,1,1,'TEST Historique','TEST-SNAPSHOT',1,15,15)");
    $db->exec("INSERT INTO livraison(id_livraison,id_commande,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,frais_snapshot) VALUES(71,128,'TEST','Client','TEST','TEST','TEST',5)");
    $db->exec("INSERT INTO paiement(id_commande,id_mode_paiement,montant,statut,taux_usd_cdf) VALUES(128,1,20,'a_encaisser',2450)");
    $call=static function(string $case='valid')use($root,$name,$assert):array{
        $environment=getenv();$environment['DB_DATABASE']=$name;
        $p=proc_open([PHP_BINARY,__FILE__,'worker',$name,$case],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment,['bypass_shell'=>true]);
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $assert(proc_close($p)===0,'Processus bootstrap : '.$err);
        return json_decode($out,true,512,JSON_THROW_ON_ERROR);
    };
    $fingerprint=static function()use($db):string{
        $rows=[];foreach(['commande','ligne_commande','livraison','paiement','livreur','journal_commande','variante_produit']as$table)$rows[$table]=$db->query('SELECT * FROM '.$table)->fetchAll();
        return serialize($rows);
    };
    $before=$fingerprint();
    $assert($call('csrf')['status']===419&&$call('client')['status']===403,'CSRF et rôle relus via le vrai bootstrap');
    $assert($fingerprint()===$before,'Refus sans écriture');
    $result=$call();
    $assert($result['status']===200&&($result['body']['success']??false)===true,'Préparation via Public/index.php : HTTP '.$result['status'].' '.json_encode($result['body']));
    $assert($result['dispatch_loaded'],'Service chargé par le bootstrap, pas par un autoloader de test');
    $delivery=$db->query('SELECT * FROM livraison WHERE id_commande=128')->fetch();
    $assert($db->query('SELECT statut FROM commande WHERE id_commande=128')->fetchColumn()==='en_preparation'&&$delivery['statut']==='en_preparation','Préparation réelle');
    $assert((int)$delivery['id_livreur']===1&&$delivery['date_preparation']!==null&&$delivery['date_expedition']===null,'Préaffectation libre, sans départ');
    $assert($db->query('SELECT statut FROM paiement WHERE id_commande=128')->fetchColumn()==='a_encaisser'&&(int)$db->query('SELECT stock FROM variante_produit WHERE id_variante=1')->fetchColumn()===20,'Paiement et stock inchangés');
    $assert($db->query('SELECT nom_produit_snapshot FROM ligne_commande WHERE id_commande=128')->fetchColumn()==='TEST Historique','Snapshot conservé');
    $assert((int)$db->query('SELECT COUNT(*) FROM journal_commande WHERE id_commande=128')->fetchColumn()===2,'Préparation et affectation journalisées');
    $prepared=$fingerprint();$retry=$call();
    $assert($retry['status']===200&&$retry['body']['reused']===true&&$fingerprint()===$prepared,'Retry navigateur sans double effet');
    $view=$call('tour-get');$assert($view['status']===200&&$view['tours_loaded']&&str_contains($view['body'],'TYE-004')&&str_contains($view['body'],'J’ai récupéré mon colis'),'Espace Livreur réellement chargé par Public/index.php');
    $assert(str_contains($view['body'],'Espace Livreur')&&!str_contains($view['body'],'/assets/js/cart-store.js')&&!str_contains($view['body'],'data-search'),'Layout dédié sans boutique');
    $assert($call('tour-client')['status']===403&&$call('tour-csrf')['status']===419&&$fingerprint()===$prepared,'Isolation et CSRF du vrai bootstrap Livreur, aucune écriture');
    $assert($call('tour-start')['status']===303,'Départ POST puis GET via le vrai bootstrap');
    $departed=$fingerprint();$assert($call('tour-start')['status']===303&&$departed===$fingerprint(),'Retry départ sans double événement');
    $detail=$call('tour-detail');$assert($detail['status']===200&&str_contains($detail['body'],'data-active-delivery="1"')&&str_contains($detail['body'],'name="tentative" value="1"'),'GET détail propriétaire, formulaire du seul colis ouvert');
    $assert($call('tour-foreign')['status']===404&&$call('tour-invalid')['status']===404&&$departed===$fingerprint(),'URL étrangère/invalide refusée sans mutation');
    $assert($call('tour-success')['status']===303,'Traitement réussi : POST puis retour à la tournée');
    $updated=$call('tour-get');$assert($updated['status']===200&&str_contains($updated['body'],'Tournée terminée')&&str_contains($updated['body'],'data-delivery-group="done"')&&!str_contains($updated['body'],'name="tentative"'),'Après livraison : résumé et groupe Terminées, plus aucun formulaire de livraison');
    // La suite du parcours reste confinée à cette base jetable, jamais TYE-005 réelle.
    $attempt=(int)$db->query('SELECT id_tentative FROM tentative_livraison WHERE id_tournee=1')->fetchColumn();
    require_once $root.'/App/core/Session.php';
    spl_autoload_register(static function(string $class)use($root):void{if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
    App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
    $service=new App\Services\CourierTourService($db);$service->outcome($attempt,'livree');$service->returned(1);
    $assert($call('tour-finished')['status']===303,'Ancien lien de traitement renvoie à la liste une fois traité');
    $before=$fingerprint();
    $assert($call('remit-csrf')['status']===419&&$call('remit-client')['status']===403&&$call('remit-forged')['status']===422,'Remise : CSRF, rôle et montant arbitraire refusés');
    $assert($before===$fingerprint(),'Refus de remise sans effet');
    $assert($call('remit-gap')['status']===303&&$db->query('SELECT statut FROM paiement WHERE id_commande=128')->fetchColumn()==='collecte_livreur','Écart enregistré sans faux paiement');
    $before=$fingerprint();$assert($call('remit-gap')['status']===303&&$before===$fingerprint(),'Retry écart sans double journal');
    $confirmation=$call('remit-exact');$assert($confirmation['status']===303&&$db->query('SELECT statut FROM paiement WHERE id_commande=128')->fetchColumn()==='paye','Confirmation normale avec montant serveur');
    $assert(str_contains($confirmation['flash']??'','20,00 USD reçus de TEST Libre'),'Confirmation de remise avec montant serveur et nom historique');
    $before=$fingerprint();$assert($call('remit-exact')['status']===303&&$before===$fingerprint(),'Retry confirmation sans double remise');
    echo "ADMIN_ORDER_BOOTSTRAP_OK (vrai point d’entrée, POST/CSRF/Admin, préparation, préaffectation, stock, cash, snapshots, journal, retry).\n";
}finally{
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_boot_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé.');
    $db->exec('USE '.chr(96).$config['database'].chr(96));$db->exec('DROP DATABASE IF EXISTS '.chr(96).$name.chr(96));
}
