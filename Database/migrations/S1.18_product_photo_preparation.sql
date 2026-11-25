-- Métadonnées privées uniquement ; aucune modification des références historiques.
CREATE TABLE IF NOT EXISTS product_photo_batch (
    ticket CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    cleaned_at DATETIME NULL,
    INDEX(owner_id, expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_photo_item (
    batch_ticket CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ticket CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    state ENUM('processing','ready','removed','consumed') NOT NULL,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    manifest LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(batch_ticket,ticket),
    UNIQUE KEY photo_source_per_batch(batch_ticket,source_hash),
    CONSTRAINT photo_batch_fk FOREIGN KEY(batch_ticket) REFERENCES product_photo_batch(ticket) ON DELETE CASCADE
) ENGINE=InnoDB;
