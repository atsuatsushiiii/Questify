<?php

declare(strict_types=1);

use App\Controllers\Web\AuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\LandingController;
use App\Controllers\Web\PasswordResetController;
use App\Controllers\Web\PlaceholderController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\VerifyCsrfToken;

/** @var Router $router */
$router->get('/', [LandingController::class, 'index']);

// Guest-only authentication pages.
$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, VerifyCsrfToken::class]);

$router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
$router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class, VerifyCsrfToken::class]);

$router->get('/forgot-password', [PasswordResetController::class, 'showRequest'], [GuestMiddleware::class]);
$router->post('/forgot-password', [PasswordResetController::class, 'send'], [GuestMiddleware::class, VerifyCsrfToken::class]);

$router->get('/reset-password', [PasswordResetController::class, 'showReset'], [GuestMiddleware::class]);
$router->post('/reset-password', [PasswordResetController::class, 'reset'], [GuestMiddleware::class, VerifyCsrfToken::class]);

// Authenticated routes.
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Phase 3–5 destinations. The pages are intentionally explicit about scope.
$router->get('/quests', [PlaceholderController::class, 'quests'], [AuthMiddleware::class]);
$router->get('/achievements', [PlaceholderController::class, 'achievements'], [AuthMiddleware::class]);
$router->get('/leaderboard', [PlaceholderController::class, 'leaderboard'], [AuthMiddleware::class]);
$router->get('/profile', [PlaceholderController::class, 'profile'], [AuthMiddleware::class]);
$router->get('/settings', [PlaceholderController::class, 'settings'], [AuthMiddleware::class]);

$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, VerifyCsrfToken::class]);
