<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authenticated application shell.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 */

use App\Core\Flash;
use App\Models\Notification;
use App\Support\Progression;

$currentUser = auth_user() ?? [];
$displayName = (string) ($currentUser['display_name'] ?? $currentUser['username'] ?? 'Adventurer');
$username = (string) ($currentUser['username'] ?? 'adventurer');
$email = (string) ($currentUser['email'] ?? '');
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$avatarUrl = avatar_url(isset($currentUser['avatar_path']) ? (string) $currentUser['avatar_path'] : null);
$unreadNotifications = isset($currentUser['id']) ? Notification::unreadCount((int) $currentUser['id']) : 0;
$progression = Progression::snapshot($currentUser);
$appNav = require __DIR__ . '/../partials/app-nav.php';
$pageTitle = $title ?? 'Questify';
$pageDescription = $description ?? 'Your Questify command center.';
$flash = Flash::consume();

$pageHeading = 'Dashboard';

foreach ($appNav as $section) {
    foreach ($section['items'] as $item) {
        if (nav_active($item['href'], (bool) ($item['exact'] ?? false))) {
            $pageHeading = $item['label'];
            break 2;
        }
    }
}

// Persisted by the shell module. Keeping the state server-side avoids a
// collapsed-sidebar flash on navigation and needs no inline script under CSP.
$sidebarState = ($_COOKIE['questify_sidebar'] ?? '') === 'collapsed' ? 'collapsed' : 'expanded';
$isAdminSurface = nav_active('/admin');
$isRestricted = account_restricted($currentUser);
$restrictedUntilLabel = $isRestricted
    && isset($currentUser['restricted_until'])
    && $currentUser['restricted_until'] !== null
    ? format_datetime((string) $currentUser['restricted_until'], 'UTC', 'j M Y, H:i')
    : null;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#100D09">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen overflow-x-hidden bg-void text-slate-300 antialiased">
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-sm focus:bg-brand-400 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#14100B]"
    >
        Skip to content
    </a>

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-60 mask-fade-b"></div>
    </div>

    <div class="app-shell" data-app-shell data-sidebar="<?= e($sidebarState) ?>">
        <div
            class="pointer-events-none fixed inset-0 z-40 bg-black/70 opacity-0 backdrop-blur-sm transition-opacity duration-300 lg:hidden"
            data-sidebar-overlay
            aria-hidden="true"
        ></div>

        <?php require __DIR__ . '/../partials/app-sidebar.php'; ?>

        <div class="app-content flex min-h-screen flex-col">
            <?php require __DIR__ . '/../partials/app-topbar.php'; ?>

            <main id="main" data-page-transition class="app-main mx-auto w-full max-w-[92rem] flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12 lg:pt-8"<?= $isAdminSurface ? ' data-admin-surface' : '' ?>>
                <?php if ($flash !== null): ?>
                    <?php
                    $type = $flash['type'];
                    $message = $flash['message'];
                    require __DIR__ . '/../partials/alert.php';
                    ?>
                <?php endif; ?>

                <?php if ($isRestricted): ?>
                    <div class="mb-6 flex items-start gap-3 rounded-sm border border-ember-400/30 border-l-2 border-l-ember-400 bg-ember-500/[0.06] px-4 py-3 text-sm leading-relaxed text-ember-100" role="status">
                        <i data-lucide="eye-off" class="mt-0.5 h-4 w-4 shrink-0 text-ember-300"></i>
                        <div class="min-w-0">
                            <p class="font-semibold text-ember-200">Read-only account</p>
                            <p class="mt-0.5 text-ember-100/90">
                                A moderator has limited this account while it is reviewed. You can still sign in, browse,
                                and review your quests, but creating, editing, completing, archiving, restoring, or deleting
                                quests and submitting reports is disabled.
                                <?php if ($restrictedUntilLabel !== null): ?>
                                    This limit ends on <?= e($restrictedUntilLabel) ?> UTC.
                                <?php endif; ?>
                            </p>
                            <a href="<?= e(url('/appeal')) ?>" class="mt-2 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-ember-200 underline decoration-ember-400/40 underline-offset-4 transition hover:text-white">
                                <i data-lucide="scale" class="h-3.5 w-3.5"></i>
                                Request a review
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <footer class="mt-auto border-t border-line px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-5">
                <div class="mx-auto flex w-full max-w-[92rem] flex-col items-center justify-between gap-2 font-mono text-[9px] tracking-[0.08em] text-muted-dim sm:flex-row sm:gap-4">
                    <p>Code © 2026 Kevin Cyril Maraño, MIT license.</p>
                    <p class="inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-sm bg-aqua-400"></span>
                        <?= e($displayName) ?> · <?= e($progression['rank']) ?> · Level <?= e((string) $progression['level']) ?>
                    </p>
                </div>
            </footer>
        </div>

        <?php require __DIR__ . '/../partials/app-bottom-nav.php'; ?>
    </div>
</body>
</html>
