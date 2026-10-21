-- Mode V1 réel ; aucun provider externe n'est activé.
USE tout_y_est;
INSERT INTO mode_paiement (nom, statut)
VALUES ('Espèces à la livraison', 'actif')
ON DUPLICATE KEY UPDATE statut = 'actif';
