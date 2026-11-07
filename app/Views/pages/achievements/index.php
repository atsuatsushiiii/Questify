<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Achievement hall.
 *
 * @var array<string, mixed> $user
 * @var array{metrics: array<string, int>, achievements: list<array<string, mixed>>, summary: array{total: int, unlocked: int, locked: int, percent: int}} $overview
 * @var list<array<string, mixed>> $achievements
 * @var string $category
 * @var array<string, string> $categories
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$summary = $overview['summary'];
$countsByCategory = [];

foreach ($overview['achievements'] as $item) {
    $key = (string) $item['category'];
    $countsByCategory[$key] = ($countsByCategory[$key] ?? 0) + 1;
}
?>
<section class="mx-auto max-w-6xl" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">
                <i data-lucide="trophy" class="h-3.5 w-3.5"></i>
                Trophy hall
            </p>
            <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Achievements</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">
                Every badge is evaluated from your real quest ledger, XP total, level, and streak history.
            </p>
        </div>
        <a href="<?= e(url('/quests')) ?>" class="btn-app-ghost btn inline-flex text-xs">
            <i data-lucide="scroll-text" class="h-3.5 w-3.5"></i>
            Back to quests
        </a>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <article class="app-panel relative overflow-hidden p-6">
            <div class="pointer-events-none absolute inset-0 bg-grid opacity-40" aria-hidden="true"></div>
            <div class="relative">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Collection progress</p>
                        <p class="mt-2 font-display text-4xl font-semibold tracking-tight text-white">
                            <?= e((string) $summary['unlocked']) ?>
                            <span class="text-lg font-medium text-muted">/ <?= e((string) $summary['total']) ?> unlocked</span>
                        </p>
                    </div>
                    <span class="grid h-14 w-14 place-items-center rounded-sm border border-brand-400/35 bg-brand-400/10 text-brand-300">
                        <i data-lucide="award" class="h-6 w-6"></i>
                    </span>
                </div>

                <div
                    class="mt-5 h-1.5 overflow-hidden bg-white/[0.06]"
                    role="progressbar"
                    aria-label="Achievements unlocked"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= e((string) $summary['percent']) ?>"
                >
                    <div class="h-full bg-brand-400" style="width: <?= e((string) $summary['percent']) ?>%"></div>
                </div>
                <p class="mt-2 font-mono text-[9px] uppercase tracking-[0.1em] text-muted"><?= e((string) $summary['percent']) ?>% of the trophy hall collected</p>
            </div>
        </article>

        <div class="grid grid-cols-3 gap-3">
            <article class="app-stat-card p-4">
                <p class="app-stat-label">Unlocked</p>
                <p class="mt-3 font-display text-2xl font-semibold text-brand-300"><?= e((string) $summary['unlocked']) ?></p>
            </article>
            <article class="app-stat-card p-4">
                <p class="app-stat-label">Locked</p>
                <p class="mt-3 font-display text-2xl font-semibold text-slate-300"><?= e((string) $summary['locked']) ?></p>
            </article>
            <article class="app-stat-card p-4">
                <p class="app-stat-label">Quests</p>
                <p class="mt-3 font-display text-2xl font-semibold text-white"><?= e((string) ($overview['metrics']['quests_completed'] ?? 0)) ?></p>
            </article>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        <div class="quest-tabs" role="navigation" aria-label="Achievement categories">
            <a
                href="<?= e(url('/achievements')) ?>"
                class="quest-tab <?= $category === 'all' ? 'is-active' : '' ?>"
                <?= $category === 'all' ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="layout-dashboard" class="h-3.5 w-3.5"></i>
                All
                <span class="quest-tab-count"><?= e((string) $summary['total']) ?></span>
            </a>
            <?php foreach ($categories as $key => $label): ?>
                <a
                    href="<?= e(url('/achievements?category=' . urlencode($key))) ?>"
                    class="quest-tab <?= $category === $key ? 'is-active' : '' ?>"
                    <?= $category === $key ? 'aria-current="page"' : '' ?>
                >
                    <?= e($label) ?>
                    <span class="quest-tab-count"><?= e((string) ($countsByCategory[$key] ?? 0)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($achievements === []): ?>
        <div class="mt-6 rounded-sm border border-dashed border-line bg-panel/30 px-5 py-14 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                <i data-lucide="trophy" class="h-5 w-5"></i>
            </span>
            <p class="mt-4 font-display text-sm font-semibold text-slate-200">No achievements in this category</p>
            <p class="mt-1.5 text-xs text-muted">Try another category or complete more quests to fill the trophy hall.</p>
        </div>
    <?php else: ?>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($achievements as $achievement): ?>
                <?php require __DIR__ . '/../../partials/achievement-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
