<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\CheckoutService;
use PDOException;

final class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService, private readonly Request $request) {}

    public function index(): void
    {
        if ($this->userId() < 1) {
            Response::redirect('/connexion?return=/checkout');
        }
        $context = $this->checkoutService->context($this->userId());
        if (isset($context['error'])) {
            Session::set('_checkout_notice', $context['error']);
            Response::redirect('/panier');
        }
        header('Cache-Control: no-store');
        $this->render('checkout/index', [
            'title' => 'Informations & livraison | Tout y est',
            'activePage' => 'cart',
            'pageStyles' => ['/assets/css/checkout.css'],
            'pageScripts' => ['/assets/js/checkout.js'],
            'checkout' => $context,
            'csrfToken' => CsrfMiddleware::token(),
        ]);
    }

    public function summary(): void
    {
        if ($this->request->method() !== 'GET') {
            Response::json(['error' => 'Méthode non autorisée.'], 405);
            return;
        }
        $result = $this->checkoutService->summary($this->userId(), $this->request->queryInteger('zone'));
        if (!isset($result['error'])) {
            $result['html'] = $this->renderPartial('checkout/summary', ['cart' => $result['cart'], 'summary' => $result]);
            unset($result['cart']);
        }
        Response::json($result, $result['status'] ?? 200);
    }

    public function delivery(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['error' => 'Méthode non autorisée.'], 405);
            return;
        }
        if ($this->userId() < 1) {
            Response::json(['error' => 'Connectez-vous pour continuer.'], 401);
            return;
        }
        $input = $this->request->postParameters();
        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            Response::json(['error' => 'Votre session a expiré. Rechargez la page.'], 419);
            return;
        }
        try {
            $result = $this->checkoutService->validateDelivery($this->userId(), $input);
            if (!empty($result['success'])) {
                // Brouillon de session uniquement : aucun INSERT commande ou paiement.
                Session::set('_checkout_delivery', ['userId' => $this->userId(), 'address' => $result['address']]);
                $result['summary']['html'] = $this->renderPartial('checkout/summary', ['cart' => $result['summary']['cart'], 'summary' => $result['summary']]);
                unset($result['summary']['cart']);
            }
            Response::json($result, $result['status'] ?? 200);
        } catch (PDOException) {
            Response::json(['error' => 'Impossible d’enregistrer cette adresse. Réessayez plus tard.'], 503);
        }
    }

    private function userId(): int
    {
        $user = Session::get('user');
        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
