<?php
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$editing=$product!==null;
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
<section class="container admin-products">
    <a class="btn btn-ghost" href="/admin/produits">← Retour aux produits</a>
    <h1><?= $editing?'Modifier le produit':'Ajouter un produit' ?></h1>
    <?php if ($flash!==null): ?><div class="admin-product-notice admin-product-notice--success" role="status"><strong>Enregistrement réussi</strong><p><?= $escape($flash) ?></p></div><?php endif; ?>
    <?php if ($formError!==null): ?><div class="admin-product-notice admin-product-notice--error" role="alert"><strong>Vérifiez votre saisie</strong><p><?= $escape($fieldErrors!==[]?'Corrigez le champ signalé ci-dessous.':$formError) ?></p></div><?php endif; ?>
    <form class="admin-product-form" method="post" action="/admin/produits/enregistrer">
        <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
        <?php if ($editing): ?><input type="hidden" name="id_produit" value="<?= (int)$product['id_produit'] ?>"><?php endif; ?>
        <p>Les champs marqués * sont obligatoires.</p>
        <p data-product-form-feedback role="alert" hidden></p>
        <fieldset><legend>Informations du produit</legend>
            <label>Nom du produit *<input id="product-name" name="nom" value="<?= $escape($value('nom')) ?>" maxlength="180" required<?= $attributes('nom') ?>><?= $inlineError('nom') ?></label>
            <label>Description (facultative)<textarea id="product-description" name="description" rows="5"<?= $attributes('description') ?>><?= $escape($value('description')) ?></textarea><?= $inlineError('description') ?></label>
            <label><span id="product-category-label" data-select-label>Catégorie *</span><select id="product-category" name="id_categorie" data-select-control required<?= $attributes('id_categorie') ?>><option value="">Choisir une catégorie</option>
                <?php if ($value('id_categorie')!==''&&!in_array($value('id_categorie'),array_map('strval',array_column($categories,'id_categorie')),true)): ?><option value="<?= $escape($value('id_categorie')) ?>" selected>Catégorie à corriger</option><?php endif; ?>
                <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id_categorie'] ?>"<?= $value('id_categorie')===(string)$category['id_categorie']?' selected':'' ?>><?= $escape($category['nom']) ?></option><?php endforeach; ?>
            </select><?= $inlineError('id_categorie') ?></label>
        </fieldset>
        <?php if (!$editing): ?>
        <fieldset><legend>Informations de vente</legend>
            <label>Référence (SKU) — facultatif<input id="product-sku" name="sku" value="<?= $escape($value('sku')) ?>" maxlength="100"<?= $attributes('sku','product-sku-help') ?>><?= $inlineError('sku') ?></label>
            <p id="product-sku-help">Code unique permettant d’identifier le produit. Laissez vide pour le générer automatiquement.</p>
            <div class="admin-product-form__pair">
                <label>Prix *<input id="product-price" name="prix" value="<?= $escape($value('prix')) ?>" inputmode="decimal" required<?= $attributes('prix','product-price-help') ?>><?= $inlineError('prix') ?></label>
                <label><span id="product-currency-label" data-select-label>Devise *</span><select id="product-currency" name="devise" data-select-control required<?= $attributes('devise','product-price-help') ?>><?php if (!in_array($value('devise','USD'),['USD','CDF'],true)): ?><option value="<?= $escape($value('devise')) ?>" selected><?= $escape($value('devise')) ?></option><?php endif; ?><?php foreach (['USD','CDF'] as $currency): ?><option<?= $value('devise','USD')===$currency?' selected':'' ?>><?= $currency ?></option><?php endforeach; ?></select><?= $inlineError('devise') ?></label>
            </div>
            <p id="product-price-help">Saisissez votre prix en dollars ou en francs congolais. Les francs sont convertis au taux de la boutique ; le prix est conservé en dollars, arrondi au centime.</p>
            <label>Stock initial *<input id="product-stock" type="number" name="stock_initial" value="<?= $escape($value('stock_initial','0')) ?>" min="0" max="4294967295" step="1" required<?= $attributes('stock_initial') ?>><?= $inlineError('stock_initial') ?></label>
        </fieldset>
        <?php else: ?>
        <section class="admin-product-sales" aria-labelledby="product-variants-title"><h2 id="product-variants-title">Informations de vente</h2>
            <p>Le prix et le stock seront modifiables dans la gestion des variantes.</p>
            <?php if ($variants===[]): ?><p>Aucune information de vente enregistrée.</p><?php endif; ?>
            <?php foreach ($variants as $variant): ?><dl class="admin-product-sales__details"><div><dt>Référence (SKU)</dt><dd><?= $escape($variant['sku']) ?></dd></div><div><dt>Prix</dt><dd><?= $escape($variant['priceLabel']) ?></dd></div><div><dt>Stock disponible</dt><dd><?= (int)$variant['stock'] ?> unité<?= (int)$variant['stock']===1?'':'s' ?></dd></div></dl><?php endforeach; ?>
        </section>
        <?php endif; ?>
        <fieldset class="admin-product-form__publication"<?= !$editing?$attributes('statut'):'' ?>><legend>Publication</legend>
            <?php if ($editing): ?><label><span id="product-status-label" data-select-label>Statut *</span><select id="product-status" name="statut" data-select-control required<?= $attributes('statut') ?>><option value="">Choisir un statut</option><?php foreach (['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archivé'] as $status=>$label): ?><option value="<?= $status ?>"<?= $value('statut')===$status?' selected':'' ?><?= $status==='actif'&&!$canPublish?' disabled':'' ?>><?= $label ?><?= $status==='actif'&&!$canPublish?' — photo nécessaire':'' ?></option><?php endforeach; ?></select></label>
            <?php else: ?><input type="hidden" name="statut" value="brouillon"><p>Statut : <strong>Brouillon</strong></p><?php endif; ?>
            <?= $inlineError('statut') ?>
            <?php if ($showPhotoHelp): ?><p>Ajoutez au moins une photo avant de publier ce produit.</p><?php endif; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit"><?= $editing?'Enregistrer les modifications':'Créer le brouillon' ?></button>
    </form>
</section>
