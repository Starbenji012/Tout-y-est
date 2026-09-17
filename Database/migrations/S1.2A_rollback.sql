-- S1.2-A - Rollback
-- MariaDB 10.4.32 - SCRIPT DE REVUE, NON EXECUTE
-- Utilisable uniquement si aucune nouvelle donnee metier n'a ete ecrite.

USE tout_y_est;

-- PRECONDITIONS : ces compteurs doivent etre nuls.
SELECT COUNT(*) AS lignes_panier FROM ligne_panier;
SELECT COUNT(*) AS favoris FROM favori;

-- Retirer les FK externes vers les tables nouvelles.
ALTER TABLE alerte_stock DROP FOREIGN KEY fk_alerte_stock_variante;
ALTER TABLE avis DROP FOREIGN KEY fk_avis_produit;
ALTER TABLE `valeur_caractéristique` DROP FOREIGN KEY fk_valeur_caracteristique_produit;
ALTER TABLE `caractéristique` DROP FOREIGN KEY fk_caracteristique_categorie;

-- Retirer les FK des tables nouvelles.
ALTER TABLE favori DROP FOREIGN KEY fk_favori_utilisateur, DROP FOREIGN KEY fk_favori_produit;
ALTER TABLE ligne_panier DROP FOREIGN KEY fk_ligne_panier_panier, DROP FOREIGN KEY fk_ligne_panier_variante;
ALTER TABLE panier DROP FOREIGN KEY fk_panier_utilisateur;
ALTER TABLE beneficier DROP FOREIGN KEY fk_beneficier_produit, DROP FOREIGN KEY fk_beneficier_promotion;
ALTER TABLE variante_produit DROP FOREIGN KEY fk_variante_produit_produit;
ALTER TABLE produit DROP FOREIGN KEY fk_produit_categorie;

-- Enfants avant parents.
DROP TABLE favori;
DROP TABLE ligne_panier;
DROP TABLE panier;
DROP TABLE beneficier;
DROP TABLE variante_produit;
DROP TABLE produit;
DROP TABLE promotion;

-- Restaurer les tables legacy conservees par la migration.
RENAME TABLE
    produit_legacy_s12a TO produit,
    variante_produit_legacy_s12a TO variante_produit,
    promotion_legacy_s12a TO promotion,
    benefici_legacy_s12a TO benefici,
    panier_legacy_s12a TO panier,
    ligne_panier_legacy_s12a TO ligne_panier,
    favori_legacy_s12a TO favori,
    aime_legacy_s12a TO aime;

-- Restaurer categorie : slug, types et index legacy.
ALTER TABLE categorie
    DROP INDEX uq_categorie_slug,
    CHANGE slug slug_ VARCHAR(120) NOT NULL,
    MODIFY id_categorie BIGINT NOT NULL,
    ADD UNIQUE KEY nom (nom),
    ADD UNIQUE KEY slug_ (slug_);

-- Restaurer les types legacy des colonnes dependantes.
ALTER TABLE utilisateur
    MODIFY id_utilisateur BIGINT NOT NULL,
    MODIFY telephone VARCHAR(30) NOT NULL,
    MODIFY role VARCHAR(20) NOT NULL,
    MODIFY statut VARCHAR(20) NOT NULL,
    MODIFY date_creation DATETIME NOT NULL;
ALTER TABLE panier MODIFY id_utilisateur BIGINT NOT NULL;
ALTER TABLE aime MODIFY id_utilisateur BIGINT NOT NULL;
ALTER TABLE alerte_stock MODIFY id_variante BIGINT NOT NULL, MODIFY id_utilisateur BIGINT NOT NULL;
ALTER TABLE avis MODIFY id_produit BIGINT NOT NULL, MODIFY id_utilisateur BIGINT NOT NULL;
ALTER TABLE commande MODIFY id_utilisateur BIGINT NOT NULL;
ALTER TABLE `valeur_caractéristique` MODIFY id_produit BIGINT NOT NULL;
ALTER TABLE `caractéristique` MODIFY id_categorie BIGINT NOT NULL;
ALTER TABLE variante_produit MODIFY id_ligne_commande BIGINT NOT NULL, MODIFY id_produit BIGINT NOT NULL;
ALTER TABLE benefici MODIFY id_produit BIGINT NOT NULL, MODIFY id_promotion BIGINT NOT NULL;
ALTER TABLE produit MODIFY id_image BIGINT NOT NULL, MODIFY id_categorie BIGINT NOT NULL;

-- Restaurer les FK legacy.
ALTER TABLE panier ADD CONSTRAINT panier_ibfk_1 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur);
ALTER TABLE aime
    ADD CONSTRAINT aime_ibfk_1 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur),
    ADD CONSTRAINT aime_ibfk_2 FOREIGN KEY (id_favori) REFERENCES favori (id_favori);
ALTER TABLE alerte_stock
    ADD CONSTRAINT alerte_stock_ibfk_1 FOREIGN KEY (id_variante) REFERENCES variante_produit (id_variante),
    ADD CONSTRAINT alerte_stock_ibfk_2 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur);
ALTER TABLE avis
    ADD CONSTRAINT avis_ibfk_1 FOREIGN KEY (id_produit) REFERENCES produit (id_produit),
    ADD CONSTRAINT avis_ibfk_2 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur);
ALTER TABLE commande ADD CONSTRAINT commande_ibfk_1 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur);
ALTER TABLE `valeur_caractéristique`
    ADD CONSTRAINT `valeur_caractéristique_ibfk_2` FOREIGN KEY (id_produit) REFERENCES produit (id_produit);
ALTER TABLE `caractéristique`
    ADD CONSTRAINT `caractéristique_ibfk_1` FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie);
ALTER TABLE variante_produit
    ADD CONSTRAINT variante_produit_ibfk_1 FOREIGN KEY (id_ligne_commande) REFERENCES ligne_commande (id_ligne_commande),
    ADD CONSTRAINT variante_produit_ibfk_2 FOREIGN KEY (id_produit) REFERENCES produit (id_produit);
ALTER TABLE benefici
    ADD CONSTRAINT benefici_ibfk_1 FOREIGN KEY (id_produit) REFERENCES produit (id_produit),
    ADD CONSTRAINT benefici_ibfk_2 FOREIGN KEY (id_promotion) REFERENCES promotion (id_promotion);
ALTER TABLE produit
    ADD CONSTRAINT produit_ibfk_1 FOREIGN KEY (id_image) REFERENCES image_produit (id_image),
    ADD CONSTRAINT produit_ibfk_2 FOREIGN KEY (id_categorie) REFERENCES categorie (id_categorie);

-- Restaurer l'index legacy telephone UNIQUE.
ALTER TABLE utilisateur ADD UNIQUE KEY telephone (telephone);
