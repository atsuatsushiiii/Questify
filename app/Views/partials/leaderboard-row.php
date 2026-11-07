<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One leaderboard row. Mobile shows position, player and XP; larger screens add
 * level and rank title columns.
 *
 * @var array<string, mixed> $entry
 * @var bool $isViewer
 * @var string $metricLabel
 */
$position = (int) ($entry['rank_position'] ?? 0);
$displayName = (string) ($entry['display_name'] ?? $entry['username'] ?? 'Adventurer');
$username = (string) ($entry['username'] ?? '');
$level = (int) ($entry['level'] ?? 1);
$xp = (int) ($entry['period_xp'] ?? $entry['xp_total'] ?? 0);
$rankTitle = (string) ($entry['rank_title'] ?? '');
$isViewer = $isViewer ?? false;
$metricLabel = $metricLabel ?? 'XP';
$profileUrl = (int) ($entry['show_profile'] ?? 0) === 1 && $username !== ''
    ? url('/u/' . rawurlencode($username))
    : null;
$badgeClasses = match (true) {
    $position === 1 => 'border-brand-400/45 bg-brand-400/15 text-brand-200',
    $position === 2 => 'border-slate-400/35 bg-slate-400/10 text-slate-200',
    $position === 3 => 'border-ember-400/40 bg-ember-500/10 text-ember-200',
    default => 'border-line bg-white/[0.03] text-muted',
};
?>
<div class="grid grid-cols-[2.25rem_minmax(0,1fr)_auto] items-center gap-3 rounded-sm border px-3 py-3 transition sm:grid-cols-[3rem_minmax(0,1fr)_4.5rem_6rem_6.5rem] <?= $isViewer
    ? 'border-brand-400/40 border-l-2 border-l-brand-400 bg-brand-400/[0.05]'
    : 'border-line bg-panel/30 hover:border-brand-400/30 hover:bg-panel/60' ?>">
    <span class="grid h-8 w-8 place-items-center rounded-sm border font-mono text-[11px] font-bold tabular-nums <?= e($badgeClasses) ?>">
        <?= e((string) $position) ?>
    </span>

    <div class="flex min-w-0 items-center gap-2.5">
        <?php
        $avatarPath = isset($entry['avatar_path']) ? (string) $entry['avatar_path'] : null;
        $name = $displayName;
        $size = 'sm';
        ?>
        <?php if ($profileUrl !== null): ?>
            <a href="<?= e($profileUrl) ?>" class="shrink-0 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300/70" aria-label="View <?= e($displayName) ?>'s public profile">
                <?php require __DIR__ . '/avatar.php'; ?>
            </a>
        <?php else: ?>
            <?php require __DIR__ . '/avatar.php'; ?>
        <?php endif; ?>
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-slate-200">
                <?php if ($profileUrl !== null): ?>
                    <a href="<?= e($profileUrl) ?>" class="truncate underline-offset-2 hover:text-white hover:underline"><?= e($displayName) ?></a>
                <?php else: ?>
                    <span class="truncate"><?= e($displayName) ?></span>
                <?php endif; ?>
                <?php if ($isViewer): ?>
                    <span class="rounded-sm border border-brand-400/35 bg-brand-400/10 px-1 py-0.5 font-mono text-[8px] font-bold uppercase tracking-[0.12em] text-brand-300">You</span>
                <?php endif; ?>
            </p>
            <?php if ($username !== ''): ?>
                <p class="truncate font-mono text-[9px] text-muted-dim">
                    <?php if ($profileUrl !== null): ?>
                        <a href="<?= e($profileUrl) ?>" class="underline-offset-2 hover:text-brand-300 hover:underline">@<?= e($username) ?></a>
                    <?php else: ?>
                        @<?= e($username) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="hidden text-center sm:block">
        <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">Level</p>
        <p class="font-mono text-xs font-semibold text-slate-300"><?= e((string) $level) ?></p>
    </div>

    <div class="hidden text-center sm:block">
        <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">Rank</p>
        <p class="truncate text-xs font-semibold text-slate-300"><?= e($rankTitle) ?></p>
    </div>

    <div class="text-right">
        <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim"><?= e($metricLabel) ?></p>
        <p class="font-mono text-xs font-bold tabular-nums text-slate-200"><?= e(number_format($xp)) ?></p>
        <p class="font-mono text-[9px] text-muted-dim sm:hidden">Lv <?= e((string) $level) ?></p>
    </div>
</div>
