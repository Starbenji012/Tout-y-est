<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;

final class AccountController extends Controller
{
    public function index(): void
    {
        $user = Session::get('user');

        if (!is_array($user)) {
            Response::redirect('/connexion');
        }

        $this->render('account/dashboard', [
            'title' => 'Mon compte | Tout y est',
            'metaDescription' => 'Gérez votre compte Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => [],
            'pageStyles' => ['/assets/css/account.css'],
            'accountUser' => $user,
            'csrfToken' => CsrfMiddleware::token(),
        ]);
    }
}
