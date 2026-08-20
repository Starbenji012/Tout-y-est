<?php

$productSection = [
    'id' => 'shop-title',
    'class' => 'product-section--shop',
    'products' => $products ?? [],
    'header' => [
        'headingLevel' => 1,
        'badge' => ['label' => 'Catalogue', 'variant' => 'popular'],
        'title' => 'Toute la boutique',
        'description' => 'Découvrez tous les produits disponibles et trouvez simplement ce dont vous avez besoin.',
    ],
    'catalog' => [
        'resultCount' => $productCount ?? 0,
        'categories' => $catalogCategories ?? [],
        'filters' => $activeFilters ?? [],
        'statuses' => [
            'promotion' => 'Promotions',
            'new' => 'Nouveautés',
            'limited' => 'Offres limitées',
        ],
        'sortOptions' => [
            'newest' => 'Nouveautés',
            'price-asc' => 'Prix croissant',
            'price-desc' => 'Prix décroissant',
            'popular' => 'Popularité',
            'promotion' => 'Promotions',
        ],
    ],
    'emptyState' => [
        'title' => 'Aucun produit disponible',
        'text' => 'Le catalogue sera enrichi très prochainement.',
        'action' => ['label' => 'Retour à l’accueil', 'variant' => 'secondary', 'href' => '/'],
    ],
    'pagination' => [
        'current' => $currentPage ?? 1,
        'total' => $totalPages ?? 1,
        'url' => '/boutique?page=%d',
    ],
];

require dirname(__DIR__) . '/components/product-section.php';
