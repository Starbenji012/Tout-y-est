-- S1.3 - Vérification de possession de l'adresse e-mail
-- Compatible avec MariaDB 10.4 et MySQL 8.

USE tout_y_est;

ALTER TABLE utilisateur
    ADD COLUMN email_verified_at DATETIME NULL AFTER date_creation,
    ADD COLUMN email_verification_token_hash CHAR(64) NULL AFTER email_verified_at,
    ADD COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_token_hash,
    ADD COLUMN email_verification_sent_at DATETIME NULL AFTER email_verification_expires_at,
    ADD CONSTRAINT uq_utilisateur_email_verification_token_hash
        UNIQUE (email_verification_token_hash);
