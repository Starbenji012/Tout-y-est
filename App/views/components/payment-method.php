<?php
// Présentation uniquement : aucun moyen n'est sélectionnable ou connecté à un provider.
$methodLabel = (string) ($paymentMethod['label'] ?? 'Mode de paiement');
$methodIcon = (string) ($paymentMethod['icon'] ?? 'wallet');
// Liste locale explicite : aucun chemin externe ou fourni par le navigateur.
$methodLogo = ['Orange Money' => '/assets/images/logo/orange_money.png', 'Illicocash' => '/assets/images/logo/illicocash.png'][$methodLabel] ?? null;
$hasMethodLogo = $methodLogo !== null && is_file(dirname(__DIR__, 3) . '/Public' . $methodLogo);
?>
<div class="payment-method" aria-disabled="true">
    <span class="payment-method__icon<?= $hasMethodLogo ? ' payment-method__icon--logo' : '' ?>">
        <?php if ($hasMethodLogo): ?><img src="<?= $escape($methodLogo) ?>" alt="<?= $escape($methodLabel) ?>" width="72" height="48" decoding="async">
        <?php else: ?><i data-lucide="<?= $escape($methodIcon) ?>" aria-hidden="true"></i><?php endif; ?>
    </span>
    <span class="payment-method__information"><strong><?= $escape($methodLabel) ?></strong><small>Indisponible pour le moment</small></span>
    <span class="payment-method__indicator" aria-hidden="true"></span>
</div>
