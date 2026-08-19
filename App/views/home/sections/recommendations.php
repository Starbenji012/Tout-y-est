<?php

$productSection = [
    'id' => 'recommendations-title',
    'class' => 'product-section--recommendations',
    'products' => $recommendedProducts ?? [],
    'header' => [
        'badge' => ['label' => 'Sélection pour vous', 'variant' => 'popular'],
        'title' => 'Vous pourriez aimer',
        'description' => 'Une sélection de produits appréciés, pensée pour vous aider à trouver rapidement le bon choix.',
        'action' => ['label' => 'Voir toute la sélection', 'variant' => 'outline', 'href' => '/boutique', 'icon' => 'arrow-right'],
    ],
    'emptyState' => [
        'title' => 'La sélection arrive bientôt',
        'text' => 'Nous préparons de nouvelles recommandations pour vous.',
    ],
];

require dirname(__DIR__, 2) . '/components/product-section.php';
