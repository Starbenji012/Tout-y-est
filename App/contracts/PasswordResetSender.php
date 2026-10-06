<?php

declare(strict_types=1);

namespace App\Contracts;

/** Définit le transport nécessaire à l'envoi d'un lien de réinitialisation. */
interface PasswordResetSender
{
    public function isConfigured(): bool;

    public function sendPasswordReset(string $email, string $name, string $token): bool;
}