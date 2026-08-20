<?php

$cartConfig = $cart ?? [];
$cartItems = $cartConfig['items'] ?? [];
?>

<?php if ($cartItems === []): ?>
    <?php
    $emptyState = [
        'icon' => 'shopping-bag',
        'title' => 'Votre panier est vide',
        'text' => 'Explorez la boutique et ajoutez les produits qui vous plaisent.',
        'action' => ['label' => 'Découvrir la boutique', 'variant' => 'primary', 'href' => '/boutique'],
    ];
    require __DIR__ . '/empty-state.php';
    ?>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-list-container">
            <div class="cart-list__header">
                <p><strong><?= (int) $cartConfig['count'] ?></strong> article<?= (int) $cartConfig['count'] > 1 ? 's' : '' ?> dans votre panier</p>
                <button type="button" class="btn btn-ghost" data-cart-clear><i data-lucide="trash-2" aria-hidden="true"></i>Vider le panier</button>
            </div>
            <div class="cart-list">
                <?php foreach ($cartItems as $cartItem): ?>
                    <?php require __DIR__ . '/cart-item.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="cart-summary" aria-labelledby="cart-summary-title">
            <h2 id="cart-summary-title">Récapitulatif</h2>
            <dl>
                <div><dt>Sous-total</dt><dd><?= htmlspecialchars((string) $cartConfig['subtotal'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                <div><dt>Livraison</dt><dd>Calculée à l’étape suivante</dd></div>
                <div class="cart-summary__total"><dt>Total provisoire</dt><dd><?= htmlspecialchars((string) $cartConfig['total'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
            </dl>
            <?php $button = ['label' => 'Passer la commande', 'variant' => 'primary', 'icon' => 'arrow-right', 'class' => 'cart-summary__checkout', 'attributes' => ['data-cart-checkout' => true, 'disabled' => !$cartConfig['canCheckout']]]; ?>
            <?php require __DIR__ . '/button.php'; ?>
            <p class="cart-summary__trust"><i data-lucide="shield-check" aria-hidden="true"></i>Paiement sécurisé et informations protégées</p>
        </aside>
    </div>
<?php endif; ?>

<?php unset($cart, $cartConfig, $cartItems, $cartItem, $emptyState, $button); ?>
