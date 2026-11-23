-- Schéma de référence MariaDB 10.4 pour une base neuve (S1.16 options incluse).
-- Ce fichier ne contient volontairement aucune suppression de table ou de données.

CREATE DATABASE IF NOT EXISTS tout_y_est
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tout_y_est;

CREATE TABLE utilisateur (
    id_utilisateur BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    telephone VARCHAR(30) NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'client',
    statut VARCHAR(20) NOT NULL DEFAULT 'actif',
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    email_verified_at DATETIME NULL,
    email_verification_token_hash CHAR(64) NULL,
    email_verification_expires_at DATETIME NULL,
    email_verification_sent_at DATETIME NULL,
    CONSTRAINT pk_utilisateur PRIMARY KEY (id_utilisateur),
    CONSTRAINT uq_utilisateur_email UNIQUE (email),
    CONSTRAINT uq_utilisateur_telephone UNIQUE (telephone),
    CONSTRAINT uq_utilisateur_email_verification_token_hash UNIQUE (email_verification_token_hash)
) ENGINE=InnoDB;

CREATE TABLE password_reset_token (
    id_password_reset BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_password_reset_token PRIMARY KEY (id_password_reset),
    CONSTRAINT uq_password_reset_token_user UNIQUE (id_utilisateur),
    CONSTRAINT uq_password_reset_token_hash UNIQUE (token_hash),
    CONSTRAINT fk_password_reset_token_user FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    INDEX idx_password_reset_token_expiration (expires_at)
) ENGINE=InnoDB;

CREATE TABLE categorie (
    id_categorie BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,
    statut VARCHAR(20) NOT NULL,
    CONSTRAINT pk_categorie PRIMARY KEY (id_categorie),
    CONSTRAINT uq_categorie_slug UNIQUE (slug)
) ENGINE=InnoDB;

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
        REFERENCES categorie (id_categorie)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE image_produit (
    id_image BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_produit BIGINT UNSIGNED NOT NULL,
    chemin VARCHAR(500) NOT NULL,
    ordre_affichage SMALLINT NOT NULL DEFAULT 0,
    image_principale TINYINT(1) NOT NULL DEFAULT 0,
    produit_principal BIGINT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN image_principale = 1 THEN id_produit ELSE NULL END) STORED,
    CONSTRAINT chk_image_principale CHECK (image_principale IN (0, 1)),
    CONSTRAINT chk_image_ordre CHECK (ordre_affichage >= 0),
    CONSTRAINT fk_image_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE CASCADE ON UPDATE RESTRICT,
    UNIQUE KEY uq_image_principale (produit_principal),
    INDEX idx_image_ordre (id_produit, ordre_affichage, id_image)
) ENGINE=InnoDB;

