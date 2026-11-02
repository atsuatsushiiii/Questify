<?php

declare(strict_types=1);

use App\Controllers\Web\LandingController;
use App\Core\Router;

/** @var Router $router */
$router->get('/', [LandingController::class, 'index']);
