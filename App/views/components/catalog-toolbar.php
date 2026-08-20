<?php

$catalogToolbarConfig = $catalogToolbar ?? [];
$catalogResultCount = max(0, (int) ($catalogToolbarConfig['resultCount'] ?? 0));
$catalogSortOptions = $catalogToolbarConfig['sortOptions'] ?? [];
$catalogActiveFilters = $catalogToolbarConfig['filters'] ?? [];
$catalogSearch = (string) ($catalogActiveFilters['search'] ?? '');
$catalogSort = (string) ($catalogActiveFilters['sort'] ?? 'newest');
?>

<div class="catalog-toolbar" data-catalog-toolbar data-aos="soft-down">
    <form class="catalog-search" action="/boutique" method="get" role="search" data-catalog-search>
        <label class="visually-hidden" for="catalog-search-input">Rechercher dans la boutique</label>
        <i data-lucide="search" aria-hidden="true"></i>
        <input id="catalog-search-input" type="search" name="q" value="<?= htmlspecialchars($catalogSearch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Rechercher un produit, une catégorie..." autocomplete="off">
        <button class="btn btn-primary" type="submit">Rechercher</button>
    </form>

    <div class="catalog-toolbar__controls">
        <button class="btn btn-outline catalog-toolbar__filter-button" type="button" aria-controls="catalog-filters" aria-expanded="false" data-catalog-filter-open>
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>Filtres
        </button>
        <p class="catalog-toolbar__count" aria-live="polite" data-catalog-count><strong><?= $catalogResultCount ?></strong> produits trouvés</p>
        <div class="catalog-view-switch" role="group" aria-label="Mode d’affichage">
            <button class="catalog-view-switch__button is-active" type="button" aria-label="Affichage en grille" aria-pressed="true" data-catalog-view="grid"><i data-lucide="grid-2x2" aria-hidden="true"></i></button>
            <button class="catalog-view-switch__button" type="button" aria-label="Affichage en liste" aria-pressed="false" data-catalog-view="list"><i data-lucide="list" aria-hidden="true"></i></button>
        </div>
        <label class="catalog-sort" for="catalog-sort-select">
            <span>Trier par</span>
            <select id="catalog-sort-select" name="sort" data-catalog-sort>
                <?php foreach ($catalogSortOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= (string) $value === $catalogSort ? ' selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</div>

<?php unset($catalogToolbar, $catalogToolbarConfig, $catalogResultCount, $catalogSortOptions, $catalogActiveFilters, $catalogSearch, $catalogSort, $value, $label); ?>
