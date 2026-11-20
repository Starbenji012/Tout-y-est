<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\AdminProductRead;
use DomainException;

/** Présentation uniquement : aucun calcul de vente ou mouvement de stock. */
final class AdminProductReadService
{
    public function __construct(private readonly AdminAccessService $access,private readonly AdminProductRead $products,private readonly MoneyService $money) {}

    public function listing(array $input): array
    {
        $this->access->requireCurrentAdmin();
        foreach (['q','statut','categorie','page'] as $key) if (isset($input[$key])&&!is_string($input[$key])) throw new DomainException('Filtre invalide.',422);
        $status=$input['statut']??'';
        if (!in_array($status,['','actif','brouillon','archive'],true)) throw new DomainException('Statut invalide.',422);
        $category=$input['categorie']??''; $page=$input['page']??'1';
        if (($category!==''&&(!ctype_digit($category)||(int)$category<1||filter_var($category,FILTER_VALIDATE_INT)===false))||!ctype_digit($page)||(int)$page<1||filter_var($page,FILTER_VALIDATE_INT)===false) throw new DomainException('Pagination ou catégorie invalide.',422);
        $search=trim($input['q']??'');
        if (mb_strlen($search)>100) throw new DomainException('Recherche limitée à 100 caractères.',422);
        $filters=['q'=>$search,'statut'=>$status,'categorie'=>$category===''?null:(int)$category];
        $data=$this->products->page($filters,(int)$page,20);
        $ids=array_column($data['rows'],'id_produit');$summary=[];$images=[];
        foreach ($this->products->variants($ids) as $group) {
            $id=(int)$group['id_produit'];
            $summary[$id]??=['count'=>0,'stock'=>0,'inactiveStock'=>0,'prices'=>[]];
            $summary[$id]['count']+=(int)$group['quantity'];
            if (!(bool)$group['is_active']) {
                $summary[$id]['inactiveStock']+=(int)$group['stock'];
                continue;
            }
            $summary[$id]['stock']+=(int)$group['stock'];
            $currency=$group['devise_reference'];
            if (!in_array($currency,['USD','CDF'],true)) {$summary[$id]['prices'][]='Devise à préciser';continue;}
            // CDF utilise des francs entiers, USD des cents ; aucune conversion implicite.
            $format=function(string $amount)use($currency):string {
                $minor=$this->money->minor($amount);
                if ($currency==='CDF'&&$minor%100!==0) return 'Montant CDF à vérifier';
                return $this->money->format($currency==='CDF'?intdiv($minor,100):$minor,$currency);
            };
            $min=$format($group['minimum']);$max=$format($group['maximum']);
            $summary[$id]['prices'][]=$min=== $max?$min:$min.' – '.$max;
        }
        foreach ($this->products->images($ids) as $image) {
            $id=(int)$image['id_produit'];
            if (!isset($images[$id])) {$path=ProductService::localProductImage($image['chemin']);if ($path!==null)$images[$id]=$path;}
        }
        $labels=['actif'=>'Actif','active'=>'Actif','brouillon'=>'Brouillon','archive'=>'Archivé'];
        $items=[];
        foreach ($data['rows'] as $row) {
            $id=(int)$row['id_produit'];$values=$summary[$id]??['count'=>0,'stock'=>0,'inactiveStock'=>0,'prices'=>[]];
            $items[]=$row+['statusLabel'=>$labels[strtolower($row['statut'])]??'Statut à vérifier','variantCount'=>$values['count'],'stock'=>$values['stock'],'inactiveStock'=>$values['inactiveStock'],'price'=>$values['prices']===[]?'Aucune variante active':implode(' · ',$values['prices']),'image'=>$images[$id]??null];
        }
        $urlFilters=array_filter($filters,static fn($value)=>$value!==''&&$value!==null);
        return ['products'=>$items,'total'=>$data['total'],'page'=>$data['page'],'pages'=>$data['pages'],'filters'=>$filters,'categories'=>$this->products->categories(),'paginationUrl'=>'/admin/produits?'.http_build_query($urlFilters)];
    }
}
