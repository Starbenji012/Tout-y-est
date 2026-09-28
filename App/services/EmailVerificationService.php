<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmailSender;
use App\Models\User;
use Closure;
use DateTimeImmutable;
use Throwable;

/** Gère le cycle de vie sécurisé des vérifications d'adresse e-mail. */
final class EmailVerificationService
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_LIFETIME_SECONDS = 86400;
    private const RESEND_COOLDOWN_SECONDS = 60;

    private readonly Closure $clock;

    public function __construct(
        private readonly ?User $userModel,
        private readonly EmailSender $emailSender,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    /** Retourne l'état utile à l'interface sans exposer le token. */
    public function stateForUser(int $userId): array
    {
        if ($this->userModel === null || $userId < 1) {
            return ['exists' => false, 'verified' => false, 'transportConfigured' => false];
        }

        $user = $this->userModel->findVerificationState($userId);

        return [
            'exists' => $user !== null,
            'verified' => $user !== null && $user['email_verified_at'] !== null,
            'transportConfigured' => $this->emailSender->isConfigured(),
            'email' => (string) ($user['email'] ?? ''),
        ];
    }

    /** Crée un token, remplace l'ancien puis tente l'envoi du nouveau lien. */
    public function issueForUser(int $userId): array
    {
        if ($this->userModel === null || $userId < 1) {
            return ['status' => 'unavailable'];
        }

        $user = $this->userModel->findVerificationState($userId);

        if ($user === null) {
            return ['status' => 'not_found'];
        }

        if ($user['email_verified_at'] !== null) {
            return ['status' => 'already_verified'];
        }

        if (!$this->emailSender->isConfigured()) {
            return ['status' => 'transport_unavailable'];
        }

        $now = ($this->clock)();
        $sentAt = $this->parseDate($user['email_verification_sent_at'] ?? null);

        if ($sentAt !== null) {
            $retryAfter = self::RESEND_COOLDOWN_SECONDS - ($now->getTimestamp() - $sentAt->getTimestamp());

            if ($retryAfter > 0) {
                return ['status' => 'cooldown', 'retryAfter' => $retryAfter];
            }
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);
        $expiresAt = $now->modify('+' . self::TOKEN_LIFETIME_SECONDS . ' seconds');
        $cooldownThreshold = $now->modify('-' . self::RESEND_COOLDOWN_SECONDS . ' seconds');
        $stored = $this->userModel->storeVerificationToken(
            $userId,
            $tokenHash,
            $expiresAt->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            $cooldownThreshold->format('Y-m-d H:i:s'),
        );

        if (!$stored) {
            return ['status' => 'cooldown', 'retryAfter' => self::RESEND_COOLDOWN_SECONDS];
        }

        $name = trim((string) $user['prenom'] . ' ' . (string) $user['nom']);

        try {
            $sent = $this->emailSender->sendVerification((string) $user['email'], $name, $token);
        } catch (Throwable) {
            $sent = false;
        }

        if (!$sent) {
            $this->userModel->clearUnsentVerificationToken($userId, $tokenHash);

            return ['status' => 'send_failed'];
        }

        return ['status' => 'sent', 'expiresAt' => $expiresAt->format(DATE_ATOM)];
    }

    /** Valide puis consomme un token brut reçu depuis le lien. */
    public function verify(string $token): array
    {
        if ($this->userModel === null || !$this->validTokenFormat($token)) {
            return ['status' => 'invalid'];
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userModel->findByVerificationTokenHash($tokenHash);

        if ($user === null || $user['email_verified_at'] !== null) {
            return ['status' => 'invalid'];
        }

        $now = ($this->clock)();
        $expiresAt = $this->parseDate($user['email_verification_expires_at'] ?? null);

        if ($expiresAt === null || $expiresAt < $now) {
            return ['status' => 'expired'];
        }

        if (!$this->userModel->consumeVerificationToken($tokenHash, $now->format('Y-m-d H:i:s'))) {
            return ['status' => 'invalid'];
        }

        return ['status' => 'verified', 'userId' => (int) $user['id_utilisateur']];
    }

    /** Accepte uniquement le format exact produit par random_bytes(). */
    private function validTokenFormat(string $token): bool
    {
        return strlen($token) === self::TOKEN_BYTES * 2 && ctype_xdigit($token);
    }

    /** Convertit une date SQL optionnelle sans masquer une valeur invalide. */
    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
