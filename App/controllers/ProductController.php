<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ProductService;

final class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(): void
    {
        $this->render('shop/index', [
            'title' => 'Boutique | Tout y est',
            'metaDescription' => 'Explorez tous les produits disponibles chez Tout y est.',
            'activePage' => 'shop',
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js'],
            'products' => $this->productService->getCatalog(),
        ]);
    }

    public function promotions(): void
    {
        $this->render('shop/promotions', [
            'title' => 'Promotions | Tout y est',
            'metaDescription' => 'Découvrez les promotions disponibles chez Tout y est.',
            'activePage' => 'promotions',
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js'],
            'products' => $this->productService->getPromotions(),
        ]);
    }
}
