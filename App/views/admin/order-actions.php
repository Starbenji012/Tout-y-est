<?php
// Les actions proviennent du serveur ; aucune liste de statuts modifiables côté client.
$actions=$actions??[];
$actionLabels=['prepare'=>'Commencer la préparation','ship'=>'Mettre en livraison','deliver'=>'Marquer comme livrée','collectCash'=>'Confirmer l’encaissement','cancel'=>'Annuler la commande'];
$actionPaths=['prepare'=>'preparer','ship'=>'mettre-en-livraison','deliver'=>'livrer','collectCash'=>'encaisser-cash','cancel'=>'annuler'];
?>
<p role="status" aria-live="polite" data-order-action-message></p>
<?php if($actions): ?><section class="admin-order-card admin-order-actions" aria-labelledby="order-actions-title">
  <h2 id="order-actions-title">Actions sur la commande</h2>
  <?php foreach($actionLabels as $action=>$label): ?><?php if(isset($actions[$action])): ?>
  <form method="post" action="/api/admin/commandes/<?= $actionPaths[$action] ?>" data-order-action="<?= $action ?>" data-order-reference="<?= $escape($order['number']) ?>">
    <input type="hidden" name="_token" value="<?= $escape(\App\Middleware\CsrfMiddleware::token()) ?>">
    <input type="hidden" name="commande" value="<?= (int)$row['id_commande'] ?>">
    <?php if($action==='ship'): ?><label for="order-courier">Nom du livreur</label><input id="order-courier" name="livreur" required minlength="2" maxlength="120" autocomplete="off"><?php endif; ?>
    <?php if($action==='cancel'): ?><label for="order-cancel-reason">Motif de l’annulation</label><textarea id="order-cancel-reason" name="motif" required minlength="1" maxlength="500" rows="2"></textarea><?php endif; ?>
    <?php if($action==='collectCash'): ?><p>Montant à encaisser : <strong><?= $escape($actions[$action]['montant'].' '.$actions[$action]['devise']) ?></strong></p><?php endif; ?>
    <?php $button=['label'=>$label,'type'=>'submit','variant'=>$action==='cancel'?'ghost':'primary','attributes'=>['disabled'=>true]];require dirname(__DIR__).'/components/button.php'; ?>
  </form>
  <?php endif; ?><?php endforeach; ?>
  <noscript><p>Activez JavaScript pour utiliser les actions avec confirmation et suivi du résultat.</p></noscript>
</section><?php endif; ?>
