<?php
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
        if (nav_active($item['href'])) {
            $pageHeading = $item['label'];
            break 2;
        }
    }
}

// Persisted by the shell module. Keeping the state server-side avoids a
// collapsed-sidebar flash on navigation and needs no inline script under CSP.
$sidebarState = ($_COOKIE['questify_sidebar'] ?? '') === 'collapsed' ? 'collapsed' : 'expanded';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#08070D">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen overflow-x-hidden bg-[#07060B] text-slate-300 antialiased">
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-gold-400 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-black"
    >
        Skip to content
    </a>

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-[0.18] mask-fade-b"></div>
        <div class="absolute -left-40 -top-40 h-[30rem] w-[30rem] rounded-full bg-ember-600/[0.10] blur-[130px]"></div>
        <div class="absolute right-0 top-1/4 h-80 w-80 rounded-full bg-gold-400/[0.05] blur-[120px]"></div>
        <div class="absolute bottom-0 left-1/3 h-72 w-[36rem] rounded-full bg-ember-500/[0.05] blur-[140px]"></div>
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

            <main id="main" class="app-main mx-auto w-full max-w-[92rem] flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12 lg:pt-8">
                <?php if ($flash !== null): ?>
                    <?php
                    $type = $flash['type'];
                    $message = $flash['message'];
                    require __DIR__ . '/../partials/alert.php';
                    ?>
                <?php endif; ?>

                <?= $content ?>
            </main>

            <footer class="mt-auto hidden border-t border-white/[0.05] px-8 py-5 lg:block">
                <div class="mx-auto flex w-full max-w-[92rem] items-center justify-between gap-4 text-[10px] text-slate-600">
                    <p>&copy; <?= e(date('Y')) ?> Questify · Phase 4 adventure systems</p>
                    <p class="inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        <?= e($displayName) ?> · <?= e($progression['rank']) ?> · Level <?= e((string) $progression['level']) ?>
                    </p>
                </div>
            </footer>
        </div>

        <?php require __DIR__ . '/../partials/app-bottom-nav.php'; ?>
    </div>
</body>
</html>
