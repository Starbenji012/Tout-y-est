<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;

final class CsrfMiddleware
{
    private const SESSION_KEY = '_csrf_token';

    private function __construct()
    {
    }

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function isValid(mixed $token): bool
    {
        $storedToken = Session::get(self::SESSION_KEY);

        return is_string($token)
            && is_string($storedToken)
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}
