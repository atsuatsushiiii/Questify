<?php
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
$badgeClasses = match (true) {
    $position === 1 => 'border-gold-400/35 bg-gold-400/15 text-gold-200',
    $position === 2 => 'border-slate-300/25 bg-slate-300/10 text-slate-200',
    $position === 3 => 'border-ember-400/30 bg-ember-500/10 text-ember-200',
    default => 'border-white/10 bg-white/[0.03] text-slate-500',
};
?>
<div class="grid grid-cols-[2.25rem_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border px-3 py-3 transition sm:grid-cols-[3rem_minmax(0,1fr)_4.5rem_6rem_6.5rem] <?= $isViewer
    ? 'border-gold-400/25 bg-gradient-to-r from-gold-400/[0.08] to-transparent'
    : 'border-white/[0.06] bg-white/[0.015] hover:border-gold-400/20 hover:bg-white/[0.03]' ?>">
    <span class="grid h-8 w-8 place-items-center rounded-lg border text-[11px] font-bold tabular-nums <?= e($badgeClasses) ?>">
        <?= e((string) $position) ?>
    </span>

    <div class="flex min-w-0 items-center gap-2.5">
        <?php
        $avatarPath = isset($entry['avatar_path']) ? (string) $entry['avatar_path'] : null;
        $name = $displayName;
        $size = 'sm';
        require __DIR__ . '/avatar.php';
        ?>
        <div class="min-w-0">
            <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-slate-200">
                <span class="truncate"><?= e($displayName) ?></span>
                <?php if ($isViewer): ?>
                    <span class="rounded border border-gold-400/20 bg-gold-400/[0.08] px-1 py-0.5 text-[8px] font-bold uppercase tracking-wider text-gold-300">You</span>
                <?php endif; ?>
            </p>
            <?php if ($username !== ''): ?>
                <p class="truncate text-[10px] text-slate-600">@<?= e($username) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="hidden text-center sm:block">
        <p class="text-[9px] uppercase tracking-wider text-slate-600">Level</p>
        <p class="text-xs font-semibold text-slate-300"><?= e((string) $level) ?></p>
    </div>

    <div class="hidden text-center sm:block">
        <p class="text-[9px] uppercase tracking-wider text-slate-600">Rank</p>
        <p class="truncate text-xs font-semibold text-slate-300"><?= e($rankTitle) ?></p>
    </div>

    <div class="text-right">
        <p class="text-[9px] uppercase tracking-wider text-slate-600"><?= e($metricLabel) ?></p>
        <p class="text-xs font-bold tabular-nums text-slate-200"><?= e(number_format($xp)) ?></p>
        <p class="text-[9px] text-slate-600 sm:hidden">Lv <?= e((string) $level) ?></p>
    </div>
</div>
