-- MariaDB 10.4 / MySQL 8. DDL : commit implicite, à exécuter hors transaction métier.
-- Application hors trafic de création de commandes. Aucune ancienne référence modifiée.
CREATE TABLE IF NOT EXISTS numero_commande_sequence (
    numero BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;

-- Reprise des références commerciales numériques éventuelles, sans remise à zéro.
-- Les références historiques datées/aléatoires ne correspondent pas à ce format.
INSERT IGNORE INTO numero_commande_sequence (numero)
SELECT CAST(SUBSTRING(numero_commande, 5) AS UNSIGNED)
FROM commande
WHERE numero_commande REGEXP '^TYE-[0-9]{1,20}$'
  AND CAST(SUBSTRING(numero_commande, 5) AS DECIMAL(20,0)) BETWEEN 1 AND 18446744073709551615;
