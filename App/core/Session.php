<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private function __construct()
    {
    }

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

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function remember(int $days = 30): void
    {
        setcookie(session_name(), session_id(), self::cookieOptions(time() + ($days * 24 * 60 * 60)));
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        setcookie(session_name(), '', self::cookieOptions(time() - 3600));
        session_destroy();
    }

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
