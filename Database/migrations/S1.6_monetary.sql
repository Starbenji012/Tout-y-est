-- S1.6 : USD futur, historique sans devise preserve (NULL).
USE tout_y_est;

ALTER TABLE variante_produit
    ADD COLUMN devise_reference CHAR(3) NULL DEFAULT NULL AFTER prix_reference,
    ADD CONSTRAINT chk_variante_devise CHECK (devise_reference IS NULL OR devise_reference = 'USD');
-- SET DEFAULT ne remplit pas les lignes existantes.
ALTER TABLE variante_produit ALTER COLUMN devise_reference SET DEFAULT 'USD';

ALTER TABLE zone_livraison
    ADD COLUMN devise_reference CHAR(3) NULL DEFAULT NULL,
    ADD CONSTRAINT chk_zone_devise CHECK (devise_reference IS NULL OR devise_reference = 'USD');
ALTER TABLE zone_livraison ALTER COLUMN devise_reference SET DEFAULT 'USD';

ALTER TABLE commande
    ADD COLUMN devise_reference CHAR(3) NULL,
    ADD COLUMN devise_paiement CHAR(3) NULL,
    ADD COLUMN taux_usd_cdf DECIMAL(12,4) NULL,
    ADD COLUMN total_paiement DECIMAL(18,2) NULL,
    ADD CONSTRAINT chk_commande_devise_reference CHECK (devise_reference IS NULL OR devise_reference = 'USD'),
    ADD CONSTRAINT chk_commande_devise_paiement CHECK (devise_paiement IS NULL OR devise_paiement IN ('USD','CDF')),
    ADD CONSTRAINT chk_commande_taux CHECK (taux_usd_cdf IS NULL OR taux_usd_cdf BETWEEN 0.0001 AND 100000),
    ADD CONSTRAINT chk_commande_total_paiement CHECK (total_paiement IS NULL OR total_paiement >= 0);

ALTER TABLE paiement
    ADD COLUMN devise_reglement CHAR(3) NULL,
    ADD COLUMN taux_usd_cdf DECIMAL(12,4) NULL,
    ADD COLUMN montant_reglement DECIMAL(18,2) NULL,
    ADD CONSTRAINT chk_paiement_devise CHECK (devise_reglement IS NULL OR devise_reglement IN ('USD','CDF')),
    ADD CONSTRAINT chk_paiement_taux CHECK (taux_usd_cdf IS NULL OR taux_usd_cdf BETWEEN 0.0001 AND 100000),
    ADD CONSTRAINT chk_paiement_reglement CHECK (montant_reglement IS NULL OR montant_reglement >= 0);

INSERT INTO parametre (id_parametre, cle, valeur)
SELECT next_id, 'monetary_config', '{"reference":"USD","alternative":"CDF","usd_cdf_rate":"2450"}'
FROM (SELECT COALESCE(MAX(id_parametre), 0) + 1 AS next_id FROM parametre) AS identifiers
WHERE NOT EXISTS (SELECT 1 FROM parametre WHERE cle = 'monetary_config');
