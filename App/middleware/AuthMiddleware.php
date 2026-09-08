<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

/** Refuse l'accès aux routes réservées aux utilisateurs connectés. */
final class AuthMiddleware
{
	/** Continue la requête uniquement si une session utilisateur existe. */
	public function handle(callable $next): void
	{
		if (!is_array(Session::get('user'))) {
			Response::redirect('/connexion');
		}

		$next();
	}
}
