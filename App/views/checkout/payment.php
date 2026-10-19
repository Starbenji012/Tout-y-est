<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// L'écart est calculé par la BDD, dans le même fuseau que l'expiration.
$remaining = max(0, (int) ($order['reservation_remaining_seconds'] ?? 0));
$active = $order['statut'] === 'en_attente_paiement' && $remaining > 0;
// Présentation du snapshot exact, sans conversion ni arrondi flottant.
$rateParts = explode('.', (string) $order['taux_usd_cdf'], 2);
$rateFraction = rtrim($rateParts[1] ?? '', '0');
$rateLabel = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $rateParts[0]) . ($rateFraction !== '' ? ',' . $rateFraction : '');
$status = match ($order['statut']) {
    'en_attente_paiement' => $active ? 'En attente de paiement' : 'Réservation terminée',
    'expiree' => 'Réservation expirée', 'annulee' => 'Commande annulée',
    'payee' => 'Commande payée', default => 'Commande en cours',
};
?>
<section class="checkout-page checkout-payment" aria-labelledby="order-title" data-payment-page data-reservation-seconds="<?= $active ? $remaining : 0 ?>"><div class="container">
    <?php $button = ['label' => 'Retour au panier', 'variant' => 'ghost', 'href' => '/panier', 'icon' => 'arrow-left', 'iconPosition' => 'start']; require dirname(__DIR__) . '/components/button.php'; ?>
    <header class="checkout-payment__heading">
        <span class="checkout-payment__eyebrow">Votre commande, en toute simplicité</span>
        <h1 id="order-title">2. Paiement</h1>
        <p><?= $active ? 'Choisissez votre mode de paiement' : 'Consultez le récapitulatif de votre commande.' ?></p>
        <small>Commande #<?= $escape($order['numero_commande']) ?></small>
    </header>
    <div class="checkout-layout checkout-payment__layout">
        <div class="checkout-information">
            <section class="checkout-panel checkout-reservation" aria-labelledby="reservation-title" data-motion="section">
                <div class="checkout-reservation__icon" aria-hidden="true"><i data-lucide="clock-3"></i></div>
                <div class="checkout-reservation__content">
                <h2 id="reservation-title" data-reservation-title><?= $escape($status) ?></h2>
                <?php if ($active): ?>
                    <p class="checkout-reservation__time" data-reservation-running>Articles réservés pendant <span role="timer" aria-live="off" data-reservation-clock><?= sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60) ?></span></p>
                    <p class="checkout-help" data-reservation-help>Finalisez votre paiement avant la fin de la réservation.</p>
                <?php elseif (in_array($order['statut'], ['expiree', 'en_attente_paiement'], true)): ?>
                    <p>La réservation est terminée. Retrouvez votre panier pour vérifier la disponibilité et préparer une nouvelle commande.</p>
                <?php endif; ?>
                <p role="status" aria-live="polite" data-reservation-notice></p>
                </div>
            </section>
            <?php if ($active): ?><section class="checkout-panel checkout-payment-methods" aria-labelledby="payment-method-title" data-payment-methods data-motion="section" data-motion-delay="80">
                <div class="checkout-payment-methods__heading"><div><h2 id="payment-method-title">Mode de paiement</h2><p class="checkout-help">Les moyens de paiement seront proposés ici.</p></div></div>
                <div class="payment-method-list" role="group" aria-label="Moyens de paiement à venir, tous indisponibles">
                    <?php foreach ([['label' => 'Espèces à la livraison', 'icon' => 'banknote'], ['label' => 'Orange Money', 'icon' => 'smartphone'], ['label' => 'Illicocash', 'icon' => 'wallet']] as $paymentMethod) require dirname(__DIR__) . '/components/payment-method.php'; ?>
                </div>
                <div class="checkout-payment-methods__footer"><i data-lucide="info" aria-hidden="true"></i><p id="payment-unavailable">Aucun moyen n’est encore activé. Aucun paiement ne peut être effectué depuis cet écran.</p></div>
                <button class="btn checkout-payment__submit" type="button" disabled aria-describedby="payment-unavailable">Paiement indisponible</button>
            </section><?php endif; ?>
        </div>
        <aside class="checkout-panel checkout-summary checkout-payment-summary" aria-labelledby="order-summary-title" data-motion="section" data-motion-delay="120">
            <div class="checkout-payment-summary__heading"><h2 id="order-summary-title">Résumé de commande</h2><span><?= array_sum(array_column($lines, 'quantite')) ?> article(s)</span></div>
            <ul class="checkout-products">
                <?php foreach ($lines as $line): ?><li>
                    <div class="checkout-product__media">
                        <?php if (!empty($line['image'])): ?><img src="<?= $escape($line['image']) ?>" alt="<?= $escape($line['nom_produit_snapshot']) ?>" width="96" height="96" loading="lazy" decoding="async">
                        <?php else: ?><span class="checkout-product__placeholder" role="img" aria-label="Image du produit non disponible"><i data-lucide="package" aria-hidden="true"></i></span><?php endif; ?>
                    </div>
                    <div class="checkout-product__information"><strong><?= $escape($line['nom_produit_snapshot']) ?></strong>
                        <small>Quantité : <?= (int) $line['quantite'] ?></small>
                        <span>$<?= $escape($line['prix_unitaire_applique']) ?> / unité</span>
                        <strong>$<?= $escape($line['total_ligne']) ?></strong>
                    </div>
                </li><?php endforeach; ?>
            </ul>
            <dl class="checkout-totals">
                <div><dt>Sous-total</dt><dd>$<?= $escape($order['sous_total']) ?></dd></div>
                <div><dt>Livraison</dt><dd>$<?= $escape($order['frais_livraison']) ?></dd></div>
                <div class="checkout-grand-total"><dt>Total</dt><dd><?= $escape($order['totalFormatted']) ?></dd></div>
            </dl>
            <p class="checkout-cdf"><?= $escape($order['cdfFormatted']) ?></p>
            <p class="checkout-help checkout-payment-summary__rate">1 USD = <?= $escape($rateLabel) ?> FC</p>
            <a class="checkout-payment__return" href="/panier">Retrouver mon panier <i data-lucide="arrow-right" aria-hidden="true"></i></a>
        </aside>
        <section class="checkout-panel checkout-payment-delivery" aria-labelledby="delivery-title" data-motion="section" data-motion-delay="160">
            <h2 id="delivery-title"><i data-lucide="map-pin" aria-hidden="true"></i> Votre livraison</h2>
            <p><?= $escape($delivery['prenom_destinataire_snapshot']) ?> <?= $escape($delivery['nom_destinataire_snapshot']) ?> · <?= $escape($delivery['telephone_snapshot']) ?></p>
            <p><?= $escape($delivery['adresse_snapshot']) ?>, <?= $escape($delivery['commune_snapshot']) ?></p>
            <?php if (!empty($delivery['nom_zone_snapshot'])): ?><p class="checkout-help"><?= $escape($delivery['nom_zone_snapshot']) ?></p><?php endif; ?>
            <?php if (!empty($delivery['repere_snapshot'])): ?><p>Repère : <?= $escape($delivery['repere_snapshot']) ?></p><?php endif; ?>
            <?php if (!empty($delivery['instructions_snapshot'])): ?><p><?= $escape($delivery['instructions_snapshot']) ?></p><?php endif; ?>
        </section>
    </div>
</div></section>
