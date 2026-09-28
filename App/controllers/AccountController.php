<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\EmailVerificationService;

/** Affiche l'espace personnel de l'utilisateur connecté. */
final class AccountController extends Controller
{
    public function __construct(private readonly EmailVerificationService $emailVerificationService)
    {
    }

    /** Prépare les informations nécessaires au tableau de bord du compte. */
    public function index(): void
    {
        $user = Session::get('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $verificationNotice = Session::get('_email_verification_notice');
        Session::remove('_email_verification_notice');

        try {
            $emailVerification = $this->emailVerificationService->stateForUser($userId);
        } catch (\Throwable) {
            $emailVerification = ['exists' => false, 'verified' => false, 'transportConfigured' => false];
            $verificationNotice = 'unavailable';
        }

        $this->render('account/dashboard', [
            'title' => 'Mon compte | Tout y est',
            'metaDescription' => 'Gérez votre compte Tout y est.',
            'activePage' => 'account',
            'pageLibraries' => [],
            'pageStyles' => ['/assets/css/account.css'],
            'accountUser' => $user,
            'emailVerification' => $emailVerification,
            'emailVerificationNotice' => is_string($verificationNotice) ? $verificationNotice : null,
            'csrfToken' => CsrfMiddleware::token(),
        ]);
    }
}
