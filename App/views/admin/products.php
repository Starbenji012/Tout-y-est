<?php
$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$pageUrl=static fn(int $number):string=>$paginationUrl.(str_ends_with($paginationUrl,'?')?'':'&').'page='.$number;
?>
<section class="container admin-products" aria-labelledby="admin-products-title">
    <header><h1 id="admin-products-title">Produits</h1><p>Consultez le catalogue, y compris les brouillons et les archives.</p></header>
    <a class="btn btn-primary" href="/admin/produit/ajouter">Ajouter un produit</a>
    <form class="admin-products__filters" method="get" action="/admin/produits" role="search">
        <label>Nom ou SKU<input type="search" name="q" maxlength="100" value="<?= $escape($filters['q']) ?>"></label>
        <label><span id="admin-product-status-label" data-select-label>Statut</span><select id="admin-product-status" name="statut" data-select-control>
            <?php foreach ([''=>'Tous les statuts','actif'=>'Actif','brouillon'=>'Brouillon','archive'=>'Archivé'] as $value=>$label): ?>
                <option value="<?= $escape($value) ?>"<?= $filters['statut']===$value?' selected':'' ?>><?= $escape($label) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label><span id="admin-product-category-label" data-select-label>Catégorie</span><select id="admin-product-category" name="categorie" data-select-control><option value="">Toutes les catégories</option>
            <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id_categorie'] ?>"<?= $filters['categorie']===(int)$category['id_categorie']?' selected':'' ?>><?= $escape($category['nom']) ?></option><?php endforeach; ?>
        </select></label>
        <button class="btn btn-primary" type="submit">Rechercher</button>
        <?php if ($filters['q']!==''||$filters['statut']!==''||$filters['categorie']!==null): ?><a class="btn btn-outline" href="/admin/produits">Effacer les filtres</a><?php endif; ?>
    </form>
    <p role="status"><?= (int)$total ?> produit<?= $total===1?'':'s' ?> trouvé<?= $total===1?'':'s' ?></p>
    <p class="admin-products__note">Les prix et le stock disponible concernent les variantes actives. Le stock des autres variantes est indiqué à part.</p>
    <?php if ($products===[]): ?><div class="admin-order-card"><h2>Aucun produit trouvé</h2><p>Essayez une autre recherche ou retirez les filtres.</p></div>
    <?php else: ?><div class="admin-products__list">
        <div class="admin-products__head" aria-hidden="true"><span>Photo</span><span>Produit</span><span>Statut</span><span>Variantes</span><span>Prix</span><span>Stock disponible</span></div>
        <?php foreach ($products as $product): ?>
            <article class="admin-products__item">
                <div class="admin-products__intro">
                <div class="admin-products__image">
                    <?php if ($product['image']!==null): ?><img src="<?= $escape($product['image']) ?>" alt="<?= $escape($product['nom']) ?>" width="56" height="56" loading="lazy"><?php else: ?><span>Sans image</span><?php endif; ?>
                </div>
                <div class="admin-products__identity"><h2><?= $escape($product['nom']) ?></h2><p><?= $escape($product['categorie']) ?></p><a class="admin-products__edit" href="/admin/produit?id=<?= (int)$product['id_produit'] ?>">Modifier<span class="visually-hidden"> <?= $escape($product['nom']) ?></span></a></div>
                </div>
                <dl><dt>Statut</dt><dd><span class="admin-products__status"><?= $escape($product['statusLabel']) ?></span></dd></dl>
                <dl><dt>Variantes</dt><dd><?= (int)$product['variantCount'] ?></dd></dl>
                <dl><dt>Prix</dt><dd><?= $escape($product['price']) ?></dd></dl>
                <dl><dt>Stock disponible</dt><dd><strong><?= (int)$product['stock'] ?></strong><?php if ($product['inactiveStock']>0): ?><small class="admin-products__inactive-stock">+ <?= (int)$product['inactiveStock'] ?> sur variantes inactives</small><?php endif; ?></dd></dl>
            </article>
        <?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($pages>1): ?><nav class="admin-orders__pagination" aria-label="Pagination produits">
        <?php if ($page>1): ?><a class="btn btn-outline" href="<?= $escape($pageUrl($page-1)) ?>">Précédent</a><?php endif; ?>
        <span>Page <?= (int)$page ?> sur <?= (int)$pages ?></span>
        <?php if ($page<$pages): ?><a class="btn btn-outline" href="<?= $escape($pageUrl($page+1)) ?>">Suivant</a><?php endif; ?>
    </nav><?php endif; ?>
</section>
