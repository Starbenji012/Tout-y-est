<?php

$quickViewProduct = $product ?? [];
$quickViewName = (string) ($quickViewProduct['name'] ?? 'Produit');
$quickViewId = (int) ($quickViewProduct['id'] ?? 0);
$quickViewStock = (int) ($quickViewProduct['stock'] ?? 0);
$quickViewGallery = $quickViewProduct['gallery'] ?? [[
    'src' => (string) ($quickViewProduct['image'] ?? ''),
    'alt' => (string) ($quickViewProduct['alt'] ?? $quickViewName),
]];
$quickViewOptions = $productOptions ?? [];
?>

<dialog class="quick-view" aria-labelledby="quick-view-title" data-quick-view-dialog>
    <button class="quick-view__close" type="button" aria-label="Fermer l’aperçu" data-quick-view-close><i data-lucide="x" aria-hidden="true"></i></button>
    <div class="quick-view__layout" data-product-card data-product-id="<?= $quickViewId ?>">
        <div class="quick-view__gallery">
            <div class="quick-view__media">
                <img data-gallery-main src="<?= htmlspecialchars((string) $quickViewGallery[0]['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $quickViewGallery[0]['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" width="<?= (int) ($quickViewProduct['width'] ?? 1000) ?>" height="<?= (int) ($quickViewProduct['height'] ?? 1000) ?>">
            </div>
            <?php if (count($quickViewGallery) > 1): ?>
                <div class="quick-view__thumbnails" aria-label="Images du produit">
                    <?php foreach ($quickViewGallery as $imageIndex => $image): ?>
                        <button class="quick-view__thumbnail<?= $imageIndex === 0 ? ' is-active' : '' ?>" type="button" aria-label="Afficher l’image <?= $imageIndex + 1 ?>" aria-pressed="<?= $imageIndex === 0 ? 'true' : 'false' ?>" data-gallery-thumbnail data-image-src="<?= htmlspecialchars((string) $image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-image-alt="<?= htmlspecialchars((string) $image['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <img src="<?= htmlspecialchars((string) $image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" width="96" height="96" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="quick-view__information">
            <div class="quick-view__eyebrow">
                <span class="quick-view__category"><?= htmlspecialchars((string) ($quickViewProduct['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php if (!empty($quickViewProduct['badge'])): ?><?php $badge = ['label' => (string) $quickViewProduct['badge'], 'variant' => (string) ($quickViewProduct['badgeVariant'] ?? 'new')]; require __DIR__ . '/badge.php'; ?><?php endif; ?>
            </div>
            <h2 id="quick-view-title"><?= htmlspecialchars($quickViewName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            <p class="quick-view__rating" aria-label="<?= htmlspecialchars((string) ($quickViewProduct['rating'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> sur 5"><i data-lucide="star" aria-hidden="true"></i><strong><?= htmlspecialchars((string) ($quickViewProduct['rating'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><span>(<?= (int) ($quickViewProduct['reviews'] ?? 0) ?> avis)</span></p>
            <div class="quick-view__price"><strong><?= htmlspecialchars((string) ($quickViewProduct['price'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><?php if (!empty($quickViewProduct['oldPrice'])): ?><del><?= htmlspecialchars((string) $quickViewProduct['oldPrice'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></del><?php endif; ?><?php if (!empty($quickViewProduct['discount'])): ?><span>-<?= (int) $quickViewProduct['discount'] ?> %</span><?php endif; ?></div>
            <p class="quick-view__stock<?= $quickViewStock === 0 ? ' is-unavailable' : '' ?>"><i data-lucide="<?= $quickViewStock > 0 ? 'circle-check' : 'circle-x' ?>" aria-hidden="true"></i><?= $quickViewStock > 0 ? 'Disponible' : 'Rupture de stock' ?></p>

            <?php foreach ($quickViewOptions as $optionName => $optionValues): ?>
                <fieldset class="quick-view__options">
                    <legend><?= htmlspecialchars((string) $optionName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></legend>
                    <div>
                        <?php foreach ($optionValues as $optionIndex => $optionValue): ?>
                            <label><input type="radio" name="quick-option-<?= htmlspecialchars((string) $optionName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $optionIndex === 0 ? ' checked' : '' ?>><span><?= htmlspecialchars((string) $optionValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="quick-view__purchase">
                <div class="quantity-control" role="group" aria-label="Quantité">
                    <button type="button" aria-label="Diminuer la quantité" data-quantity-change="-1"><i data-lucide="minus" aria-hidden="true"></i></button>
                    <input type="number" value="1" min="1" max="<?= max(1, $quickViewStock) ?>" aria-label="Quantité du produit" data-quantity-input>
                    <button type="button" aria-label="Augmenter la quantité" data-quantity-change="1"><i data-lucide="plus" aria-hidden="true"></i></button>
                </div>
                <?php $button = ['label' => 'Ajouter au panier', 'variant' => 'primary', 'icon' => 'shopping-cart', 'iconPosition' => 'start', 'attributes' => ['data-product-action' => 'cart', 'data-product-name' => $quickViewName, 'disabled' => $quickViewStock === 0]]; require __DIR__ . '/button.php'; ?>
            </div>
            <div class="quick-view__actions">
                <button class="btn btn-outline" type="button" data-product-action="favorite" aria-pressed="false"><i data-lucide="heart" aria-hidden="true"></i>Favoris</button>
                <?php $button = ['label' => 'Voir tous les détails', 'variant' => 'ghost', 'href' => (string) ($quickViewProduct['url'] ?? '/boutique'), 'icon' => 'arrow-right']; require __DIR__ . '/button.php'; ?>
            </div>
        </div>
    </div>
</dialog>

<?php unset($product, $productOptions, $quickViewProduct, $quickViewName, $quickViewId, $quickViewStock, $quickViewGallery, $quickViewOptions, $imageIndex, $image, $optionName, $optionValues, $optionIndex, $optionValue, $badge, $button); ?>
