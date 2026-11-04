<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

use App\Controllers\Api\AdminStatsController;
use App\Controllers\Api\HealthController;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;

/** @var Router $router */
$router->get('/api/health', [HealthController::class, 'index']);
$router->get('/api/admin/stats', [AdminStatsController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
