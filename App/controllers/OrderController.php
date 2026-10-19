<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Middleware\CsrfMiddleware;
use App\Services\OrderService;
use App\Services\ProductService;
use DomainException;
use Throwable;

final class OrderController extends Controller
{
    public function __construct(private readonly ?OrderService $orders, private readonly Request $request, private readonly ?ProductService $products = null) {}

    public function create(): void
    {
        if (!$this->request->isPost()) { Response::json(['error' => 'Méthode non autorisée.'], 405); return; }
        $userId = $this->userId();
        if ($userId < 1) { Response::json(['error' => 'Connectez-vous pour continuer.'], 401); return; }
        $input = $this->request->postParameters();
        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) { Response::json(['error' => 'Session expirée. Rechargez la page.'], 419); return; }
        $key = is_string($input['_creation_key'] ?? null) ? $input['_creation_key'] : '';
        $keys = Session::get('_checkout_creation_keys', []);
        if (!is_array($keys) || ($keys[$key] ?? null) !== $userId) { Response::json(['error' => 'Clé Checkout invalide. Rechargez la page.'], 422); return; }
        if ($this->orders === null) { Response::json(['error' => 'Création momentanément indisponible.'], 503); return; }
        $draft = Session::get('_checkout_delivery');
        if (!is_array($draft) || ($draft['userId'] ?? null) !== $userId) { Response::json(['error' => 'Validez votre livraison avant de continuer.'], 422); return; }
        try {
            $result = $this->orders->create($userId, $draft, $key);
            Response::json(['success' => true, 'reused' => $result['reused'], 'url' => '/checkout/paiement?commande=' . $result['id']]);
        } catch (DomainException $error) { Response::json(['error' => $error->getMessage()], 422); }
        catch (Throwable $error) {
            error_log('Création commande : ' . get_class($error) . ' [' . $error->getCode() . ']');
            Response::json(['error' => 'Impossible de créer la commande. Réessayez : la même demande ne sera pas dupliquée.'], 503);
        }
    }

    public function payment(): void
    {
        if ($this->userId() < 1) { Response::redirect('/connexion'); }
        if ($this->orders === null) { http_response_code(503); echo 'Service indisponible.'; return; }
        try {
            $data = $this->orders->forUser($this->userId(), $this->request->queryInteger('commande'));
            // Images actuelles uniquement ; noms, prix et taux restent les snapshots historiques.
            $images = $this->products?->productImages(array_column($data['lines'], 'id_produit')) ?? [];
            foreach ($data['lines'] as &$line) $line['image'] = $images[(int) $line['id_produit']][0] ?? null;
            unset($line);
            header('Cache-Control: no-store');
            $this->render('checkout/payment', $data + ['title' => 'Paiement | Tout y est', 'pageStyles' => ['/assets/css/checkout.css'], 'pageScripts' => ['/assets/js/checkout-payment.js']]);
        } catch (DomainException) { http_response_code(404); echo 'Commande introuvable pour ce compte.'; }
        catch (Throwable) { http_response_code(503); echo 'Commande momentanément indisponible. Réessayez.'; }
    }

    private function userId(): int
    {
        $user = Session::get('user');
        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
