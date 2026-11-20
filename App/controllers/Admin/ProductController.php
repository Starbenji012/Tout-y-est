<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\{Controller,Request,Response,Session};
use App\Middleware\CsrfMiddleware;
use App\Services\{AdminProductReadService,AdminProductWriteService,ProductFormValidationException};
use DomainException;
use Throwable;

final class ProductController extends Controller
{
    public function __construct(private readonly ?AdminProductReadService $products,private readonly Request $request,private readonly ?AdminProductWriteService $writer=null) {}

    public function create(): void {$this->formPage(null);}
    public function edit(): void {$this->formPage($this->request->queryInteger('id'));}

    public function save(): void
    {
        header('Cache-Control: no-store');$input=$this->request->postParameters();
        try {
            if (!$this->writer) throw new DomainException('Administration indisponible.',503);
            $this->writer->authorize();
            if (!$this->request->isPost()) throw new DomainException('Méthode non autorisée.',405);
            if (!CsrfMiddleware::isValid($input['_token']??null)) throw new DomainException('Session expirée. Rechargez la page avant de réessayer.',419);
            $result=$this->writer->save($input);
            Session::set('_admin_product_flash',['id'=>$result['id'],'message'=>$result['message']]);
            Response::redirect('/admin/produit?'.http_build_query(['id'=>$result['id']]));
        } catch (DomainException $error) {
            if (in_array($error->getCode(),[419,422,409],true)) {
                $id=array_key_exists('id_produit',$input)?filter_var($input['id_produit'],FILTER_VALIDATE_INT):null;
                if ($id===false) {$this->formFailure(422,'Produit invalide.');return;}
                $this->formPage($id,$input,$error->getMessage(),$error->getCode(),$error instanceof ProductFormValidationException?$error->errors:[]);return;
            }
            $this->formFailure($error->getCode(),$error->getMessage());
        } catch (Throwable $error) {
            error_log('Enregistrement Admin produit : '.get_class($error));
            $id=array_key_exists('id_produit',$input)?filter_var($input['id_produit'],FILTER_VALIDATE_INT):null;
            $this->formPage($id===false?null:$id,$input,'Enregistrement impossible pour le moment. Réessayez sans modifier votre demande.',503);
        }
    }

    private function formPage(?int $id,array $old=[],?string $error=null,int $status=200,array $fieldErrors=[]): void
    {
        header('Cache-Control: no-store');
        try {
            if (!$this->writer) throw new DomainException('Administration indisponible.',503);
            if ($old===[]&&$this->request->method()!=='GET') throw new DomainException('Méthode non autorisée.',405);
            $data=$this->writer->form($id);
            $flash=Session::get('_admin_product_flash');Session::remove('_admin_product_flash');
            http_response_code($status);
            $this->render('admin/product-form',$data+['old'=>$old,'fieldErrors'=>$fieldErrors,'formError'=>$error,'flash'=>is_array($flash)&&($flash['id']??null)===$id?$flash['message']:null,'csrfToken'=>CsrfMiddleware::token(),'title'=>$id===null?'Ajouter un produit':'Modifier le produit','adminPage'=>'products','pageStyles'=>['/assets/css/admin-products.css','/assets/css/select-control.css'],'pageScripts'=>['/assets/js/select-control.js','/assets/js/admin-product-form.js']],'admin');
        } catch (DomainException $failure) {$this->formFailure($failure->getCode(),$failure->getMessage());}
        catch (Throwable $failure) {error_log('Formulaire Admin produit : '.get_class($failure));$this->formFailure(503,'Administration temporairement indisponible.');}
    }

    private function formFailure(int $status,string $message): void
    {
        if (in_array($status,[401,403,405],true)) {(new AccessController())->failure($status,'/admin/produits');return;}
        http_response_code(in_array($status,[404,422,503],true)?$status:503);
        $this->render('admin/product-form-error',['title'=>'Produits | Tout y est','adminPage'=>'products','formError'=>$message],'admin');
    }

    public function index(): void
    {
        header('Cache-Control: no-store');
        try {
            if ($this->request->method()!=='GET') throw new DomainException('Méthode non autorisée.',405);
            if (!$this->products) throw new DomainException('Administration indisponible.',503);
            $data=$this->products->listing($this->request->queryParameters());
            $this->render('admin/products',$data+['title'=>'Produits | Tout y est','adminPage'=>'products','pageStyles'=>['/assets/css/admin-products.css','/assets/css/select-control.css'],'pageScripts'=>['/assets/js/select-control.js','/assets/js/admin-products.js']],'admin');
        } catch (DomainException $error) {
            if ($error->getCode()===422) {
                http_response_code(422);
                $this->render('admin/product-filter-error',['title'=>'Filtres produits | Tout y est','adminPage'=>'products','filterError'=>$error->getMessage()],'admin');
                return;
            }
            (new AccessController())->failure($error->getCode(),'/admin/produits');
        } catch (Throwable $error) {
            error_log('Lecture Admin produits : '.get_class($error));
            http_response_code(503);echo 'Administration temporairement indisponible.';
        }
    }
}
