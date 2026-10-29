<?php $escape=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<section class="container admin-orders">
    <header><h1>Commandes</h1><p>Consultez les commandes, leur livraison et les règlements à recevoir.</p></header>
    <form class="admin-orders__filters" method="get" action="/admin/commandes" data-admin-orders-filter>
        <label><span id="admin-state-label" data-select-label>État de la commande</span><select id="admin-state" name="etat" data-select-control><?php foreach($states as $value=>$label): ?><option value="<?= $escape($value) ?>"<?= $state===$value?' selected':'' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
        <label class="admin-orders__check"><input type="checkbox" name="encaisser" value="1"<?= $cash?' checked':'' ?>><span>À encaisser</span></label>
        <noscript><?php $button=['label'=>'Appliquer les filtres','type'=>'submit','variant'=>'secondary'];require dirname(__DIR__).'/components/button.php'; ?></noscript>
    </form>
    <?php if(!$orders): ?><p class="admin-order-card">Aucune commande pour cette sélection. <a href="/admin/commandes">Voir toutes les commandes</a></p><?php endif; ?>
    <div class="admin-orders__list"><?php foreach($orders as $order): ?><article class="admin-order-card">
        <header><div><h2><?= $escape($order['number']) ?></h2><small><?= $escape($order['date']) ?></small></div><strong><?= $escape($order['total']) ?></strong></header>
        <?php if($order['cdf']): ?><p class="admin-orders__muted"><?= $escape($order['cdf']) ?></p><?php endif; ?>
        <p><?= $escape(trim(($order['row']['client_prenom']??'').' '.($order['row']['client_nom']??''))?:'Compte indisponible') ?></p>
        <dl class="admin-orders__facts"><div><dt>Commande</dt><dd><?= $escape($order['status']) ?></dd></div><div><dt>Paiement</dt><dd><?= $escape($order['mode']) ?> · <?= $escape($order['paymentStatus']) ?></dd></div><div><dt>Livraison</dt><dd><?= $escape($order['deliveryStatus']) ?></dd></div></dl>
        <a class="btn btn-outline" href="/admin/commande?<?= $escape(http_build_query(['reference'=>$order['number']])) ?>" aria-label="Voir la commande <?= $escape($order['number']) ?>">Voir</a>
    </article><?php endforeach; ?></div>
    <?php if($page>1||$hasNext): ?><nav class="admin-orders__pagination" aria-label="Pages des commandes">
        <?php if($page>1): ?><a href="?<?= $escape(http_build_query($filters+['page'=>$page-1])) ?>">Page précédente</a><?php endif; ?><span>Page <?= $page ?></span><?php if($hasNext): ?><a href="?<?= $escape(http_build_query($filters+['page'=>$page+1])) ?>">Page suivante</a><?php endif; ?>
    </nav><?php endif; ?>
</section>
