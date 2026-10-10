-- S1.5 : fondation Checkout, MariaDB 10.4 / MySQL 8.
-- Sauvegarder avant execution. Les DDL ne sont pas transactionnels.
-- Aucun tarif, moyen de paiement ou autre contenu commercial n'est insere.
USE tout_y_est;

DELIMITER $$
CREATE PROCEDURE migrate_s15_checkout()
BEGIN
    -- Refuser de remplacer des tables qui ne sont plus vides.
    IF (SELECT COUNT(*) FROM commande) + (SELECT COUNT(*) FROM ligne_commande)
        + (SELECT COUNT(*) FROM livraison) + (SELECT COUNT(*) FROM zone_livraison)
        + (SELECT COUNT(*) FROM mode_paiement) + (SELECT COUNT(*) FROM paiement) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S1.5 refusee : donnees checkout presentes';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'adresse_utilisateur') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S1.5 refusee : adresse_utilisateur existe deja';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IN ('commande','ligne_commande','livraison','zone_livraison','mode_paiement','paiement')
        AND (TABLE_SCHEMA <> DATABASE() OR TABLE_NAME NOT IN
            ('commande','ligne_commande','livraison','zone_livraison','mode_paiement','paiement'))) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S1.5 refusee : cle etrangere externe';
    END IF;

    -- Retirer les enfants avant les parents, sans desactiver les FK.
    DROP TABLE paiement, livraison, ligne_commande;
    DROP TABLE commande, mode_paiement, zone_livraison;

    CREATE TABLE zone_livraison (
        id_zone_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nom VARCHAR(120) NOT NULL,
        description TEXT NULL,
        frais_reference DECIMAL(12, 2) NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
        CONSTRAINT pk_zone_livraison PRIMARY KEY (id_zone_livraison),
        CONSTRAINT uq_zone_livraison_nom UNIQUE (nom),
        CONSTRAINT chk_zone_livraison_frais CHECK (frais_reference >= 0),
        CONSTRAINT chk_zone_livraison_statut CHECK (statut IN ('actif', 'inactif'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE adresse_utilisateur (
        id_adresse BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_utilisateur BIGINT UNSIGNED NOT NULL,
        id_zone_livraison BIGINT UNSIGNED NULL,
        nom_destinataire VARCHAR(100) NOT NULL,
        prenom_destinataire VARCHAR(100) NOT NULL,
        telephone VARCHAR(30) NOT NULL,
        commune VARCHAR(100) NOT NULL,
        adresse VARCHAR(255) NOT NULL,
        repere VARCHAR(255) NULL,
        instructions TEXT NULL,
        latitude DECIMAL(10, 7) NULL,
        longitude DECIMAL(10, 7) NULL,
        adresse_par_defaut BOOLEAN NOT NULL DEFAULT FALSE,
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT pk_adresse_utilisateur PRIMARY KEY (id_adresse),
        CONSTRAINT chk_adresse_defaut CHECK (adresse_par_defaut IN (0, 1)),
        CONSTRAINT chk_adresse_latitude CHECK (latitude BETWEEN -90 AND 90),
        CONSTRAINT chk_adresse_longitude CHECK (longitude BETWEEN -180 AND 180),
        CONSTRAINT fk_adresse_utilisateur FOREIGN KEY (id_utilisateur)
            REFERENCES utilisateur (id_utilisateur) ON DELETE CASCADE ON UPDATE RESTRICT,
        CONSTRAINT fk_adresse_zone FOREIGN KEY (id_zone_livraison)
            REFERENCES zone_livraison (id_zone_livraison) ON DELETE SET NULL ON UPDATE RESTRICT,
        INDEX idx_adresse_utilisateur_defaut (id_utilisateur, adresse_par_defaut),
        INDEX idx_adresse_zone (id_zone_livraison)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE commande (
        id_commande BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_utilisateur BIGINT UNSIGNED NOT NULL,
        numero_commande VARCHAR(50) NOT NULL,
        date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
        sous_total DECIMAL(12, 2) NOT NULL,
        frais_livraison DECIMAL(12, 2) NOT NULL,
        total DECIMAL(12, 2) NOT NULL,
        CONSTRAINT pk_commande PRIMARY KEY (id_commande),
        CONSTRAINT uq_commande_numero UNIQUE (numero_commande),
        CONSTRAINT chk_commande_sous_total CHECK (sous_total >= 0),
        CONSTRAINT chk_commande_frais_livraison CHECK (frais_livraison >= 0),
        CONSTRAINT chk_commande_total CHECK (total = sous_total + frais_livraison),
        CONSTRAINT fk_commande_utilisateur FOREIGN KEY (id_utilisateur)
            REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT,
        INDEX idx_commande_utilisateur_date (id_utilisateur, date_commande)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Les FK gardent l'origine ; les snapshots gardent l'achat historique.
    CREATE TABLE ligne_commande (
        id_ligne_commande BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_commande BIGINT UNSIGNED NOT NULL,
        id_produit BIGINT UNSIGNED NOT NULL,
        id_variante BIGINT UNSIGNED NOT NULL,
        nom_produit_snapshot VARCHAR(180) NOT NULL,
        sku_snapshot VARCHAR(100) NOT NULL,
        description_variante_snapshot VARCHAR(255) NULL,
        quantite INT UNSIGNED NOT NULL,
        prix_unitaire_applique DECIMAL(12, 2) NOT NULL,
        remise_unitaire_appliquee DECIMAL(12, 2) NULL,
        total_ligne DECIMAL(12, 2) NOT NULL,
        CONSTRAINT pk_ligne_commande PRIMARY KEY (id_ligne_commande),
        CONSTRAINT chk_ligne_commande_quantite CHECK (quantite > 0),
        CONSTRAINT chk_ligne_commande_prix CHECK (prix_unitaire_applique >= 0),
        CONSTRAINT chk_ligne_commande_remise CHECK (remise_unitaire_appliquee IS NULL OR remise_unitaire_appliquee >= 0),
        CONSTRAINT chk_ligne_commande_total CHECK (total_ligne = ROUND(quantite * prix_unitaire_applique, 2)),
        CONSTRAINT fk_ligne_commande_commande FOREIGN KEY (id_commande)
            REFERENCES commande (id_commande) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_ligne_commande_produit FOREIGN KEY (id_produit)
            REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_ligne_commande_variante FOREIGN KEY (id_variante)
            REFERENCES variante_produit (id_variante) ON DELETE RESTRICT ON UPDATE RESTRICT,
        INDEX idx_ligne_commande_commande (id_commande),
        INDEX idx_ligne_commande_produit (id_produit),
        INDEX idx_ligne_commande_variante (id_variante)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE livraison (
        id_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_commande BIGINT UNSIGNED NOT NULL,
        id_zone_livraison BIGINT UNSIGNED NULL,
        nom_destinataire_snapshot VARCHAR(100) NOT NULL,
        prenom_destinataire_snapshot VARCHAR(100) NOT NULL,
        telephone_snapshot VARCHAR(30) NOT NULL,
        commune_snapshot VARCHAR(100) NOT NULL,
        adresse_snapshot VARCHAR(255) NOT NULL,
        repere_snapshot VARCHAR(255) NULL,
        instructions_snapshot TEXT NULL,
        latitude_snapshot DECIMAL(10, 7) NULL,
        longitude_snapshot DECIMAL(10, 7) NULL,
        nom_zone_snapshot VARCHAR(120) NULL,
        frais_snapshot DECIMAL(12, 2) NOT NULL,
        statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        date_preparation DATETIME NULL,
        date_expedition DATETIME NULL,
        date_livraison DATETIME NULL,
        CONSTRAINT pk_livraison PRIMARY KEY (id_livraison),
        CONSTRAINT uq_livraison_commande UNIQUE (id_commande),
        CONSTRAINT chk_livraison_frais CHECK (frais_snapshot >= 0),
        CONSTRAINT chk_livraison_latitude CHECK (latitude_snapshot BETWEEN -90 AND 90),
        CONSTRAINT chk_livraison_longitude CHECK (longitude_snapshot BETWEEN -180 AND 180),
        CONSTRAINT fk_livraison_commande FOREIGN KEY (id_commande)
            REFERENCES commande (id_commande) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_livraison_zone FOREIGN KEY (id_zone_livraison)
            REFERENCES zone_livraison (id_zone_livraison) ON DELETE SET NULL ON UPDATE RESTRICT,
        INDEX idx_livraison_zone (id_zone_livraison)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE mode_paiement (
        id_mode_paiement BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nom VARCHAR(100) NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
        CONSTRAINT pk_mode_paiement PRIMARY KEY (id_mode_paiement),
        CONSTRAINT uq_mode_paiement_nom UNIQUE (nom),
        CONSTRAINT chk_mode_paiement_statut CHECK (statut IN ('actif', 'inactif'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE paiement (
        id_paiement BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_commande BIGINT UNSIGNED NOT NULL,
        id_mode_paiement BIGINT UNSIGNED NOT NULL,
        montant DECIMAL(12, 2) NOT NULL,
        reference_externe VARCHAR(191) NULL,
        statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        date_paiement DATETIME NULL,
        CONSTRAINT pk_paiement PRIMARY KEY (id_paiement),
        CONSTRAINT uq_paiement_reference UNIQUE (id_mode_paiement, reference_externe),
        CONSTRAINT chk_paiement_montant CHECK (montant >= 0),
        CONSTRAINT fk_paiement_commande FOREIGN KEY (id_commande)
            REFERENCES commande (id_commande) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_paiement_mode FOREIGN KEY (id_mode_paiement)
            REFERENCES mode_paiement (id_mode_paiement) ON DELETE RESTRICT ON UPDATE RESTRICT,
        INDEX idx_paiement_commande (id_commande)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
END$$
DELIMITER ;
CALL migrate_s15_checkout();
DROP PROCEDURE migrate_s15_checkout;
