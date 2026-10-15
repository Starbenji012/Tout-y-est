<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="checkout-page" aria-labelledby="order-title"><div class="container">
    <a class="btn btn-ghost" href="/panier">Retour au panier</a>
    <h1 id="order-title">2. Paiement</h1>
    <div class="checkout-panel">
        <h2>Commande <?= $escape($order['numero_commande']) ?></h2>
        <p>Statut : <?= $escape($order['statut']) ?></p>
        <p><?= $order['statut'] === 'en_attente_paiement' ? 'Articles réservés jusqu’au' : 'Échéance de réservation' ?> <time><?= $escape($order['reservation_expire_at']) ?></time> (heure du serveur).</p>
        <ul class="checkout-products">
            <?php foreach ($lines as $line): ?><li><div><strong><?= $escape($line['nom_produit_snapshot']) ?></strong><small><?= $escape($line['sku_snapshot']) ?> — <?= $escape($line['description_variante_snapshot']) ?></small><small>Quantité : <?= (int) $line['quantite'] ?></small></div><div>$<?= $escape($line['prix_unitaire_applique']) ?> / unité<br>$<?= $escape($line['total_ligne']) ?></div></li><?php endforeach; ?>
        </ul>
        <dl class="checkout-totals">
            <div><dt>Sous-total</dt><dd>$<?= $escape($order['sous_total']) ?></dd></div>
            <div><dt>Livraison</dt><dd>$<?= $escape($order['frais_livraison']) ?></dd></div>
            <div><dt>Total</dt><dd><?= $escape($order['totalFormatted']) ?> — <?= $escape($order['cdfFormatted']) ?></dd></div>
        </dl>
        <p>Taux figé : <?= $escape($order['taux_usd_cdf']) ?> CDF/USD.</p>
        <p><?= $escape($delivery['prenom_destinataire_snapshot']) ?> <?= $escape($delivery['nom_destinataire_snapshot']) ?> — <?= $escape($delivery['telephone_snapshot']) ?></p>
        <p><?= $escape($delivery['adresse_snapshot']) ?>, <?= $escape($delivery['commune_snapshot']) ?> — <?= $escape($delivery['nom_zone_snapshot']) ?></p>
        <?php if ($delivery['repere_snapshot']): ?><p>Repère : <?= $escape($delivery['repere_snapshot']) ?></p><?php endif; ?>
        <?php if ($delivery['instructions_snapshot']): ?><p><?= $escape($delivery['instructions_snapshot']) ?></p><?php endif; ?>
        <p role="status">Aucun moyen de paiement n’est encore disponible/configuré. Aucun paiement n’a été effectué.</p>
        <?php if ($order['statut'] === 'expiree'): ?><p>La réservation a expiré et le stock a été libéré. Votre panier est conservé.</p><?php endif; ?>
    </div>
</div></section>
