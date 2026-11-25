<?php
$versionErrors=$fieldErrors??[];
$versionError=static fn(string $key):string=>isset($versionErrors[$key])?'<small class="admin-product-form__error" role="alert">'.$escape($versionErrors[$key]).'</small>':'';
$configured=$versions['definitions']!==[];
$draft=$versionsDraft??[];
$draftId=static fn(string $key):string=>is_int($draft[$key]??null)||is_string($draft[$key]??null)?(string)$draft[$key]:'';
$draftOptions=is_array($draft['options']??null)?array_slice($draft['options'],0,3):[['nom'=>'','valeurs'=>['']]];
$mode=$configured||($draft['mode']??'')==='yes';
$skuRequest=is_string($draft['sku_request']??null)?$draft['sku_request']:bin2hex(random_bytes(16));
$versionToken=static function()use($escape,$csrfToken,$product,$skuRequest):void { ?><input type="hidden" name="sku_request" value="<?= $escape($skuRequest) ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="id_produit" value="<?= (int)$product['id_produit'] ?>"><?php };
?>
<section class="admin-product-options" id="product-options" aria-labelledby="product-options-title" data-version-editor>
    <h2 id="product-options-title">Options et variantes</h2>
    <?php if ($draft!==[]&&$formError!==null): ?><p class="admin-product-form__error" role="alert"><?= $escape($formError) ?></p><?php endif; ?>
    <?php if (!$versions['available']): ?>
        <p>Ce produit conserve son fonctionnement actuel. La gestion des versions n’est pas encore disponible.</p>
    <?php elseif (!$configured): ?>
    <form method="post" action="/admin/produits/versions" class="admin-product-options-form" data-version-builder>
        <?php $versionToken(); ?>
        <fieldset class="admin-product-options__mode"><legend>Ce produit existe-t-il en plusieurs versions ?</legend>
            <label><input type="radio" name="mode" value="no"<?= !$mode?' checked':'' ?>> Non</label>
            <label><input type="radio" name="mode" value="yes"<?= $mode?' checked':'' ?>> Oui</label>
        </fieldset>
        <noscript><button class="btn btn-secondary" name="action" value="choose-mode">Choisir le mode</button></noscript>
        <div data-options-panel<?= !$mode?' hidden':'' ?>>
            <p>Une option décrit ce qui change ; ses valeurs sont les choix possibles. Par exemple : Couleur → Noir, Blanc. Trois options maximum.</p>
            <?= $versionError('options') ?>
            <p class="admin-product-options__warning">Après conversion, ce produit ne sera plus proposé à l’achat tant que le choix des versions n’est pas disponible.</p>
            <?php if (count($versions['variants'])===1): $original=$versions['variants'][0]; ?>
                <p>Version existante à conserver : <strong><?= $escape($original['sku']) ?></strong> — <?= $escape($original['prix_reference']) ?> <?= $escape($original['devise_reference']??'') ?> · <?= (int)$original['stock'] ?> en stock. Son prix, son stock et son état seront conservés.</p>
            <?php else: ?><p>Ce produit possède plusieurs versions historiques. Une conversion automatique n’est pas proposée.</p><?php endif; ?>
            <div data-option-rows>
            <?php foreach ($draftOptions as $index=>$definition): $definition=is_array($definition)?$definition:[];$values=is_array($definition['valeurs']??null)?array_slice($definition['valeurs'],0,100):['']; ?>
                <fieldset class="admin-product-options__option" data-option-row>
                    <legend>Option <?= $index+1 ?></legend>
                    <label>Nom de l’option<input name="options[<?= $index ?>][nom]" value="<?= $escape(is_string($definition['nom']??null)?$definition['nom']:'') ?>" placeholder="Ex. Couleur, Taille, Matière" maxlength="100" data-option-name></label>
                    <?= $versionError('options.'.$index.'.nom') ?>
                    <div data-value-rows>
                        <?php foreach ($values as $valueIndex=>$label): ?>
                        <div class="admin-product-options__value" data-value-row><label>Valeur<input name="options[<?= $index ?>][valeurs][<?= $valueIndex ?>]" value="<?= $escape(is_string($label)?$label:'') ?>" maxlength="100" data-option-value></label><button class="btn btn-danger" type="submit" name="action" value="remove-value-draft:<?= $index ?>:<?= $valueIndex ?>" data-remove-value>Retirer cette valeur</button><?= $versionError('options.'.$index.'.valeurs.'.$valueIndex) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?= $versionError('options.'.$index.'.valeurs') ?>
                    <div class="admin-product-options__actions"><button class="btn btn-secondary" type="submit" name="action" value="add-value-draft:<?= $index ?>" data-add-value>+ Ajouter une valeur</button><button class="btn btn-danger" type="submit" name="action" value="remove-option:<?= $index ?>" data-remove-option>Retirer cette option</button></div>
                </fieldset>
            <?php endforeach; ?>
            </div>
            <input type="hidden" name="option_index" value="0" data-draft-option-index><input type="hidden" name="value_index" value="0" data-draft-value-index>
            <button class="btn btn-secondary" type="submit" name="action" value="add-option" data-add-option<?= count($draftOptions)>=3?' disabled':'' ?>>+ Ajouter une option</button>
            <p data-options-feedback role="status" aria-live="polite"></p>
            <button class="btn btn-secondary" type="submit" name="action" value="preview" data-preview-options>Voir les combinaisons</button>
            <div data-candidate-panel>
                <?php if (isset($draft['preview']['candidates'])): $candidates=$draft['preview']['candidates']; ?>
                    <p class="admin-product-options__counts"><?= count($candidates) ?> possibles · 0 enregistrée · <?= count(array_filter(array_keys($candidates),static fn(string $token):bool=>!isset($draft['selected'])||in_array($token,(array)$draft['selected'],true))) ?> sélectionnées</p>
                    <fieldset class="admin-product-options__candidates"><legend>Combinaisons vendues</legend><p>Décochez les versions non vendues et choisissez celle qui reprend la version existante.</p>
                    <?php foreach ($candidates as $token=>$candidate): ?>
                        <div class="admin-product-options__candidate"><label><input type="checkbox" name="selected[]" value="<?= $escape($token) ?>"<?= !isset($draft['selected'])||in_array($token,(array)$draft['selected'],true)?' checked':'' ?>> <?= $escape($candidate['label']) ?></label><label><input type="radio" name="reuse" value="<?= $escape($token) ?>"<?= ($draft['reuse']??null)===$token?' checked':'' ?>> Reprendre la version existante</label></div>
                    <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>
            </div>
            <?= $versionError('reuse') ?>
            <p>Les nouvelles versions reprennent provisoirement le prix existant, restent inactives et ont un stock de zéro. Vous pourrez les compléter à l’étape suivante.</p>
            <button class="btn btn-primary" type="submit" name="action" value="configure" data-save-options<?= !isset($draft['preview'])?' hidden':'' ?>>Enregistrer les versions</button>
            <noscript><p>Utilisez « Voir les combinaisons » avant d’enregistrer. Les boutons d’ajout et de retrait restent utilisables.</p></noscript>
        </div>
    </form>
    <?php else: ?>
        <p>Ce produit possède plusieurs versions. Il n’est pas proposé à l’achat tant que le choix des versions n’est pas disponible.</p>
        <?= $versionError('options') ?>
        <p>Exemples : Couleur → Noir, Blanc ; Taille → S, M, L ; Matière → Coton, Lin.</p>
        <div class="admin-product-options__blocks">
        <?php foreach ($versions['definitions'] as $definition): ?>
        <section class="admin-product-options__option" aria-label="<?= $escape($definition['nom']) ?>">
            <form method="post" action="/admin/produits/versions" class="admin-product-options__actions"><?php $versionToken(); ?><input type="hidden" name="option" value="<?= (int)$definition['id_option'] ?>"><label>Nom de l’option<input name="label" value="<?= $escape(($draft['action']??null)==='rename-option'&&$draftId('option')===(string)$definition['id_option']&&is_string($draft['label']??null)?$draft['label']:$definition['nom']) ?>" maxlength="100" required></label><button class="btn btn-secondary" name="action" value="rename-option">Renommer l’option</button><button class="btn btn-danger" name="action" value="delete-option" formnovalidate>Retirer l’option</button></form>
            <?php foreach ($definition['valeurs'] as $item): ?>
            <form method="post" action="/admin/produits/versions" class="admin-product-options__actions"><?php $versionToken(); ?><input type="hidden" name="option" value="<?= (int)$definition['id_option'] ?>"><input type="hidden" name="value" value="<?= (int)$item['id_valeur'] ?>"><label>Valeur<input name="label" value="<?= $escape(($draft['action']??null)==='rename-value'&&$draftId('value')===(string)$item['id_valeur']&&is_string($draft['label']??null)?$draft['label']:$item['libelle']) ?>" maxlength="100" required></label><button class="btn btn-secondary" name="action" value="rename-value">Renommer la valeur</button><button class="btn btn-danger" name="action" value="remove-value" formnovalidate>Retirer la valeur</button></form>
            <?php endforeach; ?>
            <form method="post" action="/admin/produits/versions" class="admin-product-options__actions"><?php $versionToken(); ?><input type="hidden" name="option" value="<?= (int)$definition['id_option'] ?>"><label>Nouvelle valeur<input name="label" maxlength="100" required value="<?= $escape(($draft['action']??null)==='add-value'&&$draftId('option')===(string)$definition['id_option']&&is_string($draft['label']??null)?$draft['label']:'') ?>"></label><button class="btn btn-secondary" name="action" value="add-value">+ Ajouter la valeur</button></form>
        </section>
        <?php endforeach; ?>
        </div>
        <?php if ($versions['candidateError']!==null): ?><p role="alert"><?= $escape($versions['candidateError']) ?></p><?php else: ?>
        <form method="post" action="/admin/produits/versions" data-combination-picker><?php $versionToken(); ?>
        <p class="admin-product-options__counts"><strong><?= count($versions['candidates']) ?> possibles</strong> · <?= count($versions['variants']) ?> enregistrées · <span data-selected-count role="status"><?= count(array_filter($versions['candidates'],static fn(array $candidate,string $token):bool=>!$candidate['existing']&&in_array($token,(array)($draft['selected']??[]),true),ARRAY_FILTER_USE_BOTH)) ?> sélectionnées</span></p>
        <fieldset class="admin-product-options__candidates"><legend>Nouvelles combinaisons disponibles</legend><p>Les variantes enregistrées sont consultables dans Informations de vente. Cochez les nouvelles combinaisons à ajouter.</p>
            <?php foreach ($versions['candidates'] as $token=>$candidate): if ($candidate['existing']) continue; ?><label><input type="checkbox" name="selected[]" value="<?= $escape($token) ?>"<?= in_array($token,(array)($draft['selected']??[]),true)?' checked':'' ?>> <?= $escape($candidate['label']) ?></label><?php endforeach; ?>
        </fieldset><p>Les nouvelles versions reprennent provisoirement le prix de la première version, restent inactives et sans stock.</p><?php if (array_filter($versions['candidates'],static fn(array $candidate):bool=>!$candidate['existing'])!==[]): ?><button class="btn btn-primary" name="action" value="extend">Ajouter les combinaisons sélectionnées</button><?php else: ?><p>Toutes les combinaisons sont déjà enregistrées.</p><?php endif; ?></form>
        <?php endif; ?>
    <?php endif; ?>
    <p class="admin-product-options__pending" data-version-pending role="status" hidden>Enregistrement en cours… Merci de patienter.</p>
</section>
