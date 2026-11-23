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
    public function __construct(private readonly ?AdminProductReadService $products,private readonly Request $request,private readonly ?AdminProductWriteService $writer=null,private readonly ?\App\Services\AdminProductImageService $images=null) {}

    public function create(): void {$this->formPage(null);}
    public function edit(): void {$this->formPage($this->request->queryInteger('id'));}

    public function managePhoto(): void
    {
        header('Cache-Control: no-store');
        $id=filter_var($_POST['id_produit']??null,FILTER_VALIDATE_INT);
        try {
            if(!$this->images)throw new DomainException('Administration indisponible.',503);
            $this->images->authorize();
            if(!$this->request->isPost())throw new DomainException('Méthode non autorisée.',405);
            if(!CsrfMiddleware::isValid($_POST['_token']??null))throw new DomainException('Session expirée. Rechargez la page avant de réessayer.',419);
            $image=filter_var($_POST['id_image']??null,FILTER_VALIDATE_INT);
            $action=$_POST['action']??null;
            if($id===false||$id<1||$image===false||$image<1)throw new DomainException('Photo introuvable pour ce produit.',404);
            if(!is_string($action))throw new DomainException('Action photo invalide.',422);
            $message=$this->images->change($id,$image,$action);
            Session::set('_admin_product_flash',['id'=>$id,'message'=>$message]);
            Response::redirect('/admin/produit?'.http_build_query(['id'=>$id]).'#product-photos');
        } catch(DomainException $error) {
            if(in_array($error->getCode(),[419,422,409,503],true)&&$id!==false&&$id>0){$this->formPage($id,[],$error->getMessage(),$error->getCode());return;}
            $this->formFailure($error->getCode(),$error->getMessage());
        } catch(Throwable $error) {
            error_log('Gestion photo Admin : '.get_class($error));
            if($id!==false&&$id>0)$this->formPage($id,[],'Modification de la galerie impossible pour le moment. Réessayez.',503);
            else $this->formFailure(503,'Modification de la galerie impossible pour le moment.');
        }
    }

    public function uploadPhoto(): void
    {
        header('Cache-Control: no-store');
        $id=filter_var($_POST['id_produit']??null,FILTER_VALIDATE_INT);
        try {
            if (!$this->images) throw new DomainException('Administration indisponible.',503);
            $this->images->authorize();
            if (!$this->request->isPost()) throw new DomainException('Méthode non autorisée.',405);
            if (!CsrfMiddleware::isValid($_POST['_token']??null)) throw new DomainException('Session expirée. Rechargez la page avant de réessayer.',419);
            if ($id===false||$id<1) throw new DomainException('Produit introuvable.',404);
            $source=\App\Services\HttpImageUpload::source($_FILES['photo']??null);
            $this->images->add($id,$source);
            Session::set('_admin_product_flash',['id'=>$id,'message'=>'Photo ajoutée.']);
            Response::redirect('/admin/produit?'.http_build_query(['id'=>$id]).'#product-photos');
        } catch (DomainException $error) {
            if (in_array($error->getCode(),[419,422,409,503],true)&&$id!==false&&$id>0) {
                $this->formPage($id,[],$error->getMessage(),$error->getCode());return;
            }
            $this->formFailure($error->getCode(),$error->getMessage());
        } catch (Throwable $error) {
            error_log('Ajout photo Admin : '.get_class($error));
            if ($id!==false&&$id>0)$this->formPage($id,[],'Ajout de la photo impossible pour le moment. Réessayez.',503);
            else $this->formFailure(503,'Ajout de la photo impossible pour le moment.');
        }
    }

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
            if ($status===200&&$old===[]&&$this->request->method()!=='GET') throw new DomainException('Méthode non autorisée.',405);
            $data=$this->writer->form($id);
            $data['photos']=$id!==null&&$this->images?$this->images->gallery($id):[];
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
