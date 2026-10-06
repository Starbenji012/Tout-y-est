<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/contracts/PasswordResetSender.php';
require_once dirname(__DIR__, 2) . '/App/models/User.php';
require_once dirname(__DIR__, 2) . '/App/models/PasswordResetToken.php';
require_once dirname(__DIR__, 2) . '/App/services/PasswordResetService.php';
require_once dirname(__DIR__, 2) . '/App/services/AuthService.php';

use App\Contracts\PasswordResetSender;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\AuthService;
use App\Services\PasswordResetService;

$config = require dirname(__DIR__, 2) . '/Config/database.php';
$database = new PDO(
    'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=' . $config['charset'],
    $config['username'],
    $config['password'],
    $config['options'],
);
$database->exec(
    'CREATE TEMPORARY TABLE utilisateur (
        id_utilisateur BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(254) NOT NULL UNIQUE,
        telephone VARCHAR(30) NULL,
        mot_de_passe VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL,
        statut VARCHAR(20) NOT NULL,
        date_creation DATETIME NOT NULL,
        email_verified_at DATETIME NULL
    )',
);
$database->exec(
    'CREATE TEMPORARY TABLE password_reset_token (
        id_password_reset BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        id_utilisateur BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        date_creation DATETIME NOT NULL,
        UNIQUE KEY uq_password_reset_token_user (id_utilisateur)
    )',
);
$oldHash = password_hash('AncienMot1', PASSWORD_DEFAULT);
$statement = $database->prepare(
    'INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe, role, statut, date_creation)
     VALUES (:nom, :prenom, :email, NULL, :password, :role, :statut, NOW())',
);
$statement->execute([
    'nom' => 'Test',
    'prenom' => 'Alice',
    'email' => 'alice@example.test',
    'password' => $oldHash,
    'role' => 'client',
    'statut' => 'actif',
]);

$sender = new class implements PasswordResetSender {
    public array $tokens = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function sendPasswordReset(string $email, string $name, string $token): bool
    {
        $this->tokens[] = $token;

        return true;
    }
};
$now = new DateTimeImmutable('2026-10-03 10:00:00', new DateTimeZone('+01:00'));
$user = new User($database);
$tokenModel = new PasswordResetToken($database);
$service = new PasswordResetService($user, $tokenModel, $sender, $database, $now);

try {
    $existing = $service->requestReset('alice@example.test');
    $unknown = $service->requestReset('missing@example.test');
    $firstToken = (string) ($sender->tokens[0] ?? '');
    $firstState = $database->query(
        'SELECT token_hash, expires_at FROM password_reset_token WHERE id_utilisateur = 1',
    )->fetch();

    if (($existing['status'] ?? '') !== 'requested' || ($unknown['status'] ?? '') !== 'requested') {
        throw new RuntimeException('La réponse publique n est pas générique.');
    }
    if (count($sender->tokens) !== 1 || strlen($firstToken) !== 64) {
        throw new RuntimeException('Le token utilisateur n a pas été généré correctement.');
    }
    if ($firstState['token_hash'] === $firstToken || $firstState['token_hash'] !== hash('sha256', $firstToken)) {
        throw new RuntimeException('Le token brut a été stocké ou son hash est incorrect.');
    }
    if ($firstState['expires_at'] !== '2026-10-03 10:00:00') {
        throw new RuntimeException('La normalisation UTC ou la durée de validité est incorrecte.');
    }
    if (($service->inspect($firstToken)['status'] ?? '') !== 'valid') {
        throw new RuntimeException('Le token valide a été refusé.');
    }
    if (($service->inspect(str_repeat('a', 64))['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('Le token invalide a été accepté.');
    }

    $database->exec("UPDATE password_reset_token SET expires_at = '2026-10-03 08:59:59' WHERE id_utilisateur = 1");
    if (($service->inspect($firstToken)['status'] ?? '') !== 'expired') {
        throw new RuntimeException('Le token expiré a été accepté.');
    }

    $secondRequest = $service->requestReset('alice@example.test');
    $secondToken = (string) ($sender->tokens[1] ?? '');
    if (($secondRequest['status'] ?? '') !== 'requested' || $secondToken === $firstToken) {
        throw new RuntimeException('Le nouveau token n a pas été généré.');
    }
    if (($service->inspect($firstToken)['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('L ancien token reste utilisable.');
    }
    if (($service->resetPassword($secondToken, 'court', 'court')['status'] ?? '') !== 'validation_failed') {
        throw new RuntimeException('Un mot de passe invalide a été accepté.');
    }
    if (($service->resetPassword($secondToken, 'NouveauMot1', 'Different1')['status'] ?? '') !== 'validation_failed') {
        throw new RuntimeException('Une confirmation différente a été acceptée.');
    }
    if (($service->resetPassword($secondToken, 'NouveauMot1', 'NouveauMot1')['status'] ?? '') !== 'reset') {
        throw new RuntimeException('La réinitialisation a échoué.');
    }
    if (($service->inspect($secondToken)['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('Le token consommé reste utilisable.');
    }

    $auth = new AuthService($user);
    if (($auth->login(['email' => 'alice@example.test', 'password' => 'AncienMot1'])['success'] ?? false) !== false) {
        throw new RuntimeException('L ancien mot de passe fonctionne encore.');
    }
    if (($auth->login(['email' => 'alice@example.test', 'password' => 'NouveauMot1'])['success'] ?? false) !== true) {
        throw new RuntimeException('Le nouveau mot de passe ne permet pas la connexion.');
    }

    $failedSender = new class implements PasswordResetSender {
        public function isConfigured(): bool
        {
            return true;
        }

        public function sendPasswordReset(string $email, string $name, string $token): bool
        {
            return false;
        }
    };
    $failedService = new PasswordResetService($user, $tokenModel, $failedSender, $database, $now);
    $failedRequest = $failedService->requestReset('alice@example.test');

    if (($failedRequest['status'] ?? '') !== 'requested'
        || (int) $database->query('SELECT COUNT(*) FROM password_reset_token')->fetchColumn() !== 0
        || isset($failedRequest['email'], $failedRequest['token'], $failedRequest['token_hash'])
    ) {
        throw new RuntimeException('L échec d envoi révèle une donnée ou conserve le token.');
    }

    echo "PASSWORD_RESET_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}