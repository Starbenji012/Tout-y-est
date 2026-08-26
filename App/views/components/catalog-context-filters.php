<?php

$catalogContextFiltersConfig = $catalogContextFilters ?? [];
$contextFacets = $catalogContextFiltersConfig['facets'] ?? [];
$contextActiveAttributes = $catalogContextFiltersConfig['filters']['attributes'] ?? [];
?>

<?php foreach ($contextFacets as $facetKey => $facet): ?>
    <fieldset class="catalog-filter-group">
        <legend><?= htmlspecialchars((string) $facet['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></legend>
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
<?php endforeach; ?>

<?php unset($catalogContextFilters, $catalogContextFiltersConfig, $contextFacets, $contextActiveAttributes, $facetKey, $facet, $option, $optionValue); ?>
