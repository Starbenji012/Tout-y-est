<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/contracts/EmailSender.php';
require_once dirname(__DIR__, 2) . '/App/core/Controller.php';
require_once dirname(__DIR__, 2) . '/App/core/Request.php';
require_once dirname(__DIR__, 2) . '/App/core/Response.php';
require_once dirname(__DIR__, 2) . '/App/core/Session.php';
require_once dirname(__DIR__, 2) . '/App/middleware/CsrfMiddleware.php';
require_once dirname(__DIR__, 2) . '/App/models/User.php';
require_once dirname(__DIR__, 2) . '/App/services/AuthService.php';
require_once dirname(__DIR__, 2) . '/App/services/EmailVerificationService.php';
require_once dirname(__DIR__, 2) . '/App/controllers/EmailVerificationController.php';

use App\Contracts\EmailSender;
use App\Controllers\EmailVerificationController;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailVerificationService;

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
        email_verified_at DATETIME NULL,
        email_verification_token_hash CHAR(64) NULL UNIQUE,
        email_verification_expires_at DATETIME NULL,
        email_verification_sent_at DATETIME NULL
    )',
);
$database->exec(
    "INSERT INTO utilisateur
        (id_utilisateur, nom, prenom, email, mot_de_passe, role, statut, date_creation, email_verified_at)
     VALUES
        (1, 'Test', 'Alice', 'alice@example.test', 'hash', 'client', 'actif', NOW(), NULL),
        (2, 'Test', 'Bob', 'bob@example.test', 'hash', 'client', 'actif', NOW(), NOW())",
);

$sender = new class implements EmailSender {
    public array $tokens = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function sendVerification(string $email, string $name, string $token): bool
    {
        $this->tokens[] = $token;

        return true;
    }
};
$now = new DateTimeImmutable('2026-09-22 10:00:00');
$service = new EmailVerificationService(
    new User($database),
    $sender,
    static fn (): DateTimeImmutable => $now,
);

try {
    $issued = $service->issueForUser(1);
    $firstToken = (string) ($sender->tokens[0] ?? '');
    $firstState = $database->query(
        'SELECT email_verification_token_hash, email_verification_expires_at FROM utilisateur WHERE id_utilisateur = 1',
    )->fetch();

    if (($issued['status'] ?? '') !== 'sent' || strlen($firstToken) !== 64) {
        throw new RuntimeException('La création du token a échoué.');
    }
    if (($service->issueForUser(1)['status'] ?? '') !== 'cooldown') {
        throw new RuntimeException('La limitation du renvoi n est pas appliquée.');
    }
    if ($firstState['email_verification_token_hash'] === $firstToken
        || $firstState['email_verification_token_hash'] !== hash('sha256', $firstToken)
    ) {
        throw new RuntimeException('Le token brut a été stocké ou son hash est incorrect.');
    }
    if ($firstState['email_verification_expires_at'] !== '2026-09-23 10:00:00') {
        throw new RuntimeException('La durée de validité de 24 heures est incorrecte.');
    }
    if (($service->verify(str_repeat('a', 64))['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('Un token invalide a été accepté.');
    }

    $database->exec("UPDATE utilisateur SET email_verification_expires_at = '2026-09-22 09:59:59' WHERE id_utilisateur = 1");
    if (($service->verify($firstToken)['status'] ?? '') !== 'expired') {
        throw new RuntimeException('Un token expiré n a pas été refusé.');
    }

    $database->exec("UPDATE utilisateur SET email_verification_sent_at = '2026-09-22 09:00:00' WHERE id_utilisateur = 1");
    $service->issueForUser(1);
    $replacedToken = (string) ($sender->tokens[1] ?? '');
    $database->exec("UPDATE utilisateur SET email_verification_sent_at = '2026-09-22 09:00:00' WHERE id_utilisateur = 1");
    $service->issueForUser(1);
    $validToken = (string) ($sender->tokens[2] ?? '');

    if ($replacedToken === '' || $validToken === '' || $replacedToken === $validToken) {
        throw new RuntimeException('Le renvoi n a pas remplacé le token.');
    }
    if (($service->verify($replacedToken)['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('Un ancien token reste utilisable après renvoi.');
    }
    if (($service->verify($validToken)['status'] ?? '') !== 'verified') {
        throw new RuntimeException('Le token valide a été refusé.');
    }
    if (($service->verify($validToken)['status'] ?? '') !== 'invalid') {
        throw new RuntimeException('Un token consommé a été réutilisé.');
    }

    $sentCount = count($sender->tokens);
    if (($service->issueForUser(2)['status'] ?? '') !== 'already_verified'
        || count($sender->tokens) !== $sentCount
    ) {
        throw new RuntimeException('Un compte déjà vérifié a reçu un nouveau token.');
    }

    $unavailableSender = new class implements EmailSender {
        public function isConfigured(): bool
        {
            return false;
        }

        public function sendVerification(string $email, string $name, string $token): bool
        {
            return false;
        }
    };
    $unavailableVerification = new EmailVerificationService(new User($database), $unavailableSender);
    $registration = (new AuthService(new User($database), $unavailableVerification))->register([
        'nom' => 'Entreprise',
        'prenom' => 'Carole',
        'email' => 'carole@entreprise.example',
        'telephone' => '+243812345678',
        'password' => 'Motdepasse1',
        'password_confirmation' => 'Motdepasse1',
    ]);

    if (!($registration['success'] ?? false)
        || ($registration['emailVerification']['status'] ?? '') !== 'transport_unavailable'
        || (int) $database->query("SELECT COUNT(*) FROM utilisateur WHERE email = 'carole@entreprise.example'")->fetchColumn() !== 1
    ) {
        throw new RuntimeException('L absence de SMTP a annulé ou bloqué l inscription.');
    }

    Session::start();
    Session::set('user', ['id' => 1]);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_token' => 'invalid'];
    http_response_code(200);
    ob_start();
    (new EmailVerificationController($service, new Request()))->resend();
    ob_end_clean();

    if (http_response_code() !== 419) {
        throw new RuntimeException('Le renvoi sans CSRF valide n a pas été refusé.');
    }

    Session::destroy();

    echo "EMAIL_VERIFICATION_OK\n";
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::destroy();
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
