<?php

$productSection = [
    'id' => 'catalog-preview-title',
    'class' => 'product-section--catalog',
    'products' => $catalogPreviewProducts ?? [],
    'header' => [
        'badge' => ['label' => 'Toute la sélection', 'variant' => 'new'],
        'title' => 'Encore plus à découvrir',
        'description' => 'Parcourez la boutique et retrouvez l’ensemble de nos catégories au même endroit.',
        'action' => ['label' => 'Voir toute la boutique', 'variant' => 'primary', 'href' => '/boutique', 'icon' => 'arrow-right'],
    ],
    'emptyState' => [
        'title' => 'Le catalogue est en préparation',
        'text' => 'Les produits seront disponibles prochainement.',
    ],
];

require dirname(__DIR__, 2) . '/components/product-section.php';
