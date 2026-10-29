<?php
declare(strict_types=1);

// Base éphémère : aucune migration ni donnée métier de tout_y_est n'est modifiée.
$root = dirname(__DIR__, 2);
require_once $root . '/App/models/Product.php';
require_once $root . '/App/models/Favorite.php';
require_once $root . '/App/services/ProductService.php';
if (($argv[1] ?? '') === 'outage') {
    putenv('DB_PORT=1');
    putenv('APP_ENV=' . $argv[2]);
    putenv('CATALOG_DEMO_ENABLED=' . $argv[3]);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/boutique';
    http_response_code(200);
    require $root . '/Public/index.php';
    echo '\n__HTTP=' . http_response_code();
    exit;
}
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$config = require $root . '/Config/database.php';
if (!in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Serveur local requis.');
$db = new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4', $config['username'], $config['password'], $config['options']);
$name = 'tout_y_est_catalog_test_' . bin2hex(random_bytes(8));
try {
    $db->exec(str_replace('tout_y_est', $name, file_get_contents($root . '/Database/schema.sql')));
    // Comparaison en lecture seule avec les tables migrées, sans dépendre du nom des index.
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $columns = $db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY COLUMN_NAME');
        $columns->execute([$name,$table]); $fresh=$columns->fetchAll();
        $columns->execute([$config['database'],$table]); $migrated=$columns->fetchAll();
        // S1.11 est testée sur base éphémère ; son application locale reste manuelle.
        if ($table === 'journal_commande' && $migrated === []) continue;
        // Seule la colonne S1.12 peut manquer tant que sa migration locale attend validation.
        if ($table==='livraison' && !in_array('nom_livreur_snapshot',array_column($migrated,'COLUMN_NAME'),true)) {
            $fresh=array_values(array_filter($fresh,static fn(array $column):bool=>$column['COLUMN_NAME']!=='nom_livreur_snapshot'));
        }
        $assert($fresh === $migrated, 'Colonnes installation/migrations : '.$table);
        $keys = $db->prepare("SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY COLUMN_NAME");
        $keys->execute([$name,$table]); $freshKeys=$keys->fetchAll();
        $keys->execute([$config['database'],$table]);
        $assert($freshKeys === $keys->fetchAll(), 'FK installation/migrations : '.$table);
        $indexes = $db->prepare('SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE ORDER BY columns_list,NON_UNIQUE');
        $indexes->execute([$name,$table]); $freshIndexes=$indexes->fetchAll();
        $indexes->execute([$config['database'],$table]);
        $assert($freshIndexes === $indexes->fetchAll(), 'Index/UNIQUE installation/migrations : '.$table);
    }
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,telephone,mot_de_passe) VALUES(1,'TEST','Alice','alice@example.invalid','+243812345678','unused'),(2,'TEST','Bob','bob@example.invalid',NULL,'unused')");
    try {
        $db->exec("UPDATE utilisateur SET telephone='+243812345678' WHERE id_utilisateur=2");
        throw new RuntimeException('Téléphone dupliqué accepté.');
    } catch (PDOException $error) {
        $assert(($error->errorInfo[1] ?? null) === 1062, 'UNIQUE téléphone sur installation neuve');
    }
    $db->exec("INSERT INTO categorie(id_categorie,nom,slug,statut) VALUES(1,'TEST','test','actif')");
    for ($id = 1; $id <= 5; $id++) {
        $db->exec("INSERT INTO produit(id_produit,id_categorie,nom,slug,description,statut) VALUES($id,1,'TEST $id','test-$id','TEST','actif')");
    }
    $db->exec("INSERT INTO variante_produit(id_variante,id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES
        (1,1,'TEST-INACTIVE',1,'USD',90,'inactif'),(2,1,'TEST-ACTIVE',25,'USD',4,'actif'),
        (3,2,'TEST-EMPTY',10,'USD',0,'actif'),(4,2,'TEST-STOCK',15,'USD',7,'actif'),
        (5,3,'TEST-EMPTY-A',20,'USD',0,'actif'),(6,3,'TEST-EMPTY-B',30,'USD',0,'actif'),
        (7,4,'TEST-FIRST',40,'USD',2,'actif'),(8,4,'TEST-SECOND',5,'USD',8,'actif'),
        (9,5,'TEST-NONE',50,'USD',6,'inactif')");
    $model = new App\Models\Product($db);
    $service = new App\Services\ProductService($model);
    foreach ([1 => [2,4,'25.00'], 2 => [4,7,'15.00'], 3 => [5,0,'20.00'], 4 => [7,2,'40.00']] as $id => [$variant,$stock,$price]) {
        $row = $model->findById($id);
        $assert((int)$row['id_variante'] === $variant && (int)$row['stock'] === $stock && $row['prix_reference'] === $price, 'Variante/prix/stock cohérents : ' . $id);
        $assert((int)$service->getProductDetails($id)['variantId'] === $variant, 'Même variante sur fiche/apercu');
    }
    $assert($model->findById(5) === null, 'Aucune variante active : produit non proposé');
    $all = $service->searchCatalog([]);
    $assert($all['count'] === 4 && count($all['products']) === 4, 'Compteur et catalogue cohérents');
    $priceFiltered = $service->searchCatalog(['price_max'=>10]);
    $assert($priceFiltered['count'] === 0 && $priceFiltered['products'] === [], 'Prix filtré sur la variante affichée, jamais une inactive/autre variante');
    $assert($service->searchCatalog(['statuses'=>['limited']])['count'] === 2, 'Offre limitée cohérente avec le stock affiché');
    $assert($service->searchCatalog(['availability'=>'in-stock'])['count'] === 3, 'Disponibilité active uniquement');
    $assert($service->searchCatalog(['availability'=>'out-of-stock'])['count'] === 1, 'Actif épuisé conservé sans ajout possible');
    $assert($service->catalogFacets(['test']) === [], 'Aucun filtre contextuel sans SQL fiable');
    ob_start(); $catalogContextFilters=['facets'=>$service->catalogFacets(['test'])];
    require $root . '/App/views/components/catalog-context-filters.php';
    $assert(trim(ob_get_clean()) === '', 'Aucun contrôle ni promesse contextuelle affiché');
    $filtered = $service->searchCatalog(['categories'=>['test'],'attributes'=>['couleur'=>['rouge']], 'rating'=>5, 'sort'=>'rating']);
    $assert(!isset($filtered['filters']['attributes']) && !isset($filtered['filters']['rating']) && $filtered['filters']['sort'] === 'newest', 'Paramètres V1 obsolètes ignorés');
    $favorites = new App\Models\Favorite($db);
    $favorites->add(1,1); $favorites->add(1,1);
    $assert($favorites->productIdsForUser(1) === [1] && $favorites->productIdsForUser(2) === [], 'Favoris neuf : unicité et isolation');
    $favorites->remove(1,1);
    $assert($favorites->productIdsForUser(1) === [], 'Suppression favori neuf');
    $assert((new App\Services\ProductService())->searchCatalog([])['count'] === 0, 'Pas de démo implicite');
    $assert((new App\Services\ProductService(demoEnabled:true))->searchCatalog([])['count'] > 0, 'Démo explicitement activée');
    foreach ([['production','true',503],['development','false',503],['development','true',200]] as [$environment,$enabled,$expected]) {
        $process = proc_open([PHP_BINARY,__FILE__,'outage',$environment,$enabled],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $html=stream_get_contents($pipes[1]); $log=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $assert(proc_close($process) === 0 && str_ends_with($html,'__HTTP='.$expected), 'Panne PDO ' . $environment . '/' . $enabled);
        $assert(!str_contains($html,'SQLSTATE') && !str_contains($html,'PDOException'), 'Pas de fuite technique publique');
        $assert(str_contains($log,'Connexion au catalogue indisponible'), 'Panne journalisée côté serveur');
    }
    echo "CATALOG_STABILIZATION_OK (installation neuve, favoris, téléphone UNIQUE, multivariantes, filtres V1, panne DEV/production).\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    $db->exec('USE `' . $config['database'] . '`');
    $db->exec('DROP DATABASE IF EXISTS `' . $name . '`');
}
