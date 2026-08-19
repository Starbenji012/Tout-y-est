<?php

$productSection = [
    'id' => 'promotions-page-title',
    'class' => 'product-section--promotions product-section--shop',
    'products' => $products ?? [],
    'header' => [
        'headingLevel' => 1,
        'badge' => ['label' => 'Offres du moment', 'variant' => 'promotion'],
        'title' => 'Toutes les promotions',
        'description' => 'Retrouvez les offres disponibles et profitez des meilleurs prix de la boutique.',
    ],
    'emptyState' => [
        'title' => 'Aucune promotion actuellement',
        'text' => 'De nouvelles offres seront proposées prochainement.',
        'action' => ['label' => 'Voir la boutique', 'variant' => 'secondary', 'href' => '/boutique'],
    ],
];

require dirname(__DIR__) . '/components/product-section.php';
