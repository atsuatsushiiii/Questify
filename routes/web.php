<?php

declare(strict_types=1);

use App\Controllers\Web\AchievementController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\LandingController;
use App\Controllers\Web\LeaderboardController;
use App\Controllers\Web\NotificationController;
use App\Controllers\Web\PasswordResetController;
use App\Controllers\Web\PlaceholderController;
use App\Controllers\Web\ProfileController;
use App\Controllers\Web\QuestController;
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

// Quest log. Every read and write is scoped to the authenticated owner.
$router->get('/quests', [QuestController::class, 'index'], [AuthMiddleware::class]);
$router->get('/quests/create', [QuestController::class, 'create'], [AuthMiddleware::class]);
$router->post('/quests', [QuestController::class, 'store'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->get('/quests/{id}', [QuestController::class, 'show'], [AuthMiddleware::class]);
$router->get('/quests/{id}/edit', [QuestController::class, 'edit'], [AuthMiddleware::class]);
$router->put('/quests/{id}', [QuestController::class, 'update'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/quests/{id}/complete', [QuestController::class, 'complete'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/quests/{id}/archive', [QuestController::class, 'archive'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/quests/{id}/restore', [QuestController::class, 'restore'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/quests/{id}/delete', [QuestController::class, 'destroy'], [AuthMiddleware::class, VerifyCsrfToken::class]);

// Phase 4: achievements, profiles, notifications and leaderboards.
$router->get('/achievements', [AchievementController::class, 'index'], [AuthMiddleware::class]);

$router->get('/leaderboard', [LeaderboardController::class, 'index'], [AuthMiddleware::class]);

$router->get('/profile', [ProfileController::class, 'show'], [AuthMiddleware::class]);
$router->post('/profile', [ProfileController::class, 'update'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/profile/avatar', [ProfileController::class, 'updateAvatar'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/profile/avatar/remove', [ProfileController::class, 'removeAvatar'], [AuthMiddleware::class, VerifyCsrfToken::class]);

$router->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [AuthMiddleware::class, VerifyCsrfToken::class]);

// Phase 5 destination.
$router->get('/settings', [PlaceholderController::class, 'settings'], [AuthMiddleware::class]);

$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, VerifyCsrfToken::class]);
