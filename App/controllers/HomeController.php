<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ProductService;

/** Compose la page d'accueil à partir des sélections de produits. */
final class HomeController extends Controller
{
    /** Injecte le service produit utilisé par toutes les sections d'accueil. */
    public function __construct(private readonly ProductService $productService)
    {
    }

    /** Prépare puis affiche le Hero et les différentes sélections de produits. */
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Accueil | Tout y est',
            'metaDescription' => 'Découvrez Tout y est, votre boutique en ligne.',
            'activePage' => 'home',
            'pageLibraries' => ['swiper', 'gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/hero.css', '/assets/css/product-section.css'],
            'pageScripts' => ['/assets/js/hero.js', '/assets/js/product-section.js'],
            'newProducts' => $this->productService->getNewArrivals(),
            'recommendedProducts' => $this->productService->getRecommendations(),
            'promotionalProducts' => $this->productService->getPromotionPreview(),
            'catalogPreviewProducts' => $this->productService->getCatalogPreview(),
        ]);
    }
}
