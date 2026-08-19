<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Services\ProductService;

$productService = new ProductService();

return [
    '/' => [new HomeController($productService), 'index'],
    '/boutique' => [new ProductController($productService), 'index'],
    '/promotions' => [new ProductController($productService), 'promotions'],
];
