<?php

declare(strict_types=1);

namespace App\Contracts;

/** Définit le transport nécessaire à l'envoi d'un lien de vérification. */
interface EmailSender
{
    public function isConfigured(): bool;

    public function sendVerification(string $email, string $name, string $token): bool;
}
