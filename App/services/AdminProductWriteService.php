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

/** Écritures atomiques du produit ; les mouvements de stock existant sont exclus. */
final class AdminProductWriteService
{
    public function __construct(private readonly PDO $database,private readonly AdminAccessService $access,private readonly AdminProductWrite $products,private readonly MoneyService $money) {}

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
        foreach ($variants as &$variant) {
            $variant['priceLabel']=$variant['devise_reference']==='USD'?$this->money->format($this->money->minor($variant['prix_reference'])):'Devise à préciser';
        }
        unset($variant);
        return ['product'=>$product,'variants'=>$variants,'categories'=>$this->products->categories(),'canPublish'=>$product!==null&&(CatalogVisibility::isActive($product['statut'])||$this->hasImage((int)$product['id_produit']))];
    }

    public function save(array $input): array
    {
        $this->authorize();
        $edit=array_key_exists('id_produit',$input);
        $allowed=['_token','nom','description','id_categorie','statut'];
        $allowed=array_merge($allowed,$edit?['id_produit']:['sku','prix','devise','stock_initial']);
        if (array_diff(array_keys($input),$allowed)) throw new DomainException('Ce formulaire contient un champ non autorisé.',422);
        $errors=[];
        $data=$this->general($input,$errors);
        $status=$this->collect(function()use($input,$edit){
        $status=$this->text($input,'statut',$edit?'':'brouillon');
        if (!in_array($status,['brouillon','actif','archive'],true)) throw new ProductFormValidationException('statut','Choisissez un statut valide.');
        if (!$edit&&$status!=='brouillon') throw new ProductFormValidationException('statut','Un nouveau produit doit être créé en brouillon. Ajoutez au moins une photo avant de le publier.');
        return $status;
        },$errors);
        $id=$edit?$this->collect(fn()=>$this->integer($input,'id_produit',1,PHP_INT_MAX),$errors):null;
        $manual=$edit?'':$this->collect(function()use($input,$edit){
        $manual=(new ProductVariantInput($this->money))->sku($this->text($input,'sku',''));
        return $manual;
        },$errors);
        $stock=$edit?0:$this->collect(fn()=>$this->integer($input,'stock_initial',0,4294967295),$errors);
        $price=$edit?null:$this->collect(fn()=>$this->referencePrice($input),$errors);
        // Aucune transaction ni écriture tant que les champs indépendants sont invalides.
        if ($errors!==[]) throw new ProductFormValidationException($errors);
        // UNIQUE protège les collisions concurrentes ; aucune recherche MAX(id)+1.
        for ($attempt=0;$attempt<3;$attempt++) {
            $this->database->beginTransaction();
            try {
                $this->access->requireCurrentAdmin(true);
                if ($edit) {
                    $current=$this->products->find($id,true);
                    if ($current===null) throw new DomainException('Produit introuvable.',404);
                    if (!$this->products->categoryExists($data['id_categorie'])) throw new ProductFormValidationException('id_categorie','Choisissez une catégorie existante.');
                    $this->validatePublication($current,$status);
                    $this->products->update($id,$data,$status);
                } else {
                    if (!$this->products->categoryExists($data['id_categorie'])) throw new ProductFormValidationException('id_categorie','Choisissez une catégorie existante.');
                    if ($manual!==''&&$this->products->skuExists($manual)) throw new ProductFormValidationException('sku','Cette référence (SKU) est déjà utilisée. Choisissez-en une autre ou laissez ce champ vide.');
                    $sku=$manual!==''?$manual:(new ProductVariantInput($this->money))->generatedSku();
                    $slug=$this->slug($data['nom']);
                    if ($attempt>0)$slug.='-'.bin2hex(random_bytes(6));
                    $id=$this->products->create($data,$slug,$sku,$price,$stock);
                }
                $this->database->commit();
                return ['id'=>$id,'message'=>$edit?'Produit enregistré.':'Produit créé en brouillon. Ajoutez au moins une photo avant de publier ce produit.'];
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
