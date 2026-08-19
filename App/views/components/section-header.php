<?php

$sectionHeaderConfig = $sectionHeader ?? [];
$sectionHeaderId = (string) ($sectionHeaderConfig['id'] ?? 'section-title');
$sectionHeaderTitle = (string) ($sectionHeaderConfig['title'] ?? '');
$sectionHeaderDescription = (string) ($sectionHeaderConfig['description'] ?? '');
$sectionHeaderBadge = $sectionHeaderConfig['badge'] ?? null;
$sectionHeaderAction = $sectionHeaderConfig['action'] ?? null;
?>

<div class="section-header">
    <div class="section-header__content">
        <?php if (is_array($sectionHeaderBadge)): ?>
            <?php $badge = $sectionHeaderBadge + ['class' => 'section-badge']; ?>
            <?php require __DIR__ . '/badge.php'; ?>
        <?php endif; ?>

        <h2 class="section-header__title" id="<?= htmlspecialchars($sectionHeaderId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?= htmlspecialchars($sectionHeaderTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h2>

        <?php if ($sectionHeaderDescription !== ''): ?>
            <p class="section-header__description"><?= htmlspecialchars($sectionHeaderDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <?php if (is_array($sectionHeaderAction)): ?>
        <div class="section-header__action">
            <?php $button = $sectionHeaderAction; ?>
            <?php require __DIR__ . '/button.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php unset($sectionHeader, $sectionHeaderConfig, $sectionHeaderId, $sectionHeaderTitle, $sectionHeaderDescription, $sectionHeaderBadge, $sectionHeaderAction); ?>
