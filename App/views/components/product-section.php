<?php

$productSectionConfig = $productSection ?? [];
$productSectionId = (string) ($productSectionConfig['id'] ?? 'products-title');
$productSectionClass = trim('product-section ' . (string) ($productSectionConfig['class'] ?? ''));
$productSectionProducts = $productSectionConfig['products'] ?? [];
$productSectionHeader = $productSectionConfig['header'] ?? [];
$productSectionBanner = $productSectionConfig['banner'] ?? null;
$productSectionFooterAction = $productSectionConfig['footerAction'] ?? null;
$productSectionCatalog = $productSectionConfig['catalog'] ?? null;
$productSectionEmptyState = $productSectionConfig['emptyState'] ?? [];
$productSectionPagination = $productSectionConfig['pagination'] ?? null;
?>

<section class="<?= htmlspecialchars($productSectionClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($productSectionId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-product-section>
    <div class="container product-section__container">
        <?php $sectionHeader = ['id' => $productSectionId] + $productSectionHeader; ?>
        <?php require __DIR__ . '/section-header.php'; ?>

        <?php if (is_array($productSectionBanner)): ?>
            <?php $promotionBanner = $productSectionBanner; ?>
            <?php require __DIR__ . '/promotion-banner.php'; ?>
        <?php endif; ?>

        <?php if (is_array($productSectionCatalog)): ?>
            <?php $catalogToolbar = $productSectionCatalog; ?>
            <?php require __DIR__ . '/catalog-toolbar.php'; ?>

            <div class="catalog-layout" data-product-catalog>
                <?php $catalogFilters = $productSectionCatalog; ?>
                <?php require __DIR__ . '/catalog-filters.php'; ?>
                <div class="catalog-results" data-catalog-results aria-live="polite">
                    <div class="catalog-results__loading" data-catalog-loader hidden>
                        <?php $loader = ['label' => 'Mise à jour du catalogue']; ?>
                        <?php require __DIR__ . '/loader.php'; ?>
                    </div>
                    <div data-catalog-content>
        <?php endif; ?>

        <?php
        $productResults = [
            'products' => $productSectionProducts,
            'emptyState' => $productSectionEmptyState,
            'footerAction' => $productSectionFooterAction,
            'pagination' => $productSectionPagination,
        ];
        require __DIR__ . '/product-results.php';
        ?>

        <?php if (is_array($productSectionCatalog)): ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php unset($productSection, $productSectionConfig, $productSectionId, $productSectionClass, $productSectionProducts, $productSectionHeader, $productSectionBanner, $productSectionFooterAction, $productSectionCatalog, $productSectionEmptyState, $productSectionPagination, $catalogToolbar, $catalogFilters, $productResults, $loader); ?>
