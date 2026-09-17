-- S1.2-A - Migration socle catalogue, panier et favoris
-- MariaDB 10.4.32 - SCRIPT DE REVUE, NON EXECUTE
-- Le fichier doit etre encode en UTF-8.

USE tout_y_est;

-- PREFLIGHT : chaque compteur doit rester conforme a l'audit.
SELECT VERSION();
SELECT COUNT(*) AS utilisateurs FROM utilisateur; -- attendu: 1
SELECT COUNT(*) AS categories FROM categorie; -- attendu: 0
SELECT COUNT(*) AS produits FROM produit; -- attendu: 0
SELECT COUNT(*) AS variantes FROM variante_produit; -- attendu: 0
SELECT COUNT(*) AS promotions FROM promotion; -- attendu: 0
SELECT COUNT(*) AS associations_promotion FROM benefici; -- attendu: 0
SELECT COUNT(*) AS paniers FROM panier; -- attendu: 0
SELECT COUNT(*) AS lignes_panier FROM ligne_panier; -- attendu: 0
SELECT COUNT(*) AS favoris FROM favori; -- attendu: 0

-- Arreter manuellement si un compteur differe des valeurs attendues.
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'produit_legacy_s12a', 'variante_produit_legacy_s12a',
      'promotion_legacy_s12a', 'benefici_legacy_s12a',
      'panier_legacy_s12a', 'ligne_panier_legacy_s12a',
      'favori_legacy_s12a', 'aime_legacy_s12a'
  );
-- Le resultat doit etre vide.

-- Verifier avant execution les 16 FK legacy dans INFORMATION_SCHEMA.
-- Aucun SET FOREIGN_KEY_CHECKS=0 n'est utilise.

ALTER TABLE panier DROP FOREIGN KEY panier_ibfk_1;
ALTER TABLE aime DROP FOREIGN KEY aime_ibfk_1, DROP FOREIGN KEY aime_ibfk_2;
ALTER TABLE alerte_stock DROP FOREIGN KEY alerte_stock_ibfk_1, DROP FOREIGN KEY alerte_stock_ibfk_2;
ALTER TABLE avis DROP FOREIGN KEY avis_ibfk_1, DROP FOREIGN KEY avis_ibfk_2;
ALTER TABLE commande DROP FOREIGN KEY commande_ibfk_1;
ALTER TABLE `valeur_caractéristique` DROP FOREIGN KEY `valeur_caractéristique_ibfk_2`;
ALTER TABLE `caractéristique` DROP FOREIGN KEY `caractéristique_ibfk_1`;
ALTER TABLE variante_produit DROP FOREIGN KEY variante_produit_ibfk_1, DROP FOREIGN KEY variante_produit_ibfk_2;
ALTER TABLE benefici DROP FOREIGN KEY benefici_ibfk_1, DROP FOREIGN KEY benefici_ibfk_2;
ALTER TABLE produit DROP FOREIGN KEY produit_ibfk_1, DROP FOREIGN KEY produit_ibfk_2;

-- utilisateur.telephone reste nullable et UNIQUE.
ALTER TABLE utilisateur
    MODIFY id_utilisateur BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY telephone VARCHAR(30) NULL;

-- categorie.nom devient non unique; categorie.slug reste unique.
ALTER TABLE categorie
    DROP INDEX nom,
    DROP INDEX slug_,
    CHANGE slug_ slug VARCHAR(120) NOT NULL,
    MODIFY id_categorie BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD UNIQUE KEY uq_categorie_slug (slug);

ALTER TABLE aime MODIFY id_utilisateur BIGINT UNSIGNED NOT NULL;
ALTER TABLE alerte_stock MODIFY id_variante BIGINT UNSIGNED NOT NULL, MODIFY id_utilisateur BIGINT UNSIGNED NOT NULL;
ALTER TABLE avis MODIFY id_produit BIGINT UNSIGNED NOT NULL, MODIFY id_utilisateur BIGINT UNSIGNED NOT NULL;
ALTER TABLE commande MODIFY id_utilisateur BIGINT UNSIGNED NOT NULL;
ALTER TABLE `valeur_caractéristique` MODIFY id_produit BIGINT UNSIGNED NOT NULL;
ALTER TABLE `caractéristique` MODIFY id_categorie BIGINT UNSIGNED NOT NULL;

RENAME TABLE
    produit TO produit_legacy_s12a,
    variante_produit TO variante_produit_legacy_s12a,
    promotion TO promotion_legacy_s12a,
    benefici TO benefici_legacy_s12a,
    panier TO panier_legacy_s12a,
    ligne_panier TO ligne_panier_legacy_s12a,
    favori TO favori_legacy_s12a,
    aime TO aime_legacy_s12a;

