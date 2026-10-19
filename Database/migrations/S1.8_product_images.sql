-- Vérifier avant exécution : image_produit doit être vide (aucune attribution automatique).
-- DDL : commit implicite MariaDB/MySQL. Sauvegarder avant application.
ALTER TABLE image_produit
    MODIFY id_image BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD id_produit BIGINT UNSIGNED NOT NULL AFTER id_image,
    MODIFY ordre_affichage SMALLINT NOT NULL DEFAULT 0,
    MODIFY image_principale TINYINT(1) NOT NULL DEFAULT 0,
    ADD produit_principal BIGINT UNSIGNED GENERATED ALWAYS AS
        (CASE WHEN image_principale = 1 THEN id_produit ELSE NULL END) STORED,
    ADD CONSTRAINT chk_image_principale CHECK (image_principale IN (0, 1)),
    ADD CONSTRAINT chk_image_ordre CHECK (ordre_affichage >= 0),
    ADD CONSTRAINT fk_image_produit FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE CASCADE ON UPDATE RESTRICT,
    ADD UNIQUE KEY uq_image_principale (produit_principal),
    ADD INDEX idx_image_ordre (id_produit, ordre_affichage, id_image);
