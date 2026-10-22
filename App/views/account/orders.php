<?php $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
<section class="account-dashboard" aria-labelledby="orders-title"><div class="container account-dashboard__container">
    <div><?php $button = ['label'=>'Retour à mon compte','href'=>'/compte','variant'=>'ghost','icon'=>'arrow-left','iconPosition'=>'start']; require dirname(__DIR__).'/components/button.php'; ?></div>
    <header><h1 id="orders-title">Mes commandes</h1><p>Retrouvez les informations et le règlement de vos commandes.</p></header>
    <form class="customer-orders-filter" method="get" action="/compte/commandes" aria-label="Filtrer les commandes par période" data-orders-filter>
        <label for="orders-period"><span id="orders-period-label" data-select-label>Période</span><select id="orders-period" name="period"><?php foreach (['all'=>'Toutes les commandes','current'=>'Ce mois-ci','previous'=>'Mois précédent','month'=>'Choisir un mois'] as $value=>$label): ?><option value="<?= $value ?>"<?= $period === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label for="orders-month" data-orders-month<?= $period !== 'month' ? ' hidden' : '' ?>><span id="orders-month-label" data-select-label>Mois personnalisé</span><select id="orders-month" name="month" aria-describedby="orders-month-help"<?= $months === [] || $period !== 'month' ? ' disabled' : '' ?>><option value=""<?= $period !== 'month' ? ' selected' : '' ?>><?= $months === [] ? 'Aucun mois disponible' : 'Sélectionnez un mois' ?></option><?php foreach ($months as $value=>$label): ?><option value="<?= $escape($value) ?>"<?= $period === 'month' && $month === $value ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select><small id="orders-month-help">Seuls les mois contenant vos commandes sont proposés.</small></label>
        <noscript><p>Choisissez une période puis appliquez-la. Pour un mois personnalisé, appliquez d’abord « Choisir un mois », puis sélectionnez le mois souhaité.</p><?php $button=['label'=>'Appliquer la période','type'=>'submit','variant'=>'secondary']; require dirname(__DIR__).'/components/button.php'; ?></noscript>
    </form>
    <?php if ($periodError !== null): ?><p role="alert"><?= $escape($periodError) ?></p><?php endif; ?>
    <?php if ($orders === []): ?>
        <?php $emptyState = ['icon'=>'package-open','title'=>$period === 'all' ? 'Aucune commande à afficher' : 'Aucune commande pour cette période.','text'=>$period === 'all' ? 'Vos commandes apparaîtront ici après votre premier achat.' : 'Essayez une autre période ou affichez toutes vos commandes.','action'=>['label'=>$period === 'all' ? 'Découvrir la boutique' : 'Toutes les commandes','href'=>$period === 'all' ? '/boutique' : '/compte/commandes','variant'=>'primary']]; require dirname(__DIR__).'/components/empty-state.php'; ?>
    <?php else: ?><div class="customer-orders" data-motion="section">
        <?php foreach ($orders as $order): ?><article class="customer-order customer-order--compact">
            <header><div><h2>N° de commande <span><?= $escape($order['number']) ?></span></h2><p><?= $escape($order['date']) ?></p></div><span class="customer-order__status"><?= $escape($order['status']) ?></span></header>
            <dl class="customer-order__facts">
                <div><dt>Total</dt><dd><strong><?= $escape($order['total']) ?></strong><?php if ($order['cdf'] !== null): ?><small><?= $escape($order['cdf']) ?></small><?php endif; ?></dd></div>
                <div><dt>Mode de paiement</dt><dd><?= $escape($order['paymentMode']) ?></dd></div>
                <div><dt>État du paiement</dt><dd><?= $escape($order['paymentStatus']) ?></dd></div>
            </dl>
            <?php $button = ['label'=>'Voir la commande','href'=>'/compte/commande?commande='.$order['id'],'variant'=>'outline','icon'=>'arrow-right']; require dirname(__DIR__).'/components/button.php'; ?>
        </article><?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($page > 1 || $hasNext): ?><nav class="customer-orders__pagination" aria-label="Pages de mes commandes">
        <?php if ($page > 1): ?><a class="btn btn-secondary" href="/compte/commandes?<?= $escape(http_build_query($paginationFilters + ['page'=>$page-1])) ?>">Page précédente</a><?php endif; ?>
        <span>Page <?= (int) $page ?></span>
        <?php if ($hasNext): ?><a class="btn btn-secondary" href="/compte/commandes?<?= $escape(http_build_query($paginationFilters + ['page'=>$page+1])) ?>">Page suivante</a><?php endif; ?>
    </nav><?php endif; ?>
</div></section>
