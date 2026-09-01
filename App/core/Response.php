<?php

declare(strict_types=1);

namespace App\Core;

/** Centralise les réponses HTTP qui ne rendent pas directement une vue. */
final class Response
{
    /** Redirige le navigateur vers une autre adresse puis arrête la réponse. */
    public static function redirect(string $location, int $status = 303): void
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    /** Envoie une réponse JSON avec le statut HTTP demandé. */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
