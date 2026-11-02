<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{Controller,Request,Response,Session};
use App\Middleware\CsrfMiddleware;
use App\Services\AdminCourierService;
use DomainException;
use Throwable;

/** Formulaires HTML classiques : POST puis redirection GET après réussite. */
final class CourierController extends Controller
{
    public function __construct(private readonly ?AdminCourierService $couriers,private readonly Request $request) {}
    public function index(): void
    {
        header('Cache-Control: no-store');
        try {
            if($this->request->method()!=='GET')throw new DomainException('Méthode non autorisée.',405);
            $this->page();
        }catch(Throwable $error){$this->failure($error);}
    }
    public function save(): void {$this->submit(false);}
    public function availability(): void {$this->submit(true);}
    private function submit(bool $availability): void
    {
        header('Cache-Control: no-store');$input=$this->request->postParameters();
        try {
            if(!$this->couriers)throw new DomainException('Administration temporairement indisponible.',503);
            $this->couriers->authorize();
            if(!$this->request->isPost())throw new DomainException('Méthode non autorisée.',405);
            if(!CsrfMiddleware::isValid($input['_token']??null))throw new DomainException('Session expirée. Rechargez la page avant de réessayer.',419);
            $allowed=$availability?['_token','id_livreur','statut']:['_token','id_livreur','nom'];
            if(array_diff(array_keys($input),$allowed))throw new DomainException('Paramètres non autorisés.',422);
            $id=null;
            if(array_key_exists('id_livreur',$input)) {
                $id=filter_var($input['id_livreur'],FILTER_VALIDATE_INT);
                if(!$id||$id<1)throw new DomainException('Livreur invalide.',422);
            }
            if($availability) {
                if($id===null||!is_string($input['statut']??null))throw new DomainException('Disponibilité invalide.',422);
                $result=$this->couriers->setAvailability($id,$input['statut']);
            }else {
                if(!is_string($input['nom']??null))throw new DomainException('Indiquez le nom du livreur.',422);
                $result=$this->couriers->save($id,$input['nom']);
            }
            Session::set('_admin_courier_flash',$result['message']);
            Response::redirect('/admin/livreurs');
        }catch(Throwable $error){$this->failure($error,$input);}
    }
    private function page(?string $error=null,array $old=[]): void
    {
        if(!$this->couriers)throw new DomainException('Administration temporairement indisponible.',503);
        $data=$this->couriers->listing();
        $flash=Session::get('_admin_courier_flash');Session::remove('_admin_courier_flash');
        $this->render('admin/couriers',$data+['title'=>'Livreurs | Tout y est','adminPage'=>'couriers','courierError'=>$error,'courierFlash'=>is_string($flash)?$flash:null,'old'=>$old,'csrfToken'=>CsrfMiddleware::token(),'pageScripts'=>['/assets/js/admin-couriers.js']],'admin');
    }
    private function failure(Throwable $error,array $old=[]): void
    {
        if($error instanceof DomainException&&in_array($error->getCode(),[419,422,404],true)) {
            http_response_code($error->getCode());
            try {$this->page($error->getMessage(),$old);return;}catch(Throwable) {}
        }
        if(!($error instanceof DomainException))error_log('Gestion des livreurs : '.get_class($error));
        (new AccessController())->failure($error instanceof DomainException?$error->getCode():503,'/admin/livreurs');
    }
}
