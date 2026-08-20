<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\HomeController;
use App\Controllers\FavoriteController;
use App\Controllers\ProductController;

$accountController = new AccountController();
$authController = new AuthController($authService, $request);
$cartController = new CartController($cartService, $request);
$homeController = new HomeController($productService);
$favoriteController = new FavoriteController();
$productController = new ProductController($productService, $request);

return [
    '/' => [$homeController, 'index'],
    '/compte' => [$accountController, 'index'],
    '/connexion' => [$authController, 'index'],
    '/deconnexion' => [$authController, 'logout'],
    '/panier' => [$cartController, 'index'],
    '/boutique' => [$productController, 'index'],
    '/produit' => [$productController, 'show'],
    '/promotions' => [$productController, 'promotions'],
    '/favoris' => [$favoriteController, 'index'],
    '/api/catalogue' => [$productController, 'catalog'],
    '/api/panier' => [$cartController, 'content'],
    '/api/favoris' => [$productController, 'favorites'],
    '/api/recherche' => [$productController, 'suggestions'],
    '/api/produit/apercu' => [$productController, 'quickView'],
];
