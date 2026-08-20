<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ErrorController extends Controller
{
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
