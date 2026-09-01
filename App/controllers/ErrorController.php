<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/** Affiche les pages d'erreur publiques de manière cohérente. */
final class ErrorController extends Controller
{
    /** Rend la page 404 lorsqu'aucune route ne correspond. */
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', [
            'title' => 'Page introuvable | Tout y est',
            'metaDescription' => 'La page demandée est introuvable.',
            'activePage' => '',
            'pageLibraries' => [],
        ]);
    }
}
