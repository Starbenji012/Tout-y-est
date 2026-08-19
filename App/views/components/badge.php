<?php

$badgeConfig = $badge ?? [];
$badgeLabel = trim((string) ($badgeConfig['label'] ?? ''));
$badgeVariant = (string) ($badgeConfig['variant'] ?? 'new');
$badgeVariants = ['new', 'promotion', 'limited', 'popular'];
$badgeVariant = in_array($badgeVariant, $badgeVariants, true) ? $badgeVariant : 'new';
$badgeClass = trim('badge badge-' . $badgeVariant . ' ' . (string) ($badgeConfig['class'] ?? ''));

if ($badgeLabel === '') {
    return;
}
?>

<span class="<?= htmlspecialchars($badgeClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?= htmlspecialchars($badgeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</span>

<?php unset($badge, $badgeConfig, $badgeLabel, $badgeVariant, $badgeVariants, $badgeClass); ?>
