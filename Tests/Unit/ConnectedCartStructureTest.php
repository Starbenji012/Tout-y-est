<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/models/Cart.php';
require_once dirname(__DIR__, 2) . '/App/models/Product.php';
require_once dirname(__DIR__, 2) . '/App/services/ProductService.php';
require_once dirname(__DIR__, 2) . '/App/services/CartService.php';

$config = require dirname(__DIR__, 2) . '/Config/database.php';
$database = new PDO(
    'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=' . $config['charset'],
    $config['username'],
    $config['password'],
    $config['options'],
);

try {
    $database->exec('CREATE TEMPORARY TABLE panier (id_panier BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, id_utilisateur BIGINT UNSIGNED NOT NULL UNIQUE, date_creation DATETIME, date_modification DATETIME)');
    $database->exec('CREATE TEMPORARY TABLE ligne_panier (id_ligne_panier BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, id_panier BIGINT UNSIGNED NOT NULL, id_variante BIGINT UNSIGNED NOT NULL, quantite INT UNSIGNED NOT NULL, date_ajout DATETIME, date_modification DATETIME, UNIQUE KEY uq_ligne_panier_variante (id_panier, id_variante))');
    $database->exec('CREATE TEMPORARY TABLE categorie (id_categorie BIGINT UNSIGNED PRIMARY KEY, nom VARCHAR(100), slug VARCHAR(120), statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE produit (id_produit BIGINT UNSIGNED PRIMARY KEY, id_categorie BIGINT UNSIGNED, nom VARCHAR(180), slug VARCHAR(220), description TEXT, statut VARCHAR(20), date_creation DATETIME)');
    $database->exec('CREATE TEMPORARY TABLE variante_produit (id_variante BIGINT UNSIGNED PRIMARY KEY, id_produit BIGINT UNSIGNED, sku VARCHAR(100), prix_reference DECIMAL(12, 2), stock INT UNSIGNED, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE promotion (id_promotion BIGINT UNSIGNED PRIMARY KEY, pourcentage DECIMAL(5, 2), date_debut DATETIME, date_fin DATETIME, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE beneficier (id_produit BIGINT UNSIGNED, id_promotion BIGINT UNSIGNED)');

    $database->exec("INSERT INTO categorie VALUES (1, 'Test', 'test', 'actif')");
    $database->exec("INSERT INTO produit VALUES (1, 1, 'Produit connecté', 'produit-connecte', 'Test', 'actif', NOW())");
    $database->exec("INSERT INTO variante_produit VALUES (15, 1, 'TEST-15', 100, 2, 'actif'), (16, 1, 'TEST-16', 200, 15, 'actif')");
    $database->exec("INSERT INTO promotion VALUES (1, 10, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY), 'actif')");
    $database->exec('INSERT INTO beneficier VALUES (1, 1)');

    $cartModel = new App\Models\Cart($database);
    $productService = new App\Services\ProductService(new App\Models\Product($database));
    $cartService = new App\Services\CartService($productService, $cartModel);

    if (!$cartService->mergeGuestCart(41, '') || $cartModel->lines($cartModel->findOrCreateForUser(41)) !== []) {
        throw new RuntimeException('La fusion d un panier invité vide a échoué.');
    }

    $emptyCart = $cartService->buildConnectedCart(42);
    if ($emptyCart['items'] !== []) {
        throw new RuntimeException('Le panier utilisateur neuf n est pas vide.');
    }

    $cartId = $cartModel->findOrCreateForUser(42);
    $statement = $database->prepare('INSERT INTO ligne_panier (id_panier, id_variante, quantite) VALUES (:cart_id, :variant_id, :quantity)');
    $statement->execute(['cart_id' => $cartId, 'variant_id' => 15, 'quantity' => 2]);
    $statement->execute(['cart_id' => $cartId, 'variant_id' => 16, 'quantity' => 20]);

    $cart = $cartService->buildConnectedCart(42);
    $itemsByVariant = [];
    foreach ($cart['items'] as $item) {
        $itemsByVariant[(int) $item['product']['variantId']] = $item;
    }

    if (count($itemsByVariant) !== 2) {
        throw new RuntimeException('Les deux variantes ne sont pas chargées.');
    }
    if ((int) $itemsByVariant[15]['product']['stock'] !== 2 || (int) $itemsByVariant[15]['quantity'] !== 2) {
        throw new RuntimeException('Stock ou quantité de la variante 15 incorrect.');
    }
    if ((int) $itemsByVariant[16]['product']['stock'] !== 15 || (int) $itemsByVariant[16]['quantity'] !== 15) {
        throw new RuntimeException('Stock ou quantité de la variante 16 incorrect.');
    }
    if ((float) $itemsByVariant[15]['product']['priceValue'] !== 90.0 || (float) $itemsByVariant[16]['product']['priceValue'] !== 180.0) {
        throw new RuntimeException('Promotion ou prix non recalculé.');
    }

    $mergeCartId = $cartModel->findOrCreateForUser(43);
    $statement = $database->prepare('INSERT INTO ligne_panier (id_panier, id_variante, quantite) VALUES (:cart_id, :variant_id, :quantity)');
    $statement->execute(['cart_id' => $mergeCartId, 'variant_id' => 15, 'quantity' => 1]);
    if (!$cartService->mergeGuestCart(43, '15:5,16:1,999:2')) {
        throw new RuntimeException('La fusion du panier invité a échoué.');
    }

    $mergedLines = $cartModel->lines($mergeCartId);
    $mergedByVariant = [];
    foreach ($mergedLines as $line) {
        $mergedByVariant[(int) $line['variant_id']] = (int) $line['quantity'];
    }
    if (($mergedByVariant[15] ?? 0) !== 2 || ($mergedByVariant[16] ?? 0) !== 1 || isset($mergedByVariant[999])) {
        throw new RuntimeException('Règles de fusion incorrectes.');
    }

    $mutationCartId = $cartModel->findOrCreateForUser(44);
    if (!$cartService->mutateConnectedCart(44, 'add', 15, 1)) {
        throw new RuntimeException('Ajout d une variante échoué.');
    }
    if (!$cartService->mutateConnectedCart(44, 'add', 16, 3)) {
        throw new RuntimeException('Ajout de plusieurs variantes échoué.');
    }
    if (!$cartService->mutateConnectedCart(44, 'add', 15, 1)) {
        throw new RuntimeException('Double ajout d une variante échoué.');
    }
    if (!$cartService->mutateConnectedCart(44, 'set', 15, 99)) {
        throw new RuntimeException('Modification de quantité échouée.');
    }

    $mutationLines = [];
    foreach ($cartModel->lines($mutationCartId) as $line) {
        $mutationLines[(int) $line['variant_id']] = (int) $line['quantity'];
    }
    if (($mutationLines[15] ?? 0) !== 2 || ($mutationLines[16] ?? 0) !== 3) {
        throw new RuntimeException('Quantités persistées ou plafond de stock incorrects.');
    }

    $mutationCart = $cartService->buildConnectedCart(44);
    $mutationItems = [];
    foreach ($mutationCart['items'] as $item) {
        $mutationItems[(int) $item['product']['variantId']] = $item;
    }
    if ((float) $mutationItems[15]['product']['priceValue'] !== 90.0 || (int) $mutationItems[15]['product']['stock'] !== 2) {
        throw new RuntimeException('Prix promotionnel ou stock recalculé incorrect.');
    }
    if (!$cartService->mutateConnectedCart(44, 'remove', 16)) {
        throw new RuntimeException('Suppression de variante échouée.');
    }
    foreach ($cartModel->lines($mutationCartId) as $line) {
        if ((int) $line['variant_id'] === 16) {
            throw new RuntimeException('Suppression de variante échouée.');
        }
    }
    if ($cartService->mutateConnectedCart(44, 'add', 999, 1)) {
        throw new RuntimeException('Une variante inexistante a été acceptée.');
    }
    if (!$cartService->mutateConnectedCart(44, 'clear') || $cartModel->lines($mutationCartId) !== []) {
        throw new RuntimeException('Vidage du panier échoué.');
    }

    $guestCart = $cartService->buildCart('15:1');
    if (count($guestCart['items']) !== 1 || (int) $guestCart['items'][0]['quantity'] !== 1) {
        throw new RuntimeException('Le panier visiteur a changé de comportement.');
    }

    $database->exec("UPDATE promotion SET date_fin = DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $database->exec("UPDATE variante_produit SET prix_reference = 125 WHERE id_variante = 15");
    $recalculatedCart = $cartService->buildConnectedCart(43);
    $recalculatedProduct = $recalculatedCart['items'][0]['product'] ?? [];
    if ((float) ($recalculatedProduct['priceValue'] ?? 0) !== 125.0) {
        throw new RuntimeException('Prix après expiration de promotion incorrect.');
    }

    echo "CONNECTED_CART_STRUCTURE_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
