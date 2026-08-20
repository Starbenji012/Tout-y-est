<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly Request $request,
    ) {
    }

    public function index(): void
    {
        if (is_array(Session::get('user'))) {
            Response::redirect('/compte');
        }

        if (!$this->request->isPost()) {
            $this->renderPage();
            return;
        }

        $input = $this->request->postParameters();

        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            http_response_code(419);
            $this->renderPage(['Votre session a expiré. Rechargez la page et réessayez.'], $input);
            return;
        }

        $result = ($input['mode'] ?? '') === 'register'
            ? $this->authService->register($input)
            : $this->authService->login($input);

        if (!$result['success']) {
            http_response_code(422);
            $this->renderPage($result['errors'], $input);
            return;
        }

        Session::regenerate();
        Session::set('user', $result['user']);

        if (!empty($input['remember'])) {
            Session::remember();
        }

        Response::redirect('/compte');
    }

    public function logout(): void
    {
        if ($this->request->isPost() && CsrfMiddleware::isValid($this->request->postParameters()['_token'] ?? null)) {
            Session::destroy();
        }

        Response::redirect('/');
    }

    private function renderPage(array $errors = [], array $old = []): void
    {
        unset($old['_token'], $old['password'], $old['password_confirmation']);

        $this->render('auth/index', [
            'title' => 'Connexion et inscription | Tout y est',
            'metaDescription' => 'Connectez-vous ou créez votre compte Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => ['gsap'],
            'pageStyles' => ['/assets/css/account.css'],
            'pageScripts' => ['/assets/js/account.js'],
            'csrfToken' => CsrfMiddleware::token(),
            'authErrors' => $errors,
            'oldInput' => $old,
        ]);
    }
}
