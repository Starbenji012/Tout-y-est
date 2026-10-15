-- Ajouts uniquement : les anciennes commandes restent sans réservation.
USE tout_y_est;
ALTER TABLE commande
    ADD COLUMN cle_idempotence CHAR(64) NULL,
    ADD COLUMN reservation_expire_at DATETIME NULL,
    ADD COLUMN stock_restitue_at DATETIME NULL,
    ADD CONSTRAINT uq_commande_creation UNIQUE (id_utilisateur, cle_idempotence),
    ADD INDEX idx_commande_expiration (statut, reservation_expire_at);
