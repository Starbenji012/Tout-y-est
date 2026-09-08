<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use RuntimeException;

/** Résout les routes et exécute leurs middlewares avant le contrôleur. */
final class Router
{
	/** Empêche l'instanciation du routeur statique. */
	private function __construct()
	{
	}

	/** Exécute la route demandée avec les middlewares déclarés par celle-ci. */
	public static function dispatch(array $routes, string $path): void
	{
		$route = $routes[$path] ?? null;

		if ($route === null) {
			throw new RuntimeException('Route introuvable.');
		}

		$handler = $route['handler'] ?? $route;
		$middlewareNames = $route['middleware'] ?? [];
		[$controller, $action] = $handler;
		$next = static function () use ($controller, $action): void {
			$controller->{$action}();
		};

		foreach (array_reverse($middlewareNames) as $middlewareName) {
			$middleware = self::middleware($middlewareName);
			$next = static fn () => $middleware->handle($next);
		}

		$next();
	}

	/** Construit uniquement les middlewares autorisés par la configuration des routes. */
	private static function middleware(string $name): object
	{
		return match ($name) {
			'auth' => new AuthMiddleware(),
			'guest' => new GuestMiddleware(),
			'admin' => new AdminMiddleware(),
			default => throw new RuntimeException('Middleware introuvable.'),
		};
	}
}
