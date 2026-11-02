<?php

declare(strict_types=1);

use App\Controllers\Api\HealthController;
use App\Core\Router;

/** @var Router $router */
$router->get('/api/health', [HealthController::class, 'index']);
