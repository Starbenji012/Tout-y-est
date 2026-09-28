<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\EmailVerificationService;

/** Coordonne la validation du lien et le renvoi demandé depuis le compte. */
final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly Request $request,
    ) {
    }

    /** Affiche le résultat de la consommation du token reçu par e-mail. */
    public function verify(): void
    {
        try {
            $result = $this->emailVerificationService->verify(
                (string) ($this->request->queryParameters()['token'] ?? ''),
            );
        } catch (\Throwable) {
            $result = ['status' => 'unavailable'];
        }
        $user = Session::get('user');

        if (($result['status'] ?? '') === 'verified'
            && is_array($user)
            && (int) ($user['id'] ?? 0) === (int) ($result['userId'] ?? 0)
        ) {
            $user['emailVerified'] = true;
            Session::set('user', $user);
        }

        $this->renderResult((string) ($result['status'] ?? 'invalid'));
    }

    /** Renvoie un lien uniquement pour l'utilisateur de la session courante. */
    public function resend(): void
    {
        if (!$this->request->isPost()) {
            http_response_code(405);
            $this->renderResult('method_not_allowed');
            return;
        }

        $input = $this->request->postParameters();

        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            http_response_code(419);
            $this->renderResult('csrf_error');
            return;
        }

        $user = Session::get('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        try {
            $result = $this->emailVerificationService->issueForUser($userId);
        } catch (\Throwable) {
            $result = ['status' => 'unavailable'];
        }
        Session::set('_email_verification_notice', (string) ($result['status'] ?? 'unavailable'));

        Response::redirect('/compte');
    }

    /** Centralise la présentation des résultats sans logique métier. */
    private function renderResult(string $status): void
    {
        $this->render('auth/email-verification', [
            'title' => 'Vérification de votre e-mail | Tout y est',
            'metaDescription' => 'Résultat de la vérification de votre adresse e-mail.',
            'activePage' => 'account',
            'pageLibraries' => [],
            'pageStyles' => ['/assets/css/account.css'],
            'verificationStatus' => $status,
        ]);
    }
}
