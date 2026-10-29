<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Request, Response};
use App\Middleware\CsrfMiddleware;
use App\Services\AdminOrderService;
use DomainException;
use Throwable;

/** Mutations uniquement : aucune interface ni changement libre de statut. */
final class OrderController
{
    public function __construct(private readonly ?AdminOrderService $orders, private readonly Request $request) {}
    public function prepare(): void { $this->handle('prepare'); }
    public function ship(): void { $this->handle('ship'); }
    public function deliver(): void { $this->handle('deliver'); }
    public function collectCash(): void { $this->handle('collectCash'); }
    public function cancel(): void { $this->handle('cancel'); }

    private function handle(string $action): void
    {
        if (!$this->request->isPost()) { Response::json(['error'=>'Méthode non autorisée.'],405); return; }
        if (!$this->orders) { Response::json(['error'=>'Administration indisponible.'],503); return; }
        try {
            $this->orders->authorize();
            $input = $this->request->postParameters();
            if (!CsrfMiddleware::isValid($input['_token'] ?? null)) { Response::json(['error'=>'Session expirée. Rechargez la page.'],419); return; }
            $allowed = match($action) {'cancel'=>['commande','motif','_token'],'ship'=>['commande','livreur','_token'],default=>['commande','_token']};
            if (array_diff(array_keys($input),$allowed) !== []) throw new DomainException('Paramètres non autorisés.',422);
            $id = filter_var($input['commande'] ?? null,FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new DomainException('Commande invalide.',422);
            $result = $action === 'cancel'
                ? $this->orders->cancel($id,is_string($input['motif'] ?? null) ? $input['motif'] : '')
                : ($action==='ship'?$this->orders->ship($id,is_string($input['livreur']??null)?$input['livreur']:''):$this->orders->{$action}($id));
            Response::json($result);
        } catch (DomainException $error) {
            $code = in_array($error->getCode(),[401,403,404,409,422,503],true) ? $error->getCode() : 422;
            Response::json(['error'=>$error->getMessage()],$code);
        } catch (Throwable $error) {
            error_log('Action Admin commande : ' . get_class($error) . ' [' . $error->getCode() . ']');
            Response::json(['error'=>'Action impossible pour le moment. Réessayez sans modifier votre demande.'],503);
        }
    }
}
