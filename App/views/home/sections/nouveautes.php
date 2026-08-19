<?php

$productSection = [
    'id' => 'new-products-title',
    'class' => 'product-section--new',
    'products' => $newProducts ?? [],
    'header' => [
        'badge' => ['label' => 'Derniers arrivages', 'variant' => 'new'],
        'title' => 'Nos nouveautés',
        'description' => 'Découvrez les derniers produits ajoutés à la boutique, sélectionnés pour répondre à vos envies du moment.',
        'action' => ['label' => 'Voir tout', 'variant' => 'secondary', 'href' => '/boutique', 'icon' => 'arrow-right'],
    ],
    'emptyState' => [
        'title' => 'Aucune nouveauté pour le moment',
        'text' => 'Revenez bientôt pour découvrir les prochains arrivages.',
        'action' => ['label' => 'Explorer la boutique', 'variant' => 'secondary', 'href' => '/boutique'],
    ],
];

require dirname(__DIR__, 2) . '/components/product-section.php';
