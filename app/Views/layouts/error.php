<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Standalone layout for error pages.
 *
 * @var string $content
 * @var string|null $title
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Error — Questify') ?></title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#06060D">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="grid min-h-screen place-items-center overflow-hidden bg-void px-5 py-16">
    <div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0 bg-grid mask-fade-b opacity-40"></div>
        <div class="absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-brand-600/20 blur-[120px]"></div>
    </div>

    <div class="w-full max-w-lg text-center">
        <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify home">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="font-display text-xl font-bold tracking-tight text-white">Questify</span>
        </a>

        <main class="mt-10">
            <?= $content ?>
        </main>

        <p class="mt-10 text-[11px] text-muted-dim">Code © 2026 Kevin Cyril Maraño, MIT license.</p>
    </div>
</body>
</html>
