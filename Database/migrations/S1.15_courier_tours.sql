-- S1.15 forward MariaDB 10.4. Précontrôle AVANT tout DDL durable.
DROP TEMPORARY TABLE IF EXISTS s115_precheck;
CREATE TEMPORARY TABLE s115_precheck (ok INT NOT NULL CHECK(ok=1));
INSERT INTO s115_precheck SELECT IF(COUNT(*)=0,1,0) FROM livraison
 WHERE id_livreur IS NOT NULL AND statut IN ('en_preparation','en_livraison')
 AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
   AND TABLE_NAME='livraison' AND INDEX_NAME='uq_livraison_livreur_engage');
DROP TEMPORARY TABLE s115_precheck;

CREATE TABLE IF NOT EXISTS verrou_dispatch (id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1),version_schema TINYINT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB;
INSERT IGNORE INTO verrou_dispatch(id) VALUES(1);

ALTER TABLE livreur ADD COLUMN IF NOT EXISTS id_utilisateur BIGINT UNSIGNED NULL;
ALTER TABLE livreur ADD UNIQUE INDEX IF NOT EXISTS uq_livreur_compte(id_utilisateur);
SET @s115_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='livreur' AND CONSTRAINT_NAME='fk_livreur_compte'),'SELECT 1','ALTER TABLE livreur ADD CONSTRAINT fk_livreur_compte FOREIGN KEY(id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT');
PREPARE s115_statement FROM @s115_sql;
EXECUTE s115_statement;
DEALLOCATE PREPARE s115_statement;

