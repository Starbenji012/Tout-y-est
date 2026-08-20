<?php

$paginationConfig = $pagination ?? [];
$paginationCurrent = max(1, (int) ($paginationConfig['current'] ?? 1));
$paginationTotal = max(1, (int) ($paginationConfig['total'] ?? 1));
$paginationCurrent = min($paginationCurrent, $paginationTotal);
$paginationUrl = (string) ($paginationConfig['url'] ?? '?page=%d');
$paginationPages = range(max(1, $paginationCurrent - 2), min($paginationTotal, $paginationCurrent + 2));
$paginationPages = array_values(array_unique([1, ...$paginationPages, $paginationTotal]));
sort($paginationPages);
$paginationPreviousPage = null;
?>

<nav class="pagination" aria-label="Pagination">
    <ul class="pagination__list">
        <li><a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, max(1, $paginationCurrent - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Page précédente" <?= $paginationCurrent === 1 ? 'aria-disabled="true"' : '' ?> data-page="<?= max(1, $paginationCurrent - 1) ?>"><i data-lucide="chevron-left" aria-hidden="true"></i></a></li>
        <?php foreach ($paginationPages as $page): ?>
            <?php if ($paginationPreviousPage !== null && $page > $paginationPreviousPage + 1): ?>
                <li class="pagination__ellipsis" aria-hidden="true">…</li>
            <?php endif; ?>
            <li><a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, $page), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $page === $paginationCurrent ? 'aria-current="page"' : '' ?> data-page="<?= $page ?>"><?= $page ?></a></li>
            <?php $paginationPreviousPage = $page; ?>
        <?php endforeach; ?>
        <li><a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, min($paginationTotal, $paginationCurrent + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Page suivante" <?= $paginationCurrent === $paginationTotal ? 'aria-disabled="true"' : '' ?> data-page="<?= min($paginationTotal, $paginationCurrent + 1) ?>"><i data-lucide="chevron-right" aria-hidden="true"></i></a></li>
    </ul>
</nav>

<?php unset($pagination, $paginationConfig, $paginationCurrent, $paginationTotal, $paginationUrl, $paginationPages, $paginationPreviousPage, $page); ?>
