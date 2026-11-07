<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Public site layout.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 */
$pageTitle = $title ?? config('app.name', 'Questify');
$pageDescription = $description ?? 'Questify turns everyday tasks into quests. Earn XP, build streaks, unlock achievements and level up your real life.';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0B0907">
    <meta name="color-scheme" content="dark">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Questify">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">

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

    <?php require __DIR__ . '/../partials/public-nav.php'; ?>

    <main id="main" data-page-transition>
        <?= $content ?>
    </main>

    <?php require __DIR__ . '/../partials/public-footer.php'; ?>
</body>
</html>
