<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;

/** Protège les formulaires contre les requêtes envoyées depuis un autre site. */
final class CsrfMiddleware
{
    private const SESSION_KEY = '_csrf_token';

    /** Empêche l'instanciation de cette classe utilitaire. */
    private function __construct()
    {
    }

    /** Retourne le jeton existant ou en crée un nouveau pour la session. */
    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = self::refresh();
        }

        return $token;
    }

    /** Remplace le jeton courant pour invalider immédiatement les anciens formulaires. */
    public static function refresh(): string
    {
        Session::start();

        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);

        return $token;
    }

    /** Compare de manière sûre le jeton reçu avec celui de la session. */
    public static function isValid(mixed $token): bool
    {
        Session::start();

        $storedToken = Session::get(self::SESSION_KEY);

        return is_string($token)
            && is_string($storedToken)
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}
