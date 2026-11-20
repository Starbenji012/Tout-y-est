<?php
declare(strict_types=1);
// Aucune donnée réelle : tous les POST, triggers et fixtures visent cette base jetable.
$root=dirname(__DIR__,2);ini_set('session.save_path',sys_get_temp_dir());
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Serveur local requis');
$connect=static fn(string $name):PDO=>new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$build=static fn(PDO $db):App\Services\AdminProductWriteService=>new App\Services\AdminProductWriteService($db,new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminProductWrite($db),new App\Services\MoneyService(new App\Models\Setting($db)));
$input=['nom'=>'TEST Nouveau produit','description'=>'Description TEST','id_categorie'=>'1','sku'=>'','prix'=>'25.50','devise'=>'USD','stock_initial'=>'5','statut'=>'brouillon'];
if(in_array($argv[1]??'',['worker','http'],true)) {
    $name=$argv[2]??'';if(!preg_match('/^tout_y_est_product_write_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Base worker refusée');
    App\Core\Session::start();App\Core\Session::set('user',['id'=>(int)($argv[3]??1),'name'=>'TEST Admin']);
    if($argv[1]==='worker') {echo json_encode($build($connect($name))->save(array_replace($input,['nom'=>'TEST Concurrent'])));exit;}
    putenv('DB_DATABASE='.$name);$_SERVER['REQUEST_METHOD']=$argv[4];$_SERVER['REQUEST_URI']=$argv[5];
    $payload=json_decode(base64_decode($argv[6]),true,512,JSON_THROW_ON_ERROR);
    $_GET=$_SERVER['REQUEST_METHOD']==='GET'?$payload:[];$_POST=$_SERVER['REQUEST_METHOD']==='POST'?$payload:[];
    if($_SERVER['REQUEST_METHOD']==='POST')$_POST['_token']=($argv[7]??'')==='bad'?'bad':App\Middleware\CsrfMiddleware::token();
    http_response_code(200);register_shutdown_function(static function():void {echo '\n__HTTP='.http_response_code();});
    require $root.'/Public/index.php';exit;
}
$db=$connect($config['database']);$name='tout_y_est_product_write_test_'.bin2hex(random_bytes(8));
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$rejected=static function(callable $call,int $code=422)use($assert):void {try{$call();}catch(DomainException $e){$assert($e->getCode()===$code,'Code de refus : '.$e->getMessage());return;}throw new RuntimeException('Enregistrement interdit accepté');};
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Base jetable active');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe,role,statut) VALUES(1,'TEST','Admin','admin@example.invalid','unused','admin','actif'),(2,'TEST','Client','client@example.invalid','unused','client','actif'),(3,'TEST','Disabled','disabled@example.invalid','unused','admin','inactif')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif'),(2,'TEST autre','test-autre','actif')");
    $count=static fn():array=>[(int)$db->query('SELECT COUNT(*) FROM produit')->fetchColumn(),(int)$db->query('SELECT COUNT(*) FROM variante_produit')->fetchColumn()];
    $first=$build($db)->save(array_replace($input,['sku'=>' test-manuel ']));$id=$first['id'];
    $row=$db->query('SELECT p.*,v.sku,v.prix_reference,v.devise_reference,v.stock,v.statut AS variante_statut FROM produit p JOIN variante_produit v ON v.id_produit=p.id_produit WHERE p.id_produit='.(int)$id)->fetch();
    $assert($row['statut']==='brouillon'&&$row['variante_statut']==='actif'&&$row['sku']==='TEST-MANUEL'&&$row['prix_reference']==='25.50'&&$row['devise_reference']==='USD'&&(int)$row['stock']===5,'Création atomique USD et brouillon');
    $assert((new App\Models\Product($db))->findById($id)===null,'Brouillon invisible publiquement');
    $reader=new App\Services\AdminProductReadService(new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminProductRead($db),new App\Services\MoneyService());
    $assert($reader->listing(['q'=>'TEST-MANUEL'])['total']===1,'Visible Admin');
    $cdf=$build($db)->save(array_replace($input,['prix'=>'49 000','devise'=>'CDF']));
    $query=$db->prepare('SELECT prix_reference,devise_reference FROM variante_produit WHERE id_produit=?');$query->execute([$cdf['id']]);
    $assert($query->fetch()===['prix_reference'=>'20.00','devise_reference'=>'USD'],'CDF converti par taux serveur 2450');
    $zero=$build($db)->save(array_replace($input,['prix'=>'0','stock_initial'=>'0']));
    $query->execute([$zero['id']]);$assert($query->fetch()['prix_reference']==='0.00','Prix nul valide');
    $variants=$db->query('SELECT sku FROM variante_produit WHERE sku<>\'TEST-MANUEL\'')->fetchAll(PDO::FETCH_COLUMN);
    $assert(count($variants)===count(array_unique($variants))&&(bool)preg_match('/^TYE-[A-F0-9]{16}$/D',$variants[0]),'SKU auto unique');
    $slugs=$db->query('SELECT slug FROM produit')->fetchAll(PDO::FETCH_COLUMN);$assert(count($slugs)===count(array_unique($slugs)),'Slug unique malgré noms identiques');
    foreach ([['nom'=>''],['id_categorie'=>'999'],['prix'=>'-1'],['prix'=>'abc'],['prix'=>'1.234'],['stock_initial'=>'-1'],['stock_initial'=>'1.5'],['stock_initial'=>'4294967296'],['devise'=>'EUR'],['sku'=>'test-manuel'],['statut'=>'actif'],['slug'=>'client-slug'],['taux'=>'1'],['prix_usd'=>'1'],['description'=>str_repeat('é',33000)]] as $change) {
        $before=$count();$rejected(fn()=>$build($db)->save(array_replace($input,$change)));$assert($count()===$before,'Aucun produit incomplet après refus');
    }
    $configJson=$db->query("SELECT valeur FROM parametre WHERE cle='monetary_config'")->fetchColumn();
    $update=$db->prepare("UPDATE parametre SET valeur=? WHERE cle='monetary_config'");
    foreach (['{}','{bad',json_encode(['reference'=>'USD','alternative'=>'CDF','usd_cdf_rate'=>'0'])] as $bad) {
        $update->execute([$bad]);$before=$count();$rejected(fn()=>$build($db)->save(array_replace($input,['prix'=>'49000','devise'=>'CDF'])));$assert($count()===$before,'Taux invalide : rien créé');
    }
    $db->exec("DELETE FROM parametre WHERE cle='monetary_config'");
    $rejected(fn()=>$build($db)->save(array_replace($input,['prix'=>'49000','devise'=>'CDF'])));
    $withoutRate=$build($db)->save(array_replace($input,['prix'=>'12,50']));
    $query->execute([$withoutRate['id']]);$assert($query->fetch()===['prix_reference'=>'12.50','devise_reference'=>'USD'],'USD indépendant du taux absent');
    $restore=$db->prepare("INSERT INTO parametre(id_parametre,cle,valeur) VALUES(1,'monetary_config',?)");$restore->execute([$configJson]);
    // Une erreur SQL APRÈS l'INSERT produit doit aussi annuler le produit.
    $db->exec("CREATE TRIGGER test_variant_failure BEFORE INSERT ON variante_produit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TEST rollback'");
    $before=$count();try{$build($db)->save($input);throw new RuntimeException('Trigger non déclenché');}catch(PDOException){}
    $assert($count()===$before&&!$db->inTransaction(),'Rollback après échec variante');$db->exec('DROP TRIGGER test_variant_failure');
    $edit=['id_produit'=>(string)$id,'nom'=>'TEST Renommé','description'=>'Nouvelle description','id_categorie'=>'2','statut'=>'brouillon'];
    $slug=$row['slug'];$beforeVariants=$db->query('SELECT * FROM variante_produit ORDER BY id_variante')->fetchAll();
    $build($db)->save($edit);$changed=$build($db)->form($id)['product'];
    $assert($changed['nom']==='TEST Renommé'&&(int)$changed['id_categorie']===2&&$changed['slug']===$slug,'Informations modifiées, slug stable');
    $assert($db->query('SELECT * FROM variante_produit ORDER BY id_variante')->fetchAll()===$beforeVariants,'Aucune modification de variante');
    foreach (['stock_initial'=>'99','stock'=>'99','sku'=>'OTHER','prix'=>'1','devise'=>'CDF'] as $field=>$value)$rejected(fn()=>$build($db)->save($edit+[$field=>$value]));
    $rejected(fn()=>$build($db)->save(array_replace($edit,['statut'=>'actif'])));
    $assert(!$build($db)->form($id)['canPublish'],'Activation désactivée sans image');
    $db->exec("INSERT INTO image_produit(id_produit,chemin) VALUES(".(int)$id.",'/assets/images/products/test-image-absente.jpg')");
    $rejected(fn()=>$build($db)->save(array_replace($edit,['statut'=>'actif'])));
    $assert(!$build($db)->form($id)['canPublish'],'Chemin cassé insuffisant pour publier');
    $db->exec('UPDATE produit SET statut=\'actif\' WHERE id_produit='.(int)$id);
    $build($db)->save(array_replace($edit,['statut'=>'actif']));$assert($build($db)->form($id)['product']['statut']==='actif','Actif historique sans image conservé');
    $variantId=(int)$db->query('SELECT id_variante FROM variante_produit WHERE id_produit='.(int)$id)->fetchColumn();
    $db->exec("INSERT INTO commande(id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,'TYE-TEST-PRODUCT','confirmee',25.50,0,25.50,'USD',2450)");
    $orderId=(int)$db->lastInsertId();$snap=$db->prepare('INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(?,?,?,?,?,1,25.50,25.50)');$snap->execute([$orderId,$id,$variantId,'TEST Snapshot','TEST-MANUEL']);
    $snapshot=$db->query('SELECT * FROM ligne_commande')->fetchAll();$order=$db->query('SELECT * FROM commande')->fetchAll();
    $db->exec('INSERT INTO panier(id_utilisateur) VALUES(1)');$cartId=(int)$db->lastInsertId();$db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES('.$cartId.','.$variantId.',2)');
    $db->exec('INSERT INTO favori(id_utilisateur,id_produit) VALUES(1,'.(int)$id.')');
    $build($db)->save(array_replace($edit,['nom'=>'TEST Archivé','statut'=>'archive']));
    $assert($db->query('SELECT * FROM ligne_commande')->fetchAll()===$snapshot&&$db->query('SELECT * FROM commande')->fetchAll()===$order,'Snapshots et commande intacts');
    $assert($count()===$before&&(int)$db->query('SELECT COUNT(*) FROM ligne_panier')->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM favori')->fetchColumn()===1,'Archivage sans suppression de relations');
    $assert($db->query('SELECT * FROM variante_produit ORDER BY id_variante')->fetchAll()===$beforeVariants,'Stock et prix intacts après archivage');
    foreach ([[],['id'=>2],['id'=>3]] as $user) {App\Core\Session::set('user',$user);$rejected(fn()=>$build($db)->save($input),$user===[]?401:403);}
    App\Core\Session::set('user',['id'=>1]);
    // Deux créations concurrentes, même nom : UNIQUE et retry sans écrasement.
    $workers=[];
    for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__FILE__,'worker',$name,'1'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $ids=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$log=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0,'Worker : '.$log);$ids[]=json_decode($out,true,512,JSON_THROW_ON_ERROR)['id'];}
    $assert(count(array_unique($ids))===2,'Créations concurrentes distinctes');
    $http=static function(int $user,string $method,string $url,array $payload,int $status,string $csrf='good')use($name,$assert):string {
        $process=proc_open([PHP_BINARY,__FILE__,'http',$name,(string)$user,$method,$url,base64_encode(json_encode($payload,JSON_THROW_ON_ERROR)),$csrf],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$log=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$assert(proc_close($process)===0&&str_ends_with($out,'__HTTP='.$status),'HTTP '.$status.' '.substr($out,-100).' '.$log);return $out;
    };
    $html=$http(1,'GET','/admin/produit/ajouter',[],200);$assert(str_contains($html,'Créer le brouillon')&&str_contains($html,'name="stock_initial"'),'Formulaire réel création');
    $html=$http(1,'GET','/admin/produit',['id'=>(string)$id],200);$assert(!str_contains($html,'name="stock_initial"')&&str_contains($html,'name="id_produit"'),'Stock existant seulement en lecture');
    $http(2,'GET','/admin/produit/ajouter',[],403);$http(3,'POST','/admin/produits/enregistrer',$input,403);
    $before=$count();$http(1,'POST','/admin/produits/enregistrer',$input,419,'bad');$assert($count()===$before,'CSRF invalide sans écriture');
    $html=$http(1,'POST','/admin/produits/enregistrer',array_replace($input,['nom'=>'TEST <b>gardé</b>','prix'=>'-1']),422);
    $assert(str_contains($html,'TEST &lt;b&gt;gardé&lt;/b&gt;')&&!str_contains($html,'SQLSTATE'),'Valeurs conservées et échappées');
    $http(1,'POST','/admin/produits/enregistrer',array_replace($input,['nom'=>'TEST HTTP CDF','devise'=>'CDF','prix'=>'49000']),303);
    $assert($count()===[$before[0]+1,$before[1]+1],'POST réel atomique + redirection');
    $before=$count();
    $http(1,'POST','/admin/produits/enregistrer',array_replace($edit,['nom'=>'TEST Édition HTTP','statut'=>'archive']),303);
    $assert($count()===$before&&$db->query('SELECT nom FROM produit WHERE id_produit='.(int)$id)->fetchColumn()==='TEST Édition HTTP','POST réel édition sans création parasite');
    $assert(App\Services\AdminNavigation::safeReturn('/admin/produit/ajouter')==='/admin/produit/ajouter'&&App\Services\AdminNavigation::safeReturn('/admin/produit?id='.$id)==='/admin/produit?id='.$id,'Retours auth bornés vers les formulaires');
    // Les erreurs inline proviennent des validations serveur, pas d'une analyse de leur texte.
    $render=static function(array $data,array $old,array $errors,?string $flash=null)use($root):string {
        extract($data);$fieldErrors=$errors;$formError=$errors===[]?null:reset($errors);$csrfToken='TEST';
        ob_start();require $root.'/App/views/admin/product-form.php';return ob_get_clean();
    };
    $formData=$build($db)->form();
    foreach (['nom'=>'','description'=>str_repeat('é',33000),'id_categorie'=>'999','sku'=>'bad sku','prix'=>'-1','devise'=>'EUR','stock_initial'=>'-1','statut'=>'actif'] as $field=>$invalid) {
        $old=array_replace($input,[$field=>$invalid]);
        try {$build($db)->save($old);throw new RuntimeException('Validation attendue');}
        catch(App\Services\ProductFormValidationException $error) {
            $assert(isset($error->errors[$field]),'Champ réel associé : '.$field);
            $html=$render($formData,$old,$error->errors);
            $assert(str_contains($html,'id="product-error-'.$field.'"')&&str_contains($html,'Erreur : '),'Erreur inline : '.$field);
            if($field==='statut')$assert((bool)preg_match('/<fieldset[^>]*aria-invalid="true"[^>]*aria-describedby="product-error-statut"/',$html),'Publication associée');
            else $assert((bool)preg_match('/name="'.$field.'"[^>]*aria-invalid="true"[^>]*aria-describedby="[^"]*product-error-'.$field.'/',$html),'Association accessible : '.$field);
            if(in_array($field,['sku','prix','stock_initial'],true))$assert(str_contains($html,'value="'.htmlspecialchars($invalid,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"'),'Saisie conservée : '.$field);
            if($field==='statut')$assert(substr_count($html,'Ajoutez au moins une photo')===1,'Photo non dupliquée dans les erreurs');
        }
    }
    foreach ([['sku'=>'référence','prix'=>'-10'],['nom'=>'','description'=>str_repeat('é',33000),'stock_initial'=>'-2','id_categorie'=>'0']] as $changes) {
        $old=array_replace($input,$changes);$before=$count();
        try {$build($db)->save($old);throw new RuntimeException('Erreurs multiples attendues');}
        catch(\App\Services\ProductFormValidationException $error) {
            $assert(count($error->errors)===count($changes),'Toutes les erreurs indépendantes collectées');
            $html=$render($formData,$old,$error->errors);
            foreach($changes as $field=>$value) {
                $assert(isset($error->errors[$field])&&str_contains($html,'id="product-error-'.$field.'"'),'Erreur multiple associée : '.$field);
                if(in_array($field,['sku','prix','stock_initial'],true))$assert(str_contains($html,'value="'.htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"'),'Valeur multiple conservée : '.$field);
            }
            $assert(!$db->inTransaction()&&$count()===$before,'Aucune écriture après erreurs préalables');
        }
    }
    $html=$http(1,'POST','/admin/produits/enregistrer',array_replace($input,['sku'=>'référence','prix'=>'-10']),422);
    $assert(str_contains($html,'id="product-error-sku"')&&str_contains($html,'id="product-error-prix"')&&str_contains($html,'value="référence"')&&str_contains($html,'value="-10"'),'POST réel : deux erreurs et saisies conservées ensemble');
    $html=$render($formData,[],[]);
    $assert(str_contains($html,'Référence (SKU) — facultatif')&&str_contains($html,'Code unique permettant'),'SKU expliqué');
    $assert(substr_count($html,'Ajoutez au moins une photo')===1,'Photo unique en création');
    $html=$render($build($db)->form($id),[],[],$first['message']);
    $assert(str_contains($html,'Informations de vente')&&!str_contains($html,'Variantes — consultation')&&str_contains($html,'<dt>Référence (SKU)</dt><dd>TEST-MANUEL</dd>'),'Vente lisible et SKU contextualisé');
    $assert(str_contains($html,'5 unités')&&str_contains($html,'admin-product-notice--success')&&str_contains($html,'Enregistrement réussi'),'Stock lisible et confirmation de succès');
    $assert(substr_count($html,'Ajoutez au moins une photo')===1,'Photo unique après confirmation');
    $html=$http(1,'POST','/admin/produits/enregistrer',array_replace($input,['prix'=>'-1']),422);
    $assert(str_contains($html,'id="product-error-prix"')&&str_contains($html,'aria-describedby="product-price-help product-error-prix"'),'Inline via vrai contrôleur');
    $assert(str_contains($db->query('SHOW CREATE TABLE variante_produit')->fetch(PDO::FETCH_NUM)[1],"`devise_reference` = 'USD'"),'Contrainte USD intacte');
    echo "ADMIN_PRODUCTS_WRITE_OK (USD/CDF serveur, taux absents/invalides, brouillon, CSRF, droits, rollback, SKU/slug/concurrence, édition sans stock, archivage, snapshots, bootstrap PRG).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
