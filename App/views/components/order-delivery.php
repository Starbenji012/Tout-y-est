<?php
// Ce composant accepte uniquement le snapshot de livraison préparé par le service.
$deliveryLabels = ['repere_snapshot'=>'Repère','instructions_snapshot'=>'Instructions'];
?>
<?php if ($delivery): ?><div class="order-delivery">
    <div class="order-delivery__recipient"><strong><?= $escape($delivery['prenom_destinataire_snapshot'].' '.$delivery['nom_destinataire_snapshot']) ?></strong><span><span class="visually-hidden">Téléphone : </span><?= $escape($delivery['telephone_snapshot']) ?></span></div>
    <div class="order-delivery__address"><p><?= $escape($delivery['adresse_snapshot']) ?>, <?= $escape($delivery['commune_snapshot']) ?></p><?php if (!empty($delivery['nom_zone_snapshot'])): ?><p class="order-delivery__secondary">Zone : <?= $escape($delivery['nom_zone_snapshot']) ?></p><?php endif; ?></div>
    <?php if (!empty($delivery['repere_snapshot']) || !empty($delivery['instructions_snapshot'])): ?><dl class="order-delivery__notes">
    <?php foreach ($deliveryLabels as $field=>$label): ?>
        <?php if (!empty($delivery[$field])): ?><div><dt><?= $label ?></dt><dd><?= $escape($delivery[$field]) ?></dd></div><?php endif; ?>
    <?php endforeach; ?>
    </dl><?php endif; ?>
</div><?php else: ?><p>Les informations historiques de livraison ne sont pas disponibles.</p><?php endif; ?>
<?php unset($deliveryLabels, $field, $label); ?>
