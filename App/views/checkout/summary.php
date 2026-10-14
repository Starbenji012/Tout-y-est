<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<h2 id="checkout-summary-title">Votre sélection</h2>
<ul class="checkout-products">
    <?php foreach ($cart['items'] as $item): ?>
        <li>
            <div class="checkout-product__media">
                <?php $thumbnail = (string) ($item['product']['image'] ?? ''); ?>
                <?php if ($thumbnail !== '' && $thumbnail !== '/assets/images/branding/logo-square.png'): ?>
                    <img src="<?= $escape($thumbnail) ?>" alt="<?= $escape($item['product']['name']) ?>" width="96" height="96" loading="lazy" decoding="async">
                <?php else: ?><span class="checkout-product__placeholder" role="img" aria-label="Image du produit non disponible"><i data-lucide="package" aria-hidden="true"></i></span><?php endif; ?>
            </div>
            <div class="checkout-product__information"><strong><?= $escape($item['product']['name']) ?></strong>
                <small>Variante : <?= $escape($item['product']['sku'] ?? $item['product']['variantId']) ?></small>
                <small>Quantité : <?= (int) $item['quantity'] ?></small>
                <span><?= $escape($item['product']['price']) ?> / unité</span>
            </div>
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