CREATE TABLE variante_produit (
    id_variante BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_produit BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL,
    prix_reference DECIMAL(12, 2) NOT NULL,
    devise_reference CHAR(3) NULL DEFAULT 'USD',
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    statut VARCHAR(20) NOT NULL,
    description_variante VARCHAR(255) NULL,
    cle_combinaison VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    CONSTRAINT pk_variante_produit PRIMARY KEY (id_variante),
    CONSTRAINT uq_variante_produit_sku UNIQUE (sku),
    UNIQUE KEY uq_variante_combinaison (id_produit,cle_combinaison),
    UNIQUE KEY uq_variante_identite_produit (id_variante,id_produit),
    CONSTRAINT chk_variante_combinaison CHECK (
        cle_combinaison IS NULL OR (cle_combinaison = 'simple' AND OCTET_LENGTH(cle_combinaison)=6) OR (
        cle_combinaison REGEXP '^[1-9][0-9]{0,19}:[1-9][0-9]{0,19}([|][1-9][0-9]{0,19}:[1-9][0-9]{0,19}){0,2}$'
        AND cle_combinaison NOT REGEXP '[[:space:]]'
        AND (LOCATE('|',cle_combinaison)=0 OR
          CAST(SUBSTRING_INDEX(cle_combinaison,':',1) AS DECIMAL(20,0)) <
          CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,'|',2),'|',-1),':',1) AS DECIMAL(20,0)))
        AND (LENGTH(cle_combinaison)-LENGTH(REPLACE(cle_combinaison,'|',''))<2 OR
          CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,'|',2),'|',-1),':',1) AS DECIMAL(20,0)) <
          CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,'|',-1),':',1) AS DECIMAL(20,0)))
    )),
    CONSTRAINT chk_variante_produit_prix CHECK (prix_reference >= 0),
    CONSTRAINT chk_variante_devise CHECK (devise_reference IS NULL OR devise_reference = 'USD'),
    CONSTRAINT chk_variante_produit_stock CHECK (stock >= 0),
    CONSTRAINT fk_variante_produit_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE option_produit (
    id_option BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_produit BIGINT UNSIGNED NOT NULL,
    nom VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    nom_normalise VARCHAR(100) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (LOWER(TRIM(nom))) PERSISTENT,
    position TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_option_nom (id_produit,nom_normalise),
    UNIQUE KEY uq_option_position (id_produit,position),
    UNIQUE KEY uq_option_identite_produit (id_option,id_produit),
    CONSTRAINT chk_option_nom CHECK (CHAR_LENGTH(TRIM(nom)) BETWEEN 1 AND 100),
    CONSTRAINT chk_option_position CHECK (position BETWEEN 1 AND 3),
    CONSTRAINT fk_option_produit FOREIGN KEY (id_produit) REFERENCES produit(id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE valeur_option_produit (
    id_valeur BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_option BIGINT UNSIGNED NOT NULL,
    libelle VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    libelle_normalise VARCHAR(100) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (LOWER(TRIM(libelle))) PERSISTENT,
    ordre_affichage SMALLINT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_valeur_libelle (id_option,libelle_normalise),
    UNIQUE KEY uq_valeur_identite_option (id_option,id_valeur),
    KEY idx_valeur_ordre (id_option,ordre_affichage,id_valeur),
    CONSTRAINT chk_valeur_libelle CHECK (CHAR_LENGTH(TRIM(libelle)) BETWEEN 1 AND 100),
    CONSTRAINT chk_valeur_ordre CHECK (ordre_affichage >= 0),
    CONSTRAINT fk_valeur_option FOREIGN KEY (id_option) REFERENCES option_produit(id_option) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE variante_valeur_option (
    id_variante BIGINT UNSIGNED NOT NULL,
    id_produit BIGINT UNSIGNED NOT NULL,
    id_option BIGINT UNSIGNED NOT NULL,
    id_valeur BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_variante,id_option),
    KEY idx_vvo_variante_produit (id_variante,id_produit),
    KEY idx_vvo_option_produit (id_option,id_produit),
    KEY idx_vvo_option_valeur (id_option,id_valeur),
    CONSTRAINT fk_vvo_variante_produit FOREIGN KEY (id_variante,id_produit) REFERENCES variante_produit(id_variante,id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vvo_option_produit FOREIGN KEY (id_option,id_produit) REFERENCES option_produit(id_option,id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vvo_valeur_option FOREIGN KEY (id_option,id_valeur) REFERENCES valeur_option_produit(id_option,id_valeur) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotion (
    id_promotion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    pourcentage DECIMAL(5, 2) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut VARCHAR(20) NOT NULL,
    CONSTRAINT pk_promotion PRIMARY KEY (id_promotion),
    CONSTRAINT chk_promotion_pourcentage CHECK (pourcentage > 0 AND pourcentage <= 100),
    CONSTRAINT chk_promotion_dates CHECK (date_fin > date_debut)
) ENGINE=InnoDB;

CREATE TABLE beneficier (
    id_produit BIGINT UNSIGNED NOT NULL,
    id_promotion BIGINT UNSIGNED NOT NULL,
    CONSTRAINT pk_beneficier PRIMARY KEY (id_produit, id_promotion),
    CONSTRAINT fk_beneficier_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT fk_beneficier_promotion FOREIGN KEY (id_promotion)
        REFERENCES promotion (id_promotion)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    INDEX idx_beneficier_promotion (id_promotion)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE panier (
    id_panier BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur BIGINT UNSIGNED NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_panier PRIMARY KEY (id_panier),
    CONSTRAINT uq_panier_utilisateur UNIQUE (id_utilisateur),
    CONSTRAINT fk_panier_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

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
        REFERENCES panier (id_panier)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT fk_ligne_panier_variante FOREIGN KEY (id_variante)
        REFERENCES variante_produit (id_variante)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_ligne_panier_variante (id_variante)
) ENGINE=InnoDB;

-- Journal indépendant des numéros commerciaux : ne jamais vider ni réinitialiser.
CREATE TABLE numero_commande_sequence (
    numero BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;

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
    devise_reference CHAR(3) NULL,
    devise_paiement CHAR(3) NULL,
    taux_usd_cdf DECIMAL(12, 4) NULL,
    total_paiement DECIMAL(18, 2) NULL,
    cle_idempotence CHAR(64) NULL,
    reservation_expire_at DATETIME NULL,
    stock_restitue_at DATETIME NULL,
    CONSTRAINT pk_commande PRIMARY KEY (id_commande),
    CONSTRAINT uq_commande_numero UNIQUE (numero_commande),
    CONSTRAINT uq_commande_creation UNIQUE (id_utilisateur, cle_idempotence),
    INDEX idx_commande_expiration (statut, reservation_expire_at),
    CONSTRAINT chk_commande_sous_total CHECK (sous_total >= 0),
    CONSTRAINT chk_commande_frais_livraison CHECK (frais_livraison >= 0),
    CONSTRAINT chk_commande_total CHECK (total = sous_total + frais_livraison),
    CONSTRAINT chk_commande_devise_reference CHECK (devise_reference IS NULL OR devise_reference = 'USD'),
    CONSTRAINT chk_commande_devise_paiement CHECK (devise_paiement IS NULL OR devise_paiement IN ('USD','CDF')),
    CONSTRAINT chk_commande_taux CHECK (taux_usd_cdf IS NULL OR taux_usd_cdf BETWEEN 0.0001 AND 100000),
    CONSTRAINT chk_commande_total_paiement CHECK (total_paiement IS NULL OR total_paiement >= 0),
    CONSTRAINT fk_commande_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_commande_utilisateur_date (id_utilisateur, date_commande)
) ENGINE=InnoDB;

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
    CONSTRAINT chk_ligne_commande_remise CHECK (
        remise_unitaire_appliquee IS NULL OR remise_unitaire_appliquee >= 0
    ),
    CONSTRAINT chk_ligne_commande_total CHECK (total_ligne = ROUND(quantite * prix_unitaire_applique, 2)),
    CONSTRAINT fk_ligne_commande_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_ligne_commande_variante FOREIGN KEY (id_variante)
        REFERENCES variante_produit (id_variante)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_ligne_commande_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_ligne_commande_commande (id_commande),
    INDEX idx_ligne_commande_variante (id_variante),
    INDEX idx_ligne_commande_produit (id_produit)
) ENGINE=InnoDB;

CREATE TABLE mode_paiement (
    id_mode_paiement BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
    CONSTRAINT pk_mode_paiement PRIMARY KEY (id_mode_paiement),
    CONSTRAINT uq_mode_paiement_nom UNIQUE (nom),
    CONSTRAINT chk_mode_paiement_statut CHECK (statut IN ('actif', 'inactif'))
) ENGINE=InnoDB;

-- Seul le cash est activé pour la V1 ; aucune intégration externe.
INSERT INTO mode_paiement (nom, statut) VALUES ('Espèces à la livraison', 'actif');

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
    devise_reglement CHAR(3) NULL,
    taux_usd_cdf DECIMAL(12, 4) NULL,
    montant_reglement DECIMAL(18, 2) NULL,
    CONSTRAINT pk_paiement PRIMARY KEY (id_paiement),
    CONSTRAINT uq_paiement_reference UNIQUE (id_mode_paiement, reference_externe),
    CONSTRAINT chk_paiement_montant CHECK (montant >= 0),
    CONSTRAINT chk_paiement_devise CHECK (devise_reglement IS NULL OR devise_reglement IN ('USD','CDF')),
    CONSTRAINT chk_paiement_taux CHECK (taux_usd_cdf IS NULL OR taux_usd_cdf BETWEEN 0.0001 AND 100000),
    CONSTRAINT chk_paiement_reglement CHECK (montant_reglement IS NULL OR montant_reglement >= 0),
    CONSTRAINT fk_paiement_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_paiement_mode FOREIGN KEY (id_mode_paiement)
        REFERENCES mode_paiement (id_mode_paiement)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_paiement_commande (id_commande)
) ENGINE=InnoDB;

CREATE TABLE zone_livraison (
    id_zone_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    description TEXT NULL,
    frais_reference DECIMAL(12, 2) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
    devise_reference CHAR(3) NULL DEFAULT 'USD',
    CONSTRAINT pk_zone_livraison PRIMARY KEY (id_zone_livraison),
    CONSTRAINT uq_zone_livraison_nom UNIQUE (nom),
    CONSTRAINT chk_zone_livraison_frais CHECK (
        frais_reference >= 0
    ),
    CONSTRAINT chk_zone_livraison_statut CHECK (statut IN ('actif', 'inactif')),
    CONSTRAINT chk_zone_devise CHECK (devise_reference IS NULL OR devise_reference = 'USD')
) ENGINE=InnoDB;

-- Configuration commerciale reutilisee par le futur Admin.
CREATE TABLE parametre (
    id_parametre BIGINT NOT NULL,
    cle VARCHAR(100) NOT NULL,
    valeur TEXT NULL,
    PRIMARY KEY (id_parametre),
    UNIQUE KEY uq_parametre_cle (cle)
) ENGINE=InnoDB;
INSERT INTO parametre (id_parametre, cle, valeur)
VALUES (1, 'monetary_config', '{"reference":"USD","alternative":"CDF","usd_cdf_rate":"2450"}');

-- Adresse editable du compte, jamais utilisee comme historique de livraison.
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
) ENGINE=InnoDB;

CREATE TABLE livreur (
    id_livreur BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
    CONSTRAINT chk_livreur_nom CHECK (CHAR_LENGTH(TRIM(nom)) BETWEEN 2 AND 120),
    CONSTRAINT chk_livreur_statut CHECK (statut IN ('actif','inactif'))
) ENGINE=InnoDB;

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
    nom_livreur_snapshot VARCHAR(120) NULL,
    id_livreur BIGINT UNSIGNED NULL,
    id_livreur_engage BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN statut IN ('en_preparation','en_livraison') THEN id_livreur ELSE NULL END) PERSISTENT,
    CONSTRAINT pk_livraison PRIMARY KEY (id_livraison),
    CONSTRAINT uq_livraison_commande UNIQUE (id_commande),
    CONSTRAINT chk_livraison_frais CHECK (frais_snapshot >= 0),
    CONSTRAINT chk_livraison_latitude CHECK (latitude_snapshot BETWEEN -90 AND 90),
    CONSTRAINT chk_livraison_longitude CHECK (longitude_snapshot BETWEEN -180 AND 180),
    CONSTRAINT fk_livraison_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_livraison_zone FOREIGN KEY (id_zone_livraison)
        REFERENCES zone_livraison (id_zone_livraison)
        ON DELETE SET NULL
        ON UPDATE RESTRICT,
    CONSTRAINT fk_livraison_livreur FOREIGN KEY (id_livreur)
        REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_livraison_livreur (id_livreur),
    UNIQUE KEY uq_livraison_livreur_engage (id_livreur_engage),
    INDEX idx_livraison_zone (id_zone_livraison)
) ENGINE=InnoDB;

-- MariaDB 10.4 / MySQL 8. DDL à appliquer hors transaction métier.
-- Ajout uniquement ; aucune commande, aucun paiement ni stock modifié.
CREATE TABLE IF NOT EXISTS journal_commande (
    id_journal BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_commande BIGINT UNSIGNED NOT NULL,
    id_administrateur BIGINT UNSIGNED NOT NULL,
    id_paiement BIGINT UNSIGNED NULL,
    action VARCHAR(32) NOT NULL,
    ancien_etat JSON NOT NULL,
    nouvel_etat JSON NOT NULL,
    donnees_complementaires JSON NOT NULL,
    date_action DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action_unique VARCHAR(32) GENERATED ALWAYS AS (CASE WHEN action='assign' THEN NULL ELSE action END) PERSISTENT,
    CONSTRAINT uq_journal_transition UNIQUE (id_commande, action_unique),
    CONSTRAINT chk_journal_action CHECK (action IN ('prepare','ship','deliver','collect-cash','cancel','assign')),
    CONSTRAINT fk_journal_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_journal_admin FOREIGN KEY (id_administrateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_journal_paiement FOREIGN KEY (id_paiement)
        REFERENCES paiement (id_paiement) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_journal_admin (id_administrateur),
    INDEX idx_journal_paiement (id_paiement)
) ENGINE=InnoDB;

-- BEGIN S1.15 (forward identique à la migration, sans réécriture des données historiques)
-- S1.15 forward MariaDB 10.4. Précontrôle AVANT tout DDL durable.
DROP TEMPORARY TABLE IF EXISTS s115_precheck;
CREATE TEMPORARY TABLE s115_precheck (ok INT NOT NULL CHECK(ok=1));
INSERT INTO s115_precheck SELECT IF(COUNT(*)=0,1,0) FROM livraison
 WHERE id_livreur IS NOT NULL AND statut IN ('en_preparation','en_livraison')
 AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
   AND TABLE_NAME='livraison' AND INDEX_NAME='uq_livraison_livreur_engage');
DROP TEMPORARY TABLE s115_precheck;

CREATE TABLE IF NOT EXISTS verrou_dispatch (id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1),version_schema TINYINT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB;
INSERT IGNORE INTO verrou_dispatch(id) VALUES(1);

ALTER TABLE livreur ADD COLUMN IF NOT EXISTS id_utilisateur BIGINT UNSIGNED NULL;
ALTER TABLE livreur ADD UNIQUE INDEX IF NOT EXISTS uq_livreur_compte(id_utilisateur);
SET @s115_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='livreur' AND CONSTRAINT_NAME='fk_livreur_compte'),'SELECT 1','ALTER TABLE livreur ADD CONSTRAINT fk_livreur_compte FOREIGN KEY(id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT');
PREPARE s115_statement FROM @s115_sql;
EXECUTE s115_statement;
DEALLOCATE PREPARE s115_statement;

