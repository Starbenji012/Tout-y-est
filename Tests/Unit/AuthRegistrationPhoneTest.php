<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/models/User.php';
require_once dirname(__DIR__, 2) . '/App/services/AuthService.php';

use App\Models\User;
use App\Services\AuthService;

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
        telephone VARCHAR(30) NULL UNIQUE,
        mot_de_passe VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL,
        statut VARCHAR(20) NOT NULL,
        date_creation DATETIME NOT NULL,
        email_verified_at DATETIME NULL
    )',
);

$service = new AuthService(new User($database));
$validInput = [
    'nom' => 'Test',
    'prenom' => 'Alice',
    'email' => 'alice.phone@example.test',
    'telephone' => '081 234 56 78',
    'password' => 'Motdepasse1',
    'password_confirmation' => 'Motdepasse1',
];

try {
    $registration = $service->register($validInput);
    $storedUser = $database->query(
        "SELECT telephone, mot_de_passe FROM utilisateur WHERE email = 'alice.phone@example.test'",
    )->fetch();

    if (!($registration['success'] ?? false)
        || ($registration['user']['phone'] ?? null) !== '+243812345678'
        || ($storedUser['telephone'] ?? null) !== '+243812345678'
    ) {
        throw new RuntimeException('Le téléphone valide n a pas été normalisé et stocké correctement.');
    }

    $login = $service->login([
        'email' => $validInput['email'],
        'password' => $validInput['password'],
    ]);

    if (!($login['success'] ?? false) || ($login['user']['phone'] ?? null) !== '+243812345678') {
        throw new RuntimeException('La connexion ne restitue pas le téléphone normalisé.');
    }

    $duplicate = $service->register([
        ...$validInput,
        'email' => 'bob.phone@example.test',
        'telephone' => '243812345678',
    ]);

    if (($duplicate['success'] ?? true)
        || ($duplicate['reason'] ?? '') !== 'phone_exists'
        || ($duplicate['fieldErrors']['telephone'] ?? '') === ''
    ) {
        throw new RuntimeException('Un téléphone déjà utilisé n a pas été refusé clairement.');
    }

    foreach (['+242812345678', '08123', 'numéro invalide'] as $index => $phone) {
        $invalid = $service->register([
            ...$validInput,
            'email' => 'invalid' . $index . '@example.test',
            'telephone' => $phone,
        ]);

        if ($invalid['success'] ?? true) {
            throw new RuntimeException('Un téléphone RDC invalide a été accepté.');
        }

        if (($invalid['fieldErrors']['telephone'] ?? '') === '') {
            throw new RuntimeException('L erreur téléphone serveur n est pas associée au champ concerné.');
        }
    }

    echo "AUTH_PHONE_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
