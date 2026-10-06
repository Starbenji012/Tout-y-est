<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmailSender;
use App\Contracts\PasswordResetSender;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/** Envoie les liens de vérification via le serveur SMTP configuré localement. */
final class SmtpEmailSender implements EmailSender, PasswordResetSender
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

        return $this->sendMessage(
            $email,
            $name,
            'Vérifiez votre adresse e-mail',
            "Bonjour {$name},\n\n"
                . "Confirmez votre adresse e-mail en ouvrant ce lien valable 24 heures :\n"
                . $verificationUrl
                . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
        );
    }

    /** Envoie un lien de réinitialisation via le même transport SMTP. */
    public function sendPasswordReset(string $email, string $name, string $token): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $resetUrl = (string) $this->config['app_url']
            . '/reinitialiser-mot-de-passe?token=' . rawurlencode($token);

        return $this->sendMessage(
            $email,
            $name,
            'Réinitialisation de votre mot de passe',
            "Bonjour {$name},\n\n"
                . "Choisissez un nouveau mot de passe en ouvrant ce lien valable 60 minutes :\n"
                . $resetUrl
                . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            'PASSWORD_RESET_MAIL_SEND_FAILED',
        );
    }

    /** Centralise uniquement la construction et l'envoi PHPMailer. */
    private function sendMessage(string $email, string $name, string $subject, string $body, ?string $failureEvent = null): bool
    {
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
            $mailer->Subject = $subject;
            $mailer->Body = $body;

            $sent = $mailer->send();

            if (!$sent && $failureEvent !== null) {
                error_log($failureEvent . ' reason=send_returned_false');
            }

            return $sent;
        } catch (Throwable $exception) {
            if ($failureEvent !== null) {
                error_log($failureEvent . ' reason=exception exception=' . $exception::class);
            }

            return false;
        }
    }
}
