<?php

$productSection = [
    'id' => 'catalog-preview-title',
    'class' => 'product-section--catalog',
    'products' => $catalogPreviewProducts ?? [],
    'header' => [
        'badge' => ['label' => 'Voir tout', 'variant' => 'popular'],
        'title' => 'Toute la diversité de Tout y est',
        'description' => 'Explorez une sélection variée parmi nos univers mode, technologie, accessoires, maison et loisirs.',
    ],
    'footerAction' => [
        'label' => 'Accéder à toute la boutique',
        'variant' => 'primary',
        'href' => '/boutique',
        'icon' => 'arrow-right',
    ],
    'emptyState' => [
        'title' => 'Le catalogue est en préparation',
        'text' => 'Les produits seront disponibles prochainement.',
    ],
];

require dirname(__DIR__, 2) . '/components/product-section.php';
