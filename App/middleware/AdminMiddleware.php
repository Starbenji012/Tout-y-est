<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Session;

/** Limite les routes d'administration aux comptes possédant le rôle admin. */
final class AdminMiddleware
{
	/** Continue la requête uniquement pour un administrateur authentifié. */
	public function handle(callable $next): void
	{
		$user = Session::get('user');

		if (!is_array($user) || strtolower((string) ($user['role'] ?? '')) !== 'admin') {
			http_response_code(403);
			echo 'Accès interdit.';
			return;
		}

		$next();
	}
}
