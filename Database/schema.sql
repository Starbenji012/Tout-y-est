-- Schéma de référence MySQL 8 pour une base neuve.
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
    CONSTRAINT pk_utilisateur PRIMARY KEY (id_utilisateur),
    CONSTRAINT uq_utilisateur_email UNIQUE (email)
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

CREATE TABLE variante_produit (
    id_variante BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_produit BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL,
    prix_reference DECIMAL(12, 2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    statut VARCHAR(20) NOT NULL,
    description_variante VARCHAR(255) NULL,
    CONSTRAINT pk_variante_produit PRIMARY KEY (id_variante),
    CONSTRAINT uq_variante_produit_sku UNIQUE (sku),
    CONSTRAINT chk_variante_produit_prix CHECK (prix_reference >= 0),
    CONSTRAINT chk_variante_produit_stock CHECK (stock >= 0),
    CONSTRAINT fk_variante_produit_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE=InnoDB;

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

CREATE TABLE commande (
    id_commande BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_utilisateur BIGINT UNSIGNED NOT NULL,
    numero_commande VARCHAR(50) NOT NULL,
    date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(30) NOT NULL,
    sous_total DECIMAL(12, 2) NOT NULL,
    frais_livraison DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total DECIMAL(12, 2) NOT NULL,
    CONSTRAINT pk_commande PRIMARY KEY (id_commande),
    CONSTRAINT uq_commande_numero UNIQUE (numero_commande),
    CONSTRAINT chk_commande_sous_total CHECK (sous_total >= 0),
    CONSTRAINT chk_commande_frais_livraison CHECK (frais_livraison >= 0),
    CONSTRAINT chk_commande_total CHECK (total >= 0),
    CONSTRAINT fk_commande_utilisateur FOREIGN KEY (id_utilisateur)
        REFERENCES utilisateur (id_utilisateur)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_commande_utilisateur_date (id_utilisateur, date_commande)
) ENGINE=InnoDB;

CREATE TABLE ligne_commande (
    id_ligne_commande BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_commande BIGINT UNSIGNED NOT NULL,
    id_variante BIGINT UNSIGNED NOT NULL,
    nom_produit_snapshot VARCHAR(180) NOT NULL,
    sku_snapshot VARCHAR(100) NOT NULL,
    description_variante_snapshot VARCHAR(255) NULL,
    quantite INT UNSIGNED NOT NULL,
    prix_unitaire_applique DECIMAL(12, 2) NOT NULL,
    remise_unitaire_appliquee DECIMAL(12, 2) NULL,
    CONSTRAINT pk_ligne_commande PRIMARY KEY (id_ligne_commande),
    CONSTRAINT chk_ligne_commande_quantite CHECK (quantite > 0),
    CONSTRAINT chk_ligne_commande_prix CHECK (prix_unitaire_applique >= 0),
    CONSTRAINT chk_ligne_commande_remise CHECK (
        remise_unitaire_appliquee IS NULL OR remise_unitaire_appliquee >= 0
    ),
    CONSTRAINT fk_ligne_commande_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_ligne_commande_variante FOREIGN KEY (id_variante)
        REFERENCES variante_produit (id_variante)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_ligne_commande_commande (id_commande),
    INDEX idx_ligne_commande_variante (id_variante)
) ENGINE=InnoDB;

CREATE TABLE mode_paiement (
    id_mode_paiement BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    statut VARCHAR(20) NOT NULL,
    CONSTRAINT pk_mode_paiement PRIMARY KEY (id_mode_paiement),
    CONSTRAINT uq_mode_paiement_nom UNIQUE (nom)
) ENGINE=InnoDB;

CREATE TABLE paiement (
    id_paiement BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_commande BIGINT UNSIGNED NOT NULL,
    id_mode_paiement BIGINT UNSIGNED NOT NULL,
    montant DECIMAL(12, 2) NOT NULL,
    reference_externe VARCHAR(191) NULL,
    statut VARCHAR(30) NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_paiement DATETIME NULL,
    CONSTRAINT pk_paiement PRIMARY KEY (id_paiement),
    CONSTRAINT uq_paiement_reference UNIQUE (reference_externe),
    CONSTRAINT chk_paiement_montant CHECK (montant >= 0),
    CONSTRAINT fk_paiement_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_paiement_mode FOREIGN KEY (id_mode_paiement)
        REFERENCES mode_paiement (id_mode_paiement)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    INDEX idx_paiement_commande (id_commande),
    INDEX idx_paiement_mode (id_mode_paiement)
) ENGINE=InnoDB;

CREATE TABLE zone_livraison (
    id_zone_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    frais_reference DECIMAL(12, 2) NULL,
    statut VARCHAR(20) NOT NULL,
    CONSTRAINT pk_zone_livraison PRIMARY KEY (id_zone_livraison),
    CONSTRAINT uq_zone_livraison_nom UNIQUE (nom),
    CONSTRAINT chk_zone_livraison_frais CHECK (
        frais_reference IS NULL OR frais_reference >= 0
    )
) ENGINE=InnoDB;

CREATE TABLE livraison (
    id_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_commande BIGINT UNSIGNED NOT NULL,
    id_zone_livraison BIGINT UNSIGNED NULL,
    adresse_snapshot VARCHAR(255) NOT NULL,
    ville_snapshot VARCHAR(100) NOT NULL,
    province_snapshot VARCHAR(100) NULL,
    frais_snapshot DECIMAL(12, 2) NOT NULL,
    statut VARCHAR(30) NOT NULL,
    date_preparation DATETIME NULL,
    date_expedition DATETIME NULL,
    date_livraison DATETIME NULL,
    CONSTRAINT pk_livraison PRIMARY KEY (id_livraison),
    CONSTRAINT uq_livraison_commande UNIQUE (id_commande),
    CONSTRAINT chk_livraison_frais CHECK (frais_snapshot >= 0),
    CONSTRAINT fk_livraison_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_livraison_zone FOREIGN KEY (id_zone_livraison)
        REFERENCES zone_livraison (id_zone_livraison)
        ON DELETE SET NULL
        ON UPDATE RESTRICT,
    INDEX idx_livraison_zone (id_zone_livraison)
) ENGINE=InnoDB;
