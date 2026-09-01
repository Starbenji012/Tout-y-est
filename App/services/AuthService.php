<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PDOException;

/** Regroupe les règles métier de connexion et d'inscription. */
final class AuthService
{
    /** Reçoit le modèle utilisateur lorsqu'une base de données est disponible. */
    public function __construct(private readonly ?User $userModel)
    {
    }

    /** Valide les identifiants puis retourne un résultat sans gérer l'affichage. */
    public function login(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->failure(
                'Adresse e-mail ou mot de passe incorrect.',
                'invalid_credentials',
                'Vérifiez votre adresse e-mail ou utilisez « Mot de passe oublié ? » si nécessaire.',
            );
        }

        if ($this->userModel === null) {
            return $this->failure('La connexion au compte est temporairement indisponible.', 'service_unavailable');
        }

        try {
            $user = $this->userModel->findByEmail($email);
        } catch (PDOException) {
            return $this->failure('La connexion au compte est temporairement indisponible.', 'service_unavailable');
        }

        if ($user === null || !password_verify($password, (string) $user['mot_de_passe'])) {
            return $this->failure(
                'Adresse e-mail ou mot de passe incorrect.',
                'invalid_credentials',
                'Vérifiez votre adresse e-mail ou utilisez « Mot de passe oublié ? » si nécessaire.',
            );
        }

        if (!in_array(strtolower((string) $user['statut']), ['actif', 'active'], true)) {
            return $this->failure('Ce compte est actuellement indisponible.', 'account_unavailable');
        }

        return ['success' => true, 'user' => $this->publicUser($user), 'errors' => []];
    }

    /** Valide et crée un compte avec un mot de passe correctement chiffré. */
    public function register(array $input): array
    {
        $data = $this->registrationData($input);
        $errors = $this->registrationErrors($data);

        if ($errors !== []) {
            return ['success' => false, 'user' => null, 'errors' => $errors];
        }

        if ($this->userModel === null) {
            return $this->failure('La création de compte est temporairement indisponible.');
        }

        try {
            if ($this->userModel->emailExists($data['email'])) {
                return $this->failure('Un compte utilise déjà cette adresse e-mail.', 'email_exists');
            }

            $userId = $this->userModel->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'mot_de_passe' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => 'client',
                'statut' => 'actif',
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return $this->failure('Cette adresse e-mail est déjà utilisée.', 'email_exists');
            }

            return $this->failure('La création de compte est temporairement indisponible.');
        }

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $data['prenom'] . ' ' . $data['nom'],
                'email' => $data['email'],
                'phone' => null,
                'role' => 'client',
            ],
            'errors' => [],
        ];
    }

    /** Nettoie et normalise les champs reçus du formulaire d'inscription. */
    private function registrationData(array $input): array
    {
        return [
            'nom' => trim((string) ($input['nom'] ?? '')),
            'prenom' => trim((string) ($input['prenom'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'password' => (string) ($input['password'] ?? ''),
            'password_confirmation' => (string) ($input['password_confirmation'] ?? ''),
        ];
    }

    /** Produit les messages de validation associés à chaque champ incorrect. */
    private function registrationErrors(array $data): array
    {
        $errors = [];

        if (!$this->validName($data['prenom']) || !$this->validName($data['nom'])) {
            $errors[] = 'Le prénom et le nom doivent contenir entre 2 et 100 caractères.';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Saisissez une adresse e-mail valide.';
        }

        if (strlen($data['password']) < 8 || !preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.';
        }

        if (!hash_equals($data['password'], $data['password_confirmation'])) {
            $errors[] = 'La confirmation du mot de passe ne correspond pas.';
        }

        return $errors;
    }

    /** Vérifie qu'un nom contient uniquement des caractères humains attendus. */
    private function validName(string $name): bool
    {
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

        return $length >= 2 && $length <= 100;
    }

    /** Retire les informations sensibles avant de placer l'utilisateur en session. */
    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id_utilisateur'],
            'name' => trim((string) $user['prenom'] . ' ' . (string) $user['nom']),
            'email' => (string) $user['email'],
            'phone' => $user['telephone'] !== null ? (string) $user['telephone'] : null,
            'role' => (string) $user['role'],
        ];
    }

    /** Uniformise les réponses d'échec retournées au contrôleur. */
    private function failure(string $message, string $reason = 'validation_failed', ?string $advice = null): array
    {
        return [
            'success' => false,
            'user' => null,
            'errors' => [$message],
            'reason' => $reason,
            'advice' => $advice,
        ];
    }
}
