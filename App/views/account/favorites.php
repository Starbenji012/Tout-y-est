<?php

$sectionHeader = [
    'id' => 'favorites-title',
    'headingLevel' => 1,
    'badge' => ['label' => 'Votre sélection', 'variant' => 'popular'],
    'title' => 'Mes favoris',
    'description' => 'Gardez sous la main les produits qui vous plaisent et reprenez votre découverte à tout moment.',
];
?>

<section class="product-section product-section--favorites" aria-labelledby="favorites-title" data-product-section data-favorites-page>
    <div class="container product-section__container">
        <div class="page-actions">
            <?php $button = ['label' => 'Retour à la boutique', 'variant' => 'ghost', 'href' => '/boutique', 'icon' => 'arrow-left', 'iconPosition' => 'start']; ?>
            <?php require dirname(__DIR__) . '/components/button.php'; ?>
            <?php $button = ['label' => 'Vider les favoris', 'variant' => 'ghost', 'icon' => 'trash-2', 'iconPosition' => 'start', 'attributes' => ['data-favorites-clear' => true, 'hidden' => true]]; ?>
            <?php require dirname(__DIR__) . '/components/button.php'; ?>
        </div>

        <?php require dirname(__DIR__) . '/components/section-header.php'; ?>

        <div class="favorites-results" aria-live="polite" aria-busy="true" data-favorites-results>
            <div class="favorites-results__loader" data-favorites-loader>
                <?php $loader = ['label' => 'Chargement de vos favoris']; ?>
                <?php require dirname(__DIR__) . '/components/loader.php'; ?>
            </div>
            <div data-favorites-content></div>
        </div>

        <noscript>
            <p>JavaScript doit être activé pour afficher vos favoris.</p>
        </noscript>
    </div>
</section>
