<?php

$newProducts = $newProducts ?? [
    [
        'id' => 1,
        'name' => 'Smartphone Essential',
        'category' => 'Téléphonie',
        'price' => '185 000 FCFA',
        'oldPrice' => '210 000 FCFA',
        'rating' => 4.8,
        'reviews' => 24,
        'image' => '/assets/images/products/telephone.jpg',
        'alt' => 'Smartphone blanc présenté sur un fond doré',
        'width' => 1400,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 2,
        'name' => 'Chemise Denim Classic',
        'category' => 'Vêtements',
        'price' => '24 500 FCFA',
        'oldPrice' => null,
        'rating' => 4.6,
        'reviews' => 18,
        'image' => '/assets/images/products/shirts.jpg',
        'alt' => 'Chemise denim bleue sur un cintre',
        'width' => 1400,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 3,
        'name' => 'Sneakers Urban Bordeaux',
        'category' => 'Chaussures',
        'price' => '38 000 FCFA',
        'oldPrice' => '45 000 FCFA',
        'rating' => 4.9,
        'reviews' => 31,
        'image' => '/assets/images/products/shoes.jpg',
        'alt' => 'Chaussure bordeaux sur un fond doré',
        'width' => 960,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 4,
        'name' => 'Jean Coupe Droite',
        'category' => 'Vêtements',
        'price' => '29 500 FCFA',
        'oldPrice' => null,
        'rating' => 4.5,
        'reviews' => 15,
        'image' => '/assets/images/products/jeans.jpg',
        'alt' => 'Collection de jeans bleus sur cintres',
        'width' => 933,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 5,
        'name' => 'Bague Cœur Dorée',
        'category' => 'Bijoux',
        'price' => '18 000 FCFA',
        'oldPrice' => '22 000 FCFA',
        'rating' => 4.7,
        'reviews' => 12,
        'image' => '/assets/images/products/jewelry.jpg',
        'alt' => 'Bagues dorées serties présentées dans un écrin',
        'width' => 1120,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 6,
        'name' => 'Smartphone Élégance',
        'category' => 'Téléphonie',
        'price' => '165 000 FCFA',
        'oldPrice' => null,
        'rating' => 4.4,
        'reviews' => 9,
        'image' => '/assets/images/products/telephone.jpg',
        'alt' => 'Smartphone blanc présenté sur un fond doré',
        'width' => 1400,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 7,
        'name' => 'Surchemise Denim',
        'category' => 'Vêtements',
        'price' => '32 000 FCFA',
        'oldPrice' => '36 000 FCFA',
        'rating' => 4.6,
        'reviews' => 17,
        'image' => '/assets/images/products/shirts.jpg',
        'alt' => 'Surchemise denim bleue sur un cintre',
        'width' => 1400,
        'height' => 1400,
        'url' => '/boutique',
    ],
    [
        'id' => 8,
        'name' => 'Sneakers Quotidiennes',
        'category' => 'Chaussures',
        'price' => '42 000 FCFA',
        'oldPrice' => null,
        'rating' => 4.8,
        'reviews' => 27,
        'image' => '/assets/images/products/shoes.jpg',
        'alt' => 'Sneaker bordeaux sur un fond doré',
        'width' => 960,
        'height' => 1400,
        'url' => '/boutique',
    ],
];
?>

<section class="new-products" aria-labelledby="new-products-title" data-new-products>
    <div class="container new-products__container">
        <?php
        $sectionHeader = [
            'id' => 'new-products-title',
            'badge' => ['label' => 'Derniers arrivages', 'variant' => 'new'],
            'title' => 'Nos nouveautés',
            'description' => 'Découvrez les derniers produits ajoutés à la boutique, sélectionnés pour répondre à vos envies du moment.',
            'action' => ['label' => 'Voir tout', 'variant' => 'secondary', 'href' => '/boutique', 'icon' => 'arrow-right'],
        ];
        require dirname(__DIR__, 2) . '/components/section-header.php';
        ?>

        <?php if ($newProducts !== []): ?>
            <div class="new-products__grid">
                <?php foreach ($newProducts as $product): ?>
                    <?php require dirname(__DIR__, 2) . '/components/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php
            $emptyState = [
                'title' => 'Aucune nouveauté pour le moment',
                'text' => 'Revenez bientôt pour découvrir les prochains arrivages.',
                'action' => ['label' => 'Explorer la boutique', 'variant' => 'secondary', 'href' => '/boutique'],
            ];
            require dirname(__DIR__, 2) . '/components/empty-state.php';
            ?>
        <?php endif; ?>

        <?php if (($newProductsTotalPages ?? 1) > 1): ?>
            <div class="new-products__pagination" data-new-products-pagination>
                <?php
                $pagination = [
                    'current' => $newProductsCurrentPage ?? 1,
                    'total' => $newProductsTotalPages,
                    'url' => '/boutique?page=%d',
                ];
                require dirname(__DIR__, 2) . '/components/pagination.php';
                ?>
            </div>
        <?php endif; ?>
    </div>
</section>
