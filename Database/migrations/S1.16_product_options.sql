-- Phase 4A / MariaDB 10.4. DDL à commit implicite : sauvegarde et hors trafic.
-- Aucune variante, donnée commerciale ou table historique n'est réécrite.
-- NULL = variante préexistante/non classifiée ; les écritures 4B fourniront la clé.
ALTER TABLE variante_produit ADD COLUMN IF NOT EXISTS cle_combinaison VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL;
ALTER TABLE variante_produit ADD UNIQUE INDEX IF NOT EXISTS uq_variante_combinaison (id_produit,cle_combinaison);
ALTER TABLE variante_produit ADD UNIQUE INDEX IF NOT EXISTS uq_variante_identite_produit (id_variante,id_produit);

SET @s116_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='variante_produit' AND CONSTRAINT_NAME='chk_variante_combinaison'),'SELECT 1',
 'ALTER TABLE variante_produit ADD CONSTRAINT chk_variante_combinaison CHECK (
    cle_combinaison IS NULL OR (cle_combinaison = ''simple'' AND OCTET_LENGTH(cle_combinaison)=6) OR (
    cle_combinaison REGEXP ''^[1-9][0-9]{0,19}:[1-9][0-9]{0,19}([|][1-9][0-9]{0,19}:[1-9][0-9]{0,19}){0,2}$''
    AND cle_combinaison NOT REGEXP ''[[:space:]]''
    AND (LOCATE(''|'',cle_combinaison)=0 OR
      CAST(SUBSTRING_INDEX(cle_combinaison,'':'',1) AS DECIMAL(20,0)) <
      CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,''|'',2),''|'',-1),'':'',1) AS DECIMAL(20,0)))
    AND (LENGTH(cle_combinaison)-LENGTH(REPLACE(cle_combinaison,''|'',''''))<2 OR
      CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,''|'',2),''|'',-1),'':'',1) AS DECIMAL(20,0)) <
      CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cle_combinaison,''|'',-1),'':'',1) AS DECIMAL(20,0)))
 ))');
PREPARE s116_statement FROM @s116_sql;
EXECUTE s116_statement;
DEALLOCATE PREPARE s116_statement;

CREATE TABLE IF NOT EXISTS option_produit (
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

CREATE TABLE IF NOT EXISTS valeur_option_produit (
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

CREATE TABLE IF NOT EXISTS variante_valeur_option (
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
