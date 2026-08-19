<?php

$emptyStateConfig = $emptyState ?? [];
$emptyStateTitle = (string) ($emptyStateConfig['title'] ?? 'Aucun produit disponible');
$emptyStateText = (string) ($emptyStateConfig['text'] ?? 'De nouveaux produits seront bientôt disponibles.');
$emptyStateIcon = preg_match('/^[a-z0-9-]+$/', (string) ($emptyStateConfig['icon'] ?? 'package-open')) ? (string) $emptyStateConfig['icon'] : 'package-open';
$emptyStateAction = $emptyStateConfig['action'] ?? null;
?>

<div class="empty-state" role="status">
    <div class="empty-state__illustration" aria-hidden="true">
        <i data-lucide="<?= $emptyStateIcon ?>"></i>
    </div>
    <h3 class="empty-state__title"><?= htmlspecialchars($emptyStateTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
    <p class="empty-state__description"><?= htmlspecialchars($emptyStateText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>

    <?php if (is_array($emptyStateAction)): ?>
        <?php $button = $emptyStateAction; ?>
        <?php require __DIR__ . '/button.php'; ?>
    <?php endif; ?>
</div>

<?php unset($emptyState, $emptyStateConfig, $emptyStateTitle, $emptyStateText, $emptyStateIcon, $emptyStateAction); ?>
