<?php

$cartItemConfig = $cartItem ?? [];
$cartItemProduct = $cartItemConfig['product'] ?? [];
$cartItemId = (int) ($cartItemProduct['id'] ?? 0);
$cartItemName = (string) ($cartItemProduct['name'] ?? 'Produit');
$cartItemQuantity = (int) ($cartItemConfig['quantity'] ?? 1);
$cartItemStock = max(0, (int) ($cartItemProduct['stock'] ?? 0));
$cartItemAvailable = (bool) ($cartItemConfig['available'] ?? false);
$cartItemAvailability = $cartItemConfig['availability'] ?? [];
?>

<article class="cart-item<?= $cartItemAvailable ? '' : ' is-unavailable' ?>" data-cart-item data-product-id="<?= $cartItemId ?>" data-motion="card">
    <a class="cart-item__media" href="<?= htmlspecialchars((string) ($cartItemProduct['url'] ?? '/boutique'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars((string) ($cartItemProduct['image'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($cartItemProduct['alt'] ?? $cartItemName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" width="160" height="160" loading="lazy">
    </a>

    <div class="cart-item__information">
        <h2 class="cart-item__title"><a href="<?= htmlspecialchars((string) ($cartItemProduct['url'] ?? '/boutique'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></h2>
        <div class="cart-item__prices">
            <span class="cart-item__unit-price"><?= htmlspecialchars((string) ($cartItemProduct['price'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <?php if (!empty($cartItemProduct['oldPrice'])): ?>
                <del><?= htmlspecialchars((string) $cartItemProduct['oldPrice'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></del>
            <?php endif; ?>
        </div>
        <span class="cart-item__availability is-<?= htmlspecialchars((string) ($cartItemAvailability['state'] ?? 'unavailable'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <i data-lucide="<?= htmlspecialchars((string) ($cartItemAvailability['icon'] ?? 'circle-x'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
            <?= htmlspecialchars((string) ($cartItemAvailability['label'] ?? 'Indisponible'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </span>
    </div>

    <div class="cart-item__quantity">
        <span class="cart-item__label">Quantité</span>
        <div class="quantity-control" role="group" aria-label="Quantité de <?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button type="button" aria-label="Diminuer la quantité" data-cart-quantity-change="-1"<?= !$cartItemAvailable || $cartItemQuantity <= 1 ? ' disabled' : '' ?>><i data-lucide="minus" aria-hidden="true"></i></button>
            <input type="number" min="1" max="<?= max(1, $cartItemStock) ?>" value="<?= $cartItemQuantity ?>" aria-label="Quantité" data-cart-quantity-input<?= $cartItemAvailable ? '' : ' disabled' ?>>
            <button type="button" aria-label="Augmenter la quantité" data-cart-quantity-change="1"<?= !$cartItemAvailable || $cartItemQuantity >= $cartItemStock ? ' disabled' : '' ?>><i data-lucide="plus" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="cart-item__actions">
        <?php if (!$cartItemAvailable): ?>
            <button class="cart-item__wait" type="button" data-cart-wait><i data-lucide="clock-3" aria-hidden="true"></i>Attendre</button>
        <?php endif; ?>
        <button class="cart-item__remove" type="button" aria-label="Retirer <?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> du panier" data-cart-remove><i data-lucide="trash-2" aria-hidden="true"></i><span>Retirer</span></button>
    </div>
</article>

<?php unset($cartItem, $cartItemConfig, $cartItemProduct, $cartItemId, $cartItemName, $cartItemQuantity, $cartItemStock, $cartItemAvailable, $cartItemAvailability); ?>
