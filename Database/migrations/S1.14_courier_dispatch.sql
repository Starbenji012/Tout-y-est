-- MariaDB 10.4 : contrôler/sauvegarder avant application manuelle.
-- Échec volontaire si des affectations actives sont déjà en doublon.
ALTER TABLE livraison ADD COLUMN IF NOT EXISTS id_livreur_engage BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN statut IN ('en_preparation','en_livraison') THEN id_livreur ELSE NULL END) PERSISTENT;
ALTER TABLE livraison ADD UNIQUE INDEX IF NOT EXISTS uq_livraison_livreur_engage (id_livreur_engage);
ALTER TABLE journal_commande ADD COLUMN IF NOT EXISTS action_unique VARCHAR(32)
    GENERATED ALWAYS AS (CASE WHEN action='assign' THEN NULL ELSE action END) PERSISTENT;
ALTER TABLE journal_commande ADD UNIQUE INDEX IF NOT EXISTS uq_journal_transition (id_commande,action_unique);
ALTER TABLE journal_commande DROP INDEX IF EXISTS uq_journal_commande_action;
ALTER TABLE journal_commande DROP CONSTRAINT IF EXISTS chk_journal_action,
    ADD CONSTRAINT chk_journal_action CHECK (action IN ('prepare','ship','deliver','collect-cash','cancel','assign'));
