<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmailSender;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/** Envoie les liens de vérification via le serveur SMTP configuré localement. */
final class SmtpEmailSender implements EmailSender
{
    public function __construct(private readonly array $config)
    {
    }

    /** Vérifie la présence du transport, de sa configuration et de PHPMailer. */
    public function isConfigured(): bool
    {
        $credentialsAreComplete = ($this->config['username'] ?? '') === ''
            || ($this->config['password'] ?? '') !== '';

        return ($this->config['transport'] ?? '') === 'smtp'
            && class_exists(PHPMailer::class)
            && ($this->config['host'] ?? '') !== ''
            && filter_var($this->config['from_address'] ?? '', FILTER_VALIDATE_EMAIL) !== false
            && ($this->config['app_url'] ?? '') !== ''
            && in_array(($this->config['encryption'] ?? ''), ['tls', 'ssl'], true)
            && $credentialsAreComplete;
    }

    /** Construit puis transmet un message texte sans exposer le token aux logs. */
    public function sendVerification(string $email, string $name, string $token): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $verificationUrl = (string) $this->config['app_url']
            . '/verification-email?token=' . rawurlencode($token);

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = (string) $this->config['host'];
            $mailer->Port = (int) $this->config['port'];
            $mailer->SMTPAuth = ($this->config['username'] ?? '') !== '';
            $mailer->Username = (string) ($this->config['username'] ?? '');
            $mailer->Password = (string) ($this->config['password'] ?? '');
            $mailer->CharSet = 'UTF-8';

            if (($this->config['encryption'] ?? '') === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (($this->config['encryption'] ?? '') === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->setFrom(
                (string) $this->config['from_address'],
                (string) ($this->config['from_name'] ?? 'Tout y est'),
            );
            $mailer->addAddress($email, $name);
            $mailer->Subject = 'Vérifiez votre adresse e-mail';
            $mailer->Body = "Bonjour {$name},\n\n"
                . "Confirmez votre adresse e-mail en ouvrant ce lien valable 24 heures :\n"
                . $verificationUrl
                . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.";

            return $mailer->send();
        } catch (Throwable) {
            return false;
        }
    }
}
