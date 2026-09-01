<?php

declare(strict_types=1);

namespace App\Core;

/** Fournit un accès simple et typé aux données de la requête HTTP. */
final class Request
{
    /** Retourne la méthode HTTP en majuscules. */
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Indique si la requête courante utilise la méthode POST. */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /** Retourne les paramètres présents dans l'URL. */
    public function queryParameters(): array
    {
        return $_GET;
    }

    /** Retourne les données envoyées par un formulaire POST. */
    public function postParameters(): array
    {
        return $_POST;
    }

    /** Lit un paramètre d'URL et le convertit en entier positif. */
    public function queryInteger(string $key): int
    {
        $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);

        return $value === false ? 0 : (int) $value;
    }
}
