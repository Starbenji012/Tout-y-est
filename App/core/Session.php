<?php

declare(strict_types=1);

namespace App\Core;

/** Encapsule la session PHP et ses réglages de sécurité. */
final class Session
{
    /** Empêche l'instanciation de cette classe utilitaire. */
    private function __construct()
    {
    }

    /** Démarre la session une seule fois avec des cookies sécurisés. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) (30 * 24 * 60 * 60));
        session_set_cookie_params(self::cookieOptions());
        session_start();
    }

    /** Lit une valeur de session ou retourne la valeur par défaut. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Enregistre une valeur dans la session courante. */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Supprime une valeur précise de la session. */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Renouvelle l'identifiant de session après une action sensible. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Prolonge la durée du cookie lorsque l'utilisateur souhaite être mémorisé. */
    public static function remember(int $days = 30): void
    {
        setcookie(session_name(), session_id(), self::cookieOptions(time() + ($days * 24 * 60 * 60)));
    }

    /** Efface les données et le cookie de la session courante. */
    public static function destroy(): void
    {
        $_SESSION = [];
        setcookie(session_name(), '', self::cookieOptions(time() - 3600));
        session_destroy();
    }

    /** Regroupe les options appliquées à tous les cookies de session. */
    private static function cookieOptions(?int $expires = null): array
    {
        $options = [
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ];

        if ($expires !== null) {
            $options['expires'] = $expires;
        }

        return $options;
    }
}
