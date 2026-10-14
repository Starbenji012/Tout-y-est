<?php if ($zones === []): ?>
    <p role="status">Aucune zone renseignée. Aucun tarif de livraison n’est disponible.</p>
<?php else: ?>
    <?php foreach ($zones as $zone): ?>
        <details class="admin-zone<?= $zone['statut'] !== 'actif' ? ' admin-zone--inactive' : '' ?>" data-zone-accordion="<?= (int) $zone['id_zone_livraison'] ?>">
            <summary>
                <span class="admin-zone__name"><?= htmlspecialchars($zone['nom'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="admin-zone__fees"><?= htmlspecialchars($zone['formattedFees'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="admin-zone__status"><?= $zone['statut'] === 'actif' ? 'Active' : 'Inactive' ?></span>
            </summary>
            <div class="admin-zone__body"><?php require __DIR__ . '/delivery-zone-form.php'; ?></div>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
