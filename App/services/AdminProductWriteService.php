<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{AdminProductWrite,CatalogVisibility};
use DomainException;
use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/ProductFormValidationException.php';
require_once __DIR__ . '/ProductVariantInput.php';
require_once __DIR__ . '/SkuService.php';
require_once __DIR__ . '/ProductVariantWriter.php';
require_once __DIR__ . '/ProductCreationInput.php';

/** Écritures atomiques du produit ; les mouvements de stock existant sont exclus. */
final class AdminProductWriteService
{
    public function __construct(private readonly PDO $database,private readonly AdminAccessService $access,private readonly AdminProductWrite $products,private readonly MoneyService $money,private readonly ?ProductPhotoPreparation $preparation=null) {}

    public function authorize(): void {$this->access->requireCurrentAdmin();}

    public function form(?int $id=null): array
    {
        $this->authorize();
        $product=$id===null?null:$this->products->find($id);
        if ($id!==null&&$product===null) throw new DomainException('Produit introuvable.',404);
        if ($product!==null) {
            $normalized=strtolower($product['statut']);
            if (in_array($normalized,['actif','active','brouillon','archive'],true))$product['statut']=$normalized==='active'?'actif':$normalized;
        }
        $variants=$id===null?[]:$this->products->variants($id);
        $activePrices=[];$activeStock=0;$activeCount=0;$unknownCurrency=false;
        foreach ($variants as &$variant) {
            $variant['priceLabel']=$variant['devise_reference']==='USD'?$this->money->format($this->money->minor($variant['prix_reference'])):'Devise à préciser';
            $variant['active']=CatalogVisibility::isActive((string)$variant['statut']);
            $variant['statusLabel']=$variant['active']?'Active':(in_array(strtolower((string)$variant['statut']),['inactif','inactive'],true)?'Inactive':'Statut à vérifier');
            if ($variant['active']) {
                $activeCount++;$activeStock+=(int)$variant['stock'];
                if ($variant['devise_reference']==='USD') $activePrices[]=$this->money->minor($variant['prix_reference']);
                else $unknownCurrency=true;
            }
        }
        unset($variant);
        $priceLabel='Aucune variante active';
        if ($activeCount>0) {
            if ($unknownCurrency) $priceLabel='Prix à vérifier : devise à préciser';
            elseif ($activePrices!==[]) {
                $minimum=min($activePrices);$maximum=max($activePrices);
                $priceLabel=$this->money->format($minimum).($minimum===$maximum?'':' – '.$this->money->format($maximum));
            }
        }
        $salesSummary=['count'=>count($variants),'activeCount'=>$activeCount,'stock'=>$activeStock,'priceLabel'=>$priceLabel];
        return ['product'=>$product,'variants'=>$variants,'salesSummary'=>$salesSummary,'categories'=>$this->products->categories(),'canPublish'=>$product!==null&&(CatalogVisibility::isActive($product['statut'])||$this->hasImage((int)$product['id_produit']))];
    }

    public function creationPreview(array $input): array
    {
        $this->authorize();
        return (new ProductCreationInput(new ProductVariantWriter($this->database,$this->access,$this->money),new ProductVariantInput($this->money)))->preview($input);
    }

