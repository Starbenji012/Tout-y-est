<?php
$id = (int) ($zone['id_zone_livraison'] ?? 0);
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$token = $csrfToken ?? \App\Middleware\CsrfMiddleware::token();
$unknownCurrency = $zone !== null && $zone['devise_reference'] !== 'USD';
?>
<form class="admin-zone-form" action="/api/admin/zones-livraison/enregistrer" method="post" data-zone-form>
    <input type="hidden" name="_token" value="<?= $escape($token) ?>">
    <input type="hidden" name="id_zone_livraison" value="<?= $id ?>">
    <?php if ($unknownCurrency): ?><p>La devise historique doit être clarifiée avant toute modification.</p><?php endif; ?>
    <fieldset <?= $unknownCurrency ? 'disabled' : '' ?>>
        <legend><?= $id ? 'Modifier cette zone' : 'Nouvelle zone' ?></legend>
        <p><label for="zone-<?= $id ?>-name">Nom de l’endroit</label>
            <input id="zone-<?= $id ?>-name" name="nom" value="<?= $escape($zone['nom'] ?? '') ?>" maxlength="120" required aria-describedby="zone-<?= $id ?>-nom-error">
            <small id="zone-<?= $id ?>-nom-error" data-error-for="nom"></small>
        </p>
        <p><label for="zone-<?= $id ?>-fees">Frais de livraison (USD)</label>
            <input id="zone-<?= $id ?>-fees" name="frais_reference" type="text" inputmode="decimal" value="<?= $escape($zone['frais_reference'] ?? '') ?>" required aria-describedby="zone-<?= $id ?>-fees-error">
            <small id="zone-<?= $id ?>-fees-error" data-error-for="frais_reference"></small>
        </p>
        <p><label for="zone-<?= $id ?>-status">Statut</label>
            <span class="form-select"><select id="zone-<?= $id ?>-status" name="statut" aria-describedby="zone-<?= $id ?>-status-error">
                <option value="inactif" <?= ($zone['statut'] ?? 'inactif') === 'inactif' ? 'selected' : '' ?>>Inactive</option>
                <option value="actif" <?= ($zone['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Active</option>
            </select></span>
            <small id="zone-<?= $id ?>-status-error" data-error-for="statut"></small>
        </p>
        <?php $button = ['label' => $id ? 'Enregistrer les modifications' : 'Créer la zone', 'type' => 'submit', 'variant' => 'primary']; require dirname(__DIR__) . '/components/button.php'; ?>
    </fieldset>
    <p role="status" aria-live="polite" tabindex="-1" data-zone-message></p>
    <noscript>Activez JavaScript pour enregistrer les zones sans rechargement.</noscript>
</form>
