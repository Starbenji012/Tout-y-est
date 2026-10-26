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
    CONSTRAINT uq_journal_commande_action UNIQUE (id_commande, action),
    CONSTRAINT chk_journal_action CHECK (action IN ('prepare','ship','deliver','collect-cash','cancel')),
    CONSTRAINT fk_journal_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_journal_admin FOREIGN KEY (id_administrateur)
        REFERENCES utilisateur (id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_journal_paiement FOREIGN KEY (id_paiement)
        REFERENCES paiement (id_paiement) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_journal_admin (id_administrateur),
    INDEX idx_journal_paiement (id_paiement)
) ENGINE=InnoDB;
