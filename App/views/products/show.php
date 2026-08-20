<?php

$productName = (string) $product['name'];
$productStock = (int) ($product['stock'] ?? 0);
$productGallery = $product['gallery'] ?? [['src' => $product['image'], 'alt' => $product['alt']]];
$productCharacteristics = $product['characteristics'] ?? [];
$productReviews = $product['reviewItems'] ?? [];
$breadcrumb = ['items' => [
    ['label' => 'Accueil', 'href' => '/'],
    ['label' => 'Boutique', 'href' => '/boutique'],
    ['label' => $productName],
]];
?>

<section class="product-detail" aria-labelledby="product-title" data-product-detail>
    <div class="container">
        <?php require dirname(__DIR__) . '/components/breadcrumb.php'; ?>

        <div class="product-detail__layout" data-product-card data-product-id="<?= (int) $product['id'] ?>">
            <div class="product-detail__media" data-motion="side">
                <div class="product-detail__image-container">
                    <img data-gallery-main src="<?= htmlspecialchars((string) $productGallery[0]['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $productGallery[0]['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" width="<?= (int) $product['width'] ?>" height="<?= (int) $product['height'] ?>" fetchpriority="high">
                    <button class="product-detail__zoom" type="button" aria-label="Agrandir l’image" aria-pressed="false" data-product-zoom><i data-lucide="zoom-in" aria-hidden="true"></i></button>
                </div>
                <?php if (count($productGallery) > 1): ?>
                    <div class="product-detail__thumbnails" aria-label="Images du produit">
                        <?php foreach ($productGallery as $imageIndex => $image): ?>
                            <button class="product-detail__thumbnail<?= $imageIndex === 0 ? ' is-active' : '' ?>" type="button" aria-label="Afficher l’image <?= $imageIndex + 1 ?>" aria-pressed="<?= $imageIndex === 0 ? 'true' : 'false' ?>" data-gallery-thumbnail data-image-src="<?= htmlspecialchars((string) $image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-image-alt="<?= htmlspecialchars((string) $image['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><img src="<?= htmlspecialchars((string) $image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" width="112" height="112" loading="lazy"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail__information" data-motion="section">
                <div class="product-detail__eyebrow">
                    <span><?= htmlspecialchars((string) $product['category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php if (!empty($product['badge'])): ?>
                        <?php $badge = ['label' => (string) $product['badge'], 'variant' => (string) ($product['badgeVariant'] ?? 'new')]; require dirname(__DIR__) . '/components/badge.php'; ?>
                    <?php endif; ?>
                </div>
                <h1 id="product-title"><?= htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                <div class="product-detail__rating" aria-label="<?= htmlspecialchars((string) $product['rating'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> sur 5, <?= (int) $product['reviews'] ?> avis">
                    <i data-lucide="star" aria-hidden="true"></i><strong><?= htmlspecialchars((string) $product['rating'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><span><?= (int) $product['reviews'] ?> avis</span>
                </div>
                <p class="product-detail__description"><?= htmlspecialchars((string) $product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <div class="product-detail__price-container">
                    <strong><?= htmlspecialchars((string) $product['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                    <?php if (!empty($product['oldPrice'])): ?><del><?= htmlspecialchars((string) $product['oldPrice'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></del><?php endif; ?>
                </div>
                <p class="product-detail__availability<?= $productStock === 0 ? ' is-unavailable' : '' ?>"><i data-lucide="<?= $productStock > 0 ? 'circle-check' : 'circle-x' ?>" aria-hidden="true"></i><?= $productStock > 0 ? 'Disponible' : 'Rupture de stock' ?></p>

                <div class="product-detail__purchase-container">
                    <div class="quantity-control" role="group" aria-label="Quantité">
                        <button type="button" aria-label="Diminuer la quantité" data-quantity-change="-1"><i data-lucide="minus" aria-hidden="true"></i></button>
                        <input type="number" value="1" min="1" max="<?= max(1, $productStock) ?>" aria-label="Quantité du produit" data-quantity-input>
                        <button type="button" aria-label="Augmenter la quantité" data-quantity-change="1"><i data-lucide="plus" aria-hidden="true"></i></button>
                    </div>
                    <?php $button = ['label' => 'Ajouter au panier', 'variant' => 'primary', 'icon' => 'shopping-cart', 'iconPosition' => 'start', 'attributes' => ['data-product-action' => 'cart', 'data-product-name' => $productName, 'disabled' => $productStock === 0]]; require dirname(__DIR__) . '/components/button.php'; ?>
                    <button class="btn btn-outline" type="button" data-product-action="favorite" aria-pressed="false"><i data-lucide="heart" aria-hidden="true"></i>Favoris</button>
                </div>

                <div class="product-detail__assurances">
                    <span><i data-lucide="truck" aria-hidden="true"></i>Livraison suivie</span>
                    <span><i data-lucide="shield-check" aria-hidden="true"></i>Paiement sécurisé</span>
                    <span><i data-lucide="rotate-ccw" aria-hidden="true"></i>Retour simplifié</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="product-content" aria-label="Informations détaillées">
    <div class="container product-content__container">
        <article class="product-content__panel" data-motion="section">
            <span class="section-badge">Le produit</span>
            <h2>Description détaillée</h2>
            <p><?= htmlspecialchars((string) $product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <p>Notre sélection privilégie des produits utiles, actuels et présentés avec des informations claires pour vous aider à choisir sereinement.</p>
        </article>

        <article class="product-content__panel" data-motion="section">
            <span class="section-badge">Informations</span>
            <h2>Caractéristiques</h2>
            <?php if ($productCharacteristics !== []): ?>
                <dl class="product-specifications">
                    <?php foreach ($productCharacteristics as $characteristic): ?>
                        <div><dt><?= htmlspecialchars((string) $characteristic['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) $characteristic['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <p>Les caractéristiques détaillées seront ajoutées par le propriétaire.</p>
            <?php endif; ?>
        </article>

        <div class="product-content__services" data-motion="section">
            <details open><summary><i data-lucide="truck" aria-hidden="true"></i>Livraison</summary><p>Le délai et le tarif exacts seront confirmés selon votre adresse au moment de la commande.</p></details>
            <details><summary><i data-lucide="rotate-ccw" aria-hidden="true"></i>Retours</summary><p>Les conditions applicables seront présentées clairement avant la validation de votre achat.</p></details>
            <details><summary><i data-lucide="shield-check" aria-hidden="true"></i>Garantie</summary><p>Les informations de garantie propres à ce produit seront précisées avec les données du propriétaire.</p></details>
        </div>

        <article class="product-content__panel product-content__faq" data-motion="section">
            <span class="section-badge">Besoin d’aide ?</span>
            <h2>Questions fréquentes</h2>
            <details><summary>Comment connaître la disponibilité réelle ?</summary><p>L’état du stock affiché provient des variantes enregistrées dans la boutique.</p></details>
            <details><summary>Quand vais-je recevoir ma commande ?</summary><p>Une estimation adaptée à votre adresse sera communiquée pendant le parcours de commande.</p></details>
            <details><summary>Puis-je demander plus d’informations ?</summary><p>Oui, le service client pourra vous accompagner avant votre décision d’achat.</p></details>
        </article>

        <article class="product-content__panel product-reviews" id="product-reviews" data-motion="section">
            <span class="section-badge">Expériences clients</span>
            <h2>Avis clients</h2>
            <p class="product-reviews__summary"><i data-lucide="star" aria-hidden="true"></i><strong><?= htmlspecialchars((string) $product['rating'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/5</strong><span>sur <?= (int) $product['reviews'] ?> avis</span></p>
            <?php if ($productReviews !== []): ?>
                <div class="product-reviews__list">
                    <?php foreach ($productReviews as $review): ?>
                        <?php $reviewName = (string) $review['nom']; $reviewInitial = preg_match('/^./u', $reviewName, $reviewMatch) ? $reviewMatch[0] : ''; ?>
                        <blockquote><div><strong><?= htmlspecialchars(trim((string) $review['prenom'] . ' ' . $reviewInitial) . '.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><span><?= (int) $review['note'] ?>/5</span></div><p><?= htmlspecialchars((string) $review['commentaire'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p></blockquote>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="product-reviews__empty">Aucun avis détaillé n’est encore publié pour ce produit.</p>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php if (($relatedProducts ?? []) !== []): ?>
    <?php
    $productSection = [
        'id' => 'related-products-title',
        'class' => 'product-section--recommendations',
        'products' => $relatedProducts,
        'header' => [
            'badge' => ['label' => 'À découvrir', 'variant' => 'popular'],
            'title' => 'Dans le même univers',
            'description' => 'Complétez votre découverte avec cette sélection cohérente.',
            'action' => ['label' => 'Voir la boutique', 'variant' => 'outline', 'href' => '/boutique', 'icon' => 'arrow-right'],
        ],
    ];
    require dirname(__DIR__) . '/components/product-section.php';
    ?>
<?php endif; ?>

<?php unset($product, $relatedProducts, $productName, $productStock, $productGallery, $productCharacteristics, $productReviews, $breadcrumb, $imageIndex, $image, $characteristic, $review, $reviewName, $reviewInitial, $reviewMatch, $badge, $button, $productSection); ?>
