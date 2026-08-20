<?php

$catalogFiltersConfig = $catalogFilters ?? [];
$catalogCategories = $catalogFiltersConfig['categories'] ?? [];
$catalogStatuses = $catalogFiltersConfig['statuses'] ?? [];
$catalogActiveFilters = $catalogFiltersConfig['filters'] ?? [];
$activeCategories = $catalogActiveFilters['categories'] ?? [];
$activeStatuses = $catalogActiveFilters['statuses'] ?? [];
$activeAvailability = (string) ($catalogActiveFilters['availability'] ?? '');
$activeRating = (int) ($catalogActiveFilters['rating'] ?? 0);
?>

<aside class="catalog-filters" id="catalog-filters" aria-labelledby="catalog-filters-title" data-catalog-filters-panel>
    <div class="catalog-filters__header">
        <h2 id="catalog-filters-title">Filtrer les produits</h2>
        <button class="catalog-filters__close" type="button" aria-label="Fermer les filtres" data-catalog-filter-close><i data-lucide="x" aria-hidden="true"></i></button>
    </div>
    <form class="catalog-filters__form" data-catalog-filters>
        <fieldset class="catalog-filter-group">
            <legend>Catégorie</legend>
            <?php foreach ($catalogCategories as $value => $label): ?>
                <label class="catalog-filter-option"><input type="checkbox" name="categories[]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array((string) $value, $activeCategories, true) ? ' checked' : '' ?>><span><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="catalog-filter-group">
            <legend>Prix</legend>
            <div class="catalog-price-range">
                <label><span>Minimum</span><input type="number" name="price_min" min="0" inputmode="numeric" placeholder="0" value="<?= htmlspecialchars((string) ($catalogActiveFilters['priceMin'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label>
                <label><span>Maximum</span><input type="number" name="price_max" min="0" inputmode="numeric" placeholder="FCFA" value="<?= htmlspecialchars((string) ($catalogActiveFilters['priceMax'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label>
            </div>
        </fieldset>
        <fieldset class="catalog-filter-group">
            <legend>Offres</legend>
            <?php foreach ($catalogStatuses as $value => $label): ?>
                <label class="catalog-filter-option"><input type="checkbox" name="statuses[]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= in_array((string) $value, $activeStatuses, true) ? ' checked' : '' ?>><span><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="catalog-filter-group">
            <legend>Disponibilité</legend>
            <?php foreach (['' => 'Tous les produits', 'in-stock' => 'En stock', 'out-of-stock' => 'Rupture de stock'] as $value => $label): ?>
                <label class="catalog-filter-option"><input type="radio" name="availability" value="<?= $value ?>"<?= $value === $activeAvailability ? ' checked' : '' ?>><span><?= $label ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="catalog-filter-group">
            <legend>Note minimale</legend>
            <?php foreach ([0 => 'Toutes les notes', 4 => '4 étoiles et plus', 3 => '3 étoiles et plus'] as $value => $label): ?>
                <label class="catalog-filter-option"><input type="radio" name="rating" value="<?= $value ?>"<?= $value === $activeRating ? ' checked' : '' ?>><span><?= $label ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <?php $button = ['label' => 'Réinitialiser', 'variant' => 'ghost', 'type' => 'reset']; require __DIR__ . '/button.php'; ?>
    </form>
</aside>
<button class="catalog-filters-overlay" type="button" aria-label="Fermer les filtres" data-catalog-filter-close tabindex="-1"></button>

<?php unset($catalogFilters, $catalogFiltersConfig, $catalogCategories, $catalogStatuses, $catalogActiveFilters, $activeCategories, $activeStatuses, $activeAvailability, $activeRating, $value, $label, $button); ?>
