<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Controllers\ErrorController;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CartService;
use App\Models\CharacteristicValue;
use App\Models\Product;
use App\Models\Review;
use App\Services\ProductService;

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestedFile = __DIR__ . ($requestedPath ?: '/');

    if (is_file($requestedFile)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/App/core/Database.php';
require_once dirname(__DIR__) . '/App/core/Request.php';
require_once dirname(__DIR__) . '/App/core/Response.php';
require_once dirname(__DIR__) . '/App/core/Controller.php';
require_once dirname(__DIR__) . '/App/core/Session.php';
require_once dirname(__DIR__) . '/App/middleware/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/App/models/Product.php';
require_once dirname(__DIR__) . '/App/models/CharacteristicValue.php';
require_once dirname(__DIR__) . '/App/models/Review.php';
require_once dirname(__DIR__) . '/App/models/User.php';
require_once dirname(__DIR__) . '/App/services/AuthService.php';
require_once dirname(__DIR__) . '/App/services/ProductService.php';
require_once dirname(__DIR__) . '/App/services/CartService.php';
require_once dirname(__DIR__) . '/App/controllers/CartController.php';
require_once dirname(__DIR__) . '/App/controllers/AccountController.php';
require_once dirname(__DIR__) . '/App/controllers/AuthController.php';
require_once dirname(__DIR__) . '/App/controllers/ErrorController.php';
require_once dirname(__DIR__) . '/App/controllers/HomeController.php';
require_once dirname(__DIR__) . '/App/controllers/FavoriteController.php';
require_once dirname(__DIR__) . '/App/controllers/ProductController.php';

$databaseConfig = require dirname(__DIR__) . '/Config/database.php';
$database = null;
Session::start();

try {
    $database = Database::connect($databaseConfig);
    $productService = new ProductService(
        new Product($database),
        new CharacteristicValue($database),
        new Review($database),
    );
} catch (PDOException $exception) {
    error_log('Connexion au catalogue indisponible : ' . $exception->getMessage());
    $productService = new ProductService();
}
$request = new Request();
$authService = new AuthService($database instanceof PDO ? new User($database) : null);
$cartService = new CartService($productService);
$routes = require dirname(__DIR__) . '/Routes/web.php';
$requestPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$route = $routes[$requestPath] ?? null;

if ($route === null) {
    (new ErrorController())->notFound();

    return;
}

[$controller, $action] = $route;
$controller->{$action}();
