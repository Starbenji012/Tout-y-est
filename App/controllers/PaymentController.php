<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Controller, Request, Response, Session};
use App\Middleware\CsrfMiddleware;
use App\Services\{PaymentService, MoneyService};
use DomainException;
use Throwable;

final class PaymentController extends Controller
{
    public function __construct(private readonly ?PaymentService $payments, private readonly Request $request, private readonly MoneyService $money) {}

    public function confirmCash(): void
    {
        if (!$this->request->isPost()) { Response::json(['error' => 'Méthode non autorisée.'], 405); return; }
        $userId = $this->userId();
        if ($userId < 1) { Response::json(['error' => 'Connectez-vous pour confirmer.'], 401); return; }
        $input = $this->request->postParameters();
        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) { Response::json(['error' => 'Session expirée. Rechargez la page.'], 419); return; }
        if (!$this->payments) { Response::json(['error' => 'Confirmation momentanément indisponible.'], 503); return; }
        $id = filter_var($input['commande'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) { Response::json(['error' => 'Commande invalide.'], 422); return; }
        try {
            $result = $this->payments->confirmCash($userId, $id);
            Response::json(['success' => true, 'reused' => $result['reused'], 'url' => '/checkout/confirmation?commande=' . $result['id']]);
        } catch (DomainException $error) { Response::json(['error' => $error->getMessage()], 422); }
        catch (Throwable $error) {
            error_log('Confirmation cash : ' . get_class($error) . ' [' . $error->getCode() . ']');
            Response::json(['error' => 'Confirmation impossible. Réessayez : votre demande ne sera pas dupliquée.'], 503);
        }
    }

    public function confirmation(): void
    {
        if ($this->userId() < 1) { Response::redirect('/connexion'); return; }
        if (!$this->payments) { http_response_code(503); echo 'Service indisponible.'; return; }
        try {
            $data = $this->payments->confirmedForUser($this->userId(), $this->request->queryInteger('commande'));
            // Une ancienne URL Checkout reste utile après progression, sans écran de confirmation périmé.
            if ($data['order']['statut'] !== 'confirmee') {
                Response::redirect('/compte/commande?commande=' . (int)$data['order']['id_commande']);
            }
            $data['totalFormatted'] = $this->money->format($this->money->minor($data['order']['total']));
            $data['cdfFormatted'] = $this->money->format($this->money->usdToCdfAtRate($this->money->minor($data['order']['total']), $data['order']['taux_usd_cdf']), 'CDF');
            header('Cache-Control: no-store');
            $this->render('checkout/confirmation', $data + ['title' => 'Commande confirmée | Tout y est', 'pageStyles' => ['/assets/css/checkout.css']]);
        } catch (DomainException) { http_response_code(404); echo 'Confirmation introuvable pour ce compte.'; }
        catch (Throwable) { http_response_code(503); echo 'Confirmation momentanément indisponible.'; }
    }

    private function userId(): int
    {
        $user = Session::get('user');
        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
