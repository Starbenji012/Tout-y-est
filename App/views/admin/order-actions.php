<?php
// Les actions proviennent du serveur ; aucune liste de statuts modifiables côté client.
$actions=$actions??[];
$actionLabels=['prepare'=>'Préparer la commande','ship'=>'Mettre en livraison','deliver'=>'Marquer comme livrée','collectCash'=>'Confirmer l’encaissement','cancel'=>'Annuler la commande'];
$actionPaths=['prepare'=>'preparer','ship'=>'mettre-en-livraison','deliver'=>'livrer','collectCash'=>'encaisser-cash','cancel'=>'annuler'];
$flash=\App\Core\Session::get('_admin_order_flash');
$flashText=is_array($flash)&&($flash['reference']??null)===$order['number']?($flash['message']??''):'';
if($flashText!=='')\App\Core\Session::remove('_admin_order_flash');
?>
<p role="status" aria-live="polite" data-order-action-message><?= $escape($flashText) ?></p>
<?php if($courierNotice??null): ?><p><?= $escape($courierNotice) ?></p><?php endif; ?>
<?php if($actions): ?><section class="admin-order-card admin-order-actions" aria-labelledby="order-actions-title">
  <h2 id="order-actions-title">Actions sur la commande</h2>
  <?php foreach($actionLabels as $action=>$label): ?><?php if(isset($actions[$action])): ?>
  <form method="post" action="/api/admin/commandes/<?= $actionPaths[$action] ?>" data-order-action="<?= $action ?>" data-order-reference="<?= $escape($order['number']) ?>">
    <input type="hidden" name="_token" value="<?= $escape(\App\Middleware\CsrfMiddleware::token()) ?>">
    <input type="hidden" name="commande" value="<?= (int)$row['id_commande'] ?>">
    <?php if($action==='ship'): ?><label for="order-courier"><span id="order-courier-label" data-select-label>Livreur affecté</span><select id="order-courier" name="id_livreur" required data-select-control><option value="">Choisir un livreur</option><?php foreach($couriers??[] as $courier): ?><option value="<?= (int)$courier['id_livreur'] ?>"><?= $escape($courier['nom']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <?php if($action==='cancel'): ?><label for="order-cancel-reason">Motif de l’annulation</label><textarea id="order-cancel-reason" name="motif" required minlength="1" maxlength="500" rows="2"></textarea><?php endif; ?>
    <?php if($action==='collectCash'): ?><p>Montant à encaisser : <strong><?= $escape($actions[$action]['montant'].' '.$actions[$action]['devise']) ?></strong></p><?php endif; ?>
    <?php $button=['label'=>$label,'type'=>'submit','variant'=>$action==='cancel'?'ghost':'primary','attributes'=>['disabled'=>true]];require dirname(__DIR__).'/components/button.php'; ?>
  </form>
  <?php endif; ?><?php endforeach; ?>
  <noscript><p>Activez JavaScript pour utiliser les actions avec confirmation et suivi du résultat.</p></noscript>
</section><?php endif; ?>
