<?php

if (($promotionalProducts ?? []) === []) {
    return;
}

$productSection = [
    'id' => 'promotions-title',
    'class' => 'product-section--promotions',
    'products' => $promotionalProducts,
    'header' => [
        'badge' => ['label' => 'Offres du moment', 'variant' => 'promotion'],
        'title' => 'Profitez de nos promotions',
        'description' => 'Des offres sélectionnées pour vous permettre de profiter davantage de votre budget.',
    ],
    'banner' => [
        'id' => 'promotion-banner-title',
        'title' => 'Des offres choisies pour vous faire plaisir',
        'text' => 'Profitez de réductions sur une sélection de produits, pendant une durée limitée.',
        'action' => [
            'label' => 'Découvrir les offres',
            'variant' => 'primary',
            'href' => '/promotions',
            'icon' => 'arrow-right',
        ],
    ],
    'footerAction' => [
        'label' => 'Voir toutes les promotions',
        'variant' => 'outline',
        'href' => '/promotions',
        'icon' => 'arrow-right',
    ],
];

require dirname(__DIR__, 2) . '/components/product-section.php';
