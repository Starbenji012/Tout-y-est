<?php
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$oldId=isset($old['id_livreur'])&&is_scalar($old['id_livreur'])?(string)$old['id_livreur']:null;
$oldName=is_string($old['nom']??null)?(function_exists('mb_substr')?mb_substr($old['nom'],0,120):substr($old['nom'],0,120)):'';
?>
<section class="container admin-couriers" aria-labelledby="couriers-title">
  <header><h1 id="couriers-title">Livreurs</h1><p>Enregistrez votre propre nom ou celui d’un collaborateur, sans créer de compte de connexion.</p></header>
  <?php if($courierFlash): ?><p role="status"><?= $escape($courierFlash) ?></p><?php endif; ?>
  <?php if($courierError): ?><p role="alert"><?= $escape($courierError) ?></p><?php endif; ?>
  <?php if(!$installed): ?><p class="admin-order-card">La gestion des livreurs sera disponible après application de la mise à jour prévue. Aucun livreur n’a été créé.</p>
  <?php else: ?>
    <p>« Disponible » permet une nouvelle affectation. « Indisponible » conserve le livreur et toutes ses anciennes livraisons.</p>
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
    <?php foreach($couriers as $courier): $id=(int)$courier['id_livreur'];$available=$courier['statut']==='actif'; ?>
      <article class="admin-order-card">
        <header><h2><?= $escape($courier['nom']) ?></h2><span class="admin-courier-status<?= $available?' is-available':' is-unavailable' ?>"><span aria-hidden="true"></span><?= $available?'Disponible':'Indisponible' ?></span></header>
        <form method="post" action="/admin/livreurs/disponibilite" data-courier-form>
          <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
          <input type="hidden" name="id_livreur" value="<?= $id ?>">
          <input type="hidden" name="statut" value="<?= $available?'inactif':'actif' ?>">
          <?php $button=['label'=>$available?'Rendre indisponible':'Rendre disponible','type'=>'submit','variant'=>'outline'];require dirname(__DIR__).'/components/button.php'; ?>
        </form>
        <details<?= $courierError&&$oldId===(string)$id?' open':'' ?>>
          <summary>Modifier le nom</summary>
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
