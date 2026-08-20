<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ProductService;

final class HomeController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Accueil | Tout y est',
            'metaDescription' => 'Découvrez Tout y est, votre boutique en ligne.',
            'activePage' => 'home',
            'pageLibraries' => ['swiper', 'aos', 'gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/hero.css', '/assets/css/product-section.css'],
            'pageScripts' => ['/assets/js/hero.js', '/assets/js/product-section.js'],
            'newProducts' => $this->productService->getNewArrivals(),
            'recommendedProducts' => $this->productService->getRecommendations(),
            'promotionalProducts' => $this->productService->getPromotionPreview(),
            'catalogPreviewProducts' => $this->productService->getCatalogPreview(),
        ]);
    }
}
