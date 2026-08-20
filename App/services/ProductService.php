<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CharacteristicValue;
use App\Models\Product;
use App\Models\Review;

final class ProductService
{
    private ?bool $databaseHasProducts = null;

    public function __construct(
        private readonly ?Product $productModel = null,
        private readonly ?CharacteristicValue $characteristicValueModel = null,
        private readonly ?Review $reviewModel = null,
    ) {
    }

    public function getNewArrivals(): array
    {
        return $this->withBadge(array_slice($this->allProducts(), 0, 8), 'Nouveau', 'new');
    }

    public function getRecommendations(): array
    {
        $products = $this->usesDatabase()
            ? array_slice($this->allProducts('popular'), 0, 4)
            : $this->select([9, 2, 10, 6]);

        return $this->withBadge($products, 'Populaire', 'popular');
    }

    public function getPromotions(): array
    {
        $products = array_filter(
            $this->allProducts(),
            static fn (array $product): bool => $product['oldPrice'] !== null,
        );

        return $this->withBadge(array_values($products), 'Promotion', 'promotion');
    }

    public function getPromotionPreview(): array
    {
        return array_slice($this->getPromotions(), 0, 4);
    }

    public function getCatalogPreview(): array
    {
        return $this->usesDatabase()
            ? array_slice($this->allProducts(), 0, 8)
            : $this->withCatalogBadges($this->select([6, 2, 5, 8, 1, 7, 4, 9]));
    }

    public function getCatalog(): array
    {
        return $this->searchCatalog([])['products'];
    }

    public function searchCatalog(array $parameters): array
    {
        $filters = $this->normalizeFilters($parameters);
        $page = max(1, (int) ($parameters['page'] ?? 1));
        $perPage = 12;

        if ($this->usesDatabase()) {
            $total = $this->productModel->countCatalog($filters);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $rows = $this->productModel->findCatalog($filters, $filters['sort'], $perPage, ($page - 1) * $perPage);
            $products = array_map(fn (array $row): array => $this->mapDatabaseProduct($row), $rows);
        } else {
            $filteredProducts = $this->filterDemoProducts($this->withCatalogBadges($this->demoProducts()), $filters);
            $total = count($filteredProducts);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $products = array_slice($filteredProducts, ($page - 1) * $perPage, $perPage);
        }

        return [
            'products' => $products,
            'count' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'categories' => $this->catalogCategories(),
        ];
    }

    public function findProduct(int $productId): ?array
    {
        if ($productId < 1) {
            return null;
        }

        if ($this->usesDatabase()) {
            $product = $this->productModel->findById($productId);

            return is_array($product) ? $this->mapDatabaseProduct($product) : null;
        }

        foreach ($this->withCatalogBadges($this->demoProducts()) as $product) {
            if ((int) $product['id'] === $productId) {
                return $product;
            }
        }

        return null;
    }

    public function getRelatedProducts(array $product, int $limit = 4): array
    {
        $productId = (int) ($product['id'] ?? 0);
        $category = (string) ($product['category'] ?? '');
        $products = array_values(array_filter(
            $this->allProducts('popular'),
            static fn (array $candidate): bool => (int) $candidate['id'] !== $productId,
        ));

        usort($products, static function (array $first, array $second) use ($category): int {
            $firstMatches = (string) $first['category'] === $category;
            $secondMatches = (string) $second['category'] === $category;

            return $secondMatches <=> $firstMatches;
        });

        return array_slice($products, 0, max(1, $limit));
    }

    public function getProductDetails(int $productId): ?array
    {
        $product = $this->findProduct($productId);

        if ($product === null) {
            return null;
        }

        $product['characteristics'] = $this->productCharacteristics($product);
        $product['reviewItems'] = $this->usesDatabase()
            ? ($this->reviewModel?->findPublishedByProduct($productId) ?? [])
            : [];

        return $product;
    }

