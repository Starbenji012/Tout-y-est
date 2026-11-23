<?php
declare(strict_types=1);
// Déploiement et rollback exclusivement en base jetable, jamais sur tout_y_est.
$root=dirname(__DIR__,2);$config=require $root.'/Config/database.php';
if($config['database']!=='tout_y_est'||!in_array($config['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Cible locale requise');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname=tout_y_est;charset=utf8mb4',$config['username'],$config['password'],$config['options']);
$name='tout_y_est_options_test_'.bin2hex(random_bytes(8));
$forward=file_get_contents($root.'/Database/migrations/S1.16_product_options.sql');
$rollback=file_get_contents($root.'/Database/migrations/S1.16_product_options_rollback.sql');
$assert=static function(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);};
$reject=static function(string $sql)use($db):void {
    try{$db->exec($sql);}catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1062,1451,1452,4025,3819,1264],true))return;throw $e;}
    throw new RuntimeException('Contrainte attendue : '.$sql);
};
$tables=['produit','variante_produit','panier','ligne_panier','commande','ligne_commande'];
$snapshot=static function()use($db,$tables):array {
    $result=[];foreach($tables as $table){$rows=$db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)unset($row['cle_combinaison']);unset($row);$result[$table]=$rows;}return $result;
};
$shape=static function()use($db):array {
    $result=[];
    foreach(['option_produit','valeur_option_produit','variante_valeur_option'] as $table){$ddl=$db->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];$result[$table]=preg_replace('/ AUTO_INCREMENT=\d+/','',$ddl);}
    $result['keys']=$db->query("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='variante_produit' AND INDEX_NAME IN ('uq_variante_combinaison','uq_variante_identite_produit') ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
    return $result;
};
try {
    $db->exec(str_replace('tout_y_est',$name,file_get_contents($root.'/Database/schema.sql')));
    $assert($db->query('SELECT DATABASE()')->fetchColumn()===$name,'Base jetable');
    $fresh=$shape();$db->exec($rollback);
    $assert((int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='variante_produit' AND COLUMN_NAME='cle_combinaison'")->fetchColumn()===0,'Retour au schéma pré-4A');
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe) VALUES(1,'TEST','Options','options@example.invalid','unused')");
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(1,1,'TEST simple','test-simple','','actif'),(2,1,'TEST ancien multiple','test-multiple','','brouillon')");
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut,description_variante) VALUES(101,1,'TEST-SIMPLE',25,'USD',20,'actif','Ancienne description'),(102,2,'TEST-A',15,'USD',4,'actif',NULL),(103,2,'TEST-B',20,'USD',7,'inactif',NULL)");
    $db->exec('INSERT INTO panier(id_panier,id_utilisateur) VALUES(1,1)');$db->exec('INSERT INTO ligne_panier(id_panier,id_variante,quantite) VALUES(1,101,2)');
    $db->exec("INSERT INTO commande(id_commande,id_utilisateur,numero_commande,statut,sous_total,frais_livraison,total,devise_reference,taux_usd_cdf) VALUES(1,1,'TYE-TEST-OPTIONS','confirmee',50,5,55,'USD',2450)");
    $db->exec("INSERT INTO ligne_commande(id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,description_variante_snapshot,quantite,prix_unitaire_applique,total_ligne) VALUES(1,1,101,'TEST Historique','TEST-SIMPLE','Description figée',2,25,50)");
    $before=$snapshot();$db->exec($forward);$assert($snapshot()===$before,'IDs, données commerciales, paniers et snapshots préservés');
    $assert($fresh===$shape(),'Installation neuve et migration cohérentes');
    $assert((int)$db->query('SELECT COUNT(*) FROM variante_produit WHERE cle_combinaison IS NOT NULL')->fetchColumn()===0,'Anciennes variantes simples/multiples NULL sans options inventées');
    $shapeBefore=$shape();$db->exec($forward);$assert($shape()===$shapeBefore&&$snapshot()===$before,'Rejeu idempotent');
    $db->exec("INSERT INTO option_produit(id_option,id_produit,nom,position) VALUES(1,1,'Couleur',1),(2,1,'Taille',2),(3,1,'Matière',3),(4,2,'Couleur',1)");
    $reject("INSERT INTO option_produit(id_produit,nom,position) VALUES(1,'Quatrième',4)");
    $reject("INSERT INTO option_produit(id_produit,nom,position) VALUES(1,'Autre',1)");
    $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES(3,1,'TEST noms','test-noms','','brouillon')");
    $db->exec("INSERT INTO option_produit(id_produit,nom,position) VALUES(3,' Couleur ',1)");
    $reject("INSERT INTO option_produit(id_produit,nom,position) VALUES(3,'couleur',2)");
    $reject("INSERT INTO option_produit(id_produit,nom,position) VALUES(3,'   ',2)");
    $reject("INSERT INTO option_produit(id_produit,nom,position) VALUES(999,'Autre',1)");
    $db->exec("INSERT INTO valeur_option_produit(id_valeur,id_option,libelle,ordre_affichage) VALUES(11,1,'Noir',0),(12,1,'Blanc',1),(21,2,'S',0),(22,2,'M',1),(31,3,'Coton',0),(41,4,'Noir',0)");
    $reject("INSERT INTO valeur_option_produit(id_option,libelle) VALUES(1,' noir ')");
    $reject("INSERT INTO valeur_option_produit(id_option,libelle) VALUES(1,'   ')");
    $reject("INSERT INTO valeur_option_produit(id_option,libelle,ordre_affichage) VALUES(1,'Gris',-1)");
    $reject("INSERT INTO valeur_option_produit(id_option,libelle) VALUES(999,'Autre')");
    $db->exec("UPDATE variante_produit SET cle_combinaison='1:11|2:21|3:31' WHERE id_variante=101");
    $db->exec('INSERT INTO variante_valeur_option(id_variante,id_produit,id_option,id_valeur) VALUES(101,1,1,11),(101,1,2,21),(101,1,3,31)');
    $reject('INSERT INTO variante_valeur_option VALUES(101,1,1,12)');
    $reject('UPDATE variante_valeur_option SET id_valeur=21 WHERE id_variante=101 AND id_option=1');
    $reject('INSERT INTO variante_valeur_option VALUES(102,2,1,11)');
    $reject('INSERT INTO variante_valeur_option VALUES(102,1,1,11)');
    $reject('INSERT INTO variante_valeur_option VALUES(101,2,4,41)');
    $reject('INSERT INTO variante_valeur_option VALUES(999,2,4,41)');
    $reject("INSERT INTO variante_produit(id_produit,sku,prix_reference,stock,statut,cle_combinaison) VALUES(1,'TEST-DUP',25,1,'actif','1:11|2:21|3:31')");
    $db->exec("INSERT INTO variante_produit(id_produit,sku,prix_reference,stock,statut,cle_combinaison) VALUES(1,'TEST-DIFFERENT',25,1,'actif','1:12|2:22|3:31')");$different=(int)$db->lastInsertId();
    $db->exec('INSERT INTO variante_valeur_option(id_variante,id_produit,id_option,id_valeur) VALUES('.$different.',1,1,12),('.$different.',1,2,22),('.$different.',1,3,31)');
    foreach(['2:21|1:11','1:11|1:12','1:11|2:21|3:31|4:41','01:11','1:011','1:0','Simple','1:11 ',"1:11\n"] as $bad){$sql=$db->quote($bad);$reject("UPDATE variante_produit SET cle_combinaison=$sql WHERE id_variante=102");}
    $db->exec("UPDATE variante_produit SET cle_combinaison='simple' WHERE id_variante=102");
    $reject("UPDATE variante_produit SET cle_combinaison='simple' WHERE id_variante=103");
    $db->exec("UPDATE variante_produit SET cle_combinaison='simple' WHERE id_variante=$different"); // Unicité par produit, pas globale.
    $db->exec("UPDATE variante_produit SET cle_combinaison='1:12|2:22|3:31' WHERE id_variante=$different");
    $db->exec("UPDATE valeur_option_produit SET libelle='Noir profond' WHERE id_valeur=11");
    $db->exec("UPDATE option_produit SET nom='Teinte' WHERE id_option=1");
    $assert($db->query('SELECT cle_combinaison FROM variante_produit WHERE id_variante=101')->fetchColumn()==='1:11|2:21|3:31','Renommage sans changement identité');
    $reject('DELETE FROM variante_produit WHERE id_variante=101');
    $reject('DELETE FROM valeur_option_produit WHERE id_valeur=11');
    $reject('UPDATE option_produit SET id_produit=2 WHERE id_option=1');
    $reject('UPDATE variante_produit SET id_produit=2 WHERE id_variante='.$different);
    $reject($rollback);$assert(count($db->query('SELECT * FROM option_produit')->fetchAll())===5,'Rollback refuse de perdre données modernes');
    $replay=$shape();$db->exec($forward);$assert($shape()===$replay,'Rejeu avec données modernes préservées');
    $db->exec('DELETE FROM variante_valeur_option');$db->exec('DELETE FROM valeur_option_produit');$db->exec('DELETE FROM option_produit');
    $reject($rollback); // Une clé moderne seule doit aussi empêcher un rollback destructif.
    $db->exec('UPDATE variante_produit SET cle_combinaison=NULL');$db->exec('DELETE FROM variante_produit WHERE id_variante='.$different);$db->exec('DELETE FROM produit WHERE id_produit=3');
    $assert($snapshot()===$before,'Données initiales intactes après tests et nettoyage');
    $db->exec($rollback);$assert($snapshot()===$before,'Rollback conserve anciennes lignes et FK');
    $db->exec($forward);$assert($snapshot()===$before&&$shape()===$fresh,'Réapplication après rollback');
    echo 'PRODUCT_OPTIONS_SCHEMA_OK MariaDB='.$db->query('SELECT VERSION()')->fetchColumn()." (installation neuve, migration/rejeu/rollback, options 1..3, noms, valeurs, FK composites, combinaison, compatibilité panier/commande).\n";
} finally {
    if($db->inTransaction())$db->rollBack();$db->exec('USE tout_y_est');$db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
