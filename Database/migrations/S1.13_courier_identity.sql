-- MariaDB 10.4. Appliquer manuellement après contrôle/sauvegarde, jamais dans une transaction métier.
CREATE TABLE IF NOT EXISTS livreur (
    id_livreur BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'inactif',
    CONSTRAINT chk_livreur_nom CHECK (CHAR_LENGTH(TRIM(nom)) BETWEEN 2 AND 120),
    CONSTRAINT chk_livreur_statut CHECK (statut IN ('actif','inactif'))
) ENGINE=InnoDB;
ALTER TABLE livraison ADD COLUMN IF NOT EXISTS id_livreur BIGINT UNSIGNED NULL;
ALTER TABLE livraison ADD INDEX IF NOT EXISTS idx_livraison_livreur (id_livreur);
SET @s113_sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='livraison' AND CONSTRAINT_NAME='fk_livraison_livreur'),
    'SELECT 1',
    'ALTER TABLE livraison ADD CONSTRAINT fk_livraison_livreur FOREIGN KEY (id_livreur) REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT');
PREPARE s113_statement FROM @s113_sql;
EXECUTE s113_statement;
DEALLOCATE PREPARE s113_statement;
