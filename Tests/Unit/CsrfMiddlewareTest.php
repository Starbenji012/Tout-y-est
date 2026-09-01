<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/core/Session.php';
require_once dirname(__DIR__, 2) . '/App/middleware/CsrfMiddleware.php';

use App\Core\Session;
use App\Middleware\CsrfMiddleware;

Session::start();
Session::destroy();

$initialToken = CsrfMiddleware::token();
$rotatedToken = CsrfMiddleware::refresh();

if ($initialToken === $rotatedToken) {
    fwrite(STDERR, "Le jeton CSRF n'a pas été renouvelé.\n");
    exit(1);
}

if (CsrfMiddleware::isValid($initialToken)) {
    fwrite(STDERR, "Un ancien jeton CSRF reste valide après rotation.\n");
    exit(1);
}

if (!CsrfMiddleware::isValid($rotatedToken)) {
    fwrite(STDERR, "Le nouveau jeton CSRF n'est pas validé.\n");
    exit(1);
}

echo "CSRF rotation OK\n";
