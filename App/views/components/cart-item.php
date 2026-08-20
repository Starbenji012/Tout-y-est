<?php

$cartItemConfig = $cartItem ?? [];
$cartItemProduct = $cartItemConfig['product'] ?? [];
$cartItemId = (int) ($cartItemProduct['id'] ?? 0);
$cartItemName = (string) ($cartItemProduct['name'] ?? 'Produit');
$cartItemQuantity = (int) ($cartItemConfig['quantity'] ?? 1);
$cartItemStock = max(0, (int) ($cartItemProduct['stock'] ?? 0));
?>

<article class="cart-item" data-cart-item data-product-id="<?= $cartItemId ?>" data-motion="card">
    <a class="cart-item__media" href="<?= htmlspecialchars((string) ($cartItemProduct['url'] ?? '/boutique'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars((string) ($cartItemProduct['image'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($cartItemProduct['alt'] ?? $cartItemName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" width="160" height="160" loading="lazy">
    </a>

    <div class="cart-item__information">
        <span class="cart-item__category"><?= htmlspecialchars((string) ($cartItemProduct['category'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <h2 class="cart-item__title"><a href="<?= htmlspecialchars((string) ($cartItemProduct['url'] ?? '/boutique'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></h2>
        <span class="cart-item__unit-price"><?= htmlspecialchars((string) ($cartItemProduct['price'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php if (!$cartItemConfig['available']): ?><span class="cart-item__availability">Actuellement indisponible</span><?php endif; ?>
    </div>

    <div class="cart-item__quantity">
        <span class="cart-item__label">Quantité</span>
        <div class="quantity-control" role="group" aria-label="Quantité de <?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button type="button" aria-label="Diminuer la quantité" data-cart-quantity-change="-1"><i data-lucide="minus" aria-hidden="true"></i></button>
            <input type="number" min="1" max="<?= max(1, $cartItemStock) ?>" value="<?= $cartItemQuantity ?>" aria-label="Quantité" data-cart-quantity-input>
            <button type="button" aria-label="Augmenter la quantité" data-cart-quantity-change="1"><i data-lucide="plus" aria-hidden="true"></i></button>
        </div>
    </div>

    <strong class="cart-item__total"><?= htmlspecialchars((string) $cartItemConfig['lineTotal'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
    <button class="cart-item__remove" type="button" aria-label="Retirer <?= htmlspecialchars($cartItemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> du panier" data-cart-remove><i data-lucide="trash-2" aria-hidden="true"></i></button>
</article>

<?php unset($cartItem, $cartItemConfig, $cartItemProduct, $cartItemId, $cartItemName, $cartItemQuantity, $cartItemStock); ?>