    public function save(array $input): array
    {
        $this->authorize();
        $edit=array_key_exists('id_produit',$input);
        if (!$edit) ProductCreationInput::guard($input);
        $variable=!$edit&&($input['product_type']??'simple')==='variable';
        $creation=new ProductCreationInput(new ProductVariantWriter($this->database,$this->access,$this->money),new ProductVariantInput($this->money));
        $preview=$variable?$creation->preview($input):null;
        $variants=$variable?$creation->variants($input,$preview):[];
        $allowed=['_token','nom','description','id_categorie','statut'];
        $allowed=array_merge($allowed,$edit?['id_produit']:['sku','sku_mode','prix','devise','stock_initial','sku_request','photo_batch','photo_ids','primary_photo','product_type','creation_options','creation_variants','creation_signature','creation_preview','creation_end']);
        if (array_diff(array_keys($input),$allowed)) throw new DomainException('Ce formulaire contient un champ non autorisé.',422);
        $errors=[];
        $data=$this->general($input,$errors);
        $status=$this->collect(function()use($input,$edit){
        $status=$this->text($input,'statut',$edit?'':'brouillon');
        if (!in_array($status,['brouillon','actif','archive'],true)) throw new ProductFormValidationException('statut','Choisissez un statut valide.');
        if (!$edit&&$status==='archive') throw new ProductFormValidationException('statut','Choisissez Actif ou Brouillon.');
        return $status;
        },$errors);
        $id=$edit?$this->collect(fn()=>$this->integer($input,'id_produit',1,PHP_INT_MAX),$errors):null;
        $manual=($edit||$variable)?'':$this->collect(function()use($input,$edit){
        $raw=$this->text($input,'sku','');
        // Les anciens appels sans mode gardent leur contrat : vide=auto, sinon manuel.
        $mode=$this->text($input,'sku_mode',trim($raw)===''?'auto':'manual');
        if (!in_array($mode,['auto','manual'],true)) throw new ProductFormValidationException('sku_mode','Choisissez la génération automatique ou la saisie manuelle.');
        if ($mode==='auto') return '';
        $manual=(new ProductVariantInput($this->money))->sku($raw);
        if ($manual==='') throw new ProductFormValidationException('sku','Saisissez votre référence ou choisissez la génération automatique.');
        return $manual;
        },$errors);
        $stock=($edit||$variable)?0:$this->collect(fn()=>$this->integer($input,'stock_initial',0,4294967295),$errors);
        $price=($edit||$variable)?null:$this->collect(fn()=>$this->referencePrice($input),$errors);
        $photos=$input['photo_ids']??[];$primary=$input['primary_photo']??'';
        if(!is_array($photos)||!array_is_list($photos)||count($photos)>6||array_filter($photos,static fn($v)=>!is_string($v)||!preg_match('/^[a-f0-9]{32}$/D',$v))||count(array_unique($photos))!==count($photos)){$errors['photos']='Vérifiez les photos préparées (six maximum).';$photos=[];}
        if(!is_string($primary)||($photos!==[]&&!in_array($primary,$photos,true)))$errors['photos']='Choisissez une photo principale.';
        if(!$edit&&$status==='actif'&&$photos===[])$errors['photos']='Ajoutez au moins une photo valide pour publier le produit.';
        // Aucune transaction ni écriture tant que les champs indépendants sont invalides.
        if ($errors!==[]) throw new ProductFormValidationException($errors);
        if ($this->database->inTransaction()) throw new \LogicException('Transaction catalogue déjà active.');
        $skus=new SkuService($this->database);
        $request=$input['sku_request']??null;
        if ($request!==null&&!is_string($request)) throw new DomainException('Clé de création invalide.',422);
        CatalogueWriteLock::acquire($this->database);
        try {
        $payload=$input;unset($payload['_token'],$payload['sku_request']);
        if ($variable) {
            unset($payload['sku_mode'],$payload['sku'],$payload['prix'],$payload['devise'],$payload['stock_initial']);
        } elseif (array_key_exists('sku_mode',$input)) {
            // Même intention avec/sans JS : l'input désactivé peut être absent du POST.
            // Les anciens appels sans mode conservent leur empreinte historique.
            unset($payload['sku_mode']);$payload['sku']=$manual;ksort($payload);
        }
        $operation=$edit?null:$skus->operation('product:create',$request,$payload);
        // UNIQUE protège les collisions concurrentes ; aucune recherche MAX(id)+1.
        for ($attempt=0;$attempt<3;$attempt++) {
            $this->database->beginTransaction();
            try {
                $this->access->requireCurrentAdmin(true);
                if ($operation!==null && ($replay=$skus->replay($operation))!==null) {
                    $this->database->commit();return $replay;
                }
                if ($edit) {
                    $current=$this->products->find($id,true);
                    if ($current===null) throw new DomainException('Produit introuvable.',404);
                    if (!$this->products->categoryExists($data['id_categorie'])) throw new ProductFormValidationException('id_categorie','Choisissez une catégorie existante.');
                    $this->validatePublication($current,$status);
                    $this->products->update($id,$data,$status);
                } else {
                    if (!$this->products->categoryExists($data['id_categorie'])) throw new ProductFormValidationException('id_categorie','Choisissez une catégorie existante.');
                    if ($manual!==''&&$this->products->skuExists($manual)) throw new ProductFormValidationException('sku','Cette référence (SKU) est déjà utilisée. Choisissez-en une autre ou laissez ce champ vide.');
                    $reservation=$variable?null:$skus->reserve($operation,'initial',$manual);
                    $sku=$reservation['sku']??'';
                    $slug=$this->slug($data['nom']);
                    if ($attempt>0)$slug.='-'.bin2hex(random_bytes(6));
                    $id=$this->products->createDraft($data,$slug);
                    $writer=new ProductVariantWriter($this->database,$this->access,$this->money);
                    if ($variable) {
                        $writer->useOperation($operation);
                        $writer->initializeLocked($id,$preview['definitions'],$variants);
                    } else {
                        $variant=$writer->createSimple($id,$sku,$price,$stock);
                        $skus->bind($reservation['cle'],$variant);
                    }
                    if($photos!==[]) {
                        if(!$this->preparation)throw new DomainException('Préparation des photos indisponible.',503);
                        $this->preparation->attach(ProductPhotoPreparation::token($input['photo_batch']??null),$photos,$primary,$id);
                    }
                    // L'activation est la dernière écriture métier, jamais visible avant commit.
                    if($status==='actif')$this->products->update($id,$data,'actif');
                }
                $result=['id'=>$id,'message'=>$edit?'Produit enregistré.':($status==='actif'?'Produit publié.':'Brouillon créé.')];
                if ($variable) $result['message'].=' Produit avec variantes : indisponible aux clients jusqu’à la phase 5.';
                if ($operation!==null) $skus->complete($operation,$result);
                $this->database->commit();
                return $result;
            } catch (Throwable $error) {
                if ($this->database->inTransaction())$this->database->rollBack();
                if (!$edit&&$error instanceof PDOException&&($error->errorInfo[1]??null)===1062) {
                    if ($manual!==''&&$this->products->skuExists($manual)) throw new ProductFormValidationException('sku','Cette référence (SKU) est déjà utilisée. Choisissez-en une autre ou laissez ce champ vide.');
                    continue;
                }
                throw $error;
            }
        }
        throw new DomainException('La création a rencontré un conflit. Réessayez sans changer vos informations.',409);
        } finally { CatalogueWriteLock::release($this->database); }
    }

