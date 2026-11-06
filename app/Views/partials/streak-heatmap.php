<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * 12-week streak heatmap.
 *
 * @var list<list<array{date: string, label: string, active: bool, today: bool, future: bool}>> $heatmap
 * @var int $current
 * @var int $longest
 */
$activeDays = 0;

foreach ($heatmap as $week) {
    foreach ($week as $day) {
        if ($day['active']) {
            $activeDays++;
        }
    }
}
?>
<div>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Streak history</p>
            <p class="mt-1 font-display text-sm font-semibold text-white">
                <?= e((string) $activeDays) ?> active <?= $activeDays === 1 ? 'day' : 'days' ?> in 12 weeks
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-gold-300">
            <i data-lucide="flame" class="h-3 w-3"></i>
            <?= e((string) $current) ?> day streak
        </span>
    </div>

    <div
        class="mt-4 flex gap-1 overflow-hidden"
        role="group"
        aria-label="Daily quest completion history for the last 12 weeks"
    >
        <?php foreach ($heatmap as $week): ?>
            <div class="flex flex-col gap-1">
                <?php foreach ($week as $day): ?>
                    <span
                        class="h-2.5 w-2.5 rounded-[3px] <?= $day['future']
                            ? 'bg-white/[0.02]'
                            : ($day['active']
                                ? 'bg-gradient-to-br from-ember-400 to-gold-400'
                                : 'bg-white/[0.07]') ?> <?= $day['today'] ? 'ring-1 ring-gold-300/80' : '' ?>"
                        title="<?= e($day['label']) ?><?= $day['active'] ? ' — quest completed' : ' — no quest' ?>"
                        aria-label="<?= e($day['label']) ?><?= $day['active'] ? ', quest completed' : ', no quest' ?>"
                        role="img"
                    ></span>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[10px] text-muted-dim">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-[3px] bg-white/[0.07]"></span>
            No quest
            <span class="ml-2 h-2.5 w-2.5 rounded-[3px] bg-gradient-to-br from-ember-400 to-gold-400"></span>
            Quest completed
        </span>
        <span>Longest <?= e((string) $longest) ?> <?= $longest === 1 ? 'day' : 'days' ?></span>
    </div>
</div>
