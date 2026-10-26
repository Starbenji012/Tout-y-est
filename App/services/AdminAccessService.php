<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Session;
use App\Models\User;
use DomainException;

/** Le rôle en session ne fait jamais autorité ; les droits sont relus en BDD. */
final class AdminAccessService
{
    public function __construct(private readonly ?User $users) {}

    /** Identifiant fourni par le contrôleur serveur, jamais par les champs du formulaire. */
    public function accessForUser(int $id, bool $lock = false): array
    {
        if ($id < 1) return ['error'=>'Connectez-vous avec un compte administrateur.', 'status'=>401];
        if (!$this->users) return ['error'=>'Administration indisponible.', 'status'=>503];
        $user = $this->users->findAccessState($id, $lock);
        if (!$user || $user['role'] !== 'admin' || !in_array($user['statut'], ['actif','active'], true)) {
            return ['error'=>'Accès réservé aux administrateurs autorisés.', 'status'=>403];
        }
        return ['success'=>true];
    }

    public function requireCurrentAdmin(bool $lock = false): int
    {
        $user = Session::get('user');
        $id = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $result = $this->accessForUser($id, $lock);
        if (isset($result['error'])) throw new DomainException($result['error'], $result['status']);
        return $id;
    }
}
