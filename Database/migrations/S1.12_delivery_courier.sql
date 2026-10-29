-- MariaDB 10.4 : ajout non destructif et rejouable, sans modifier les livraisons existantes.
ALTER TABLE livraison ADD COLUMN IF NOT EXISTS nom_livreur_snapshot VARCHAR(120) NULL;
