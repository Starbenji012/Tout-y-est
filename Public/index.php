<?php

declare(strict_types=1);

use App\Controllers\HomeController;

require dirname(__DIR__) . '/App/core/Controller.php';
require dirname(__DIR__) . '/App/controllers/HomeController.php';

(new HomeController())->index();
