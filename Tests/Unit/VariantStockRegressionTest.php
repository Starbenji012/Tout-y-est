<?php

declare(strict_types=1);

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

$database->beginTransaction();

try {
    $database->exec('CREATE TEMPORARY TABLE categorie (id_categorie BIGINT UNSIGNED PRIMARY KEY, nom VARCHAR(100), slug VARCHAR(120), statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE produit (id_produit BIGINT UNSIGNED PRIMARY KEY, id_categorie BIGINT UNSIGNED, nom VARCHAR(180), slug VARCHAR(220), description TEXT, statut VARCHAR(20), date_creation DATETIME)');
    $database->exec('CREATE TEMPORARY TABLE variante_produit (id_variante BIGINT UNSIGNED PRIMARY KEY, id_produit BIGINT UNSIGNED, sku VARCHAR(100), prix_reference DECIMAL(12, 2), stock INT UNSIGNED, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE promotion (id_promotion BIGINT UNSIGNED PRIMARY KEY, pourcentage DECIMAL(5, 2), date_debut DATETIME, date_fin DATETIME, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE beneficier (id_produit BIGINT UNSIGNED, id_promotion BIGINT UNSIGNED)');

    $database->exec("INSERT INTO categorie VALUES (1, 'Test categorie', 'test-categorie', 'actif')");
    $categoryId = 1;

    $suffix = bin2hex(random_bytes(6));
    $productId = 900001;
    $productStatement = $database->prepare(
        'INSERT INTO produit (id_produit, id_categorie, nom, slug, description, statut, date_creation) VALUES (:product_id, :category_id, :name, :slug, :description, :status, NOW())',
    );
    $productStatement->execute([
        'product_id' => $productId,
        'category_id' => $categoryId,
        'name' => 'Test variantes ' . $suffix,
        'slug' => 'test-variantes-' . $suffix,
        'description' => 'Fixture temporaire de regression.',
        'status' => 'actif',
    ]);
    $variantStatement = $database->prepare(
        'INSERT INTO variante_produit (id_variante, id_produit, sku, prix_reference, stock, statut) VALUES (:variant_id, :product_id, :sku, :price, :stock, :status)',
    );
    $variantStatement->execute([
        'variant_id' => 15,
        'product_id' => $productId,
        'sku' => 'TEST-A-' . $suffix,
        'price' => 100,
        'stock' => 2,
        'status' => 'actif',
    ]);
    $variantA = 15;
    $variantStatement->execute([
        'variant_id' => 16,
        'product_id' => $productId,
        'sku' => 'TEST-B-' . $suffix,
        'price' => 150,
        'stock' => 15,
        'status' => 'actif',
    ]);
    $variantB = 16;

    $productModel = new App\Models\Product($database);
    $productService = new App\Services\ProductService($productModel);
    $cartService = new App\Services\CartService($productService);
    $variantAProduct = $cartService->buildCart($variantA . ':5')['items'][0]['product'];
    $variantBProduct = $cartService->buildCart($variantB . ':20')['items'][0]['product'];
    $legacyProduct = $cartService->buildCart($variantA . ':2')['items'][0]['product'];

    if ((int) $variantAProduct['variantId'] !== $variantA || (int) $variantAProduct['stock'] !== 2) {
        throw new RuntimeException('Variante A ou stock incorrect.');
    }
    if ((int) $variantBProduct['variantId'] !== $variantB || (int) $variantBProduct['stock'] !== 15) {
        throw new RuntimeException('Variante B ou stock incorrect.');
    }
    if ((int) $variantAProduct['variantId'] === (int) $variantBProduct['variantId']) {
        throw new RuntimeException('Deux variantes ont ete confondues.');
    }
    if ((int) $legacyProduct['variantId'] !== $variantA || (int) $legacyProduct['stock'] !== 2) {
        throw new RuntimeException('Ancien format id/quantity incompatible.');
    }

    $database->rollBack();
    echo "VARIANT_STOCK_REGRESSION_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
