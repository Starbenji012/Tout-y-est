<?php

$productId = (string) ($product['id'] ?? '');
$productName = (string) ($product['name'] ?? 'Produit');
$productUrl = (string) ($product['url'] ?? '/boutique');
?>

<article class="product-card" data-product-card data-product-id="<?= htmlspecialchars($productId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-motion="card" data-motion-delay="<?= (int) ($productCardAnimationDelay ?? 0) ?>">
    <div class="product-card__media">
        <a class="product-card__image-link" href="<?= htmlspecialchars($productUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Voir <?= htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <img
                class="product-card__image"
                src="<?= htmlspecialchars((string) $product['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                alt="<?= htmlspecialchars((string) $product['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                width="<?= (int) $product['width'] ?>"
                height="<?= (int) $product['height'] ?>"
                loading="lazy"
            >
        </a>

        <?php if (!empty($product['badge'])): ?>
            <?php $badge = ['label' => (string) $product['badge'], 'variant' => (string) ($product['badgeVariant'] ?? 'new'), 'class' => 'product-card__badge']; ?>
            <?php require __DIR__ . '/badge.php'; ?>
        <?php endif; ?>

        <div class="product-card__actions" role="group" aria-label="Actions rapides">
            <button class="product-card__action" type="button" data-product-action="favorite" aria-label="Ajouter <?= htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> aux favoris" aria-pressed="false">
                <i data-lucide="heart" aria-hidden="true"></i>
            </button>
            <button class="product-card__action" type="button" data-product-action="quick-view" aria-label="Aperçu rapide de <?= htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <i data-lucide="eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="product-card__information">
        <span class="product-card__category"><?= htmlspecialchars((string) $product['category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <h3 class="product-card__title">
            <a href="<?= htmlspecialchars($productUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($productName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
        </h3>

        <div class="product-card__rating" role="img" aria-label="<?= htmlspecialchars((string) $product['rating'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> sur 5, <?= (int) $product['reviews'] ?> avis">
            <span class="product-card__stars" aria-hidden="true">
                <?php for ($star = 1; $star <= 5; $star++): ?>
                    <i class="<?= $star <= round((float) $product['rating']) ? 'is-active' : '' ?>" data-lucide="star"></i>
                <?php endfor; ?>
            </span>
            <span class="product-card__reviews">(<?= (int) $product['reviews'] ?>)</span>
        </div>

        <div class="product-card__price-container">
            <span class="product-card__price<?= !empty($product['oldPrice']) ? ' product-card__price--sale' : '' ?>"><?= htmlspecialchars((string) $product['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <?php if (!empty($product['oldPrice'])): ?>
                <del class="product-card__old-price"><?= htmlspecialchars((string) $product['oldPrice'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></del>
            <?php endif; ?>
            <?php if (!empty($product['discount'])): ?>
                <?php $badge = ['label' => '-' . (int) $product['discount'] . ' %', 'variant' => 'promotion', 'class' => 'product-card__discount']; ?>
                <?php require __DIR__ . '/badge.php'; ?>
            <?php endif; ?>
        </div>

        <?php
        $button = [
            'label' => 'Ajouter au panier',
            'variant' => 'primary',
            'icon' => 'shopping-cart',
            'iconPosition' => 'start',
            'class' => 'product-card__add',
            'attributes' => ['data-product-action' => 'cart', 'data-product-name' => $productName, 'disabled' => (int) ($product['stock'] ?? 0) === 0],
        ];
        require __DIR__ . '/button.php';
        ?>
    </div>
</article>
