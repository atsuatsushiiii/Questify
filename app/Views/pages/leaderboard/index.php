<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Leaderboard page.
 *
 * @var array<string, mixed> $user
 * @var array<string, mixed> $progression
 * @var array<string, mixed> $board
 * @var array<string, string> $periods
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$period = (string) $board['period'];
$rows = $board['rows'];
$podium = array_slice($board['podium'], 0, 3);
$metricLabel = $period === 'all' ? 'Total XP' : 'Period XP';
$podiumOrder = [];

foreach ($podium as $entry) {
    $position = (int) $entry['rank_position'];

    if ($position <= 3) {
        $podiumOrder[$position] = $entry;
    }
}

// Visual podium order: second, first, third.
$podiumLayout = array_values(array_filter([
    $podiumOrder[2] ?? null,
    $podiumOrder[1] ?? null,
    $podiumOrder[3] ?? null,
]));
$podiumHeights = [2 => 'sm:mt-8', 1 => '', 3 => 'sm:mt-12'];
$podiumTones = [
    1 => 'border-gold-400/35 bg-gradient-to-br from-gold-400/[0.16] to-transparent text-gold-200',
    2 => 'border-slate-300/25 bg-gradient-to-br from-slate-300/[0.10] to-transparent text-slate-200',
    3 => 'border-ember-400/30 bg-gradient-to-br from-ember-500/[0.12] to-transparent text-ember-200',
];
$query = static fn (array $overrides = []): string => url('/leaderboard?' . http_build_query(array_merge(
    ['period' => $period],
    $overrides,
)));
?>
<section class="mx-auto max-w-6xl" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">
                <i data-lucide="crown" class="h-3.5 w-3.5"></i>
                Hall of adventurers
            </p>
            <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">Leaderboard</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">
                Rankings are built from the XP ledger. Only adventurers who chose to appear publicly are listed.
            </p>
        </div>
        <a href="<?= e(url('/profile')) ?>" class="btn-app-ghost btn inline-flex text-xs">
            <i data-lucide="settings" class="h-3.5 w-3.5"></i>
            Visibility settings
        </a>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="quest-tabs" role="navigation" aria-label="Leaderboard period">
            <?php foreach ($periods as $key => $label): ?>
                <a
                    href="<?= e(url('/leaderboard?period=' . urlencode($key))) ?>"
                    class="quest-tab <?= $period === $key ? 'is-active' : '' ?>"
                    <?= $period === $key ? 'aria-current="page"' : '' ?>
                >
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-muted-dim"><?= e((string) $board['period_note']) ?></p>
    </div>

    <?php if (!$board['viewer_visible']): ?>
        <div class="mt-4 flex items-start gap-3 rounded-xl border border-gold-400/20 bg-gold-400/[0.06] px-4 py-3 text-xs leading-relaxed text-gold-100">
            <i data-lucide="eye-off" class="mt-0.5 h-4 w-4 shrink-0 text-gold-300"></i>
            <p>
                You are hidden from public leaderboards. Turn on visibility in
                <a href="<?= e(url('/profile')) ?>" class="font-semibold underline decoration-gold-400/40 underline-offset-2 hover:text-white">your profile</a>
                to compete.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($podiumLayout !== []): ?>
        <div class="mt-6 grid gap-3 sm:grid-cols-3 sm:items-end">
            <?php foreach ($podiumLayout as $entry): ?>
                <?php
                $position = (int) $entry['rank_position'];
                $entryName = (string) ($entry['display_name'] ?? $entry['username'] ?? 'Adventurer');
                $entryUsername = (string) ($entry['username'] ?? '');
                $entryProfileUrl = (int) ($entry['show_profile'] ?? 0) === 1 && $entryUsername !== ''
                    ? url('/u/' . rawurlencode($entryUsername))
                    : null;
                $isViewer = (int) $entry['id'] === (int) $user['id'];
                ?>
                <article class="relative overflow-hidden rounded-2xl border p-5 <?= e($podiumTones[$position] ?? $podiumTones[3]) ?> <?= e($podiumHeights[$position] ?? '') ?>">
                    <div class="pointer-events-none absolute -right-10 -top-12 h-32 w-32 rounded-full bg-white/[0.06] blur-2xl" aria-hidden="true"></div>
                    <div class="relative flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-white/15 bg-black/20 font-display text-base font-bold">
                            #<?= e((string) $position) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">
                                <?php if ($entryProfileUrl !== null): ?>
                                    <a href="<?= e($entryProfileUrl) ?>" class="underline-offset-2 hover:underline"><?= e($entryName) ?></a>
                                <?php else: ?>
                                    <?= e($entryName) ?>
                                <?php endif; ?>
                                <?php if ($isViewer): ?>
                                    <span class="ml-1 rounded border border-gold-400/25 bg-gold-400/[0.12] px-1 py-0.5 text-[8px] font-bold uppercase tracking-wider text-gold-200">You</span>
                                <?php endif; ?>
                            </p>
                            <p class="truncate text-[10px] text-slate-400">
                                <?php if ($entryProfileUrl !== null): ?>
                                    <a href="<?= e($entryProfileUrl) ?>" class="underline-offset-2 hover:text-gold-300 hover:underline">@<?= e($entryUsername) ?></a>
                                <?php else: ?>
                                    @<?= e($entryUsername) ?>
                                <?php endif; ?>
                                · Lv <?= e((string) $entry['level']) ?> <?= e((string) $entry['rank_title']) ?>
                            </p>
                        </div>
                    </div>
                    <p class="relative mt-4 font-display text-2xl font-bold tabular-nums text-white">
                        <?= e(number_format((int) $entry['period_xp'])) ?>
                        <span class="text-[10px] font-medium uppercase tracking-wider text-slate-400"><?= e($metricLabel) ?></span>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <article class="app-panel mt-4 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="crown" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white"><?= e((string) $board['period_label']) ?> standings</h2>
                    <p class="text-[11px] text-muted">
                        <?= e(number_format((int) $board['total'])) ?> ranked <?= (int) $board['total'] === 1 ? 'adventurer' : 'adventurers' ?>
                    </p>
                </div>
            </div>
            <?php if ($board['viewer_rank'] !== null): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gold-300">
                    <i data-lucide="trending-up" class="h-3 w-3"></i>
                    Your rank #<?= e((string) $board['viewer_rank']) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="px-5 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-white/[0.07] bg-white/[0.03] text-muted">
                    <i data-lucide="crown" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No ranked adventurers yet</p>
                <p class="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-muted">
                    <?= $period === 'all'
                        ? 'Complete a quest to put the first XP on the board.'
                        : 'Nobody has earned XP in this period yet. The board resets on the next UTC boundary.' ?>
                </p>
                <a href="<?= e(url('/quests/create')) ?>" class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">
                    Create a quest
                    <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-2 p-4">
                <?php foreach ($rows as $entry): ?>
                    <?php
                    $isViewer = (int) $entry['id'] === (int) $user['id'];
                    require __DIR__ . '/../../partials/leaderboard-row.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ((int) $board['pages'] > 1): ?>
            <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-white/[0.06] px-5 py-4" aria-label="Leaderboard pagination">
                <p class="text-[11px] text-muted-dim">
                    Page <?= e((string) $board['page']) ?> of <?= e((string) $board['pages']) ?>
                </p>
                <div class="flex items-center gap-2">
                    <?php if ((int) $board['page'] > 1): ?>
                        <a href="<?= e($query(['page' => (int) $board['page'] - 1])) ?>" class="btn-app-ghost btn inline-flex text-xs" rel="prev">
                            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ((int) $board['page'] < (int) $board['pages']): ?>
                        <a href="<?= e($query(['page' => (int) $board['page'] + 1])) ?>" class="btn-app-ghost btn inline-flex text-xs" rel="next">
                            Next
                            <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </article>

    <?php if ($board['viewer_row'] !== null): ?>
        <article class="app-panel mt-4 overflow-hidden">
            <div class="border-b border-white/[0.06] px-5 py-3.5">
                <h2 class="font-display text-sm font-semibold text-white">Your standing</h2>
                <p class="text-[11px] text-muted">Calculated from the same ledger as the table above.</p>
            </div>
            <div class="p-4">
                <?php
                $entry = $board['viewer_row'];
                $isViewer = true;
                require __DIR__ . '/../../partials/leaderboard-row.php';
                ?>
            </div>
        </article>
    <?php endif; ?>

    <p class="mt-4 flex items-start gap-2 text-[11px] leading-relaxed text-muted-dim">
        <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
        Weekly and monthly boards use UTC boundaries; ties are broken by lifetime XP and then by account age.
        Timestamps are shown in <?= e(str_replace('_', ' ', $timezone)) ?>.
    </p>
</section>