    private function validatePublication(array $current,string $status): void
    {
        // Les actifs historiques restent actifs ; une nouvelle activation exige une vraie photo.
        if ($status!=='actif'||CatalogVisibility::isActive($current['statut'])) return;
        if ($this->hasImage((int)$current['id_produit'])) return;
        throw new ProductFormValidationException('statut','Ajoutez au moins une photo avant de publier ce produit.');
    }

    private function hasImage(int $id): bool
    {
        foreach ($this->products->imagePaths($id) as $path) if (ProductService::localProductImage($path)!==null) return true;
        return false;
    }

    private function referencePrice(array $input): string
    {
        $currency=$this->text($input,'devise');
        return (new ProductVariantInput($this->money))->price($this->text($input,'prix'),$currency);
    }

    private function collect(callable $validate,array &$errors): mixed
    {
        try {return $validate();}
        catch(ProductFormValidationException $error) {$errors+=$error->errors;return null;}
    }

    private function general(array $input,array &$errors): array
    {
        $name=$this->collect(function()use($input){
        $name=trim($this->text($input,'nom'));
        if (!mb_check_encoding($name,'UTF-8')||$name===''||mb_strlen($name)>180||preg_match('/[\x00-\x1F\x7F]/',$name)) throw new ProductFormValidationException('nom','Indiquez un nom de produit (180 caractères maximum).');
        return $name;
        },$errors);
        $description=$this->collect(function()use($input){
            $description=trim($this->text($input,'description',''));
            if (!mb_check_encoding($description,'UTF-8')||strlen($description)>65535||str_contains($description,"\0")) throw new ProductFormValidationException('description','La description est trop longue ou contient des caractères invalides.');
            return $description;
        },$errors);
        return ['nom'=>$name,'description'=>$description,'id_categorie'=>$this->collect(fn()=>$this->integer($input,'id_categorie',1,PHP_INT_MAX),$errors)];
    }

    private function text(array $input,string $key,?string $default=null): string
    {
        $value=$input[$key]??$default;
        if (!is_string($value)) throw new ProductFormValidationException($key,'Vérifiez la valeur de ce champ.');
        return $value;
    }

    private function integer(array $input,string $key,int $minimum,int $maximum): int
    {
        $value=$this->text($input,$key);
        if (!ctype_digit($value)||strlen($value)>20||filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<$minimum||(int)$value>$maximum) throw new ProductFormValidationException($key,$key==='stock_initial'?'Saisissez un stock entier entre 0 et 4 294 967 295.':($key==='id_categorie'?'Choisissez une catégorie valide.':'Produit invalide.'));
        return (int)$value;
    }

    private function slug(string $name): string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
        $slug=trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower($ascii===false?'':$ascii)),'-');
        return substr($slug===''?'produit':$slug,0,180);
    }
}