CREATE TABLE IF NOT EXISTS tournee (
 id_tournee BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_livreur BIGINT UNSIGNED NOT NULL,
 nom_livreur_snapshot VARCHAR(120) NOT NULL,
 statut VARCHAR(20) NOT NULL DEFAULT 'chargement',
 version_manifeste INT UNSIGNED NOT NULL DEFAULT 0,
 date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_depart DATETIME NULL,
 date_retour DATETIME NULL,
 livreur_ouvert BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN statut IN ('chargement','en_cours') THEN id_livreur ELSE NULL END) PERSISTENT,
 UNIQUE KEY uq_tournee_ouverte(livreur_ouvert),
 KEY idx_tournee_livreur(id_livreur,id_tournee),
 CONSTRAINT fk_tournee_livreur FOREIGN KEY(id_livreur) REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_tournee_statut CHECK(statut IN ('chargement','en_cours','retour_termine','annulee')),
 CONSTRAINT chk_tournee_dates CHECK((statut IN ('chargement','annulee') AND date_depart IS NULL AND date_retour IS NULL) OR (statut='en_cours' AND date_depart IS NOT NULL AND date_retour IS NULL) OR (statut='retour_termine' AND date_depart IS NOT NULL AND date_retour IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tentative_livraison (
 id_tentative BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_tournee BIGINT UNSIGNED NOT NULL,
 id_livraison BIGINT UNSIGNED NOT NULL,
 emplacement TINYINT UNSIGNED NOT NULL,
 resultat VARCHAR(20) NOT NULL DEFAULT 'en_attente',
 motif VARCHAR(32) NULL,
 commentaire VARCHAR(500) NULL,
 date_affectation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_resultat DATETIME NULL,
 date_cloture DATETIME NULL,
 livraison_active BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN date_cloture IS NULL THEN id_livraison ELSE NULL END) PERSISTENT,
 UNIQUE KEY uq_tentative_active(livraison_active),
 UNIQUE KEY uq_tournee_emplacement(id_tournee,emplacement),
 UNIQUE KEY uq_tournee_livraison(id_tournee,id_livraison),
 KEY idx_tentative_livraison(id_livraison,id_tentative),
 CONSTRAINT fk_tentative_tournee FOREIGN KEY(id_tournee) REFERENCES tournee(id_tournee) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_tentative_livraison FOREIGN KEY(id_livraison) REFERENCES livraison(id_livraison) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_tentative_place CHECK(emplacement BETWEEN 1 AND 5),
 CONSTRAINT chk_tentative_resultat CHECK(resultat IN ('en_attente','livree','probleme','annulee')),
 CONSTRAINT chk_tentative_motif CHECK(motif IS NULL OR motif IN ('absent','injoignable','adresse','refus','paiement','autre')),
 CONSTRAINT chk_tentative_probleme CHECK(resultat<>'probleme' OR (motif IS NOT NULL AND (motif<>'autre' OR CHAR_LENGTH(TRIM(commentaire))>0)))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS collecte_cash (
 id_collecte BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_paiement BIGINT UNSIGNED NOT NULL,
 id_tentative BIGINT UNSIGNED NOT NULL,
 id_livreur BIGINT UNSIGNED NOT NULL,
 montant DECIMAL(18,2) NOT NULL,
 devise CHAR(3) NOT NULL,
 date_collecte DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 date_remise DATETIME NULL,
 id_confirmateur BIGINT UNSIGNED NULL,
 UNIQUE KEY uq_collecte_paiement(id_paiement),
 UNIQUE KEY uq_collecte_tentative(id_tentative),
 KEY idx_collecte_livreur(id_livreur,date_remise),
 CONSTRAINT fk_collecte_paiement FOREIGN KEY(id_paiement) REFERENCES paiement(id_paiement) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_tentative FOREIGN KEY(id_tentative) REFERENCES tentative_livraison(id_tentative) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_livreur FOREIGN KEY(id_livreur) REFERENCES livreur(id_livreur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_collecte_confirmateur FOREIGN KEY(id_confirmateur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT chk_collecte_montant CHECK(montant>=0),
 CONSTRAINT chk_collecte_devise CHECK(devise IN ('USD','CDF')),
 CONSTRAINT chk_collecte_remise CHECK((date_remise IS NULL AND id_confirmateur IS NULL) OR (date_remise IS NOT NULL AND id_confirmateur IS NOT NULL))
) ENGINE=InnoDB;

-- Le journal conserve ses anciens auteurs et ses anciennes protections.
ALTER TABLE journal_commande MODIFY id_administrateur BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS type_acteur VARCHAR(12) NOT NULL DEFAULT 'admin',
 ADD COLUMN IF NOT EXISTS id_acteur BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS ressource VARCHAR(80) NULL;
SET @s115_sql=IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' AND CONSTRAINT_NAME='fk_journal_acteur'),'SELECT 1','ALTER TABLE journal_commande ADD CONSTRAINT fk_journal_acteur FOREIGN KEY(id_acteur) REFERENCES utilisateur(id_utilisateur) ON DELETE RESTRICT ON UPDATE RESTRICT');
PREPARE s115_statement FROM @s115_sql;
EXECUTE s115_statement;
DEALLOCATE PREPARE s115_statement;
-- Cet index reste disponible pour fk_journal_commande pendant la reconstruction de l'UNIQUE.
ALTER TABLE journal_commande ADD INDEX IF NOT EXISTS idx_journal_commande(id_commande);
ALTER TABLE journal_commande DROP INDEX IF EXISTS uq_journal_transition;
ALTER TABLE journal_commande MODIFY action_unique VARCHAR(32) GENERATED ALWAYS AS (CASE WHEN action IN ('prepare','cancel','collect-cash') OR (ressource IS NULL AND action IN ('ship','deliver')) THEN action ELSE NULL END) PERSISTENT;
ALTER TABLE journal_commande ADD UNIQUE INDEX IF NOT EXISTS uq_journal_transition(id_commande,action_unique);
ALTER TABLE journal_commande ADD UNIQUE INDEX IF NOT EXISTS uq_journal_ressource(id_commande,action,ressource);
ALTER TABLE journal_commande DROP CONSTRAINT IF EXISTS chk_journal_action,
 ADD CONSTRAINT chk_journal_action CHECK(action IN ('prepare','ship','deliver','collect-cash','cancel','assign','depart','incident','return','cash-collected','cash-remitted'));
ALTER TABLE journal_commande DROP CONSTRAINT IF EXISTS chk_journal_acteur,
 ADD CONSTRAINT chk_journal_acteur CHECK(type_acteur IN ('admin','livreur','client','systeme'));

-- Les deux invariants de remplacement existent AVANT de retirer celui de S1.14.
ALTER TABLE livraison DROP INDEX IF EXISTS uq_livraison_livreur_engage;
ALTER TABLE livraison DROP COLUMN IF EXISTS id_livreur_engage;
UPDATE verrou_dispatch SET version_schema=15 WHERE id=1;
