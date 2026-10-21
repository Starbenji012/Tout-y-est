<?php
// Seul le cash actif est sélectionnable ; les providers restent indisponibles.
$methodLabel = (string) ($paymentMethod['label'] ?? 'Mode de paiement');
$methodIcon = (string) ($paymentMethod['icon'] ?? 'wallet');
// Liste locale explicite : aucun chemin externe ou fourni par le navigateur.
$methodLogo = ['Orange Money' => '/assets/images/logo/orange_money.png', 'Illicocash' => '/assets/images/logo/illicocash.png'][$methodLabel] ?? null;
$hasMethodLogo = $methodLogo !== null && is_file(dirname(__DIR__, 3) . '/Public' . $methodLogo);
$selectable = $methodLabel === 'Espèces à la livraison' && !empty($cashAvailable);
?>
<?php if ($selectable): ?><label class="payment-method payment-method--available">
<?php else: ?><div class="payment-method" aria-disabled="true"><?php endif; ?>
    <span class="payment-method__icon<?= $hasMethodLogo ? ' payment-method__icon--logo' : '' ?>">
        <?php if ($hasMethodLogo): ?><img src="<?= $escape($methodLogo) ?>" alt="<?= $escape($methodLabel) ?>" width="72" height="48" decoding="async">
        <?php else: ?><i data-lucide="<?= $escape($methodIcon) ?>" aria-hidden="true"></i><?php endif; ?>
    </span>
    <span class="payment-method__information"><strong><?= $escape($methodLabel) ?></strong><small><?= $selectable ? 'À régler lors de la livraison' : 'Indisponible pour le moment' ?></small></span>
    <?php if ($selectable): ?><input type="radio" name="mode" value="cash" required aria-label="Espèces à la livraison">
    <?php else: ?><span class="payment-method__indicator" aria-hidden="true"></span><?php endif; ?>
<?php if ($selectable): ?></label><?php else: ?></div><?php endif; ?>