    public function getProductOptions(array $product): array
    {
        $options = [];

        foreach ($this->productCharacteristics($product) as $characteristic) {
            $name = $this->normalizeText((string) $characteristic['name']);

            if (str_contains($name, 'couleur') || str_contains($name, 'taille')) {
                $options[(string) $characteristic['name']][] = (string) $characteristic['value'];
            }
        }

        return array_map('array_values', array_map('array_unique', $options));
    }

    public function catalogCategories(): array
    {
        if ($this->usesDatabase()) {
            $categories = [];

            foreach ($this->productModel->categories() as $category) {
                $categories[(string) $category['slug_']] = (string) $category['nom'];
            }

            return $categories;
        }

        $categories = [];

        foreach ($this->demoProducts() as $product) {
            $categories[$this->slugify((string) $product['category'])] = (string) $product['category'];
        }

        asort($categories);

        return $categories;
    }

    private function select(array $ids): array
    {
        $productsById = [];

        foreach ($this->demoProducts() as $product) {
            $productsById[$product['id']] = $product;
        }

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $productsById[$id] ?? null,
            $ids,
        )));
    }

    private function allProducts(string $sort = 'newest'): array
    {
        if (!$this->usesDatabase()) {
            return $this->withCatalogBadges($this->demoProducts());
        }

        $rows = $this->productModel->findCatalog($this->normalizeFilters(['sort' => $sort]), $sort, 100, 0);

        return array_map(fn (array $row): array => $this->mapDatabaseProduct($row), $rows);
    }

    private function usesDatabase(): bool
    {
        if ($this->databaseHasProducts === null) {
            $this->databaseHasProducts = $this->productModel?->hasProducts() ?? false;
        }

        return $this->databaseHasProducts;
    }

    private function normalizeFilters(array $parameters): array
    {
        $categories = is_array($parameters['categories'] ?? null) ? $parameters['categories'] : [];
        $statuses = is_array($parameters['statuses'] ?? null) ? $parameters['statuses'] : [];
        $allowedStatuses = ['promotion', 'new', 'limited'];
        $allowedSorts = ['newest', 'price-asc', 'price-desc', 'popular', 'promotion'];
        $allowedAvailability = ['in-stock', 'out-of-stock'];
        $sort = (string) ($parameters['sort'] ?? 'newest');
        $availability = (string) ($parameters['availability'] ?? '');
        $priceMin = filter_var($parameters['price_min'] ?? null, FILTER_VALIDATE_FLOAT);
        $priceMax = filter_var($parameters['price_max'] ?? null, FILTER_VALIDATE_FLOAT);
        $rating = filter_var($parameters['rating'] ?? 0, FILTER_VALIDATE_INT);

        return [
            'search' => substr(trim((string) ($parameters['q'] ?? '')), 0, 100),
            'categories' => array_values(array_unique(array_slice(array_filter(
                array_map(static fn (mixed $value): string => substr(trim((string) $value), 0, 120), $categories),
            ), 0, 10))),
            'statuses' => array_values(array_intersect($allowedStatuses, $statuses)),
            'priceMin' => $priceMin !== false && $priceMin !== null && $priceMin >= 0 ? (float) $priceMin : null,
            'priceMax' => $priceMax !== false && $priceMax !== null && $priceMax >= 0 ? (float) $priceMax : null,
            'availability' => in_array($availability, $allowedAvailability, true) ? $availability : '',
            'rating' => $rating !== false ? min(5, max(0, (int) $rating)) : 0,
            'sort' => in_array($sort, $allowedSorts, true) ? $sort : 'newest',
        ];
    }

    private function mapDatabaseProduct(array $product): array
    {
        $basePrice = (float) $product['prix_base'];
        $discount = max(0, min(100, (float) ($product['reduction'] ?? 0)));
        $currentPrice = $discount > 0 ? $basePrice * (1 - ($discount / 100)) : $basePrice;
        $stock = (int) ($product['stock'] ?? 0);
        $badge = $this->databaseBadge($product, $discount, $stock);
        $image = trim((string) ($product['image'] ?? ''));

        if ($image === '') {
            $image = '/assets/images/branding/logo-square.png';
        } elseif (!str_starts_with($image, '/')) {
            $image = '/' . ltrim($image, '/');
        }

        return [
            'id' => (int) $product['id_produit'],
            'name' => (string) $product['nom'],
            'description' => trim((string) $product['description']) !== ''
                ? (string) $product['description']
                : 'Un produit sélectionné avec soin pour répondre à vos besoins du quotidien.',
            'category' => (string) $product['categorie'],
            'categorySlug' => (string) $product['categorie_slug'],
            'price' => $this->formatPrice($currentPrice),
            'priceValue' => $currentPrice,
            'oldPrice' => $discount > 0 ? $this->formatPrice($basePrice) : null,
            'discount' => $discount > 0 ? (int) round($discount) : null,
            'rating' => round((float) ($product['note'] ?? 0), 1),
            'reviews' => (int) ($product['avis_count'] ?? 0),
            'stock' => $stock,
            'image' => $image,
            'alt' => (string) $product['nom'],
            'width' => 1000,
            'height' => 1000,
            'url' => '/produit?id=' . (int) $product['id_produit'],
            'gallery' => [[
                'src' => $image,
                'alt' => (string) $product['nom'],
            ]],
        ] + $badge;
    }

    private function productCharacteristics(array $product): array
    {
        if ($this->usesDatabase()) {
            return array_map(static fn (array $characteristic): array => [
                'name' => (string) $characteristic['nom'],
                'type' => (string) $characteristic['type'],
                'value' => (string) $characteristic['valeur'],
            ], $this->characteristicValueModel?->findByProduct((int) $product['id']) ?? []);
        }

        return [
            ['name' => 'Référence', 'type' => 'text', 'value' => 'TYE-' . str_pad((string) $product['id'], 4, '0', STR_PAD_LEFT)],
            ['name' => 'Catégorie', 'type' => 'text', 'value' => (string) $product['category']],
            ['name' => 'Disponibilité', 'type' => 'text', 'value' => (int) $product['stock'] > 0 ? 'En stock' : 'Rupture de stock'],
        ];
    }

    private function databaseBadge(array $product, float $discount, int $stock): array
    {
        if ($discount > 0) {
            return ['badge' => 'Promotion', 'badgeVariant' => 'promotion'];
        }

        if ($stock > 0 && $stock <= 5) {
            return ['badge' => 'Offre limitée', 'badgeVariant' => 'limited'];
        }

        if (strtotime((string) $product['date_creation']) >= strtotime('-30 days')) {
            return ['badge' => 'Nouveau', 'badgeVariant' => 'new'];
        }

        return [];
    }

    private function filterDemoProducts(array $products, array $filters): array
    {
        $products = array_values(array_filter($products, function (array $product) use ($filters): bool {
            $searchableText = $this->normalizeText(implode(' ', [$product['name'], $product['category'], $product['description'] ?? '']));
            $matchesSearch = $filters['search'] === '' || str_contains($searchableText, $this->normalizeText($filters['search']));
            $matchesCategory = $filters['categories'] === [] || in_array($this->slugify($product['category']), $filters['categories'], true);
            $price = $this->priceValue((string) $product['price']);
            $matchesMinimum = $filters['priceMin'] === null || $price >= $filters['priceMin'];
            $matchesMaximum = $filters['priceMax'] === null || $price <= $filters['priceMax'];
            $matchesStatus = $this->matchesDemoStatuses($product, $filters['statuses']);
            $stock = (int) ($product['stock'] ?? (($product['badgeVariant'] ?? '') === 'limited' ? 3 : 10));
            $matchesAvailability = $filters['availability'] === ''
                || ($filters['availability'] === 'in-stock' && $stock > 0)
                || ($filters['availability'] === 'out-of-stock' && $stock === 0);
            $matchesRating = $filters['rating'] === 0 || (float) $product['rating'] >= $filters['rating'];

            return $matchesSearch && $matchesCategory && $matchesMinimum && $matchesMaximum
                && $matchesStatus && $matchesAvailability && $matchesRating;
        }));

        usort($products, function (array $first, array $second) use ($filters): int {
            return match ($filters['sort']) {
                'price-asc' => $this->priceValue($first['price']) <=> $this->priceValue($second['price']),
                'price-desc' => $this->priceValue($second['price']) <=> $this->priceValue($first['price']),
                'popular' => [$second['reviews'], $second['rating']] <=> [$first['reviews'], $first['rating']],
                'promotion' => ($second['discount'] ?? 0) <=> ($first['discount'] ?? 0),
                default => (int) $second['id'] <=> (int) $first['id'],
            };
        });

        return $products;
    }

    private function matchesDemoStatuses(array $product, array $statuses): bool
    {
        if ($statuses === []) {
            return true;
        }

        return (in_array('promotion', $statuses, true) && !empty($product['oldPrice']))
            || (in_array('new', $statuses, true) && ($product['badgeVariant'] ?? '') === 'new')
            || (in_array('limited', $statuses, true) && ($product['badgeVariant'] ?? '') === 'limited');
    }

    private function priceValue(string $price): float
    {
        return (float) preg_replace('/[^0-9.]+/', '', str_replace(',', '.', $price));
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', ' ') . ' FCFA';
    }

    private function slugify(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $this->normalizeText($value)), '-');
    }

    private function normalizeText(string $value): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
    }

    private function withBadge(array $products, string $label, string $variant): array
    {
        return array_map(
            static fn (array $product): array => $product + [
                'badge' => $label,
                'badgeVariant' => $variant,
            ],
            $products,
        );
    }

    private function withCatalogBadges(array $products): array
    {
        $badges = [
            6 => ['badge' => 'Offre limitée', 'badgeVariant' => 'limited'],
            2 => ['badge' => 'Nouveau', 'badgeVariant' => 'new'],
            5 => ['badge' => 'Promotion', 'badgeVariant' => 'promotion'],
            8 => ['badge' => 'Nouveau', 'badgeVariant' => 'new'],
            1 => ['badge' => 'Promotion', 'badgeVariant' => 'promotion'],
        ];

        return array_map(
            static fn (array $product): array => $product + ($badges[$product['id']] ?? []),
            $products,
        );
    }

    private function withDiscounts(array $products): array
    {
        return array_map(
            static function (array $product): array {
                $currentPrice = (int) preg_replace('/\D+/', '', (string) $product['price']);
                $oldPrice = (int) preg_replace('/\D+/', '', (string) ($product['oldPrice'] ?? ''));
                $product['discount'] = $oldPrice > $currentPrice
                    ? (int) round((1 - ($currentPrice / $oldPrice)) * 100)
                    : null;

                return $product;
            },
            $products,
        );
    }

    private function demoProducts(): array
    {
        $products = $this->withDiscounts([
            [
                'id' => 1,
                'name' => 'Montre Signature',
                'category' => 'Accessoires',
                'price' => '32 000 FCFA',
                'oldPrice' => '38 000 FCFA',
                'rating' => 4.8,
                'reviews' => 24,
                'image' => '/assets/images/products/watch.jpg',
                'alt' => 'Montre rectangulaire argentée posée sur une surface réfléchissante',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 2,
                'name' => 'T-shirt Essential',
                'category' => 'Vêtements',
                'price' => '15 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.6,
                'reviews' => 18,
                'image' => '/assets/images/products/tshirt-card.jpg',
                'alt' => 'T-shirts noir et blanc soigneusement pliés',
                'width' => 1400,
                'height' => 934,
                'url' => '/boutique',
            ],
            [
                'id' => 3,
                'name' => 'Casquette Urbaine',
                'category' => 'Accessoires',
                'price' => '12 500 FCFA',
                'oldPrice' => '15 000 FCFA',
                'rating' => 4.9,
                'reviews' => 31,
                'image' => '/assets/images/products/hat.jpg',
                'alt' => 'Sélection de casquettes présentées en boutique',
                'width' => 1400,
                'height' => 933,
                'url' => '/boutique',
            ],
            [
                'id' => 4,
                'name' => 'Costume Élégance',
                'category' => 'Vêtements',
                'price' => '95 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.5,
                'reviews' => 15,
                'image' => '/assets/images/products/suit.jpg',
                'alt' => 'Costume anthracite avec détail doré sur le revers',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 5,
                'name' => 'Ordinateur Portable Pro',
                'category' => 'Informatique',
                'price' => '650 000 FCFA',
                'oldPrice' => '700 000 FCFA',
                'rating' => 4.7,
                'reviews' => 12,
                'image' => '/assets/images/products/laptop.jpg',
                'alt' => 'Ordinateur portable utilisé sur un espace de travail',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 6,
                'name' => 'Téléviseur Smart 4K',
                'category' => 'Électronique',
                'price' => '420 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.4,
                'reviews' => 9,
                'image' => '/assets/images/products/television.jpg',
                'alt' => 'Téléviseur grand écran installé dans un salon moderne',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 7,
                'name' => 'Manette Gaming Sans Fil',
                'category' => 'Gaming',
                'price' => '55 000 FCFA',
                'oldPrice' => '65 000 FCFA',
                'rating' => 4.6,
                'reviews' => 17,
                'image' => '/assets/images/products/gaming-controller.jpg',
                'alt' => 'Manette de jeu sans fil dans une ambiance gaming',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 8,
                'name' => 'Bague Cœur Dorée',
                'category' => 'Bijoux',
                'price' => '18 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.8,
                'reviews' => 27,
                'image' => '/assets/images/products/jewelry.jpg',
                'alt' => 'Bagues dorées serties présentées dans un écrin',
                'width' => 1120,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 9,
                'name' => 'Écouteurs Sans Fil',
                'category' => 'Audio',
                'price' => '28 000 FCFA',
                'oldPrice' => '32 000 FCFA',
                'rating' => 4.7,
                'reviews' => 21,
                'image' => '/assets/images/products/wireless-earbuds.jpg',
                'alt' => 'Sélection d’écouteurs sans fil dans leurs boîtiers',
                'width' => 1400,
                'height' => 788,
                'url' => '/boutique',
            ],
            [
                'id' => 10,
                'name' => 'Casque Audio Confort',
                'category' => 'Audio',
                'price' => '45 000 FCFA',
                'oldPrice' => '52 000 FCFA',
                'rating' => 4.8,
                'reviews' => 19,
                'image' => '/assets/images/products/headphones.jpg',
                'alt' => 'Casque audio beige avec finitions dorées',
                'width' => 1050,
                'height' => 1400,
                'url' => '/boutique',
            ],
        ]);

        return array_map(fn (array $product): array => array_replace($product, [
            'categorySlug' => $this->slugify((string) $product['category']),
            'description' => sprintf(
                '%s a été sélectionné pour sa qualité, son style et son utilité au quotidien.',
                (string) $product['name'],
            ),
            'stock' => (int) $product['id'] === 6 ? 3 : 10,
            'url' => '/produit?id=' . (int) $product['id'],
            'gallery' => array_map(fn (string $image): array => [
                'src' => $image,
                'alt' => (string) $product['alt'],
            ], $this->demoGallery((int) $product['id'], (string) $product['image'])),
        ]), $products);
    }

    private function demoGallery(int $productId, string $mainImage): array
    {
        $alternates = [
            1 => '/assets/images/products/montre.jpg',
            2 => '/assets/images/products/tshirt.jpg',
            3 => '/assets/images/products/chapeau.jpg',
            4 => '/assets/images/products/costume.jpg',
            5 => '/assets/images/products/ordinateur.jpg',
            6 => '/assets/images/products/télévision.jpg',
            7 => '/assets/images/products/gaming.jpg',
            8 => '/assets/images/hero/bijoux.jpg',
            9 => '/assets/images/products/airpood.jpg',
            10 => '/assets/images/products/casque.jpg',
        ];

        return array_values(array_unique([$mainImage, $alternates[$productId] ?? $mainImage]));
    }
}
