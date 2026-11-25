-- Appliquer avec SkuMigration.php : verrou catalogue, contrôles et import obligatoires.
CREATE TABLE IF NOT EXISTS sku_sequence (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY CHECK (id=1),
    dernier_numero BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT INTO sku_sequence(id,dernier_numero) VALUES(1,0) ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS sku_attribution (
    cle CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    sku VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    origine ENUM('operation','automatique','manuel','historique') NOT NULL,
    id_variante BIGINT UNSIGNED NULL,
    empreinte CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    resultat LONGTEXT NULL,
    cree_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sku_attribution_reference (sku),
    KEY idx_sku_attribution_variante (id_variante),
    CONSTRAINT chk_sku_attribution_type CHECK (
        (origine='operation' AND sku IS NULL AND empreinte IS NOT NULL AND id_variante IS NULL)
        OR (origine<>'operation' AND sku IS NOT NULL AND resultat IS NULL)
    )
) ENGINE=InnoDB;
-- Pas de FK : le registre doit survivre à la suppression d'une variante.

CREATE TRIGGER IF NOT EXISTS sku_attribution_no_delete BEFORE DELETE ON sku_attribution
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Le registre SKU est permanent';

CREATE TRIGGER IF NOT EXISTS sku_attribution_identity BEFORE UPDATE ON sku_attribution
FOR EACH ROW
BEGIN
    IF NOT (NEW.cle <=> OLD.cle) OR NOT (BINARY NEW.sku <=> BINARY OLD.sku)
       OR NEW.origine<>OLD.origine OR NOT (NEW.empreinte <=> OLD.empreinte)
       OR NEW.cree_at<>OLD.cree_at
       OR (OLD.id_variante IS NOT NULL AND NOT (NEW.id_variante <=> OLD.id_variante))
       OR (OLD.resultat IS NOT NULL AND NOT (BINARY NEW.resultat <=> BINARY OLD.resultat)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Attribution SKU immuable';
    END IF;
END;

CREATE TRIGGER IF NOT EXISTS sku_sequence_no_delete BEFORE DELETE ON sku_sequence
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Le compteur SKU est permanent';

CREATE TRIGGER IF NOT EXISTS sku_sequence_monotonic BEFORE UPDATE ON sku_sequence
FOR EACH ROW
BEGIN
    IF NEW.id<>OLD.id OR NEW.dernier_numero<OLD.dernier_numero THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Le compteur SKU ne peut pas reculer';
    END IF;
END;
