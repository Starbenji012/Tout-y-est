-- S1.3 - Retour arrière de la vérification d'e-mail

USE tout_y_est;

ALTER TABLE utilisateur
    DROP INDEX uq_utilisateur_email_verification_token_hash,
    DROP COLUMN email_verification_sent_at,
    DROP COLUMN email_verification_expires_at,
    DROP COLUMN email_verification_token_hash,
    DROP COLUMN email_verified_at;