CREATE TABLE IF NOT EXISTS tournee (
 id_tournee BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_livreur BIGINT UNSIGNED NOT NULL,
 nom_livreur_snapshot VARCHAR(120) NOT NULL,
 statut VARCHAR(20) NOT NULL DEFAULT 'chargement',
 version_manifeste INT UNSIGNED NOT NULL DEFAULT 0,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_depart DATETIME NULL,
 date_retour DATETIME NULL,
 livreur_ouvert BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN statut IN ('chargement','en_cours') THEN id_livreur ELSE NULL END) PERSISTENT,
 UNIQUE KEY uq_tournee_ouverte(livreur_ouvert),
 KEY idx_tournee_livreur(id_livreur,id_tournee),
 CONSTRAINT fk_tournee_livreur FOREIGN KEY(id_livreur) REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_tournee_statut CHECK(statut IN ('chargement','en_cours','retour_termine','annulee')),
 CONSTRAINT chk_tournee_dates CHECK((statut IN ('chargement','annulee') AND date_depart IS NULL AND date_retour IS NULL) OR (statut='en_cours' AND date_depart IS NOT NULL AND date_retour IS NULL) OR (statut='retour_termine' AND date_depart IS NOT NULL AND date_retour IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tentative_livraison (
 id_tentative BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_tournee BIGINT UNSIGNED NOT NULL,
 id_livraison BIGINT UNSIGNED NOT NULL,
 emplacement TINYINT UNSIGNED NOT NULL,
 resultat VARCHAR(20) NOT NULL DEFAULT 'en_attente',
 motif VARCHAR(32) NULL,
 commentaire VARCHAR(500) NULL,
 date_affectation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_resultat DATETIME NULL,
 date_cloture DATETIME NULL,
 livraison_active BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN date_cloture IS NULL THEN id_livraison ELSE NULL END) PERSISTENT,
 UNIQUE KEY uq_tentative_active(livraison_active),
 UNIQUE KEY uq_tournee_emplacement(id_tournee,emplacement),
 UNIQUE KEY uq_tournee_livraison(id_tournee,id_livraison),
 KEY idx_tentative_livraison(id_livraison,id_tentative),
 CONSTRAINT fk_tentative_tournee FOREIGN KEY(id_tournee) REFERENCES tournee(id_tournee) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_tentative_livraison FOREIGN KEY(id_livraison) REFERENCES livraison(id_livraison) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_tentative_place CHECK(emplacement BETWEEN 1 AND 5),
 CONSTRAINT chk_tentative_resultat CHECK(resultat IN ('en_attente','livree','probleme','annulee')),
 CONSTRAINT chk_tentative_motif CHECK(motif IS NULL OR motif IN ('absent','injoignable','adresse','refus','paiement','autre')),
 CONSTRAINT chk_tentative_probleme CHECK(resultat<>'probleme' OR (motif IS NOT NULL AND (motif<>'autre' OR CHAR_LENGTH(TRIM(commentaire))>0)))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS collecte_cash (
 id_collecte BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_paiement BIGINT UNSIGNED NOT NULL,
 id_tentative BIGINT UNSIGNED NOT NULL,
 id_livreur BIGINT UNSIGNED NOT NULL,
 montant DECIMAL(18,2) NOT NULL,
 devise CHAR(3) NOT NULL,
 date_collecte DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_remise DATETIME NULL,
 id_confirmateur BIGINT UNSIGNED NULL,
 UNIQUE KEY uq_collecte_paiement(id_paiement),
 UNIQUE KEY uq_collecte_tentative(id_tentative),
 KEY idx_collecte_livreur(id_livreur,date_remise),
 CONSTRAINT fk_collecte_paiement FOREIGN KEY(id_paiement) REFERENCES paiement(id_paiement) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_tentative FOREIGN KEY(id_tentative) REFERENCES tentative_livraison(id_tentative) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_livreur FOREIGN KEY(id_livreur) REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_confirmateur FOREIGN KEY(id_confirmateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_collecte_montant CHECK(montant>=0),
 CONSTRAINT chk_collecte_devise CHECK(devise IN ('USD','CDF')),
 CONSTRAINT chk_collecte_remise CHECK((date_remise IS NULL AND id_confirmateur IS NULL) OR (date_remise IS NOT NULL AND id_confirmateur IS NOT NULL))
) ENGINE=InnoDB;

-- Le journal conserve ses anciens auteurs et ses anciennes protections.
ALTER TABLE journal_commande MODIFY id_administrateur BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS type_acteur VARCHAR(12) NOT NULL DEFAULT 'admin',
 ADD COLUMN IF NOT EXISTS id_acteur BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS ressource VARCHAR(80) NULL;
SET @s115_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' AND CONSTRAINT_NAME='fk_journal_acteur'),'SELECT 1','ALTER TABLE journal_commande ADD CONSTRAINT fk_journal_acteur FOREIGN KEY(id_acteur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT');
PREPARE s115_statement FROM @s115_sql;
EXECUTE s115_statement;
DEALLOCATE PREPARE s115_statement;
-- Cet index reste disponible pour fk_journal_commande pendant la reconstruction de l'UNIQUE.
ALTER TABLE journal_commande ADD INDEX IF NOT EXISTS idx_journal_commande(id_commande);
ALTER TABLE journal_commande DROP INDEX IF EXISTS uq_journal_transition;
ALTER TABLE journal_commande MODIFY action_unique VARCHAR(32) GENERATED ALWAYS AS (CASE WHEN action IN ('prepare','cancel','collect-cash') OR (ressource IS NULL AND action IN ('ship','deliver')) THEN action ELSE NULL END) PERSISTENT;
ALTER TABLE journal_commande ADD UNIQUE INDEX IF NOT EXISTS uq_journal_transition(id_commande,action_unique);
ALTER TABLE journal_commande ADD UNIQUE INDEX IF NOT EXISTS uq_journal_ressource(id_commande,action,ressource);
ALTER TABLE journal_commande DROP CONSTRAINT IF EXISTS chk_journal_action,
 ADD CONSTRAINT chk_journal_action CHECK(action IN ('prepare','ship','deliver','collect-cash','cancel','assign','depart','incident','return','cash-collected','cash-remitted'));
ALTER TABLE journal_commande DROP CONSTRAINT IF EXISTS chk_journal_acteur,
 ADD CONSTRAINT chk_journal_acteur CHECK(type_acteur IN ('admin','livreur','client','systeme'));

-- Les deux invariants de remplacement existent AVANT de retirer celui de S1.14.
ALTER TABLE livraison DROP INDEX IF EXISTS uq_livraison_livreur_engage;
ALTER TABLE livraison DROP COLUMN IF EXISTS id_livreur_engage;
UPDATE verrou_dispatch SET version_schema=15 WHERE id=1;
