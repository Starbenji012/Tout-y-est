<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{Controller,Request};
use App\Services\AdminOrderReadService;
use DomainException;
use Throwable;

/** Pages GET uniquement ; les mutations restent dans leur contrôleur dédié. */
final class OrderReadController extends Controller
{
    public function __construct(private readonly ?AdminOrderReadService $orders,private readonly Request $request) {}
    public function index(): void {$this->show(false);}
    public function detail(): void {$this->show(true);}
    private function show(bool $detail): void
    {
        header('Cache-Control: no-store');
        try {
            if($this->request->method()!=='GET')throw new DomainException('Méthode non autorisée.',405);
            if(!$this->orders)throw new DomainException('Administration temporairement indisponible.',503);
            $input=$this->request->queryParameters();
            $data=$detail?$this->orders->detail(is_string($input['reference']??null)?$input['reference']:''):$this->orders->listing($input);
            $this->render($detail?'admin/order-detail':'admin/orders',$data+['title'=>'Commandes | Tout y est','adminPage'=>'orders','pageStyles'=>['/assets/css/select-control.css'],'pageScripts'=>['/assets/js/select-control.js','/assets/js/admin-orders.js','/assets/js/admin-order-actions.js']],'admin');
        }catch(DomainException $e){(new AccessController())->failure($e->getCode(),(string)($_SERVER['REQUEST_URI']??'/admin/commandes'));}
        catch(Throwable $e){error_log('Lecture Admin commandes : '.get_class($e));http_response_code(503);echo 'Administration temporairement indisponible.';}
    }
}
