<?php

$productSectionConfig = $productSection ?? [];
$productSectionId = (string) ($productSectionConfig['id'] ?? 'products-title');
$productSectionClass = trim('product-section ' . (string) ($productSectionConfig['class'] ?? ''));
$productSectionProducts = $productSectionConfig['products'] ?? [];
$productSectionHeader = $productSectionConfig['header'] ?? [];
$productSectionBanner = $productSectionConfig['banner'] ?? null;
$productSectionFooterAction = $productSectionConfig['footerAction'] ?? null;
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

        <?php if ($productSectionProducts !== []): ?>
            <div class="product-section__grid">
                <?php foreach ($productSectionProducts as $product): ?>
                    <?php require __DIR__ . '/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php $emptyState = $productSectionEmptyState; ?>
            <?php require __DIR__ . '/empty-state.php'; ?>
        <?php endif; ?>

        <?php if ($productSectionProducts !== [] && is_array($productSectionFooterAction)): ?>
            <div class="product-section__footer-action">
                <?php $button = $productSectionFooterAction; ?>
                <?php require __DIR__ . '/button.php'; ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($productSectionPagination) && ($productSectionPagination['total'] ?? 1) > 1): ?>
            <div class="product-section__pagination">
                <?php $pagination = $productSectionPagination; ?>
                <?php require __DIR__ . '/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php unset($productSection, $productSectionConfig, $productSectionId, $productSectionClass, $productSectionProducts, $productSectionHeader, $productSectionBanner, $productSectionFooterAction, $productSectionEmptyState, $productSectionPagination, $button); ?>
