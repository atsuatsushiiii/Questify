<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

use App\Controllers\Web\AchievementController;
use App\Controllers\Web\AdminAchievementController;
use App\Controllers\Web\AdminAuditController;
use App\Controllers\Web\AdminCategoryController;
use App\Controllers\Web\AdminDashboardController;
use App\Controllers\Web\AdminQuestController;
use App\Controllers\Web\AdminUserController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\GoogleAuthController;
use App\Controllers\Web\LandingController;
use App\Controllers\Web\LeaderboardController;
use App\Controllers\Web\NotificationController;
use App\Controllers\Web\PasswordResetController;
use App\Controllers\Web\ProfileController;
use App\Controllers\Web\PublicProfileController;
use App\Controllers\Web\QuestController;
use App\Controllers\Web\SettingsController;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
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

// Google OpenID Connect. The callback is a browser redirect from Google.
$router->post('/auth/google', [GoogleAuthController::class, 'redirect'], [GuestMiddleware::class, VerifyCsrfToken::class]);
$router->get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

$router->get('/forgot-password', [PasswordResetController::class, 'showRequest'], [GuestMiddleware::class]);
$router->post('/forgot-password', [PasswordResetController::class, 'send'], [GuestMiddleware::class, VerifyCsrfToken::class]);

$router->get('/reset-password', [PasswordResetController::class, 'showReset'], [GuestMiddleware::class]);
$router->post('/reset-password', [PasswordResetController::class, 'reset'], [GuestMiddleware::class, VerifyCsrfToken::class]);

// Public, opt-in profile pages. Hidden and suspended accounts resolve as 404.
$router->get('/u/{username}', [PublicProfileController::class, 'show']);

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

// Phase 5: owner-only account settings. No route accepts a target user id.
$router->get('/settings', [SettingsController::class, 'show'], [AuthMiddleware::class]);

$router->post('/settings/password', [SettingsController::class, 'updatePassword'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/email', [SettingsController::class, 'requestEmailChange'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->get('/settings/email/confirm', [SettingsController::class, 'showEmailConfirmation'], [AuthMiddleware::class]);
$router->post('/settings/email/confirm', [SettingsController::class, 'confirmEmailChange'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/notifications', [SettingsController::class, 'updateNotifications'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/sessions/revoke-others', [SettingsController::class, 'revokeOtherSessions'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/sessions/{id}/revoke', [SettingsController::class, 'revokeSession'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/export', [SettingsController::class, 'export'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/delete', [SettingsController::class, 'destroy'], [AuthMiddleware::class, VerifyCsrfToken::class]);

$router->post('/settings/google', [GoogleAuthController::class, 'link'], [AuthMiddleware::class, VerifyCsrfToken::class]);
$router->post('/settings/google/unlink', [GoogleAuthController::class, 'unlink'], [AuthMiddleware::class, VerifyCsrfToken::class]);

// Phase 6: administrator surface. Every route requires an admin role and
// every mutation is CSRF-protected and audited.
$router->get('/admin', [AdminDashboardController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);

$router->get('/admin/users', [AdminUserController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/users/{id}', [AdminUserController::class, 'show'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/users/{id}/suspend', [AdminUserController::class, 'suspend'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/users/{id}/activate', [AdminUserController::class, 'activate'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/users/{id}/promote', [AdminUserController::class, 'promote'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/users/{id}/demote', [AdminUserController::class, 'demote'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);

$router->get('/admin/quests', [AdminQuestController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/quests/{id}', [AdminQuestController::class, 'show'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/quests/{id}/archive', [AdminQuestController::class, 'archive'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/quests/{id}/restore', [AdminQuestController::class, 'restore'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/quests/{id}/delete', [AdminQuestController::class, 'destroy'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);

$router->get('/admin/categories', [AdminCategoryController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/categories/create', [AdminCategoryController::class, 'create'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/categories', [AdminCategoryController::class, 'store'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->get('/admin/categories/{id}/edit', [AdminCategoryController::class, 'edit'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->put('/admin/categories/{id}', [AdminCategoryController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/categories/{id}/delete', [AdminCategoryController::class, 'destroy'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);

$router->get('/admin/achievements', [AdminAchievementController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->get('/admin/achievements/create', [AdminAchievementController::class, 'create'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->post('/admin/achievements', [AdminAchievementController::class, 'store'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->get('/admin/achievements/{id}/edit', [AdminAchievementController::class, 'edit'], [AuthMiddleware::class, AdminMiddleware::class]);
$router->put('/admin/achievements/{id}', [AdminAchievementController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);
$router->post('/admin/achievements/{id}/delete', [AdminAchievementController::class, 'destroy'], [AuthMiddleware::class, AdminMiddleware::class, VerifyCsrfToken::class]);

$router->get('/admin/audit', [AdminAuditController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);

$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, VerifyCsrfToken::class]);
