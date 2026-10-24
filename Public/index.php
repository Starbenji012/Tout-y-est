<?php

declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Controllers\ErrorController;
use App\Controllers\CategoryController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\Setting;
use App\Services\MoneyService;
use App\Models\UserAddress;
use App\Models\DeliveryZone;
use App\Services\CheckoutService;
use App\Services\AuthService;
use App\Services\CartService;
use App\Services\CategoryService;
use App\Services\FavoriteService;
use App\Services\EmailVerificationService;
use App\Services\LoginThrottleService;
use App\Services\PasswordResetService;
use App\Services\SmtpEmailSender;
use App\Models\Product;
use App\Services\ProductService;

// Laisse le serveur PHP livrer directement les images, feuilles de style et scripts.
if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestedFile = __DIR__ . ($requestedPath ?: '/');

    if (is_file($requestedFile)) {
        return false;
    }
}

// Charge les classes actuellement utilisées par l'application.
require_once dirname(__DIR__) . '/App/core/Database.php';
require_once dirname(__DIR__) . '/App/core/Request.php';
require_once dirname(__DIR__) . '/App/core/Router.php';
require_once dirname(__DIR__) . '/App/core/Response.php';
require_once dirname(__DIR__) . '/App/core/Controller.php';
require_once dirname(__DIR__) . '/App/core/Session.php';
require_once dirname(__DIR__) . '/App/middleware/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/App/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/App/middleware/GuestMiddleware.php';
require_once dirname(__DIR__) . '/App/middleware/AdminMiddleware.php';
require_once dirname(__DIR__) . '/App/contracts/EmailSender.php';
require_once dirname(__DIR__) . '/App/contracts/PasswordResetSender.php';
require_once dirname(__DIR__) . '/App/models/Cart.php';
require_once dirname(__DIR__) . '/App/models/Payment.php';
require_once dirname(__DIR__) . '/App/models/PaymentMethod.php';
require_once dirname(__DIR__) . '/App/services/PaymentService.php';
require_once dirname(__DIR__) . '/App/controllers/PaymentController.php';
require_once dirname(__DIR__) . '/App/services/CustomerOrderService.php';
require_once dirname(__DIR__) . '/App/controllers/CustomerOrderController.php';
require_once dirname(__DIR__) . '/App/models/Favorite.php';
require_once dirname(__DIR__) . '/App/models/Product.php';
require_once dirname(__DIR__) . '/App/models/Category.php';
require_once dirname(__DIR__) . '/App/models/User.php';
require_once dirname(__DIR__) . '/App/models/Setting.php';
require_once dirname(__DIR__) . '/App/services/MoneyService.php';
require_once dirname(__DIR__) . '/App/models/UserAddress.php';
require_once dirname(__DIR__) . '/App/models/DeliveryZone.php';
require_once dirname(__DIR__) . '/App/services/CheckoutService.php';
require_once dirname(__DIR__) . '/App/controllers/CheckoutController.php';
foreach (['models/ProductVariant','models/Order','models/OrderItem','models/Delivery','services/OrderService','controllers/OrderController'] as $orderFile) require_once dirname(__DIR__) . '/App/' . $orderFile . '.php';
require_once dirname(__DIR__) . '/App/services/DeliveryZoneService.php';
require_once dirname(__DIR__) . '/App/controllers/Admin/DeliveryController.php';
require_once dirname(__DIR__) . '/App/models/PasswordResetToken.php';
require_once dirname(__DIR__) . '/App/services/AuthService.php';
require_once dirname(__DIR__) . '/App/services/LoginThrottleService.php';
require_once dirname(__DIR__) . '/App/services/ProductService.php';
require_once dirname(__DIR__) . '/App/services/CategoryService.php';
require_once dirname(__DIR__) . '/App/services/CartService.php';
require_once dirname(__DIR__) . '/App/services/FavoriteService.php';
require_once dirname(__DIR__) . '/App/services/SmtpEmailSender.php';
require_once dirname(__DIR__) . '/App/services/EmailVerificationService.php';
require_once dirname(__DIR__) . '/App/services/PasswordResetService.php';
require_once dirname(__DIR__) . '/App/controllers/CartController.php';
require_once dirname(__DIR__) . '/App/controllers/CategoryController.php';
require_once dirname(__DIR__) . '/App/controllers/AccountController.php';
require_once dirname(__DIR__) . '/App/controllers/AuthController.php';
require_once dirname(__DIR__) . '/App/controllers/EmailVerificationController.php';
require_once dirname(__DIR__) . '/App/controllers/PasswordResetController.php';
require_once dirname(__DIR__) . '/App/controllers/ErrorController.php';
require_once dirname(__DIR__) . '/App/controllers/HomeController.php';
require_once dirname(__DIR__) . '/App/controllers/FavoriteController.php';
require_once dirname(__DIR__) . '/App/controllers/ProductController.php';

