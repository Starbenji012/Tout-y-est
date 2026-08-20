<?php

$sectionHeader = [
    'id' => 'cart-title',
    'headingLevel' => 1,
    'badge' => ['label' => 'Votre commande', 'variant' => 'popular'],
    'title' => 'Mon panier',
    'description' => 'Vérifiez votre sélection, ajustez les quantités et préparez la suite de votre commande.',
    'action' => ['label' => 'Continuer mes achats', 'variant' => 'secondary', 'href' => '/boutique', 'icon' => 'arrow-right'],
];
?>

<section class="cart-page" aria-labelledby="cart-title" data-cart-page>
    <div class="container cart-page__container">
        <?php require dirname(__DIR__) . '/components/section-header.php'; ?>

        <div class="cart-results" aria-live="polite" aria-busy="true" data-cart-results>
            <div class="cart-results__loader" data-cart-loader>
                <?php $loader = ['label' => 'Chargement de votre panier']; ?>
                <?php require dirname(__DIR__) . '/components/loader.php'; ?>
            </div>
            <div data-cart-content></div>
        </div>

        <noscript>
            <p>JavaScript doit être activé pour afficher le panier enregistré sur cet appareil.</p>
        </noscript>
    </div>
</section>
