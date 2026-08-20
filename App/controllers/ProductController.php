<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly Request $request,
    ) {
    }

    public function index(): void
    {
        $catalog = $this->productService->searchCatalog($this->request->queryParameters());

        $this->render('shop/index', [
            'title' => 'Boutique | Tout y est',
            'metaDescription' => 'Explorez tous les produits disponibles chez Tout y est.',
            'activePage' => 'shop',
            'pageLibraries' => ['aos', 'gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js', '/assets/js/shop.js'],
            'products' => $catalog['products'],
            'productCount' => $catalog['count'],
            'currentPage' => $catalog['page'],
            'totalPages' => $catalog['totalPages'],
            'catalogCategories' => $catalog['categories'],
            'activeFilters' => $catalog['filters'],
        ]);
    }

    public function promotions(): void
    {
        $this->render('shop/promotions', [
            'title' => 'Promotions | Tout y est',
            'metaDescription' => 'Découvrez les promotions disponibles chez Tout y est.',
            'activePage' => 'promotions',
            'pageLibraries' => ['aos', 'gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js'],
            'products' => $this->productService->getPromotions(),
        ]);
    }

    public function show(): void
    {
        $product = $this->productService->getProductDetails($this->request->queryInteger('id'));

        if ($product === null) {
            http_response_code(404);
            $this->render('errors/404', [
                'title' => 'Produit introuvable | Tout y est',
                'metaDescription' => 'Le produit demandé est introuvable.',
                'activePage' => 'shop',
            ]);

            return;
        }

        $this->render('products/show', [
            'title' => $product['name'] . ' | Tout y est',
            'metaDescription' => $product['description'],
            'activePage' => 'shop',
            'pageLibraries' => ['aos', 'gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/product.css'],
            'pageScripts' => ['/assets/js/product-section.js', '/assets/js/product.js'],
            'product' => $product,
            'relatedProducts' => $this->productService->getRelatedProducts($product),
        ]);
    }

    public function catalog(): void
    {
        $catalog = $this->productService->searchCatalog($this->request->queryParameters());
        $html = $this->renderPartial('components/product-results', [
            'productResults' => [
                'products' => $catalog['products'],
                'emptyState' => $this->catalogEmptyState(),
                'pagination' => [
                    'current' => $catalog['page'],
                    'total' => $catalog['totalPages'],
                    'url' => '/boutique?page=%d',
                ],
            ],
        ]);

        Response::json([
            'html' => $html,
            'count' => $catalog['count'],
            'page' => $catalog['page'],
            'totalPages' => $catalog['totalPages'],
        ]);
    }

    public function quickView(): void
    {
        $product = $this->productService->findProduct($this->request->queryInteger('id'));

        if ($product === null) {
            Response::json(['message' => 'Produit introuvable.'], 404);

            return;
        }

        Response::json([
            'html' => $this->renderPartial('components/quick-view', [
                'product' => $product,
                'productOptions' => $this->productService->getProductOptions($product),
            ]),
        ]);
    }

    private function catalogEmptyState(): array
    {
        return [
            'title' => 'Aucun produit trouvé',
            'text' => 'Essayez de modifier votre recherche ou vos filtres.',
            'action' => ['label' => 'Réinitialiser les filtres', 'variant' => 'secondary', 'href' => '/boutique'],
        ];
    }
}
