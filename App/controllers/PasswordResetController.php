<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\CsrfMiddleware;
use App\Services\PasswordResetService;

/** Présente les formulaires et actions de réinitialisation du mot de passe. */
final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly Request $request,
    ) {
    }

    /** Affiche ou traite la demande générique de réinitialisation. */
    public function request(): void
    {
        if ($this->request->isPost()) {
            $input = $this->request->postParameters();

            if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
                http_response_code(419);
                $this->renderRequest(['Votre session a expiré. Rechargez la page et réessayez.']);

                return;
            }

            $this->passwordResetService->requestReset((string) ($input['email'] ?? ''));
            $this->renderRequest([], true);

            return;
        }

        $this->renderRequest();
    }

    /** Affiche le formulaire de nouveau mot de passe associé au jeton. */
    public function form(): void
    {
        $token = (string) ($this->request->queryParameters()['token'] ?? '');
        $state = $this->passwordResetService->inspect($token);
        $this->renderForm($token, $state['status'] ?? 'invalid');
    }

    /** Valide le nouveau mot de passe et redirige après réussite. */
    public function reset(): void
    {
        if (!$this->request->isPost()) {
            Response::redirect('/mot-de-passe-oublie');
        }

        $input = $this->request->postParameters();

        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            http_response_code(419);
            $this->renderForm((string) ($input['token'] ?? ''), 'invalid', ['Votre session a expiré. Rechargez la page et réessayez.']);

            return;
        }

        $result = $this->passwordResetService->resetPassword(
            (string) ($input['token'] ?? ''),
            (string) ($input['password'] ?? ''),
            (string) ($input['password_confirmation'] ?? ''),
        );

        if (($result['status'] ?? '') === 'reset') {
            Response::redirect('/connexion?reset=success');
        }

        $this->renderForm(
            (string) ($input['token'] ?? ''),
            (string) ($result['status'] ?? 'invalid'),
            is_array($result['errors'] ?? null) ? $result['errors'] : [],
        );
    }

    private function renderRequest(array $errors = [], bool $submitted = false): void
    {
        $this->render('auth/password-reset-request', [
            'title' => 'Mot de passe oublié | Tout y est',
            'metaDescription' => 'Réinitialisez votre mot de passe Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => [],
            'pageStyles' => ['/assets/css/account.css'],
            'authErrors' => $errors,
            'resetSubmitted' => $submitted,
            'csrfToken' => CsrfMiddleware::token(),
        ]);
    }

    private function renderForm(string $token, string $status, array $errors = []): void
    {
        $this->render('auth/password-reset-form', [
            'title' => 'Nouveau mot de passe | Tout y est',
            'metaDescription' => 'Choisissez un nouveau mot de passe Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => [],
            'pageStyles' => ['/assets/css/account.css'],
            'token' => $token,
            'resetStatus' => $status,
            'authErrors' => $errors,
            'csrfToken' => CsrfMiddleware::token(),
        ]);
    }
}