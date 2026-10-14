<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\DeliveryZone;
use App\Models\User;
use InvalidArgumentException;
use PDOException;

/** Valide les droits et les données sans inventer de zones ni de tarifs. */
final class DeliveryZoneService
{
    public function __construct(private readonly ?User $users, private readonly ?DeliveryZone $zones, private readonly MoneyService $money) {}

    public function access(int $userId): array
    {
        if ($userId < 1) return ['error' => 'Connectez-vous avec un compte administrateur.', 'status' => 401];
        if ($this->users === null || $this->zones === null) return ['error' => 'Administration indisponible.', 'status' => 503];
        $user = $this->users->findAccessState($userId);
        if ($user === null || ($user['role'] ?? '') !== 'admin' || !in_array($user['statut'], ['actif', 'active'], true)) {
            return ['error' => 'Accès réservé aux administrateurs autorisés.', 'status' => 403];
        }
        return ['success' => true];
    }

    public function listing(int $userId): array
    {
        $access = $this->access($userId);
        if (isset($access['error'])) return $access;
        $zones = $this->zones->all();
        foreach ($zones as &$zone) {
            $zone['formattedFees'] = $this->money->format($this->money->minor($zone['frais_reference']), $zone['devise_reference']);
        }
        return ['zones' => $zones];
    }

    public function save(int $userId, array $input): array
    {
        $access = $this->access($userId);
        if (isset($access['error'])) return $access;
        $id = filter_var($input['id_zone_livraison'] ?? '0', FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) return ['error' => 'Zone invalide.', 'status' => 422];
        $existing = $id > 0 ? $this->zones->find($id) : null;
        if ($id > 0 && $existing === null) return ['error' => 'Cette zone n’existe plus.', 'status' => 404];
        // Une devise inconnue ne doit jamais être réinterprétée silencieusement.
        if ($existing !== null && $existing['devise_reference'] !== MoneyService::REFERENCE) return ['error' => 'La devise de cette ancienne zone doit être clarifiée avant modification.', 'status' => 422];
        $name = is_string($input['nom'] ?? null) ? trim($input['nom']) : '';
        $status = is_string($input['statut'] ?? null) ? $input['statut'] : '';
        $fees = is_string($input['frais_reference'] ?? null) ? trim($input['frais_reference']) : '';
        $errors = [];
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $length > 120 || preg_match('/[\x00-\x1f\x7f]/', $name)) $errors['nom'] = 'Indiquez un nom de 1 à 120 caractères.';
        if (!in_array($status, ['actif', 'inactif'], true)) $errors['statut'] = 'Choisissez actif ou inactif.';
        try { $fees = $this->money->decimal($this->money->minor(str_replace(',', '.', $fees))); }
        catch (InvalidArgumentException) { $errors['frais_reference'] = 'Indiquez un montant USD positif ou nul, avec au maximum deux décimales.'; }
        if ($errors !== []) return ['error' => 'Vérifiez les informations de la zone.', 'errors' => $errors, 'status' => 422];
        try { $id = $this->zones->save($id ?: null, $name, $fees, $status); }
        catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) return ['error' => 'Ce nom de zone est déjà utilisé.', 'errors' => ['nom' => 'Choisissez un nom distinct.'], 'status' => 422];
            throw $error;
        }
        return ['success' => true, 'id' => $id, 'message' => 'Zone enregistrée.'];
    }
}
