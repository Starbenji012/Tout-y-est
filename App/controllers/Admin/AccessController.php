<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Services\{AdminAccessService,AdminNavigation};

/** Parcours HTML uniquement : les API gardent leurs réponses JSON existantes. */
final class AccessController extends Controller
{
    public function guard(AdminAccessService $access,string $uri): bool
    {
        try {$access->requireCurrentAdmin();return true;}
        catch (\DomainException $error) {$this->failure($error->getCode(),$uri);return false;}
        catch (\Throwable) {$this->failure(503,$uri);return false;}
    }

    public function failure(int $status,string $uri=''): void
    {
        header('Cache-Control: no-store');
        if ($status===401) {
            header('Location: /connexion?'.http_build_query(['return'=>AdminNavigation::safeReturn($uri)??'/admin/commandes']),true,303);
            return;
        }
        $status=in_array($status,[403,404,405],true)?$status:503;
        http_response_code($status);
        $this->render('admin/access-error',['title'=>'Administration | Tout y est','accessStatus'=>$status]);
    }
}
