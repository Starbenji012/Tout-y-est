<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="account-dashboard" aria-labelledby="order-title"><div class="container account-dashboard__container">
    <div><?php $button = ['label'=>'Retour à mes commandes','href'=>'/compte/commandes','variant'=>'ghost','icon'=>'arrow-left','iconPosition'=>'start']; require dirname(__DIR__).'/components/button.php'; ?></div>
    <header class="customer-order customer-order-header"><div><h1 id="order-title">Votre commande</h1><h2>N° de commande <span><?= $escape($order['number']) ?></span></h2><p><?= $escape($order['date']) ?></p></div><p class="customer-order__status"><?= $escape($order['status']) ?></p></header>
    <div class="customer-order-detail">
      <div class="customer-order-detail__left">
        <section class="customer-order" aria-labelledby="order-products-title" data-motion="section">
            <h2 id="order-products-title">Produits commandés</h2>
            <ul class="customer-order-items"><?php foreach ($lines as $line): ?><li>
                <div class="customer-order-line__media"><?php if (!empty($line['image'])): ?><img src="<?= $escape($line['image']) ?>" alt="<?= $escape($line['name']) ?>" width="96" height="96" loading="lazy"><?php else: ?><span><i data-lucide="image-off" aria-hidden="true"></i>Sans image</span><?php endif; ?></div>
                <div><strong><?= $escape($line['name']) ?></strong><?php if ($line['variant']): ?><p><?= $escape($line['variant']) ?></p><?php endif; ?></div>
                <dl><div><dt>Quantité</dt><dd><?= $line['quantity'] ?></dd></div><div><dt>Prix unitaire</dt><dd><?= $escape($line['unitPrice']) ?></dd></div><div><dt>Total de ligne</dt><dd><?= $escape($line['total']) ?></dd></div></dl>
            </li><?php endforeach; ?></ul>
        </section>
        <section class="customer-order customer-order-detail__delivery" aria-labelledby="order-delivery-title" data-motion="section">
            <h2 id="order-delivery-title">Adresse de livraison de cette commande</h2>
            <?php require dirname(__DIR__).'/components/order-delivery.php'; ?>
        </section>
      </div>
        <aside class="customer-order" aria-labelledby="order-total-title" data-motion="section" data-motion-delay="80">
            <h2 id="order-total-title">Récapitulatif</h2>
            <dl class="customer-order-totals"><div><dt>Sous-total</dt><dd><?= $escape($order['subtotal']) ?></dd></div><div><dt>Livraison</dt><dd><?= $escape($order['shipping']) ?></dd></div><div><dt>Total</dt><dd><strong><?= $escape($order['total']) ?></strong></dd></div></dl>
            <?php if ($order['cdf'] !== null): ?><p class="customer-order__muted"><?= $escape($order['cdf']) ?></p><p class="customer-order__muted">Taux de la commande : 1 USD = <?= $escape($order['rate']) ?> CDF</p><?php endif; ?>
            <div class="customer-order-payment"><h2>Paiement</h2><dl class="customer-order__facts"><div><dt>Mode</dt><dd><?= $escape($order['paymentMode']) ?></dd></div><div><dt>État</dt><dd><?= $escape($order['paymentStatus']) ?></dd></div></dl></div>
        </aside>
    </div>
</div></section>
