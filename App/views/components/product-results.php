<?php

$productResultsConfig = $productResults ?? [];
$productResultsProducts = $productResultsConfig['products'] ?? [];
$productResultsEmptyState = $productResultsConfig['emptyState'] ?? [];
$productResultsFooterAction = $productResultsConfig['footerAction'] ?? null;
$productResultsPagination = $productResultsConfig['pagination'] ?? null;
?>

<?php if ($productResultsProducts !== []): ?>
    <div class="product-section__grid">
        <?php foreach ($productResultsProducts as $productIndex => $product): ?>
            <?php $productCardAnimationDelay = min($productIndex % 4, 3) * 70; ?>
            <?php require __DIR__ . '/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php $emptyState = $productResultsEmptyState; ?>
    <?php require __DIR__ . '/empty-state.php'; ?>
<?php endif; ?>

<?php if ($productResultsProducts !== [] && is_array($productResultsFooterAction)): ?>
    <div class="product-section__footer-action">
        <?php $button = $productResultsFooterAction; ?>
        <?php require __DIR__ . '/button.php'; ?>
    </div>
<?php endif; ?>

<?php if (is_array($productResultsPagination) && ($productResultsPagination['total'] ?? 1) > 1): ?>
    <div class="product-section__pagination">
        <?php $pagination = $productResultsPagination; ?>
        <?php require __DIR__ . '/pagination.php'; ?>
    </div>
<?php endif; ?>

<?php unset($productResults, $productResultsConfig, $productResultsProducts, $productResultsEmptyState, $productResultsFooterAction, $productResultsPagination, $productIndex, $productCardAnimationDelay, $product, $emptyState, $button, $pagination); ?>
