<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\ProductController;

$homeController = new HomeController($productService);
$productController = new ProductController($productService, $request);

return [
    '/' => [$homeController, 'index'],
    '/boutique' => [$productController, 'index'],
    '/produit' => [$productController, 'show'],
    '/promotions' => [$productController, 'promotions'],
    '/api/catalogue' => [$productController, 'catalog'],
    '/api/produit/apercu' => [$productController, 'quickView'],
];
