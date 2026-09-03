<?php

// Prépare une pagination courte qui garde toujours la première et la dernière page.
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
        <!-- Le lien précédent n'est utile qu'après la première page. -->
        <?php if ($paginationCurrent > 1): ?>
            <li><a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, $paginationCurrent - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Page précédente" data-page="<?= $paginationCurrent - 1 ?>"><i data-lucide="chevron-left" aria-hidden="true"></i></a></li>
        <?php endif; ?>
        <!-- La page actuelle reste visible mais n'est pas cliquable. -->
        <?php foreach ($paginationPages as $page): ?>
            <?php if ($paginationPreviousPage !== null && $page > $paginationPreviousPage + 1): ?>
                <li class="pagination__ellipsis" aria-hidden="true">…</li>
            <?php endif; ?>
            <li>
                <?php if ($page === $paginationCurrent): ?>
                    <span class="pagination__link" aria-current="page"><?= $page ?></span>
                <?php else: ?>
                    <a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, $page), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-page="<?= $page ?>"><?= $page ?></a>
                <?php endif; ?>
            </li>
            <?php $paginationPreviousPage = $page; ?>
        <?php endforeach; ?>
        <!-- Le lien suivant disparaît lorsque le catalogue est terminé. -->
        <?php if ($paginationCurrent < $paginationTotal): ?>
            <li><a class="pagination__link" href="<?= htmlspecialchars(sprintf($paginationUrl, $paginationCurrent + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Page suivante" data-page="<?= $paginationCurrent + 1 ?>"><i data-lucide="chevron-right" aria-hidden="true"></i></a></li>
        <?php endif; ?>
    </ul>
</nav>

<?php unset($pagination, $paginationConfig, $paginationCurrent, $paginationTotal, $paginationUrl, $paginationPages, $paginationPreviousPage, $page); ?>
