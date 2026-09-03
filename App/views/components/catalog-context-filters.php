<?php

$catalogContextFiltersConfig = $catalogContextFilters ?? [];
$contextFacets = $catalogContextFiltersConfig['facets'] ?? [];
$contextActiveAttributes = $catalogContextFiltersConfig['filters']['attributes'] ?? [];
$contextCategories = $catalogContextFiltersConfig['filters']['categories'] ?? [];
$facetPosition = 0;
?>

<?php if ($contextFacets === []): ?>
    <p class="catalog-filters__context-hint">
        <?php if ($contextCategories === []): ?>
            Choisissez une catégorie pour afficher ses filtres spécifiques.
        <?php elseif (count($contextCategories) > 1): ?>
            Choisissez une seule catégorie pour affiner les caractéristiques.
        <?php else: ?>
            Aucun filtre spécifique n'est disponible pour cette catégorie.
        <?php endif; ?>
    </p>
<?php else: ?>
    <?php foreach ($contextFacets as $facetKey => $facet): ?>
        <?php
        $facetLabel = (string) ($facet['label'] ?? 'Caractéristique');
        $facetHasSelection = ($contextActiveAttributes[$facetKey] ?? []) !== [];
        ?>
        <details class="catalog-filter-group catalog-filter-group--context"<?= $facetHasSelection || $facetPosition < 2 ? ' open' : '' ?>>
            <summary><?= htmlspecialchars($facetLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></summary>
            <fieldset>
                <legend class="visually-hidden"><?= htmlspecialchars($facetLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></legend>
                <?php foreach ($facet['options'] ?? [] as $option): ?>
                    <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                    <label class="catalog-filter-option">
                        <input
                            type="checkbox"
                            name="attributes[<?= htmlspecialchars((string) $facetKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][]"
                            value="<?= htmlspecialchars($optionValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            <?= in_array($optionValue, $contextActiveAttributes[$facetKey] ?? [], true) ? 'checked' : '' ?>
                        >
                        <span><?= htmlspecialchars((string) ($option['label'] ?? $optionValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <small><?= max(0, (int) ($option['count'] ?? 0)) ?></small>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </details>
        <?php $facetPosition++; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php unset($catalogContextFilters, $catalogContextFiltersConfig, $contextFacets, $contextActiveAttributes, $contextCategories, $facetPosition, $facetKey, $facetLabel, $facetHasSelection, $facet, $option, $optionValue); ?>
