<?php
/**
 * Authenticated application layout (minimal shell for Phase 1).
 *
 * @var string $content
 * @var string|null $title
 */

use App\Core\Flash;

$pageTitle = $title ?? 'Questify';
$currentUser = auth_user();
$displayName = (string) ($currentUser['display_name'] ?? $currentUser['username'] ?? 'Adventurer');
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$flash = Flash::consume();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="theme-color" content="#06060D">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen overflow-x-hidden bg-void text-slate-300 antialiased">
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-brand-500 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
    >
        Skip to content
    </a>

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-25 mask-fade-b"></div>
        <div class="absolute -top-40 left-1/2 h-96 w-[48rem] -translate-x-1/2 rounded-full bg-brand-600/10 blur-[120px]"></div>
        <div class="absolute -right-40 top-1/3 h-80 w-80 rounded-full bg-aqua-500/[0.06] blur-[100px]"></div>
    </div>

    <header class="sticky top-0 z-40 border-b border-white/[0.06] bg-ink/80 backdrop-blur-xl">
        <div class="container-page flex h-16 items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= e(url('/dashboard')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify dashboard">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500 shadow-[0_0_24px_-6px_rgba(139,92,246,0.8)]">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                        </svg>
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight text-white">Questify</span>
                </a>
                <span class="hidden h-5 w-px bg-white/10 sm:block"></span>
                <span class="hidden text-xs font-medium uppercase tracking-[0.16em] text-slate-500 sm:block">Command center</span>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="flex items-center gap-2.5 rounded-xl border border-white/[0.07] bg-white/[0.025] py-1.5 pl-1.5 pr-3">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-aqua-500 text-[11px] font-bold text-white">
                        <?= e($initial) ?>
                    </span>
                    <span class="hidden max-w-[10rem] truncate text-xs font-medium text-slate-200 sm:block">
                        <?= e($displayName) ?>
                    </span>
                </div>

                <form method="POST" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/[0.07] bg-white/[0.025] px-3 py-2 text-xs font-medium text-slate-400 transition hover:border-rose-400/25 hover:bg-rose-500/10 hover:text-rose-200"
                        title="Sign out"
                    >
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                        <span class="hidden sm:inline">Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="main" class="container-page py-10 sm:py-12">
        <?php if ($flash !== null): ?>
            <?php
            $type = $flash['type'];
            $message = $flash['message'];
            require __DIR__ . '/../partials/alert.php';
            ?>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="border-t border-white/[0.05] py-6">
        <div class="container-page flex flex-col items-center justify-between gap-2 text-[11px] text-slate-600 sm:flex-row">
            <p>&copy; <?= e(date('Y')) ?> Questify</p>
            <p class="inline-flex items-center gap-1.5">
                <i data-lucide="shield-check" class="h-3 w-3 text-aqua-400/70"></i>
                Signed in as <?= e((string) ($currentUser['username'] ?? '')) ?>
            </p>
        </div>
    </footer>
</body>
</html>
