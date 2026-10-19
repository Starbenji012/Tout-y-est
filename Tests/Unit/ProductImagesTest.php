<?php
declare(strict_types=1);

// Toutes les écritures restent dans une base éphémère identifiée aléatoirement.
$root = dirname(__DIR__, 2);
require_once $root . '/App/models/Product.php';
require_once $root . '/App/services/ProductService.php';
require_once $root . '/App/models/Cart.php';
require_once $root . '/App/services/CartService.php';
$config = require $root . '/Config/database.php';
if (!in_array($config['host'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Serveur local requis.');
$db = new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4', $config['username'], $config['password'], $config['options']);
$name = 'tout_y_est_images_test_' . bin2hex(random_bytes(8));
$assert = static function (bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); };
$reject = static function (callable $action) use ($assert): void { try { $action(); } catch (PDOException) { return; } $assert(false, 'Contrainte SQL absente.'); };
try {
    $db->exec(str_replace('tout_y_est', $name, file_get_contents($root . '/Database/schema.sql')));
    // Vérifie aussi la migration depuis la vraie définition héritée.
    $db->exec('DROP TABLE image_produit');
    $db->exec('CREATE TABLE image_produit (id_image BIGINT NOT NULL PRIMARY KEY, chemin VARCHAR(500) NOT NULL, ordre_affichage SMALLINT NOT NULL, image_principale TINYINT(1) NOT NULL) ENGINE=InnoDB');
    $db->exec(file_get_contents($root . '/Database/migrations/S1.8_product_images.sql'));
    $db->exec("INSERT INTO categorie (id_categorie,nom,slug,statut) VALUES (1,'TEST','test','actif')");
    $db->exec("INSERT INTO produit (id_produit,id_categorie,nom,slug,description,statut) VALUES (1,1,'TEST image','test-image','TEST','actif')");
    $db->exec("INSERT INTO variante_produit (id_produit,sku,prix_reference,devise_reference,stock,statut) VALUES (1,'TEST-IMAGE',20,'USD',10,'actif')");
    $model = new App\Models\Product($db);
    $service = new App\Services\ProductService($model);
    $fallback = '/assets/images/branding/logo-square.png';
    $assert($service->findProduct(1)['image'] === $fallback, 'Sans image : fallback.');
    $db->exec("INSERT INTO image_produit (id_produit,chemin,ordre_affichage) VALUES (1,'/assets/images/products/watch.jpg',2)");
    $assert($service->findProduct(1)['image'] === '/assets/images/products/watch.jpg', 'Une image réelle.');
    $db->exec("INSERT INTO image_produit (id_produit,chemin,ordre_affichage) VALUES (1,'/assets/images/products/laptop.jpg',1),(1,'/assets/images/products/shoes.jpg',1)");
    $assert($service->findProduct(1)['image'] === '/assets/images/products/laptop.jpg', 'Sans principale : ordre puis identifiant.');
    $db->exec('UPDATE image_produit SET image_principale=1 WHERE id_image=1');
    $product = $service->findProduct(1);
    $assert($product['image'] === '/assets/images/products/watch.jpg' && count($product['gallery']) === 3, 'Principale prioritaire et galerie complète.');
    $assert(count($model->findByIds([1])) === 1, 'Galerie sans duplication des produits.');
    $db->exec("INSERT INTO utilisateur (id_utilisateur,nom,prenom,email,mot_de_passe) VALUES (1,'TEST','TEST','image@example.invalid','unused')");
    $db->exec('INSERT INTO panier (id_panier,id_utilisateur) VALUES (1,1)');
    $db->exec('INSERT INTO ligne_panier (id_panier,id_variante,quantite) VALUES (1,1,1)');
    $cart = (new App\Services\CartService($service, new App\Models\Cart($db)))->buildConnectedCart(1);
    $assert($cart['items'][0]['product']['image'] === '/assets/images/products/watch.jpg', 'Panier connecté : vraie image.');
    $summary = null;
    ob_start(); require $root . '/App/views/checkout/summary.php'; $checkoutHtml = (string) ob_get_clean();
    $assert(str_contains($checkoutHtml, 'watch.jpg') && !str_contains($checkoutHtml, 'checkout-product__placeholder'), 'Étape 1 : image réelle, sans cube.');
    $reject(fn () => $db->exec('UPDATE image_produit SET image_principale=1 WHERE id_image=2'));
    $reject(fn () => $db->exec("INSERT INTO image_produit (id_produit,chemin) VALUES (999,'/assets/images/products/watch.jpg')"));
    $reject(fn () => $db->exec('UPDATE image_produit SET ordre_affichage=-1'));
    $reject(fn () => $db->exec('UPDATE image_produit SET image_principale=2 WHERE id_image=2'));
    foreach (['../watch.jpg','/assets/images/products/../watch.jpg','https://example.invalid/a.jpg','/uploads/products/%2e%2e/a.jpg','/assets/images/branding/logo.png','/assets/images/products/missing.jpg','/assets/images/products/a.svg','C:\\secret.jpg'] as $bad) {
        $assert(App\Services\ProductService::localProductImage($bad) === null, 'Chemin interdit : ' . $bad);
    }
    $assert(App\Services\ProductService::localProductImage('assets/images/products/watch.jpg') === '/assets/images/products/watch.jpg', 'Chemin local normalisé.');
    $db->exec("UPDATE image_produit SET chemin='../invalid.jpg' WHERE id_image=1");
    $assert($service->findProduct(1)['image'] === $fallback, 'Chemin invalide jamais exposé.');

    // Rendu client de l'étape 2, sans consulter/modifier une commande réelle.
    $order = ['statut'=>'en_attente_paiement','reservation_remaining_seconds'=>1740,'numero_commande'=>'TYE-TEST','sous_total'=>'40.00','frais_livraison'=>'5.00','totalFormatted'=>'$45.00','cdfFormatted'=>'110 250 FC','taux_usd_cdf'=>'2450.0000'];
    $lines = [['image'=>'/assets/images/products/watch.jpg','nom_produit_snapshot'=>'TEST produit','sku_snapshot'=>'SKU-INTERNE','description_variante_snapshot'=>'DEV interne','quantite'=>2,'prix_unitaire_applique'=>'20.00','total_ligne'=>'40.00']];
    $delivery = ['prenom_destinataire_snapshot'=>'TEST','nom_destinataire_snapshot'=>'TEST','telephone_snapshot'=>'+243000000000','adresse_snapshot'=>'TEST','commune_snapshot'=>'TEST','nom_zone_snapshot'=>'TEST','repere_snapshot'=>null,'instructions_snapshot'=>null];
    ob_start(); require $root . '/App/views/checkout/payment.php'; $html = (string) ob_get_clean();
    $assert(str_contains($html, '2. Paiement') && str_contains($html, '29:00') && str_contains($html, 'En attente de paiement'), 'Écran actif et durée lisible.');
    $assert(substr_count($html, 'class="payment-method" aria-disabled="true"') === 3 && !str_contains($html, 'type="radio"'), 'Trois cartes de paiement non sélectionnables.');
    $assert(str_contains($html, 'data-reservation-seconds="1740"') && str_contains($html, 'checkout-payment__layout'), 'Compteur serveur et composition dédiée.');
    $motionDom = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true); $motionDom->loadHTML($html, LIBXML_NONET); libxml_clear_errors(); libxml_use_internal_errors($previousErrors);
    $assert((new DOMXPath($motionDom))->query('//*[@data-payment-page]//*[@data-motion="section"]')->length === 4, 'DOM réel Paiement : quatre grands blocs utilisent le mécanisme section de l’accueil.');
    $assert(!str_contains($html, 'en_attente_paiement') && !str_contains($html, 'SKU-INTERNE') && !str_contains($html, 'heure du serveur'), 'Informations techniques non affichées.');
    $assert(str_contains($html, 'watch.jpg') && str_contains($html, '110 250 FC') && str_contains($html, '1 USD = 2 450 FC') && !str_contains($html, '2450.0000'), 'Image et montants historiques affichés, taux lisible.');
    foreach (['orange_money.png' => 'Orange Money', 'illicocash.png' => 'Illicocash'] as $asset => $alt) {
        $assert(str_contains($html, '/assets/images/logo/' . $asset) && str_contains($html, 'alt="' . $alt . '"'), 'Logo local accessible : ' . $alt);
    }
    $assert(!str_contains($html, 'checkout-payment__step') && !str_contains($html, 'Vous n’avez rien à régler') && !str_contains($html, 'Aucun montant n’est débité'), 'Éléments refusés retirés.');
    $assert(strpos($html, 'checkout-payment-summary') < strpos($html, 'checkout-payment-delivery'), 'Ordre DOM mobile : résumé avant livraison.');
    $assert($order['taux_usd_cdf'] === '2450.0000', 'Snapshot inchangé.');
    $paymentMethod = ['label' => 'Méthode sans asset', 'icon' => 'wallet'];
    ob_start(); require $root . '/App/views/components/payment-method.php'; $missingLogo = (string) ob_get_clean();
    $assert(!str_contains($missingLogo, '<img') && str_contains($missingLogo, 'data-lucide="wallet"') && str_contains($missingLogo, 'aria-disabled="true"'), 'Absence de logo : icône de remplacement, méthode inactive.');
    $order['statut'] = 'expiree'; $order['reservation_remaining_seconds'] = -10; $lines[0]['image'] = null;
    ob_start(); require $root . '/App/views/checkout/payment.php'; $expired = (string) ob_get_clean();
    $assert(str_contains($expired, 'Réservation expirée') && !str_contains($expired, 'id="payment-method-title"') && str_contains($expired, 'checkout-product__placeholder'), 'Commande expirée : pas de paiement, fallback visible.');
    $db->exec('DELETE FROM ligne_panier');
    $db->exec('DELETE FROM variante_produit WHERE id_produit=1');
    $db->exec('DELETE FROM produit WHERE id_produit=1');
    $assert((int) $db->query('SELECT COUNT(*) FROM image_produit')->fetchColumn() === 0, 'Suppression en cascade.');
    echo "PRODUCT_IMAGES_OK (migration, contraintes, galerie, chemins, fallback, étape 2 active/expirée).\n";
} finally {
    $db->exec('DROP DATABASE IF EXISTS `' . $name . '`');
}
