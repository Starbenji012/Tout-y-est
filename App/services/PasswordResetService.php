<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PasswordResetSender;
use App\Models\PasswordResetToken;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/** Coordonne les demandes et consommations de réinitialisation de mot de passe. */
final class PasswordResetService
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly ?User $userModel,
        private readonly ?PasswordResetToken $tokenModel,
        private readonly PasswordResetSender $emailSender,
        private readonly ?PDO $database,
        private readonly ?DateTimeImmutable $now = null,
    ) {
    }

    /** Répond toujours de manière générique, même si l'adresse n'existe pas. */
    public function requestReset(string $email): array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)
            || $this->userModel === null
            || $this->tokenModel === null
            || $this->database === null
            || !$this->emailSender->isConfigured()
        ) {
            return ['status' => 'requested'];
        }

        try {
            $user = $this->userModel->findByEmail($email);

            if ($user === null) {
                return ['status' => 'requested'];
            }

            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
            $tokenHash = hash('sha256', $token);
            $now = $this->currentTime();
            $expiresAt = $now->modify('+' . self::TOKEN_LIFETIME_SECONDS . ' seconds');
            $this->tokenModel->replaceForUser(
                (int) $user['id_utilisateur'],
                $tokenHash,
                $expiresAt->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            );

            $name = trim((string) $user['prenom'] . ' ' . (string) $user['nom']);

            if (!$this->emailSender->sendPasswordReset((string) $user['email'], $name, $token)) {
                $this->tokenModel->deleteByHash($tokenHash);
            }
        } catch (Throwable) {
            // La réponse publique reste identique pour éviter toute énumération.
        }

        return ['status' => 'requested'];
    }

    /** Vérifie le format, l'existence et l'expiration sans consommer le jeton. */
    public function inspect(string $token): array
    {
        if ($this->tokenModel === null || !$this->validTokenFormat($token)) {
            return ['status' => 'invalid'];
        }

        try {
            $stored = $this->tokenModel->findByHash(hash('sha256', $token));
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }

        if ($stored === null) {
            return ['status' => 'invalid'];
        }

        try {
            $expiresAt = new DateTimeImmutable((string) $stored['expires_at'], new DateTimeZone('UTC'));
        } catch (Throwable) {
            return ['status' => 'invalid'];
        }

        return $expiresAt < $this->currentTime()
            ? ['status' => 'expired']
            : ['status' => 'valid'];
    }

    /** Valide le nouveau mot de passe puis consomme le jeton dans une transaction. */
    public function resetPassword(string $token, string $password, string $confirmation): array
    {
        $errors = $this->passwordErrors($password, $confirmation);

        if ($errors !== []) {
            return ['status' => 'validation_failed', 'errors' => $errors];
        }

        if ($this->userModel === null || $this->tokenModel === null || $this->database === null) {
            return ['status' => 'unavailable'];
        }

        if (!$this->validTokenFormat($token)) {
            return ['status' => 'invalid'];
        }

        $tokenHash = hash('sha256', $token);
        try {
            $stored = $this->tokenModel->findByHash($tokenHash);
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }

        if ($stored === null) {
            return ['status' => 'invalid'];
        }

        try {
            $expiresAt = new DateTimeImmutable((string) $stored['expires_at'], new DateTimeZone('UTC'));
        } catch (Throwable) {
            return ['status' => 'invalid'];
        }

        if ($expiresAt < $this->currentTime()) {
            return ['status' => 'expired'];
        }

        $this->database->beginTransaction();

        try {
            $updated = $this->userModel->updatePassword(
                (int) $stored['id_utilisateur'],
                password_hash($password, PASSWORD_DEFAULT),
            );
            $consumed = $this->tokenModel->deleteByHash($tokenHash);

            if (!$updated || !$consumed) {
                $this->database->rollBack();

                return ['status' => 'invalid'];
            }

            $this->database->commit();
        } catch (Throwable) {
            $this->database->rollBack();

            return ['status' => 'unavailable'];
        }

        return ['status' => 'reset'];
    }

    private function passwordErrors(string $password, string $confirmation): array
    {
        $errors = [];

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.';
        }

        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'La confirmation du mot de passe ne correspond pas.';
        }

        return $errors;
    }

    private function validTokenFormat(string $token): bool
    {
        return strlen($token) === self::TOKEN_BYTES * 2 && ctype_xdigit($token);
    }

    private function currentTime(): DateTimeImmutable
    {
        $now = $this->now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now->setTimezone(new DateTimeZone('UTC'));
    }
}