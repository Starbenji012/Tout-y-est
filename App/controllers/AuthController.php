<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use App\Services\LoginThrottleService;

/** Coordonne les écrans et les actions de connexion et d'inscription. */
final class AuthController extends Controller
{
    /** Reçoit les services nécessaires sans créer de dépendance dans le contrôleur. */
    public function __construct(
        private readonly AuthService $authService,
        private readonly Request $request,
        private readonly LoginThrottleService $loginThrottleService,
    ) {
    }

    /** Affiche le formulaire ou traite la tentative d'authentification reçue. */
    public function index(): void
    {
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

        $mode = ($input['mode'] ?? '') === 'register' ? 'register' : 'login';
        $throttle = $this->loginThrottleService->status();

        if ($mode === 'login' && $throttle['blocked']) {
            $this->renderThrottleResponse($input, $throttle);
            return;
        }

        $result = $mode === 'register'
            ? $this->authService->register($input)
            : $this->authService->login($input);

        if (!$result['success']) {
            if ($mode === 'login' && ($result['reason'] ?? '') === 'invalid_credentials') {
                $throttle = $this->loginThrottleService->recordFailure();
            }

            if ($mode === 'login' && $throttle['blocked']) {
                $this->renderThrottleResponse($input, $throttle, $result['advice'] ?? null);
                return;
            }

            http_response_code(422);
            $this->renderPage($result['errors'], $input, [
                'authAdvice' => $result['advice'] ?? null,
                'loginFailures' => $throttle['count'],
                'showLoginHelp' => $throttle['showHelp'],
            ]);
            return;
        }

        if ($mode === 'login') {
            $this->loginThrottleService->clear();
        }

        Session::regenerate();
        CsrfMiddleware::refresh();
        Session::set('user', $result['user']);

        if (!empty($input['remember'])) {
            Session::remember();
        }

        $returnTo = $this->safeReturnPath((string) ($input['return_to'] ?? ''));
        Response::redirect($returnTo ?? '/compte');
    }

    /** Ferme la session courante puis renvoie l'utilisateur vers la connexion. */
    public function logout(): void
    {
        if ($this->request->isPost() && CsrfMiddleware::isValid($this->request->postParameters()['_token'] ?? null)) {
            Session::destroy();
        }

        Response::redirect('/');
    }

    /** Centralise les données communes envoyées à la page d'authentification. */
    private function renderPage(array $errors = [], array $old = [], array $context = []): void
    {
        unset($old['_token'], $old['password'], $old['password_confirmation']);

        $this->render('auth/index', [
            'title' => 'Connexion et inscription | Tout y est',
            'metaDescription' => 'Connectez-vous ou créez votre compte Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/account.css'],
            'pageScripts' => ['/assets/js/validation.js', '/assets/js/account.js'],
            'csrfToken' => CsrfMiddleware::token(),
            'authErrors' => $errors,
            'oldInput' => $old,
            'authAdvice' => $context['authAdvice'] ?? null,
            'loginFailures' => (int) ($context['loginFailures'] ?? 0),
            'showLoginHelp' => (bool) ($context['showLoginHelp'] ?? false),
            'loginRetryAfter' => (int) ($context['loginRetryAfter'] ?? 0),
            'returnTo' => $this->safeReturnPath((string) ($old['return_to'] ?? $this->request->queryParameters()['return'] ?? '')),
        ]);
    }

    /** Présente un message clair lorsque trop de tentatives ont été effectuées. */
    private function renderThrottleResponse(array $input, array $throttle, ?string $advice = null): void
    {
        $retryAfter = max(1, (int) $throttle['retryAfter']);
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $this->renderPage(
            ['Plusieurs tentatives ont échoué. Patientez un instant avant de réessayer.'],
            $input,
            [
                'authAdvice' => $advice ?? 'Utilisez « Mot de passe oublié ? » si vous ne retrouvez plus vos informations.',
                'loginFailures' => $throttle['count'],
                'showLoginHelp' => true,
                'loginRetryAfter' => $retryAfter,
            ],
        );
    }

    /** Accepte uniquement une destination interne sûre après authentification. */
    private function safeReturnPath(string $path): ?string
    {
        return in_array($path, ['/favoris', '/panier', '/boutique'], true) ? $path : null;
    }
}
