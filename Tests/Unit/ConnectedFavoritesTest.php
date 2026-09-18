<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/core/Controller.php';
require_once dirname(__DIR__, 2) . '/App/core/Request.php';
require_once dirname(__DIR__, 2) . '/App/core/Response.php';
require_once dirname(__DIR__, 2) . '/App/core/Session.php';
require_once dirname(__DIR__, 2) . '/App/middleware/CsrfMiddleware.php';
require_once dirname(__DIR__, 2) . '/App/models/Favorite.php';
require_once dirname(__DIR__, 2) . '/App/models/Product.php';
require_once dirname(__DIR__, 2) . '/App/services/ProductService.php';
require_once dirname(__DIR__, 2) . '/App/services/FavoriteService.php';
require_once dirname(__DIR__, 2) . '/App/controllers/FavoriteController.php';

use App\Controllers\FavoriteController;
use App\Core\Request;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Models\Favorite;
use App\Models\Product;
use App\Services\FavoriteService;
use App\Services\ProductService;

$config = require dirname(__DIR__, 2) . '/Config/database.php';
$database = new PDO(
    'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=' . $config['charset'],
    $config['username'],
    $config['password'],
    $config['options'],
);

try {
    $database->exec('CREATE TEMPORARY TABLE utilisateur (id_utilisateur BIGINT UNSIGNED PRIMARY KEY)');
    $database->exec('CREATE TEMPORARY TABLE categorie (id_categorie BIGINT UNSIGNED PRIMARY KEY, nom VARCHAR(100), slug VARCHAR(120), statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE produit (id_produit BIGINT UNSIGNED PRIMARY KEY, id_categorie BIGINT UNSIGNED, nom VARCHAR(180), slug VARCHAR(220), description TEXT, statut VARCHAR(20), date_creation DATETIME)');
    $database->exec('CREATE TEMPORARY TABLE variante_produit (id_variante BIGINT UNSIGNED PRIMARY KEY, id_produit BIGINT UNSIGNED, sku VARCHAR(100), prix_reference DECIMAL(12, 2), stock INT UNSIGNED, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE promotion (id_promotion BIGINT UNSIGNED PRIMARY KEY, pourcentage DECIMAL(5, 2), date_debut DATETIME, date_fin DATETIME, statut VARCHAR(20))');
    $database->exec('CREATE TEMPORARY TABLE beneficier (id_produit BIGINT UNSIGNED, id_promotion BIGINT UNSIGNED)');
    $database->exec('CREATE TEMPORARY TABLE favori (id_favori BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, id_utilisateur BIGINT UNSIGNED NOT NULL, id_produit BIGINT UNSIGNED NOT NULL, date_ajout DATETIME NOT NULL, UNIQUE KEY uq_favori_utilisateur_produit (id_utilisateur, id_produit))');

    $database->exec('INSERT INTO utilisateur VALUES (1), (2)');
    $database->exec("INSERT INTO categorie VALUES (1, 'Test', 'test', 'actif')");
    $database->exec("INSERT INTO produit VALUES (1, 1, 'Produit A', 'produit-a', 'Test A', 'actif', NOW()), (2, 1, 'Produit B', 'produit-b', 'Test B', 'actif', NOW()), (3, 1, 'Produit inactif', 'produit-inactif', 'Test', 'inactif', NOW())");
    $database->exec("INSERT INTO variante_produit VALUES (11, 1, 'TEST-A', 100, 20, 'actif'), (12, 2, 'TEST-B', 200, 20, 'actif'), (13, 3, 'TEST-C', 300, 20, 'actif')");

    $productService = new ProductService(new Product($database));
    $favoriteService = new FavoriteService($productService, new Favorite($database));

    if (!$favoriteService->add(1, 1) || !$favoriteService->add(1, 1)) {
        throw new RuntimeException('Ajout ou doublon refusé à tort.');
    }
    if ((int) $database->query('SELECT COUNT(*) FROM favori WHERE id_utilisateur = 1 AND id_produit = 1')->fetchColumn() !== 1) {
        throw new RuntimeException('Le doublon favori n a pas été empêché.');
    }
    if ($favoriteService->add(999, 1) || $favoriteService->add(1, 999) || $favoriteService->add(1, 3)) {
        throw new RuntimeException('Un utilisateur ou produit invalide a été accepté.');
    }
    if (!$favoriteService->remove(1, 1) || $favoriteService->productIdsForUser(1) !== []) {
        throw new RuntimeException('La suppression du favori a échoué.');
    }
    if (!$favoriteService->mergeGuestFavorites(1, '1,2,2,3,999')) {
        throw new RuntimeException('La fusion invitée a échoué.');
    }
    if ($favoriteService->productIdsForUser(1) !== [2, 1]) {
        throw new RuntimeException('La fusion ne conserve pas les produits valides sans doublon.');
    }
    if (!$favoriteService->add(2, 2) || $favoriteService->productIdsForUser(2) !== [2]) {
        throw new RuntimeException('Le second utilisateur ne possède pas sa sélection.');
    }
    if (!$favoriteService->remove(1, 2) || $favoriteService->productIdsForUser(2) !== [2]) {
        throw new RuntimeException('Les favoris des utilisateurs ne sont pas isolés.');
    }

    Session::start();
    Session::set('user', ['id' => 1]);
    $controller = new FavoriteController($favoriteService, $productService, new Request());
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_token' => 'invalid', 'action' => 'add', 'productId' => 2];
    http_response_code(200);
    ob_start();
    $controller->mutate();
    ob_end_clean();

    if (http_response_code() !== 419 || in_array(2, $favoriteService->productIdsForUser(1), true)) {
        throw new RuntimeException('La mutation sans CSRF valide n a pas été refusée.');
    }

    $token = CsrfMiddleware::refresh();
    $_POST = ['_token' => $token, 'action' => 'add', 'productId' => 2, 'user_id' => 2];
    http_response_code(200);
    ob_start();
    $controller->mutate();
    $payload = json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);

    if (http_response_code() !== 200 || ($payload['active'] ?? false) !== true) {
        throw new RuntimeException('La mutation avec CSRF valide a échoué.');
    }
    if ($favoriteService->productIdsForUser(2) !== [2]) {
        throw new RuntimeException('Une identité envoyée par le navigateur a été utilisée.');
    }

    $_POST = ['_token' => 'invalid', 'ids' => '1,2'];
    http_response_code(200);
    ob_start();
    $controller->merge();
    ob_end_clean();

    if (http_response_code() !== 419) {
        throw new RuntimeException('La fusion sans CSRF valide n a pas été refusée.');
    }

    $_POST = ['_token' => $token, 'ids' => '1,2,2'];
    http_response_code(200);
    ob_start();
    $controller->merge();
    $mergePayload = json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);

    if (http_response_code() !== 200 || ($mergePayload['ids'] ?? []) !== [2, 1]) {
        throw new RuntimeException('La fusion API avec CSRF valide a échoué.');
    }

    Session::remove('user');
    $_POST = ['_token' => $token, 'action' => 'remove', 'productId' => 2];
    http_response_code(200);
    ob_start();
    $controller->mutate();
    ob_end_clean();

    if (http_response_code() !== 401) {
        throw new RuntimeException('La mutation non connectée n a pas été refusée.');
    }

    Session::destroy();
    echo "CONNECTED_FAVORITES_OK\n";
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::destroy();
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