// Une panne ne devient jamais silencieusement un faux catalogue commercial.
$appConfig = require dirname(__DIR__) . '/Config/app.php';
$databaseConfig = require dirname(__DIR__) . '/Config/database.php';
$database = null;
Session::start();

try {
    $database = Database::connect($databaseConfig);
    $moneyService = new MoneyService(new Setting($database));
    $productService = new ProductService(
        new Product($database),
        $moneyService,
    );
} catch (PDOException $exception) {
    error_log('Connexion au catalogue indisponible : ' . $exception->getMessage());
    if (!$appConfig['catalog_demo_enabled']) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="fr"><meta charset="UTF-8"><title>Service indisponible | Tout y est</title><main><h1>Nous revenons bientôt</h1><p>La boutique est momentanément indisponible. Merci de réessayer dans quelques instants.</p></main></html>';
        return;
    }
    $productService = new ProductService(demoEnabled: true);
}
$moneyService = $moneyService ?? new MoneyService();
$request = new Request();
$userModel = $database instanceof PDO ? new User($database) : null;
$mailConfig = require dirname(__DIR__) . '/Config/mail.php';
$emailSender = new SmtpEmailSender($mailConfig);
$emailVerificationService = new EmailVerificationService($userModel, $emailSender);
$passwordResetToken = $database instanceof PDO ? new PasswordResetToken($database) : null;
$passwordResetService = new PasswordResetService($userModel, $passwordResetToken, $emailSender, $database);
$authService = new AuthService($userModel, $emailVerificationService);
$loginThrottleService = new LoginThrottleService();
$categoryService = new CategoryService($database instanceof PDO ? new Category($database) : null, $productService);
$cartService = new CartService(
    $productService,
    $database instanceof PDO ? new Cart($database) : null,
    $moneyService,
);
$favoriteService = new FavoriteService(
    $productService,
    $database instanceof PDO ? new Favorite($database) : null,
);

$checkoutService = new CheckoutService(
    $cartService,
    $authService,
    $userModel,
    $database instanceof PDO ? new UserAddress($database) : null,
    $database instanceof PDO ? new DeliveryZone($database) : null,
    $moneyService,
);

// Associe l'adresse demandée au contrôleur chargé de produire la réponse.
$orderService = $database instanceof PDO ? new \App\Services\OrderService(
    $database, $userModel, new Cart($database), new \App\Models\ProductVariant($database),
    new DeliveryZone($database), new UserAddress($database), new \App\Models\Order($database),
    new \App\Models\OrderItem($database), new \App\Models\Delivery($database), $checkoutService, $moneyService,
) : null;
$paymentService = $database instanceof PDO ? new \App\Services\PaymentService($database) : null;
$customerOrderService = $database instanceof PDO ? new \App\Services\CustomerOrderService(new \App\Models\Order($database), new \App\Models\OrderItem($database), new \App\Models\Delivery($database), $moneyService, $productService) : null;
$deliveryZoneService = new \App\Services\DeliveryZoneService(
    $userModel,
    $database instanceof PDO ? new DeliveryZone($database) : null,
    $moneyService,
);
$routes = require dirname(__DIR__) . '/Routes/web.php';
$requestPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$route = $routes[$requestPath] ?? null;

if ($route === null) {
    (new ErrorController())->notFound();

    return;
}

Router::dispatch($routes, $requestPath);
