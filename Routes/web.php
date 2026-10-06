<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\EmailVerificationController;
use App\Controllers\HomeController;
use App\Controllers\FavoriteController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProductController;

// Réunit ici les contrôleurs déjà configurés par le point d'entrée.
$accountController = new AccountController($emailVerificationService);
$authController = new AuthController(
    $authService,
    $request,
    $loginThrottleService,
    $cartService,
    $favoriteService,
);
$cartController = new CartController($cartService, $request);
$categoryController = new CategoryController($categoryService, $request);
$homeController = new HomeController($productService);
$favoriteController = new FavoriteController($favoriteService, $productService, $request);
$emailVerificationController = new EmailVerificationController($emailVerificationService, $request);
$passwordResetController = new PasswordResetController($passwordResetService, $request);
$productController = new ProductController($productService, $request);

// Chaque chemin public pointe vers une seule action de contrôleur.
return [
    '/' => [$homeController, 'index'],
    '/compte' => [
        'handler' => [$accountController, 'index'],
        'middleware' => ['auth'],
    ],
    '/connexion' => [
        'handler' => [$authController, 'index'],
        'middleware' => ['guest'],
    ],
    '/deconnexion' => [$authController, 'logout'],
    '/verification-email' => [$emailVerificationController, 'verify'],
    '/verification-email/renvoyer' => [
        'handler' => [$emailVerificationController, 'resend'],
        'middleware' => ['auth'],
    ],
    '/mot-de-passe-oublie' => [$passwordResetController, 'request'],
    '/reinitialiser-mot-de-passe' => [$passwordResetController, 'form'],
    '/reinitialiser-mot-de-passe/appliquer' => [$passwordResetController, 'reset'],
    '/panier' => [$cartController, 'index'],
    '/boutique' => [$productController, 'index'],
    '/produit' => [$productController, 'show'],
    '/promotions' => [$productController, 'promotions'],
    '/favoris' => [$favoriteController, 'index'],
    '/api/catalogue' => [$productController, 'catalog'],
    '/api/navigation/categories' => [$categoryController, 'navigation'],
    '/api/navigation/highlights' => [$categoryController, 'highlights'],
    '/api/panier' => [$cartController, 'content'],
    '/api/panier/mutation' => [
        'handler' => [$cartController, 'mutate'],
        'middleware' => ['auth'],
    ],
    '/api/favoris' => [$favoriteController, 'content'],
    '/api/favoris/state' => [$favoriteController, 'state'],
    '/api/favoris/mutation' => [$favoriteController, 'mutate'],
    '/api/favoris/fusion' => [$favoriteController, 'merge'],
    '/api/recherche' => [$productController, 'suggestions'],
    '/api/produit/apercu' => [$productController, 'quickView'],
];
