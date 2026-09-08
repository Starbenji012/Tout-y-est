<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

/** Empêche un utilisateur connecté d'ouvrir les routes réservées aux invités. */
final class GuestMiddleware
{
	/** Continue la requête uniquement si aucune session utilisateur n'existe. */
	public function handle(callable $next): void
	{
		if (is_array(Session::get('user'))) {
			Response::redirect('/compte');
		}

		$next();
	}
}
