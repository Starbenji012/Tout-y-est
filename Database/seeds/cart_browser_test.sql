-- Données locales réservées au test navigateur du panier.
USE tout_y_est;

START TRANSACTION;

INSERT INTO categorie (nom, slug, description, statut)
VALUES (
    '[DEV] Tests panier',
    'dev-tests-panier',
    'Catégorie locale réservée aux contrôles du catalogue et du panier.',
    'actif'
)
ON DUPLICATE KEY UPDATE
    id_categorie = LAST_INSERT_ID(id_categorie),
    nom = VALUES(nom),
    description = VALUES(description),
    statut = VALUES(statut);

SET @dev_category_id = LAST_INSERT_ID();

INSERT INTO produit (id_categorie, nom, slug, description, statut)
VALUES (
    @dev_category_id,
    '[DEV] Sac de test panier',
    'dev-sac-test-panier',
    'Produit local utilisé uniquement pour tester le panier connecté.',
    'actif'
)
ON DUPLICATE KEY UPDATE
    id_produit = LAST_INSERT_ID(id_produit),
    id_categorie = VALUES(id_categorie),
    nom = VALUES(nom),
    description = VALUES(description),
    statut = VALUES(statut);

SET @dev_product_one_id = LAST_INSERT_ID();

INSERT INTO variante_produit (
    id_produit,
    sku,
    prix_reference,
    stock,
    statut,
    description_variante
)
VALUES (
    @dev_product_one_id,
    'DEV-CART-BAG-001',
    45000.00,
    25,
    'actif',
    'Variante de développement pour le test du panier.'
)
ON DUPLICATE KEY UPDATE
    id_produit = VALUES(id_produit),
    prix_reference = VALUES(prix_reference),
    stock = VALUES(stock),
    statut = VALUES(statut),
    description_variante = VALUES(description_variante);

INSERT INTO produit (id_categorie, nom, slug, description, statut)
VALUES (
    @dev_category_id,
    '[DEV] Casque de test panier',
    'dev-casque-test-panier',
    'Produit local utilisé uniquement pour tester les mutations du panier.',
    'actif'
)
ON DUPLICATE KEY UPDATE
    id_produit = LAST_INSERT_ID(id_produit),
    id_categorie = VALUES(id_categorie),
    nom = VALUES(nom),
    description = VALUES(description),
    statut = VALUES(statut);

SET @dev_product_two_id = LAST_INSERT_ID();

INSERT INTO variante_produit (
    id_produit,
    sku,
    prix_reference,
    stock,
    statut,
    description_variante
)
VALUES (
    @dev_product_two_id,
    'DEV-CART-HEADSET-001',
    78500.00,
    40,
    'actif',
    'Variante de développement pour le test du compteur et des quantités.'
)
ON DUPLICATE KEY UPDATE
    id_produit = VALUES(id_produit),
    prix_reference = VALUES(prix_reference),
    stock = VALUES(stock),
    statut = VALUES(statut),
    description_variante = VALUES(description_variante);

COMMIT;

SELECT
    p.id_produit,
    p.nom,
    v.id_variante,
    v.sku,
    v.prix_reference,
    v.stock
FROM produit p
INNER JOIN variante_produit v ON v.id_produit = p.id_produit
WHERE p.slug IN ('dev-sac-test-panier', 'dev-casque-test-panier')
ORDER BY p.id_produit;
