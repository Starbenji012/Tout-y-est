<?php
declare(strict_types=1);
namespace App\Services;

/** Une liste fermée de destinations, jamais une URL fournie librement au navigateur. */
final class AdminNavigation
{
    public static function safeReturn(string $value): ?string
    {
        if (in_array($value,['/admin','/admin/commandes','/admin/zones-livraison'],true)) return $value;
        if (!preg_match('~^/admin/commande\?reference=([A-Za-z0-9%_-]+)$~D',$value,$match)) return null;
        $reference=rawurldecode($match[1]);
        return preg_match('/^TYE-[A-Za-z0-9-]{1,90}$/D',$reference)
            ? '/admin/commande?'.http_build_query(['reference'=>$reference]) : null;
    }
}
