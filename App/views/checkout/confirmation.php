<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="checkout-page"><div class="container">
    <header class="checkout-payment__heading"><h1>Votre commande est confirmée</h1><p>Merci pour votre confiance.</p>
        <div class="checkout-order-number"><span>N° de commande</span><strong><?= $escape($order['numero_commande']) ?></strong><small>Conservez ce numéro pour le suivi de votre commande et vos échanges avec nous.</small></div>
    </header>
    <div class="checkout-layout">
        <section class="checkout-panel" data-motion="section">
            <h2>Espèces à la livraison</h2>
            <p>Votre commande est enregistrée. Le paiement sera effectué en espèces à la livraison. Aucun montant n’a été encaissé.</p>
            <p>Vos articles sont réservés pour cette commande.</p>
            <?php $button = ['label' => 'Continuer mes achats', 'href' => '/boutique', 'variant' => 'primary']; require dirname(__DIR__) . '/components/button.php'; ?>
        </section>
        <aside class="checkout-panel checkout-summary" data-motion="section" data-motion-delay="80">
            <h2>Total de votre commande</h2><p class="checkout-grand-total"><?= $escape($totalFormatted) ?></p><p class="checkout-cdf"><?= $escape($cdfFormatted) ?></p>
            <h2>Votre livraison</h2>
            <p><?= $escape($delivery['prenom_destinataire_snapshot']) ?> <?= $escape($delivery['nom_destinataire_snapshot']) ?></p>
            <p><?= $escape($delivery['telephone_snapshot']) ?></p>
            <p><?= $escape($delivery['adresse_snapshot']) ?>, <?= $escape($delivery['commune_snapshot']) ?></p>
            <?php if (!empty($delivery['repere_snapshot'])): ?><p>Repère : <?= $escape($delivery['repere_snapshot']) ?></p><?php endif; ?>
        </aside>
    </div>
</div></section>
