<?php
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$editing=$product!==null;
$variable=!$editing&&($old['product_type']??'simple')==='variable';
$skuManual=($old['sku_mode']??null)==='manual'||(!isset($old['sku_mode'])&&is_string($old['sku']??null)&&trim($old['sku'])!=='');
$value=static fn(string $key,string $default=''):string=>isset($old[$key])&&is_string($old[$key])?$old[$key]:(string)($product[$key]??$default);
$fieldErrors=$fieldErrors??[];
$attributes=static function(string $key,string $help='')use($fieldErrors,$escape):string {
    $invalid=isset($fieldErrors[$key]);
    $description=trim($help.' '.($invalid?'product-error-'.$key:''));
    return ($invalid?' aria-invalid="true"':'').($description!==''?' aria-describedby="'.$escape($description).'"':'');
};
$inlineError=static fn(string $key):string=>isset($fieldErrors[$key])?'<small class="admin-product-form__error" id="product-error-'.$escape($key).'">Erreur : '.$escape($fieldErrors[$key]).'</small>':'';
$showPhotoHelp=(!$editing||!$canPublish)&&!str_contains($flash??'','photo')&&!str_contains($fieldErrors['statut']??'','photo');
?>
<section class="container admin-products ui-scope">
    <a class="btn btn-secondary" href="/admin/produits">← Retour aux produits</a>
    <h1><?= $editing?'Modifier le produit':'Ajouter un produit' ?></h1>
    <?php if ($flash!==null): ?><div class="admin-product-notice admin-product-notice--success ui-message" role="status"><strong>Enregistrement réussi</strong><p><?= $escape($flash) ?></p></div><?php endif; ?>
    <?php if ($formError!==null): ?><div class="admin-product-notice ui-message ui-message--error" role="alert"><strong>Vérifiez votre saisie</strong><p><?= $escape($fieldErrors!==[]?'Corrigez les champs signalés ci-dessous.':$formError) ?></p></div><?php endif; ?>
    <form class="admin-product-form" method="post" enctype="multipart/form-data" action="/admin/produits/enregistrer">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <?php if (!$editing): ?><input type="hidden" name="sku_request" value="<?= $escape(is_string($old['sku_request']??null)?$old['sku_request']:bin2hex(random_bytes(16))) ?>"><?php endif; ?>
        <?php if (!$editing): ?><input type="hidden" name="photo_batch" value="<?= $escape(is_string($old['photo_batch']??null)?$old['photo_batch']:bin2hex(random_bytes(16))) ?>"><?php endif; ?>
        <?php if ($editing): ?><input type="hidden" name="id_produit" value="<?= (int)$product['id_produit'] ?>"><?php endif; ?>
        <?php if (!$editing): ?>
        <fieldset data-product-type><legend>Type de produit</legend>
            <label class="ui-choice"><input type="radio" name="product_type" value="simple"<?= !$variable?' checked':'' ?>>Produit simple</label>
            <label class="ui-choice"><input type="radio" name="product_type" value="variable"<?= $variable?' checked':'' ?>>Produit avec variantes</label>
            <noscript><button class="btn btn-secondary" name="creation_action" value="mode" formnovalidate>Appliquer le type de produit</button></noscript>
        </fieldset>
        <?php endif; ?>
        <p>Les champs marqués * sont obligatoires.</p>
        <p data-product-form-feedback role="alert" hidden></p>
        <fieldset><legend>Informations du produit</legend>
            <label>Nom du produit *<input id="product-name" name="nom" value="<?= $escape($value('nom')) ?>" maxlength="180" required<?= $attributes('nom') ?>><?= $inlineError('nom') ?></label>
            <label>Description (facultative)<textarea id="product-description" name="description" rows="5"<?= $attributes('description') ?>><?= $escape($value('description')) ?></textarea><?= $inlineError('description') ?></label>
            <label><span id="product-category-label" data-select-label>Catégorie *</span><select id="product-category" name="id_categorie" data-select-control required<?= $attributes('id_categorie') ?>><option value="">Choisir une catégorie</option>
                <?php if ($value('id_categorie')!==''&&!in_array($value('id_categorie'),array_map('strval',array_column($categories,'id_categorie')),true)): ?><option value="<?= $escape($value('id_categorie')) ?>" selected>Catégorie à corriger</option><?php endif; ?>
                <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id_categorie'] ?>"<?= $value('id_categorie')===(string)$category['id_categorie']?' selected':'' ?>><?= $escape($category['nom']) ?></option><?php endforeach; ?>
            </select><?= $inlineError('id_categorie') ?></label>
        <div class="admin-product-form__publication" role="group" aria-label="Statut de publication"<?= !$editing?$attributes('statut'):'' ?>>
            <?php if ($editing): ?><label><span id="product-status-label" data-select-label>Statut de publication *</span><select id="product-status" name="statut" data-select-control required<?= $attributes('statut') ?>><option value="">Choisir un statut</option><?php foreach (['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archivé'] as $status=>$label): ?><option value="<?= $status ?>"<?= $value('statut')===$status?' selected':'' ?><?= $status==='actif'&&!$canPublish?' disabled':'' ?>><?= $label ?><?= $status==='actif'&&!$canPublish?' — photo nécessaire':'' ?></option><?php endforeach; ?></select></label>
            <?php else: ?><label>Statut de publication *<select id="product-status" name="statut" required<?= $attributes('statut') ?>><option value="actif"<?= $value('statut','actif')==='actif'?' selected':'' ?>>Actif</option><option value="brouillon"<?= $value('statut','actif')==='brouillon'?' selected':'' ?>>Brouillon</option></select></label><?php endif; ?>
            <?= $inlineError('statut') ?>
            <?php if ($showPhotoHelp): ?><p>Ajoutez au moins une photo avant de publier ce produit.</p><?php endif; ?>
        </div>
        </fieldset>
        <?php if (!$editing): ?>
        <fieldset data-simple-creation<?= $variable?' hidden disabled':'' ?>><legend>Informations de vente</legend>
            <fieldset class="admin-product-sku" data-sku-mode><legend>Référence produit (SKU)</legend>
                <label class="ui-choice"><input type="radio" name="sku_mode" value="auto"<?= !$skuManual?' checked':'' ?> aria-describedby="product-sku-auto-help"<?= $attributes('sku_mode') ?>>Générer automatiquement</label>
                <label class="ui-choice"><input type="radio" name="sku_mode" value="manual"<?= $skuManual?' checked':'' ?><?= $attributes('sku_mode') ?>>Saisir ma propre référence</label>
                <?= $inlineError('sku_mode') ?>
                <p id="product-sku-auto-help" data-sku-auto-help>La référence sera générée lors de l'enregistrement du produit.</p>
                <div data-sku-manual>
                    <label>Référence (SKU) *<input id="product-sku" name="sku" value="<?= $escape($value('sku')) ?>" maxlength="100"<?= $attributes('sku','product-sku-help') ?>><?= $inlineError('sku') ?></label>
                    <p id="product-sku-help">Code unique : lettres, chiffres, points, tirets ou underscores, 100 caractères maximum.</p>
                </div>
            </fieldset>
            <div class="admin-product-form__pair">
                <label>Prix *<input id="product-price" name="prix" value="<?= $escape($value('prix')) ?>" inputmode="decimal" required<?= $attributes('prix','product-price-help') ?>><?= $inlineError('prix') ?></label>
                <label><span id="product-currency-label" data-select-label>Devise *</span><select id="product-currency" name="devise" data-select-control required<?= $attributes('devise','product-price-help') ?>><?php if (!in_array($value('devise','USD'),['USD','CDF'],true)): ?><option value="<?= $escape($value('devise')) ?>" selected><?= $escape($value('devise')) ?></option><?php endif; ?><?php foreach (['USD','CDF'] as $currency): ?><option<?= $value('devise','USD')===$currency?' selected':'' ?>><?= $currency ?></option><?php endforeach; ?></select><?= $inlineError('devise') ?></label>
            </div>
            <p id="product-price-help">Saisissez votre prix en dollars ou en francs congolais. Les francs sont convertis au taux de la boutique ; le prix est conservé en dollars, arrondi au centime.</p>
            <label>Stock initial *<input id="product-stock" type="number" name="stock_initial" value="<?= $escape($value('stock_initial','0')) ?>" min="0" max="4294967295" step="1" required<?= $attributes('stock_initial') ?>><?= $inlineError('stock_initial') ?></label>
        </fieldset>
        <?php require __DIR__.'/product-creation-variants.php'; ?>
        <?php else: ?>
        <?php require __DIR__.'/product-sales.php'; ?>
        <?php endif; ?>
        <?php if (!$editing): require __DIR__.'/product-photo-preparation.php'; endif; ?>
        <button class="btn btn-primary" type="submit" data-product-submit><?= $editing?'Enregistrer les modifications':($value('statut','actif')==='actif'?'Publier le produit':'Créer le brouillon') ?></button>
        <?php if (!$editing): ?><input type="hidden" name="creation_end" value="v1"><?php endif; ?>
    </form>
    <?php if ($editing&&isset($versions)): require __DIR__.'/product-options.php'; endif; ?>
    <?php if ($editing): $photos=$photos??[]; ?>
    <section class="admin-product-photos" id="product-photos" aria-labelledby="product-photos-title">
        <h2 id="product-photos-title">Photos du produit</h2>
        <p><?= count($photos) ?> / 6 photos</p>
        <?php if ($photos===[]): ?><p>Aucune photo pour le moment.</p><?php else: ?>
        <ul class="admin-product-photos__gallery">
            <?php foreach ($photos as $photoIndex=>$photo): ?><li>
                <?php if ($photo['thumbnail']!==null): ?><img src="<?= $escape($photo['thumbnail']) ?>" alt="Photo de <?= $escape($product['nom']) ?>" width="150" height="150" loading="lazy"><?php else: ?><span>Sans image</span><?php endif; ?>
                <?php if ((int)$photo['image_principale']===1): ?><span class="admin-product-photos__badge">Principale</span><?php endif; ?>
                <form class="admin-product-photos__actions" method="post" action="/admin/produits/photo/modifier" aria-label="Gérer la photo <?= $photoIndex+1 ?>" data-gallery-actions>
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="id_produit" value="<?= (int)$product['id_produit'] ?>">
                    <input type="hidden" name="id_image" value="<?= (int)$photo['id_image'] ?>">
                    <?php if ($photoIndex>0): ?><button class="btn btn-secondary" type="submit" name="action" value="up">Monter</button><?php endif; ?>
                    <?php if ($photoIndex<count($photos)-1): ?><button class="btn btn-secondary" type="submit" name="action" value="down">Descendre</button><?php endif; ?>
                    <?php if ((int)$photo['image_principale']!==1): ?><button class="btn btn-secondary" type="submit" name="action" value="primary">Définir comme principale</button><?php endif; ?>
                    <?php if (count($photos)>1||!\App\Models\CatalogVisibility::isActive((string)$product['statut'])): ?>
                    <button class="btn btn-danger admin-product-photos__delete" type="submit" name="action" value="delete" data-confirm-delete>Supprimer</button>
                    <?php else: ?><small>Un produit actif doit conserver au moins une photo.</small><?php endif; ?>
                </form>
            </li><?php endforeach; ?>
        </ul><?php endif; ?>
        <?php if (count($photos)<6): ?>
        <form method="post" enctype="multipart/form-data" action="/admin/produits/photo" class="admin-product-photos__form">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="id_produit" value="<?= (int)$product['id_produit'] ?>">
            <label for="product-photo">Ajouter une photo</label>
            <input id="product-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="product-photo-help" required>
            <p id="product-photo-help">JPEG, PNG ou WebP, 10 Mio maximum. Jusqu’à 6 photos par produit.</p>
            <button class="btn btn-primary" type="submit">Ajouter la photo</button>
        </form>
        <?php else: ?><p>La limite de 6 photos est atteinte pour ce produit.</p><?php endif; ?>
    </section>
    <?php endif; ?>
</section>
