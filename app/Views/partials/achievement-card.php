<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One achievement card, styled as a stamped seal on a ledger entry.
 *
 * @var array<string, mixed> $achievement
 * @var string $timezone
 */
$unlocked = (bool) ($achievement['unlocked'] ?? false);
$percent = (int) ($achievement['percent'] ?? 0);
$threshold = max(1, (int) ($achievement['threshold'] ?? 1));
$current = (int) ($achievement['current'] ?? 0);
$unlockedAt = isset($achievement['unlocked_at'])
    ? format_datetime((string) $achievement['unlocked_at'], $timezone, 'j M Y')
    : null;
?>
<article class="relative overflow-hidden rounded-sm border p-5 transition-colors duration-200 <?= $unlocked
    ? 'border-brand-400/40 bg-brand-400/[0.05]'
    : 'border-line bg-panel/30 hover:border-brand-400/25 hover:bg-panel/50' ?>">
    <div class="flex items-start gap-4">
        <span class="ledger-seal h-14 w-14 shrink-0 <?= $unlocked
            ? 'border-brand-400/55 bg-brand-400/[0.06] text-brand-300'
            : 'border-line border-dashed text-muted' ?>">
            <i data-lucide="<?= e((string) ($achievement['icon'] ?? 'trophy')) ?>" class="h-5 w-5"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-mono text-[8px] font-medium uppercase tracking-[0.18em] <?= $unlocked ? 'text-brand-300' : 'text-muted-dim' ?>">
                <?= e(ucfirst((string) ($achievement['category'] ?? 'milestone'))) ?>
            </p>
            <h3 class="mt-1 font-display text-base font-semibold leading-snug text-white"><?= e((string) $achievement['name']) ?></h3>
            <p class="mt-1.5 text-[11px] leading-relaxed text-muted"><?= e((string) $achievement['description']) ?></p>
        </div>
        <span class="shrink-0 rotate-3 rounded-sm border px-2 py-1 font-mono text-[8px] font-semibold uppercase tracking-[0.14em] <?= $unlocked
            ? 'border-brand-400/45 text-brand-300'
            : 'border-line text-muted-dim' ?>">
            <?= $unlocked ? 'Earned' : 'Locked' ?>
        </span>
    </div>

    <div class="mt-4">
        <div class="flex items-center justify-between gap-2 font-mono text-[9px] uppercase tracking-[0.12em]">
            <span class="text-muted"><?= e((string) $current) ?> / <?= e((string) $threshold) ?></span>
            <span class="<?= $unlocked ? 'text-brand-300' : 'text-muted' ?>"><?= e((string) $percent) ?>%</span>
        </div>
        <div
            class="mt-1.5 h-1 overflow-hidden bg-white/[0.06]"
            role="progressbar"
            aria-label="<?= e((string) $achievement['name']) ?> progress"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="<?= e((string) $percent) ?>"
        >
            <div
                class="h-full <?= $unlocked ? 'bg-brand-400' : 'bg-ember-500' ?>"
                style="width: <?= e((string) $percent) ?>%"
            ></div>
        </div>
    </div>

    <p class="mt-3 flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.1em] <?= $unlocked ? 'text-brand-300/90' : 'text-muted-dim' ?>">
        <i data-lucide="<?= $unlocked ? 'calendar-check' : 'lock' ?>" class="h-3 w-3"></i>
        <?= $unlocked && $unlockedAt !== null
            ? 'Unlocked ' . e($unlockedAt)
            : 'Locked — ' . e((string) ((int) ($achievement['remaining'] ?? 0))) . ' to go' ?>
    </p>
</article>
