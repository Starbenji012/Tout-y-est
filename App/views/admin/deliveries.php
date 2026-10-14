<?php
$sectionHeader = ['id' => 'delivery-admin-title', 'headingLevel' => 1, 'title' => 'Zones de livraison', 'description' => 'Gérer les endroits et leurs frais de livraison en USD.'];
?>
<section class="admin-zones" aria-labelledby="delivery-admin-title" data-delivery-admin>
    <div class="container">
        <div class="admin-zones__heading">
            <?php require dirname(__DIR__) . '/components/section-header.php'; ?>
            <?php $button = ['label' => 'Nouvelle zone', 'type' => 'button', 'variant' => 'primary', 'icon' => 'plus', 'attributes' => ['aria-expanded' => 'false', 'aria-controls' => 'delivery-create', 'data-zone-create-toggle' => true]]; require dirname(__DIR__) . '/components/button.php'; ?>
        </div>
        <section class="admin-zone admin-zone--create" id="delivery-create" data-zone-create hidden aria-label="Créer une zone de livraison">
            <div class="admin-zone__body">
                <?php $zone = null; require __DIR__ . '/delivery-zone-form.php'; ?>
                <?php $button = ['label' => 'Fermer', 'type' => 'button', 'variant' => 'ghost', 'attributes' => ['data-zone-create-close' => true]]; require dirname(__DIR__) . '/components/button.php'; ?>
            </div>
        </section>
        <section aria-labelledby="delivery-list-title">
            <h2 id="delivery-list-title">Zones existantes</h2>
            <p class="admin-zones__help">Une zone inactive reste enregistrée mais n’est plus proposée au Checkout.</p>
            <div class="admin-zone-list" data-zone-list><?php require __DIR__ . '/delivery-zones-list.php'; ?></div>
        </section>
    </div>
</section>
