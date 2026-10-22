<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Services\CustomerOrderService;
use DomainException;
use Throwable;

final class CustomerOrderController extends Controller
{
    public function __construct(private readonly ?CustomerOrderService $orders, private readonly Request $request) {}
    public function index(): void { $this->show(false); }
    public function detail(): void { $this->show(true); }

    private function show(bool $detail): void
    {
        $user = Session::get('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($userId < 1) { Response::redirect('/connexion?return=/compte/commandes'); return; }
        header('Cache-Control: no-store');
        if ($this->request->method() !== 'GET') { http_response_code(405); echo 'Méthode non autorisée.'; return; }
        if (!$this->orders) { http_response_code(503); echo 'Vos commandes sont momentanément indisponibles.'; return; }
        try {
            $data = $detail ? $this->orders->detail($userId, $this->request->queryInteger('commande')) : $this->orders->listing($userId, $this->request->queryInteger('page'), $this->request->queryParameters());
            $this->render($detail ? 'account/order-detail' : 'account/orders', $data + ['title' => 'Mes commandes | Tout y est', 'activePage' => 'account', 'pageStyles' => $detail ? ['/assets/css/account.css'] : ['/assets/css/account.css','/assets/css/select-control.css'], 'pageScripts'=>$detail ? [] : ['/assets/js/select-control.js','/assets/js/customer-orders-filter.js']]);
        } catch (DomainException) { http_response_code(404); echo 'Commande introuvable pour ce compte.'; }
        catch (Throwable) { http_response_code(503); echo 'Vos commandes sont momentanément indisponibles. Réessayez plus tard.'; }
    }
}
