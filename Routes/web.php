<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\HomeController;
use App\Controllers\FavoriteController;
use App\Controllers\ProductController;

// Réunit ici les contrôleurs déjà configurés par le point d'entrée.
$accountController = new AccountController();
$authController = new AuthController($authService, $request, $loginThrottleService);
$cartController = new CartController($cartService, $request);
$categoryController = new CategoryController($categoryService, $request);
$homeController = new HomeController($productService);
$favoriteController = new FavoriteController();
$productController = new ProductController($productService, $request);

// Chaque chemin public pointe vers une seule action de contrôleur.
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
    '/api/navigation/categories' => [$categoryController, 'navigation'],
    '/api/navigation/highlights' => [$categoryController, 'highlights'],
    '/api/panier' => [$cartController, 'content'],
    '/api/favoris' => [$productController, 'favorites'],
    '/api/recherche' => [$productController, 'suggestions'],
    '/api/produit/apercu' => [$productController, 'quickView'],
];
