<?php // Présentation en lecture seule : aucune écriture ni action de gestion. ?>
<section class="admin-product-sales" aria-labelledby="product-variants-title">
    <h2 id="product-variants-title">Informations de vente</h2>
    <?php if ($variants===[]): ?>
        <p>Aucune information de vente enregistrée.</p>
    <?php elseif (count($variants)===1): $variant=$variants[0]; ?>
        <dl class="admin-product-sales__details">
            <div><dt>Référence (SKU)</dt><dd><?= $escape($variant['sku']) ?></dd></div>
            <div><dt>Prix</dt><dd><?= $escape($variant['priceLabel']) ?></dd></div>
            <div><dt>Stock disponible</dt><dd><?= (int)$variant['stock'] ?> unité<?= (int)$variant['stock']===1?'':'s' ?></dd></div>
        </dl>
    <?php else: ?>
        <dl class="admin-product-sales__summary">
            <div><dt>Nombre de variantes</dt><dd><?= (int)$salesSummary['count'] ?></dd></div>
            <div><dt>Stock total des variantes actives</dt><dd><?= (int)$salesSummary['stock'] ?> unité<?= (int)$salesSummary['stock']===1?'':'s' ?></dd></div>
            <div><dt>Prix des variantes actives</dt><dd><?= $escape($salesSummary['priceLabel']) ?></dd></div>
        </dl>
        <details class="ui-disclosure" id="product-sales-details" data-sales-disclosure>
            <summary class="btn btn-secondary" role="button" aria-controls="product-sales-variants"><span class="ui-disclosure__more">Voir plus</span><span class="ui-disclosure__less">Voir moins</span><span class="visually-hidden"> — détails des variantes</span></summary>
            <div class="admin-product-sales__variants ui-table-region" id="product-sales-variants" tabindex="0" role="region" aria-label="Détails des variantes enregistrées">
                <table class="ui-table ui-table--stacked">
                    <caption>Variantes enregistrées — consultation uniquement</caption>
                    <thead><tr><th scope="col">Combinaison</th><th scope="col">Référence SKU</th><th scope="col">Prix</th><th scope="col">Stock</th><th scope="col">Statut</th></tr></thead>
                    <tbody><?php foreach ($variants as $variant): ?>
                        <tr>
                            <td data-label="Combinaison"><?= $escape(trim((string)($variant['description_variante']??''))!==''?$variant['description_variante']:'Combinaison non renseignée') ?></td>
                            <td data-label="Référence SKU"><?= $escape($variant['sku']) ?></td>
                            <td data-label="Prix"><?= $escape($variant['priceLabel']) ?></td>
                            <td data-label="Stock"><?= (int)$variant['stock'] ?></td>
                            <td data-label="Statut"><?= $escape($variant['statusLabel']) ?></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>
</section>
