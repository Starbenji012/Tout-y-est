-- Rollback manuel MariaDB 10.4, hors trafic et après sauvegarde.
-- Refuse de perdre des options, associations ou identités modernes déjà utilisées.
-- À utiliser sur une S1.16 entièrement appliquée (pas un réparateur d'état partiel).
DROP TEMPORARY TABLE IF EXISTS s116_rollback_precheck;
CREATE TEMPORARY TABLE s116_rollback_precheck (ok INT NOT NULL CHECK(ok=1));
INSERT INTO s116_rollback_precheck SELECT IF(
    (SELECT COUNT(*) FROM option_produit)=0 AND
    (SELECT COUNT(*) FROM valeur_option_produit)=0 AND
    (SELECT COUNT(*) FROM variante_valeur_option)=0 AND
    (SELECT COUNT(*) FROM variante_produit WHERE cle_combinaison IS NOT NULL)=0,1,0);
DROP TEMPORARY TABLE s116_rollback_precheck;

DROP TABLE variante_valeur_option;
DROP TABLE valeur_option_produit;
DROP TABLE option_produit;
-- Ne jamais retirer le dernier index support de la FK produit historique.
ALTER TABLE variante_produit ADD INDEX IF NOT EXISTS fk_variante_produit_produit (id_produit);
ALTER TABLE variante_produit DROP CONSTRAINT chk_variante_combinaison;
ALTER TABLE variante_produit DROP INDEX uq_variante_combinaison;
ALTER TABLE variante_produit DROP INDEX uq_variante_identite_produit;
ALTER TABLE variante_produit DROP COLUMN cle_combinaison;
