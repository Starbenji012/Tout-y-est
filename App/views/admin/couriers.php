<?php
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$oldId=isset($old['id_livreur'])&&is_scalar($old['id_livreur'])?(string)$old['id_livreur']:null;
$oldName=is_string($old['nom']??null)?(function_exists('mb_substr')?mb_substr($old['nom'],0,120):substr($old['nom'],0,120)):'';
?>
<section class="container admin-couriers" aria-labelledby="couriers-title">
  <header><h1 id="couriers-title">Livreurs</h1><p>Enregistrez votre propre nom ou celui d’un collaborateur. Associez ensuite son compte existant pour l’accès à ses livraisons.</p></header>
  <?php if($tourReady??false): ?><div class="admin-couriers__actions"><a class="btn btn-primary" href="/admin/remises-cash">Argent des livraisons</a><?php if($courierAccess??false): ?><a class="btn btn-outline" href="/livreur/livraisons">Mon espace Livreur</a><?php endif; ?></div><?php endif; ?>
  <?php if($courierFlash): ?><p role="status"><?= $escape($courierFlash) ?></p><?php endif; ?>
  <?php if($courierError): ?><p role="alert"><?= $escape($courierError) ?></p><?php endif; ?>
  <?php if(!$installed): ?><p class="admin-order-card">La gestion des livreurs sera disponible après application de la mise à jour prévue. Aucun livreur n’a été créé.</p>
  <?php else: ?>
    <details class="admin-couriers__help"><summary>Comprendre les disponibilités</summary><p>Disponible : libre pour une affectation. Affecté : départ en attente. En tournée : livraison démarrée. Indisponible : aucune nouvelle affectation.</p></details>
    <details class="admin-zone admin-couriers__create"<?= $courierError&&$oldId===null?' open':'' ?>>
      <summary><strong>＋ Ajouter un livreur</strong></summary>
      <div class="admin-zone__body">
        <form method="post" action="/admin/livreurs/enregistrer" data-courier-form>
          <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
          <label for="courier-name-new">Nom du livreur</label>
          <input id="courier-name-new" name="nom" value="<?= $escape($oldId===null?$oldName:'') ?>" required minlength="2" maxlength="120" autocomplete="off">
          <small>Le livreur sera enregistré comme indisponible. Vous pourrez le rendre disponible ensuite.</small>
          <?php $button=['label'=>'Ajouter le livreur','type'=>'submit','variant'=>'primary'];require dirname(__DIR__).'/components/button.php'; ?>
        </form>
      </div>
    </details>
    <?php if(!$couriers): ?><p>Aucun livreur enregistré. Ajoutez le gérant ou un collaborateur pour préparer les prochaines livraisons.</p><?php endif; ?>
    <div class="admin-couriers__list">
    <?php foreach($couriers as $courier): $id=(int)$courier['id_livreur'];$available=$courier['statut']==='actif';
      $occupied=!empty($courier['en_course'])||!empty($courier['affecte']);
      $eligible=$available&&(!($tourReady??false)||!empty($courier['compte_actif']));
      $label=!empty($courier['en_course'])?'En tournée':(!empty($courier['affecte'])?'Affecté — départ en attente':($eligible?'Disponible':'Indisponible'));
      $tone=$occupied?'is-busy':($eligible?'is-available':'is-unavailable'); ?>
      <article class="admin-order-card">
        <header><h2><?= $escape($courier['nom']) ?></h2><span class="admin-courier-status <?= $tone ?>"><span aria-hidden="true"></span><?= $label ?></span></header>
        <?php if(!$available&&$occupied): ?><p class="admin-orders__muted">Indisponible pour toute nouvelle affectation. La livraison actuelle est conservée.</p><?php endif; ?>
        <?php if($tourReady??false): ?>
        <?php $linked=null;foreach($courierAccounts as $account)if((int)$account['id_utilisateur']===(int)($courier['id_utilisateur']??0))$linked=$account; ?>
        <p><?= empty($courier['id_utilisateur'])?'Compte non associé : aucune affectation automatique possible.':($linked?'Compte associé : '.$escape(trim($linked['prenom'].' '.$linked['nom'])):'Compte associé inactif — aucune nouvelle affectation.') ?></p>
        <?php endif; ?>
        <form method="post" action="/admin/livreurs/disponibilite" data-courier-form>
          <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
          <input type="hidden" name="id_livreur" value="<?= $id ?>">
          <input type="hidden" name="statut" value="<?= $available?'inactif':'actif' ?>">
          <?php $button=['label'=>$available?'Rendre indisponible':'Rendre disponible','type'=>'submit','variant'=>'outline'];require dirname(__DIR__).'/components/button.php'; ?>
        </form>
        <details class="admin-couriers__manage"<?= $courierError&&$oldId===(string)$id?' open':'' ?>>
          <summary>Gérer</summary><h3>Réglages du livreur</h3>
          <?php if(($tourReady??false)&&!$occupied): ?><form method="post" action="/admin/livreurs/compte"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="livreur" value="<?= $id ?>"><label for="courier-account-<?= $id ?>">Associer un compte existant</label><select id="courier-account-<?= $id ?>" name="compte" required><option value="">Choisir le compte vérifié avec le collaborateur</option><?php foreach($courierAccounts as $account): ?><option value="<?= (int)$account['id_utilisateur'] ?>"<?= (int)$account['id_utilisateur']===(int)($courier['id_utilisateur']??0)?' selected':'' ?>><?= $escape(trim($account['prenom'].' '.$account['nom']).' — '.$account['email']) ?></option><?php endforeach; ?></select><button class="btn btn-outline" type="submit">Associer ce compte</button></form><?php endif; ?>
          <form method="post" action="/admin/livreurs/enregistrer" data-courier-form>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="id_livreur" value="<?= $id ?>">
            <label for="courier-name-<?= $id ?>">Nom du livreur</label>
            <input id="courier-name-<?= $id ?>" name="nom" value="<?= $escape($oldId===(string)$id?$oldName:$courier['nom']) ?>" required minlength="2" maxlength="120" autocomplete="off">
            <?php $button=['label'=>'Enregistrer le nom','type'=>'submit','variant'=>'secondary'];require dirname(__DIR__).'/components/button.php'; ?>
          </form>
        </details>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
