<?php

declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\DeliveryZoneService;
use PDOException;

/** Interface minimale des zones ; aucune commande ni livraison n'est créée. */
final class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryZoneService $service, private readonly Request $request) {}

    public function index(): void
    {
        try { $result = $this->service->listing($this->userId()); }
        catch (PDOException) { $result = ['error' => 'Administration temporairement indisponible.', 'status' => 503]; }
        if (isset($result['error'])) {
            (new AccessController())->failure($result['status'],'/admin/zones-livraison');
            return;
        }
        header('Cache-Control: no-store');
        $this->render('admin/deliveries', ['title' => 'Zones de livraison | Tout y est', 'zones' => $result['zones'], 'csrfToken' => CsrfMiddleware::token(), 'pageScripts' => ['/assets/js/admin-delivery-zones.js']], 'admin');
    }

    public function save(): void
    {
        try {
            $access = $this->service->access($this->userId());
            if (isset($access['error'])) { Response::json($access, $access['status']); return; }
            if (!$this->request->isPost()) { Response::json(['error' => 'Méthode non autorisée.'], 405); return; }
            $input = $this->request->postParameters();
            if (!CsrfMiddleware::isValid($input['_token'] ?? null)) { Response::json(['error' => 'Session expirée. Rechargez la page.'], 419); return; }
            $result = $this->service->save($this->userId(), $input);
            if (!isset($result['error'])) $result['html'] = $this->renderPartial('admin/delivery-zones-list', $this->service->listing($this->userId()));
            Response::json($result, $result['status'] ?? 200);
        } catch (PDOException) { Response::json(['error' => 'Impossible d’enregistrer la zone. Réessayez plus tard.'], 503); }
    }

    private function userId(): int
    {
        $user = Session::get('user');
        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
