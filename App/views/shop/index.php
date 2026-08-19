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
