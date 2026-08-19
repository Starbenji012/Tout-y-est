<?php

declare(strict_types=1);

require dirname(__DIR__) . '/App/core/Controller.php';
require dirname(__DIR__) . '/App/services/ProductService.php';
require dirname(__DIR__) . '/App/controllers/HomeController.php';
require dirname(__DIR__) . '/App/controllers/ProductController.php';

if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (is_file($requestedFile)) {
        return false;
    }
}

$routes = require dirname(__DIR__) . '/Routes/web.php';
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$handler = $routes[$path] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo 'Page introuvable.';
    return;
}

$handler();
