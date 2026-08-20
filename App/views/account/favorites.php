<?php

$sectionHeader = [
    'id' => 'favorites-title',
    'headingLevel' => 1,
    'badge' => ['label' => 'Votre sélection', 'variant' => 'popular'],
    'title' => 'Mes favoris',
    'description' => 'Gardez sous la main les produits qui vous plaisent et reprenez votre découverte à tout moment.',
    'action' => ['label' => 'Continuer mes achats', 'variant' => 'secondary', 'href' => '/boutique', 'icon' => 'arrow-right'],
];
?>

<section class="product-section product-section--favorites" aria-labelledby="favorites-title" data-product-section data-favorites-page>
    <div class="container product-section__container">
        <?php require dirname(__DIR__) . '/components/section-header.php'; ?>

        <div class="favorites-results" aria-live="polite" aria-busy="true" data-favorites-results>
            <div class="favorites-results__loader" data-favorites-loader>
                <?php $loader = ['label' => 'Chargement de vos favoris']; ?>
                <?php require dirname(__DIR__) . '/components/loader.php'; ?>
            </div>
            <div data-favorites-content></div>
        </div>

        <noscript>
            <p>JavaScript doit être activé pour afficher les favoris enregistrés sur cet appareil.</p>
        </noscript>
    </div>
</section>
