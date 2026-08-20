<?php

$breadcrumbItems = ($breadcrumb ?? [])['items'] ?? [];
?>

<?php if ($breadcrumbItems !== []): ?>
    <nav class="breadcrumb" aria-label="Fil d’Ariane">
        <ol class="breadcrumb__list">
            <?php foreach ($breadcrumbItems as $index => $item): ?>
                <li class="breadcrumb__item">
                    <?php if (!empty($item['href']) && $index < count($breadcrumbItems) - 1): ?>
                        <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>

<?php unset($breadcrumb, $breadcrumbItems, $index, $item); ?>
