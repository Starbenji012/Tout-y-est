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
    public function __construct(private readonly ?AdminProductReadService $products,private readonly Request $request,private readonly ?AdminProductWriteService $writer=null,private readonly ?\App\Services\AdminProductImageService $images=null,private readonly ?\App\Services\AdminProductVariantService $versions=null,private readonly ?\App\Services\ProductPhotoPreparation $preparation=null) {}

    public function prepareCreationPhoto(): void
    {
        try {
            if(!$this->writer||!$this->preparation)throw new DomainException('Préparation indisponible.',503);
            $this->writer->authorize();
            if(!$this->request->isPost())throw new DomainException('Méthode non autorisée.',405);
            \App\Services\ProductPhotoPreparation::guardPostSize();
            if(!CsrfMiddleware::isValid($_POST['_token']??null))throw new DomainException('Session expirée. Rechargez le formulaire.',419);
            $batch=\App\Services\ProductPhotoPreparation::token($_POST['batch']??null);
            $ticket=\App\Services\ProductPhotoPreparation::token($_POST['ticket']??null);
            if(($_POST['action']??'')==='remove'){$this->preparation->remove($batch,$ticket);Response::json(['removed'=>true]);return;}
            $this->preparation->cleanup(5);
            $source=\App\Services\HttpImageUpload::source($_FILES['photo']??null);
            $ticket=$this->preparation->prepare($batch,$ticket,$source);
            Response::json(['ticket'=>$ticket,'preview'=>'/admin/produits/photos/apercu?'.http_build_query(['batch'=>$batch,'ticket'=>$ticket])]);
        } catch(DomainException $error){Response::json(['error'=>$error->getMessage()],in_array($error->getCode(),[401,403,404,405,409,413,419,422,429,503],true)?$error->getCode():503);}
        catch(Throwable $error){error_log('Préparation photo : '.get_class($error));Response::json(['error'=>'Préparation interrompue. Réessayez.'],503);}
    }

    public function previewCreationPhoto(): void
    {
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
        try {
            if(!$this->writer||!$this->preparation)throw new DomainException('Indisponible.',503);
            $this->writer->authorize();
            if($this->request->method()!=='GET')throw new DomainException('Méthode non autorisée.',405);
            $file=$this->preparation->preview(\App\Services\ProductPhotoPreparation::token($_GET['batch']??null),\App\Services\ProductPhotoPreparation::token($_GET['ticket']??null));
            header('Content-Type: image/webp');header('Content-Length: '.filesize($file));readfile($file);
        } catch(DomainException $error){http_response_code(in_array($error->getCode(),[401,403,404,405,409,422],true)?$error->getCode():503);}
        catch(Throwable $error){http_response_code(503);}
    }

    public function manageVersions(): void
    {
        header('Cache-Control: no-store');$input=$this->request->postParameters();$id=filter_var($input['id_produit']??null,FILTER_VALIDATE_INT);
        try {
            if (!$this->versions) throw new DomainException('Gestion des versions indisponible.',503);
            $this->versions->authorize();
            if (!$this->request->isPost()) throw new DomainException('Méthode non autorisée.',405);
            if (!CsrfMiddleware::isValid($input['_token']??null)) throw new DomainException('Session expirée. Rechargez la page.',419);
            if ($id===false||$id<1) throw new DomainException('Produit introuvable.',404);
            if (!$this->versions->form($id)['available']) throw new DomainException('Cette fonction n’est pas encore disponible.',503);
            if (array_diff(array_keys($input),['_token','id_produit','action','mode','options','selected','reuse','option','value','label','option_index','value_index','sku_request'])) throw new DomainException('Ce formulaire contient un champ non autorisé.',422);
            $skuRequest=$input['sku_request']??null;
            if ($skuRequest!==null&&!is_string($skuRequest)) throw new DomainException('Clé de création invalide.',422);
            $action=$input['action']??'';
            if (is_string($action)&&preg_match('/^(remove-option|add-value-draft|remove-value-draft):([0-9]+)(?::([0-9]+))?$/D',$action,$parts)) {
                $action=$parts[1];$input['option_index']=$parts[2];if(isset($parts[3]))$input['value_index']=$parts[3];
            }
            $definitions=$input['options']??[['nom'=>'','valeurs'=>['']]];
            if (!is_array($definitions)||!array_is_list($definitions)||count($definitions)>3) throw new ProductFormValidationException('options','Trois options maximum.');
            foreach ($definitions as $definition) if (!is_array($definition)||!is_string($definition['nom']??null)||!is_array($definition['valeurs']??null)||!array_is_list($definition['valeurs'])||count($definition['valeurs'])>100||array_filter($definition['valeurs'],static fn($v)=>!is_string($v))) throw new ProductFormValidationException('options','Vérifiez les noms et les valeurs.');
            if ($action==='choose-mode') {$this->formPage($id,[],null,200,[],$input);return;}
            if (in_array($action,['add-option','remove-option','add-value-draft','remove-value-draft'],true)) {
                $option=filter_var($input['option_index']??-1,FILTER_VALIDATE_INT);
                if ($action==='add-option') {if(count($definitions)>=3)throw new ProductFormValidationException('options','Trois options maximum.');$definitions[]=['nom'=>'','valeurs'=>['']];}
                else {
                    if ($option===false||!isset($definitions[$option])) throw new DomainException('Option invalide.',422);
                    if ($action==='remove-option') array_splice($definitions,$option,1);
                    if ($action==='add-value-draft') {if(count($definitions[$option]['valeurs'])>=100)throw new DomainException('Cent valeurs maximum.',422);$definitions[$option]['valeurs'][]='';}
                    if ($action==='remove-value-draft') {$value=filter_var($input['value_index']??-1,FILTER_VALIDATE_INT);if($value===false||!isset($definitions[$option]['valeurs'][$value]))throw new DomainException('Valeur invalide.',422);array_splice($definitions[$option]['valeurs'],$value,1);}
                }
                $input['options']=$definitions;$input['mode']='yes';$this->formPage($id,[],null,200,[],$input);return;
            }
            if ($action==='preview') {
                $input['preview']=$this->versions->candidates($definitions);
                $this->formPage($id,[],null,200,[],$input);return;
            }
            if ($action==='configure') {
                if (($input['mode']??'')!=='yes') throw new DomainException('Choisissez plusieurs versions pour définir des options.',422);
                if (!is_array($input['selected']??null)||!is_string($input['reuse']??null)) throw new DomainException('Choisissez les combinaisons et la version à conserver.',422);
                $this->versions->configureVersions($id,$definitions,$input['selected'],$input['reuse'],$skuRequest);
            } elseif ($action==='extend') {
                if (!is_array($input['selected']??null)) throw new DomainException('Choisissez les nouvelles combinaisons.',422);
                $this->versions->extendVersions($id,$input['selected'],$skuRequest);
            } elseif (in_array($action,['rename-option','rename-value','add-value','remove-value','delete-option'],true)) {
                $option=filter_var($input['option']??null,FILTER_VALIDATE_INT);$value=filter_var($input['value']??null,FILTER_VALIDATE_INT);
                if($option===false||$option<1)throw new DomainException('Option invalide.',422);
                if(in_array($action,['rename-option','rename-value','add-value'],true)&&!is_string($input['label']??null))throw new DomainException('Indiquez un nom ou une valeur.',422);
                if(in_array($action,['rename-value','remove-value'],true)&&($value===false||$value<1))throw new DomainException('Valeur invalide.',422);
                match($action) {
                    'rename-option'=>$this->versions->renameOption($id,$option,$input['label']),
                    'rename-value'=>$this->versions->renameValue($id,$option,$value,$input['label']),
                    'add-value'=>$this->versions->addValue($id,$option,$input['label'],0,true),
                    'remove-value'=>$this->versions->removeValue($id,$option,$value),
                    'delete-option'=>$this->versions->removeOption($id,$option),
                };
            } else throw new DomainException('Action de version invalide.',422);
            Session::set('_admin_product_flash',['id'=>$id,'message'=>'Versions du produit enregistrées. Les nouvelles versions restent inactives et sans stock.']);
            Response::redirect('/admin/produit?'.http_build_query(['id'=>$id]).'#product-options');
        } catch(DomainException $error) {
            if(in_array($error->getCode(),[419,422,409,503],true)&&$id!==false&&$id>0) {
                if (($input['action']??'')==='configure'&&is_array($input['options']??null)&&in_array($error->getCode(),[422,409],true)) {
                    try {$input['preview']=$this->versions->candidates($input['options']);}catch(DomainException){}
                }
                $this->formPage($id,[],$error->getMessage(),$error->getCode(),$error instanceof ProductFormValidationException?$error->errors:[],$input);return;
            }
            $this->formFailure($error->getCode(),$error->getMessage());
        } catch(Throwable $error) {
            error_log('Options Admin produit : '.get_class($error));
            if($id!==false&&$id>0)$this->formPage($id,[],'Modification des versions impossible pour le moment.',503,[],$input);
            else $this->formFailure(503,'Modification des versions impossible pour le moment.');
        }
    }

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
            \App\Services\ProductPhotoPreparation::guardPostSize();
            if (!CsrfMiddleware::isValid($input['_token']??null)) throw new DomainException('Session expirée. Rechargez la page avant de réessayer.',419);
            if(!isset($input['id_produit'])) \App\Services\ProductCreationInput::guard($input);
            if(!isset($input['id_produit'])&&isset($input['creation_action'])) {
                $action=$input['creation_action'];unset($input['creation_action']);
                if(!in_array($action,['mode','preview'],true))throw new ProductFormValidationException('creation','Action invalide.');
                if(($_FILES['creation_photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)throw new ProductFormValidationException('photos','Préparez la photo sélectionnée avant d’afficher les combinaisons.');
                if($action==='preview') {
                    $preview=$this->writer->creationPreview($input);
                    if(($input['creation_signature']??null)!==$preview['signature'])unset($input['creation_variants']);
                    $input['creation_preview']='1';
                    $input['creation_signature']=$preview['signature'];
                }
                $this->formPage(null,$input,null,200);return;
            }
            if(!isset($input['id_produit'])&&isset($input['photo_action'])) {
                if(!$this->preparation)throw new DomainException('Préparation indisponible.',503);
                $batch=\App\Services\ProductPhotoPreparation::token($input['photo_batch']??null);
                $action=$input['photo_action'];unset($input['photo_action']);
                $ids=$input['photo_ids']??[];
                if(!is_array($ids)||!array_is_list($ids)||count($ids)>6)throw new DomainException('Photos invalides.',422);
                foreach($ids as $photoId)\App\Services\ProductPhotoPreparation::token($photoId);
                if($action==='prepare') {
                    $this->preparation->cleanup(5);
                    $id=$this->preparation->prepare($batch,bin2hex(random_bytes(16)),\App\Services\HttpImageUpload::source($_FILES['creation_photo']??null));
                    if(!in_array($id,$ids,true))$ids[]=$id;
                    $input['primary_photo']=$input['primary_photo']??$ids[0];
                } elseif(is_string($action)&&preg_match('/^(remove|up|down|primary):([a-f0-9]{32})$/D',$action,$parts)){
                    $position=array_search($parts[2],$ids,true);if($position===false)throw new DomainException('Photo invalide.',422);
                    if($parts[1]==='remove'){$this->preparation->remove($batch,$parts[2]);array_splice($ids,$position,1);if(($input['primary_photo']??'')===$parts[2])$input['primary_photo']=$ids[0]??'';}
                    elseif($parts[1]==='primary')$input['primary_photo']=$parts[2];
                    else {$other=$position+($parts[1]==='up'?-1:1);if(isset($ids[$other]))[$ids[$position],$ids[$other]]=[$ids[$other],$ids[$position]];}
                } else throw new DomainException('Action photo invalide.',422);
                $input['photo_ids']=$ids;$this->formPage(null,$input,null,200);return;
            }
            // Le fichier n'est jamais ignoré lors du clic final sans JavaScript.
            if(!isset($input['id_produit'])&&($_FILES['creation_photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)throw new ProductFormValidationException('photos','Préparez la photo sélectionnée avant d’enregistrer le produit.');
            $result=$this->writer->save($input);
            Session::set('_admin_product_flash',['id'=>$result['id'],'message'=>$result['message']]);
            Response::redirect('/admin/produit?'.http_build_query(['id'=>$result['id']]));
        } catch (DomainException $error) {
            unset($input['photo_action'],$input['creation_action']);
            if (in_array($error->getCode(),[413,419,422,429,409,503],true)) {
                $id=array_key_exists('id_produit',$input)?filter_var($input['id_produit'],FILTER_VALIDATE_INT):null;
                if ($id===false) {$this->formFailure(422,'Produit invalide.');return;}
                // Après un refus définitif, la correction est une nouvelle intention.
                // Les tickets photo sont conservés ; une erreur technique garde la clé de replay.
                if ($id===null && $error->getCode()===422) unset($input['sku_request']);
                $this->formPage($id,$input,$error->getMessage(),$error->getCode(),$error instanceof ProductFormValidationException?$error->errors:[]);return;
            }
            $this->formFailure($error->getCode(),$error->getMessage());
        } catch (Throwable $error) {
            error_log('Enregistrement Admin produit : '.get_class($error));
            $id=array_key_exists('id_produit',$input)?filter_var($input['id_produit'],FILTER_VALIDATE_INT):null;
            $this->formPage($id===false?null:$id,$input,'Enregistrement impossible pour le moment. Réessayez sans modifier votre demande.',503);
        }
    }

    private function formPage(?int $id,array $old=[],?string $error=null,int $status=200,array $fieldErrors=[],array $versionsDraft=[]): void
    {
        header('Cache-Control: no-store');
        try {
            if (!$this->writer) throw new DomainException('Administration indisponible.',503);
            if ($status===200&&$old===[]&&$versionsDraft===[]&&$this->request->method()!=='GET') throw new DomainException('Méthode non autorisée.',405);
            $data=$this->writer->form($id);
            if($id===null){
                if(!is_string($old['photo_batch']??null)||!preg_match('/^[a-f0-9]{32}$/D',$old['photo_batch']))$old['photo_batch']=bin2hex(random_bytes(16));
                try{$data['preparedPhotos']=$this->preparation?$this->preparation->listing($old['photo_batch']):[];}
                catch(DomainException $photoError){
                    if($photoError->getCode()!==409)throw $photoError;
                    // Un commit peut avoir abouti malgré la perte de sa réponse : garder
                    // exactement les tickets et la clé pour permettre le replay du résultat.
                    $data['preparedPhotos']=is_array($old['photo_ids']??null)?array_values(array_filter($old['photo_ids'],static fn($v)=>is_string($v)&&preg_match('/^[a-f0-9]{32}$/D',$v))):[];
                    $error=$error??$photoError->getMessage();
                }
            }
            $data['creationPreview']=null;
            if($id===null&&($old['product_type']??'simple')==='variable'&&(($old['creation_preview']??null)==='1'||isset($old['creation_variants']))) {
                try {
                    $data['creationPreview']=$this->writer->creationPreview($old);
                    // A stale selection stays stale until the explicit preview action.
                    if(is_string($old['creation_signature']??null))$data['creationPreview']['signature']=$old['creation_signature'];
                } catch(DomainException $previewError) {
                    $error=$error??$previewError->getMessage();$fieldErrors['creation']=$previewError->getMessage();
                    // Preserve the previous commercial inputs even while an option is invalid.
                    // These display-only tokens are never accepted without server recalculation.
                    $candidates=[];
                    foreach(array_slice(is_array($old['creation_variants']??null)?$old['creation_variants']:[],0,100,true) as $token=>$row) {
                        if(is_string($token)&&preg_match('/^[1-3]:[1-9][0-9]{0,2}(\|[1-3]:[1-9][0-9]{0,2}){0,2}$/D',$token))$candidates[$token]=['label'=>'Variante précédente '.(count($candidates)+1).' — vérifiez les options'];
                    }
                    if($candidates!==[])$data['creationPreview']=['signature'=>is_string($old['creation_signature']??null)?$old['creation_signature']:'','candidates'=>$candidates];
                }
            }
            $data['versions']=$id!==null&&$this->versions?$this->versions->form($id):null;
            $data['versionsDraft']=$versionsDraft;
            $data['photos']=$id!==null&&$this->images?$this->images->gallery($id):[];
            $flash=Session::get('_admin_product_flash');Session::remove('_admin_product_flash');
            http_response_code($status);
            $this->render('admin/product-form',$data+['old'=>$old,'fieldErrors'=>$fieldErrors,'formError'=>$error,'flash'=>is_array($flash)&&($flash['id']??null)===$id?$flash['message']:null,'csrfToken'=>CsrfMiddleware::token(),'title'=>$id===null?'Ajouter un produit':'Modifier le produit','adminPage'=>'products','pageStyles'=>['/assets/css/admin-products.css','/assets/css/select-control.css','/assets/css/ui-components.css'],'pageScripts'=>['/assets/js/select-control.js','/assets/js/admin-product-form.js','/assets/js/product-photo-preparation.js','/assets/js/admin-product-options.js','/assets/js/admin-product-creation.js']],'admin');
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
