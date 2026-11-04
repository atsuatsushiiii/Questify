<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One achievement card.
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
<article class="relative overflow-hidden rounded-2xl border p-5 transition-all duration-300 <?= $unlocked
    ? 'border-gold-400/25 bg-gradient-to-br from-gold-400/[0.09] via-white/[0.025] to-transparent shadow-[0_24px_60px_-40px_rgba(250,204,21,0.5)]'
    : 'border-white/[0.07] bg-white/[0.02] hover:border-gold-400/20 hover:bg-white/[0.03]' ?>">
    <div class="flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border <?= $unlocked
            ? 'border-gold-400/30 bg-gold-400/15 text-gold-300'
            : 'border-white/10 bg-white/[0.03] text-slate-500' ?>">
            <i data-lucide="<?= e((string) ($achievement['icon'] ?? 'trophy')) ?>" class="h-5 w-5"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[9px] font-semibold uppercase tracking-[0.16em] <?= $unlocked ? 'text-gold-300' : 'text-slate-600' ?>">
                <?= e(ucfirst((string) ($achievement['category'] ?? 'milestone'))) ?>
            </p>
            <h3 class="mt-1 font-display text-sm font-semibold text-white"><?= e((string) $achievement['name']) ?></h3>
        </div>
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg <?= $unlocked
            ? 'bg-gold-400/15 text-gold-300'
            : 'bg-white/[0.03] text-slate-600' ?>" aria-hidden="true">
            <i data-lucide="<?= $unlocked ? 'badge-check' : 'lock' ?>" class="h-3.5 w-3.5"></i>
        </span>
    </div>

    <p class="mt-3 text-[11px] leading-relaxed text-slate-500"><?= e((string) $achievement['description']) ?></p>

    <div class="mt-4">
        <div class="flex items-center justify-between gap-2 text-[10px] font-medium">
            <span class="text-slate-500"><?= e((string) $current) ?> / <?= e((string) $threshold) ?></span>
            <span class="<?= $unlocked ? 'text-gold-300' : 'text-slate-500' ?>"><?= e((string) $percent) ?>%</span>
        </div>
        <div
            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/[0.06]"
            role="progressbar"
            aria-label="<?= e((string) $achievement['name']) ?> progress"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="<?= e((string) $percent) ?>"
        >
            <div
                class="h-full rounded-full <?= $unlocked
                    ? 'bg-gradient-to-r from-gold-300 to-gold-400'
                    : 'bg-gradient-to-r from-ember-500 to-gold-400' ?>"
                style="width: <?= e((string) $percent) ?>%"
            ></div>
        </div>
    </div>

    <p class="mt-3 flex items-center gap-1.5 text-[10px] <?= $unlocked ? 'text-gold-300/90' : 'text-slate-600' ?>">
        <i data-lucide="<?= $unlocked ? 'calendar-check' : 'lock' ?>" class="h-3 w-3"></i>
        <?= $unlocked && $unlockedAt !== null
            ? 'Unlocked ' . e($unlockedAt)
            : 'Locked — ' . e((string) ((int) ($achievement['remaining'] ?? 0))) . ' to go' ?>
    </p>
</article>
