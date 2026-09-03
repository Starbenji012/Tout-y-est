<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ProductService;

/** Coordonne le catalogue, les fiches produits et leurs réponses dynamiques. */
final class ProductController extends Controller
{
    /** Injecte le service métier et la requête HTTP courante. */
    public function __construct(
        private readonly ProductService $productService,
        private readonly Request $request,
    ) {
    }

    /** Affiche la page Boutique avec les critères présents dans l'URL. */
    public function index(): void
    {
        $catalog = $this->productService->searchCatalog($this->request->queryParameters());

        $this->render('shop/index', [
            'title' => 'Boutique | Tout y est',
            'metaDescription' => 'Explorez tous les produits disponibles chez Tout y est.',
            'activePage' => 'shop',
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js', '/assets/js/shop.js'],
            'products' => $catalog['products'],
            'productCount' => $catalog['count'],
            'currentPage' => $catalog['page'],
            'totalPages' => $catalog['totalPages'],
            'paginationUrl' => $this->catalogPaginationUrl($catalog['filters']),
            'catalogCategories' => $catalog['categories'],
            'catalogFacets' => $catalog['facets'],
            'catalogSearchNotice' => $catalog['searchNotice'],
            'catalogEmptyState' => $this->catalogEmptyState(),
            'activeFilters' => $catalog['filters'],
            'catalogBreadcrumb' => $this->catalogBreadcrumb($catalog),
        ]);
    }

    /** Affiche uniquement les produits actuellement en promotion. */
    public function promotions(): void
    {
        $this->render('shop/promotions', [
            'title' => 'Promotions | Tout y est',
            'metaDescription' => 'Découvrez les promotions disponibles chez Tout y est.',
            'activePage' => 'promotions',
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/shop.css'],
            'pageScripts' => ['/assets/js/product-section.js'],
            'products' => $this->productService->getPromotions(),
        ]);
    }

    /** Affiche la fiche détaillée du produit demandé. */
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
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css', '/assets/css/product.css'],
            'pageScripts' => ['/assets/js/product-section.js', '/assets/js/product.js'],
            'product' => $product,
            'relatedProducts' => $this->productService->getRelatedProducts($product),
        ]);
    }

    /** Retourne le catalogue filtré destiné aux mises à jour Fetch API. */
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
                    'url' => $this->catalogPaginationUrl($catalog['filters']),
                ],
            ],
        ]);

        Response::json([
            'html' => $html,
            'breadcrumbHtml' => $this->renderPartial('components/breadcrumb', [
                'breadcrumb' => ['items' => $this->catalogBreadcrumb($catalog)],
            ]),
            'facetsHtml' => $this->renderPartial('components/catalog-context-filters', [
                'catalogContextFilters' => [
                    'facets' => $catalog['facets'],
                    'filters' => $catalog['filters'],
                ],
            ]),
            'searchNotice' => $catalog['searchNotice'],
            'count' => $catalog['count'],
            'page' => $catalog['page'],
            'totalPages' => $catalog['totalPages'],
        ]);
    }

    /** Retourne l'aperçu rapide d'un produit sans charger sa page complète. */
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

    /** Retourne les cartes correspondant aux identifiants favoris reçus. */
    public function favorites(): void
    {
        $rawIds = (string) ($this->request->queryParameters()['ids'] ?? '');
        $products = $this->productService->findProductsByIds(explode(',', $rawIds));
        $html = $this->renderPartial('components/product-results', [
            'productResults' => [
                'products' => $products,
                'emptyState' => [
                    'title' => 'Vos favoris vous attendent',
                    'text' => 'Ajoutez des produits à votre sélection pour les retrouver facilement ici.',
                    'action' => ['label' => 'Découvrir la boutique', 'variant' => 'primary', 'href' => '/boutique'],
                ],
            ],
        ]);

        Response::json([
            'html' => $html,
            'count' => count($products),
            'ids' => array_map(static fn (array $product): int => (int) $product['id'], $products),
        ]);
    }

    /** Retourne les suggestions correspondant au texte saisi dans la recherche. */
    public function suggestions(): void
    {
        $products = $this->productService->searchSuggestions(
            (string) ($this->request->queryParameters()['q'] ?? ''),
        );

        Response::json([
            'suggestions' => array_map(static fn (array $product): array => [
                'id' => (int) $product['id'],
                'name' => (string) $product['name'],
                'category' => (string) $product['category'],
                'price' => (string) $product['price'],
                'image' => (string) $product['image'],
                'alt' => (string) $product['alt'],
                'url' => (string) $product['url'],
            ], $products),
        ]);
    }

    /** Définit le message affiché quand aucun produit ne correspond. */
    private function catalogEmptyState(): array
    {
        return [
            'title' => 'Aucun produit trouvé',
            'text' => 'Essayez de modifier votre recherche ou vos filtres.',
            'action' => ['label' => 'Réinitialiser les filtres', 'variant' => 'secondary', 'href' => '/boutique'],
        ];
    }

    /** Construit un fil d'Ariane adapté au contexte actuel du catalogue. */
    private function catalogBreadcrumb(array $catalog): array
    {
        $items = [
            ['label' => 'Accueil', 'href' => '/'],
            ['label' => 'Boutique'],
        ];
        $filters = $catalog['filters'] ?? [];
        $selectedCategories = $filters['categories'] ?? [];
        $search = trim((string) ($filters['search'] ?? ''));

        if (count($selectedCategories) === 1) {
            $slug = (string) $selectedCategories[0];
            $items[1]['href'] = '/boutique';
            $items[] = ['label' => (string) (($catalog['categories'] ?? [])[$slug] ?? $slug)];
        } elseif ($search !== '') {
            $items[1]['href'] = '/boutique';
            $items[] = ['label' => 'Résultats pour « ' . $search . ' »'];
        }

        return $items;
    }

    /** Conserve les filtres actifs lors de la génération des liens de pagination. */
    private function catalogPaginationUrl(array $filters): string
    {
        $parameters = array_filter([
            'q' => $filters['search'] ?? '',
            'categories' => $filters['categories'] ?? [],
            'statuses' => $filters['statuses'] ?? [],
            'price_min' => $filters['priceMin'] ?? null,
            'price_max' => $filters['priceMax'] ?? null,
            'availability' => $filters['availability'] ?? '',
            'rating' => $filters['rating'] ?? 0,
            'attributes' => $filters['attributes'] ?? [],
            'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [] && $value !== 0);
        $query = http_build_query($parameters);

        return '/boutique?' . ($query !== '' ? $query . '&' : '') . 'page=%d';
    }
}
