-- S1.4 - Réinitialisation sécurisée du mot de passe
-- Compatible avec MariaDB 10.4 et MySQL 8.

USE tout_y_est;

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