CREATE TABLE produit (
    id_produit BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_categorie BIGINT UNSIGNED NOT NULL,
    nom VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    description TEXT NOT NULL,
    statut VARCHAR(20) NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_produit PRIMARY KEY (id_produit),
    CONSTRAINT uq_produit_slug UNIQUE (slug),
    CONSTRAINT fk_produit_categorie FOREIGN KEY (id_categorie)
        REFERENCES categorie (id_categorie) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE variante_produit (
    id_variante BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_produit BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL,
    prix_reference DECIMAL(12,2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    statut VARCHAR(20) NOT NULL,
    description_variante VARCHAR(255) NULL,
    CONSTRAINT pk_variante_produit PRIMARY KEY (id_variante),
    CONSTRAINT uq_variante_produit_sku UNIQUE (sku),
    CONSTRAINT chk_variante_produit_prix CHECK (prix_reference >= 0),
    CONSTRAINT chk_variante_produit_stock CHECK (stock >= 0),
    CONSTRAINT fk_variante_produit_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotion (
    id_promotion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    pourcentage DECIMAL(5,2) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut VARCHAR(20) NOT NULL,
    CONSTRAINT pk_promotion PRIMARY KEY (id_promotion),
    CONSTRAINT chk_promotion_pourcentage CHECK (pourcentage > 0 AND pourcentage <= 100),
    CONSTRAINT chk_promotion_dates CHECK (date_fin > date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE beneficier (
    id_produit BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    CONSTRAINT pk_beneficier PRIMARY KEY (id_produit, id_promotion),
    CONSTRAINT fk_beneficier_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_beneficier_promotion FOREIGN KEY (id_promotion)
        REFERENCES promotion (id_promotion) ON DELETE CASCADE ON UPDATE RESTRICT,
    INDEX idx_beneficier_promotion (id_promotion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE panier (
    id_panier BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur BIGINT UNSIGNED NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_panier PRIMARY KEY (id_panier),
    CONSTRAINT uq_panier_utilisateur UNIQUE (id_utilisateur),
    CONSTRAINT fk_panier_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ligne_panier (
    id_ligne_panier BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_panier BIGINT UNSIGNED NOT NULL,
    id_variante BIGINT UNSIGNED NOT NULL,
    quantite INT UNSIGNED NOT NULL,
    date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_ligne_panier PRIMARY KEY (id_ligne_panier),
    CONSTRAINT uq_ligne_panier_variante UNIQUE (id_panier, id_variante),
    CONSTRAINT chk_ligne_panier_quantite CHECK (quantite > 0),
    CONSTRAINT fk_ligne_panier_panier FOREIGN KEY (id_panier)
        REFERENCES panier (id_panier) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_ligne_panier_variante FOREIGN KEY (id_variante)
        REFERENCES variante_produit (id_variante) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ligne_panier_variante (id_variante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favori (
    id_favori BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur BIGINT UNSIGNED NOT NULL,
    id_produit BIGINT UNSIGNED NOT NULL,
    date_ajout DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_favori PRIMARY KEY (id_favori),
    CONSTRAINT uq_favori_utilisateur_produit UNIQUE (id_utilisateur, id_produit),
    CONSTRAINT fk_favori_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_favori_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_favori_produit (id_produit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retablissement des FK externes vers les nouvelles tables.
ALTER TABLE `caractéristique`
    ADD CONSTRAINT fk_caracteristique_categorie FOREIGN KEY (id_categorie)
    REFERENCES categorie (id_categorie) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE commande
    ADD CONSTRAINT fk_commande_utilisateur FOREIGN KEY (id_utilisateur)
    REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE avis
    ADD CONSTRAINT fk_avis_produit FOREIGN KEY (id_produit)
    REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_avis_utilisateur FOREIGN KEY (id_utilisateur)
    REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE alerte_stock
    ADD CONSTRAINT fk_alerte_stock_variante FOREIGN KEY (id_variante)
    REFERENCES variante_produit (id_variante) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_alerte_stock_utilisateur FOREIGN KEY (id_utilisateur)
    REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `valeur_caractéristique`
    ADD CONSTRAINT fk_valeur_caracteristique_produit FOREIGN KEY (id_produit)
    REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- VALIDATION POST-MIGRATION (lecture seule).
SHOW COLUMNS FROM panier;
SHOW COLUMNS FROM ligne_panier;
SHOW COLUMNS FROM favori;
SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('produit', 'panier', 'ligne_panier', 'favori')
  AND REFERENCED_TABLE_NAME IS NOT NULL;
SELECT COUNT(*) AS fk_produit_categorie
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'produit'
  AND CONSTRAINT_NAME = 'fk_produit_categorie'
  AND COLUMN_NAME = 'id_categorie'
  AND REFERENCED_TABLE_NAME = 'categorie'
  AND REFERENCED_COLUMN_NAME = 'id_categorie';
