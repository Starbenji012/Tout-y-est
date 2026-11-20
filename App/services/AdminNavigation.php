<?php
declare(strict_types=1);
namespace App\Services;

/** Une liste fermée de destinations, jamais une URL fournie librement au navigateur. */
final class AdminNavigation
{
    public static function safeReturn(string $value): ?string
    {
        if (in_array($value,['/admin','/admin/commandes','/admin/produits','/admin/produit/ajouter','/admin/zones-livraison','/admin/livreurs'],true)) return $value;
        if (preg_match('/^\/admin\/produit\?id=([1-9][0-9]*)$/D',$value,$product)&&filter_var($product[1],FILTER_VALIDATE_INT)!==false) return '/admin/produit?id='.$product[1];
        if (!preg_match('~^/admin/commande\?reference=([A-Za-z0-9%_-]+)$~D',$value,$match)) return null;
        $reference=rawurldecode($match[1]);
        return preg_match('/^TYE-[A-Za-z0-9-]{1,90}$/D',$reference)
            ? '/admin/commande?'.http_build_query(['reference'=>$reference]) : null;
    }
}
