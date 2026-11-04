<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authentication layout: branded panel plus a focused form column.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 */

use App\Core\Flash;

$pageTitle = $title ?? 'Questify';
$pageDescription = $description ?? 'Sign in or create your Questify account.';
$flash = Flash::consume();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
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

    <div class="min-h-screen lg:grid lg:grid-cols-[1.02fr_1fr]">
        <?php require __DIR__ . '/../partials/auth-aside.php'; ?>

        <div class="relative flex min-h-screen flex-col">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden lg:hidden" aria-hidden="true">
                <div class="absolute -top-24 left-1/2 h-72 w-[30rem] -translate-x-1/2 rounded-full bg-brand-600/15 blur-[90px]"></div>
                <div class="absolute inset-0 bg-grid opacity-30 mask-fade-b"></div>
            </div>

            <header class="flex items-center justify-between px-5 py-5 sm:px-8 lg:hidden">
                <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify home">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500 shadow-[0_0_24px_-6px_rgba(139,92,246,0.8)]">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                        </svg>
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight text-white">Questify</span>
                </a>
                <a href="<?= e(url('/')) ?>" class="text-xs font-medium text-slate-500 transition hover:text-white">Back home</a>
            </header>

            <main id="main" class="flex flex-1 items-center justify-center px-5 py-8 sm:px-8 lg:py-14">
                <div class="form-panel w-full max-w-[26.5rem]">
                    <?php if ($flash !== null): ?>
                        <?php
                        $type = $flash['type'];
                        $message = $flash['message'];
                        require __DIR__ . '/../partials/alert.php';
                        ?>
                    <?php endif; ?>

                    <?= $content ?>
                </div>
            </main>

            <footer class="flex flex-col items-center gap-2 px-5 pb-6 text-center text-[11px] text-slate-600 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-3">
                <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-1.5 transition hover:text-slate-300">
                    <i data-lucide="arrow-left" class="h-3 w-3"></i>
                    Back to Questify
                </a>
                <span class="hidden text-slate-700 sm:inline">&middot;</span>
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="shield-check" class="h-3 w-3 text-aqua-400/80"></i>
                    Secure sessions &amp; encrypted passwords
                </span>
                <span class="hidden text-slate-700 sm:inline">&middot;</span>
                <span>Code © 2026 Kevin Cyril Maraño, MIT license.</span>
            </footer>
        </div>
    </div>
</body>
</html>
