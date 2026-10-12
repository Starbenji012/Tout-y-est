<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<h2 id="checkout-summary-title">Votre sélection</h2>
<ul class="checkout-products">
    <?php foreach ($cart['items'] as $item): ?>
        <li>
            <div><strong><?= $escape($item['product']['name']) ?></strong>
                <small>Variante : <?= $escape($item['product']['sku'] ?? $item['product']['variantId']) ?></small>
                <small>Quantité : <?= (int) $item['quantity'] ?></small>
            </div>
            <span><?= $escape($item['product']['price']) ?> / unité</span>
        </li>
    <?php endforeach; ?>
</ul>
<dl class="checkout-totals">
    <div><dt>Sous-total</dt><dd><?= $escape($summary['subtotal'] ?? $cart['subtotal']) ?></dd></div>
    <div><dt>Livraison</dt><dd><?= $escape($summary['shipping'] ?? 'À déterminer') ?></dd></div>
    <div><dt><?= isset($summary['total']) ? 'Total estimé' : 'Total hors livraison' ?></dt><dd><?= $escape($summary['total'] ?? $cart['total']) ?></dd></div>
</dl>
<?php if (isset($summary['totalCdf'])): ?><p class="checkout-help">Équivalent : <?= $escape($summary['totalCdf']) ?> — taux serveur <?= $escape($summary['monetarySnapshot']['usd_cdf_rate']) ?> CDF / USD.</p><?php endif; ?>
<p class="checkout-help">Les prix et le stock seront revérifiés avant la commande définitive.</p>
