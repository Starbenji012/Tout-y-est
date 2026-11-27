<?php
$creationPreview=$creationPreview??null;
$creationRows=is_array($old['creation_variants']??null)?$old['creation_variants']:[];
$creationOptions=is_array($old['creation_options']??null)?$old['creation_options']:[];
$creationText=static fn(mixed $v,string $default=''):string=>is_string($v)?$v:$default;
$lastCreationOption=0;
foreach (array_slice($creationOptions,0,3,true) as $index=>$definition) {
    if (is_array($definition)&&(trim($creationText($definition['nom']??null))!==''||trim($creationText($definition['valeurs']??null))!=='')) $lastCreationOption=max($lastCreationOption,(int)$index);
}
?>
<fieldset class="admin-product-creation-section" data-variable-creation<?= !$variable?' hidden disabled':'' ?>>
    <legend>Options et versions</legend>
    <details class="ui-disclosure" data-creation-details<?= $variable?' open':'' ?>>
        <summary class="btn btn-secondary">Configurer les options et versions</summary>
        <div class="admin-product-creation">
            <p class="ui-message">Vous pouvez préparer ce produit, mais ses versions ne sont pas encore disponibles à l’achat. Les photos sont communes.</p>
            <?= $inlineError('creation') ?>
            <p>Exemples : Couleur → Noir, Blanc ; Taille → S, M. Saisissez chaque valeur sur une ligne distincte. Jusqu’à 3 options et 100 versions ; laissez les options inutilisées vides.</p>
            <?php for($optionIndex=0;$optionIndex<3;$optionIndex++): $definition=is_array($creationOptions[$optionIndex]??null)?$creationOptions[$optionIndex]:[]; ?>
            <details class="ui-disclosure admin-product-creation__option" data-creation-option<?= $optionIndex<=$lastCreationOption?' open':'' ?>>
                <summary><?php if($optionIndex===0): ?>Option 1<?php else: ?><span class="admin-product-creation__add-label">+ Ajouter une option</span><span class="admin-product-creation__option-label">Option <?= $optionIndex+1 ?> (facultative)</span><?php endif; ?></summary>
                <div class="admin-product-creation__option-fields">
                <label>Nom de l’option<input name="creation_options[<?= $optionIndex ?>][nom]" value="<?= $escape($creationText($definition['nom']??null)) ?>" maxlength="100" placeholder="Ex. Couleur" data-creation-definition></label>
                <label>Valeurs (une par ligne)<textarea name="creation_options[<?= $optionIndex ?>][valeurs]" rows="2" maxlength="41000" placeholder="Noir&#10;Blanc" data-creation-definition><?= $escape($creationText($definition['valeurs']??null)) ?></textarea></label>
                </div>
            </details>
            <?php endfor; ?>
            <button class="btn btn-secondary" name="creation_action" value="preview" formnovalidate>Afficher les versions</button>
            <p data-creation-feedback role="status" aria-live="polite"></p>
            <?php if($creationPreview!==null): ?>
                <input type="hidden" name="creation_preview" value="1">
                <input type="hidden" name="creation_signature" value="<?= $escape($creationPreview['signature']) ?>">
                <p class="ui-message" data-creation-count role="status" aria-live="polite"><?= count($creationPreview['candidates']) ?> versions possibles · <?= count(array_filter($creationPreview['candidates'],static fn(array $candidate,string $token):bool=>($creationRows[$token]['selected']??null)==='1',ARRAY_FILTER_USE_BOTH)) ?> sélectionnées</p>
                <noscript><p>« Afficher les versions » actualise le compteur sans effacer votre saisie.</p></noscript>
                <p id="creation-variants-help">Cochez les versions à vendre et renseignez prix et stock. Active + stock zéro : rupture ; inactive : non vendue. Les prix CDF sont convertis en USD au taux de la boutique, arrondis au centime. Référence automatique par défaut, personnalisable dans les réglages avancés.</p>
                <div class="admin-product-creation__list">
                <table class="admin-product-creation__table" role="table" aria-describedby="creation-variants-help"><caption>Versions du produit</caption>
                    <thead><tr><th scope="col">Vendre</th><th scope="col">Version</th><th scope="col">Prix</th><th scope="col">Devise</th><th scope="col">Stock</th><th scope="col">État</th><th scope="col">Référence</th></tr></thead><tbody>
                <?php foreach($creationPreview['candidates'] as $token=>$candidate):
                    $row=is_array($creationRows[$token]??null)?$creationRows[$token]:[];
                    $rowValue=static fn(string $key,string $default=''):string=>$creationText($row[$key]??null,$default);
                    $selected=($row['selected']??null)==='1';$manual=$rowValue('sku_mode','auto')==='manual';
                    $rowId='creation-variant-'.substr(hash('sha256',$token),0,12);
                    $prefix='creation_variants['.$token.']';
                ?>
                    <tr class="admin-product-creation__variant" role="row" data-creation-variant>
                        <td role="cell"><label class="ui-choice"><input type="checkbox" name="<?= $escape($prefix) ?>[selected]" value="1"<?= $selected?' checked':'' ?> data-creation-selected aria-label="Vendre <?= $escape($candidate['label']) ?>"><span class="admin-product-creation__mobile-label">Vendre</span></label></td>
                        <th scope="row" role="rowheader"><?= $escape($candidate['label']) ?><small data-creation-selection><?= $selected?'Sélectionnée':'Non sélectionnée' ?></small><?php if(isset($fieldErrors['creation_variants.'.$token])): ?><p class="admin-product-form__error" role="alert"><?= $escape($fieldErrors['creation_variants.'.$token]) ?></p><?php endif; ?></th>
                        <td role="cell"><label><span class="admin-product-creation__field-label">Prix *</span><input name="<?= $escape($prefix) ?>[prix]" value="<?= $escape($rowValue('prix')) ?>" inputmode="decimal" data-creation-price data-creation-row-field aria-label="Prix — <?= $escape($candidate['label']) ?>"></label></td>
                        <td role="cell"><label><span class="admin-product-creation__field-label">Devise</span><select name="<?= $escape($prefix) ?>[devise]" data-creation-row-field aria-label="Devise — <?= $escape($candidate['label']) ?>"><?php foreach(['USD','CDF'] as $currency): ?><option<?= $rowValue('devise','USD')===$currency?' selected':'' ?>><?= $currency ?></option><?php endforeach; ?></select></label></td>
                        <td role="cell"><label><span class="admin-product-creation__field-label">Stock</span><input type="number" name="<?= $escape($prefix) ?>[stock]" value="<?= $escape($rowValue('stock','0')) ?>" min="0" max="4294967295" step="1" data-creation-row-field aria-label="Stock — <?= $escape($candidate['label']) ?>"></label></td>
                        <td role="cell"><label><span class="admin-product-creation__field-label">État</span><select name="<?= $escape($prefix) ?>[statut]" data-creation-row-field aria-label="État — <?= $escape($candidate['label']) ?>"><option value="actif"<?= $rowValue('statut','actif')==='actif'?' selected':'' ?>>Actif</option><option value="inactif"<?= $rowValue('statut')==='inactif'?' selected':'' ?>>Inactif</option></select></label></td>
                        <td role="cell"><details data-creation-advanced<?= $manual||isset($fieldErrors['creation_variants.'.$token])?' open':'' ?>><summary>Réglages avancés<span class="admin-product-creation__sr"> — <?= $escape($candidate['label']) ?></span></summary><label>Référence<select name="<?= $escape($prefix) ?>[sku_mode]" data-creation-sku-mode data-creation-row-field aria-controls="<?= $rowId ?>"><option value="auto"<?= !$manual?' selected':'' ?>>Automatique</option><option value="manual"<?= $manual?' selected':'' ?>>Personnalisée</option></select></label><div id="<?= $rowId ?>" data-creation-sku-manual><label>Référence personnalisée *<input name="<?= $escape($prefix) ?>[sku]" value="<?= $escape($rowValue('sku')) ?>" maxlength="100" data-creation-row-field></label></div></details></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
            <noscript><p>Cochez les versions à vendre et remplissez leurs champs. Les autres versions ne seront pas créées. Votre saisie et les photos préparées sont conservées entre les étapes.</p></noscript>
        </div>
    </details>
</fieldset>
