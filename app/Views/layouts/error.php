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
    <meta name="theme-color" content="#0B0907">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="grid min-h-screen place-items-center overflow-hidden bg-void px-5 py-16">
    <div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0 bg-grid mask-fade-b opacity-60"></div>
    </div>

    <div class="w-full max-w-lg text-center">
        <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify home">
            <span class="grid h-10 w-10 place-items-center rounded-sm border border-brand-500 bg-brand-400 shadow-offset">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-[#14100B]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="font-display text-xl font-semibold tracking-tight text-white">Questify</span>
        </a>

        <main class="mt-10">
            <?= $content ?>
        </main>

        <p class="mt-10 font-mono text-[9px] tracking-[0.08em] text-muted-dim">Code © 2026 Kevin Cyril Maraño, MIT license.</p>
    </div>
</body>
</html>
