<?php
$readyPhotos=$preparedPhotos??[];
$orderedPhotos=is_array($old['photo_ids']??null)?array_values(array_intersect(array_filter($old['photo_ids'],'is_string'),$readyPhotos)):[];
$mainPhoto=in_array($old['primary_photo']??'', $orderedPhotos,true)?$old['primary_photo']:($orderedPhotos[0]??'');
$batchPhoto=$old['photo_batch']??'';
?>
<fieldset data-photo-preparation><legend>Photos du produit</legend>
    <p id="creation-photo-help">Jusqu’à six photos JPEG, PNG ou WebP, 10 Mio maximum chacune. Une photo valide est nécessaire pour publier. Les préparations expirent après 24 heures.</p>
    <?= $inlineError('photos') ?>
    <label for="creation-photo">Ajouter des photos</label>
    <input id="creation-photo" type="file" name="creation_photo" accept="image/jpeg,image/png,image/webp" aria-describedby="creation-photo-help">
    <button class="btn btn-secondary" type="submit" name="photo_action" value="prepare" formnovalidate data-prepare-photo>Préparer la photo sélectionnée</button>
    <p data-photo-feedback role="status" aria-live="polite" hidden></p>
    <noscript><p>Sans JavaScript : préparez une photo à la fois, puis enregistrez le produit. Les photos déjà préparées sont conservées lors des erreurs. Si un envoi dépasse 12 Mio, le formulaire devra être ressaisi ; chaque fichier reste limité à 10 Mio.</p></noscript>
    <ul class="admin-product-photos__gallery" data-prepared-list<?= $orderedPhotos===[]?' hidden':'' ?>>
    <?php foreach($orderedPhotos as $index=>$photoId): ?>
        <li data-prepared-ticket="<?= $escape($photoId) ?>">
            <input type="hidden" name="photo_ids[]" value="<?= $escape($photoId) ?>">
            <img width="150" height="150" alt="Photo <?= $index+1 ?>" src="/admin/produits/photos/apercu?<?= $escape(http_build_query(['batch'=>$batchPhoto,'ticket'=>$photoId])) ?>">
            <p data-photo-state>Photo prête<?= $photoId===$mainPhoto?' — Principale':'' ?></p>
            <div class="admin-product-photos__actions">
                <?php foreach(['up'=>'Monter','down'=>'Descendre','primary'=>'Définir comme principale','remove'=>'Retirer'] as $action=>$label): ?>
                <button type="submit" class="btn <?= $action==='remove'?'btn-danger':'btn-secondary' ?>" name="photo_action" value="<?= $action.':'.$escape($photoId) ?>" formnovalidate><?= $label ?></button>
                <?php endforeach; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
    <input type="hidden" name="primary_photo" value="<?= $escape($mainPhoto) ?>">
</fieldset>
