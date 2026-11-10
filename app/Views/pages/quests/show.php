<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Quest detail.
 *
 * @var array<string, mixed> $user
 * @var array<string, mixed> $quest
 * @var array<string, mixed>|null $award
 */
use App\Support\Recurrence;

$timezone = (string) ($user['timezone'] ?? 'UTC');
$isRestricted = account_restricted($user);
$questId = (int) $quest['id'];
$status = (string) $quest['status'];
$difficulty = (string) $quest['difficulty'];
$reward = (int) $quest['xp_reward'];
$recurrence = (string) ($quest['recurrence'] ?? Recurrence::NONE);
$isRecurring = Recurrence::isRecurring($recurrence);
$completedAt = isset($quest['completed_at']) ? (string) $quest['completed_at'] : null;
$isCompleted = $status === 'completed';
$isArchived = $status === 'archived';
$doneThisPeriod = Recurrence::isDoneForCurrentPeriod(
    $recurrence,
    isset($quest['last_completed_period']) ? (string) $quest['last_completed_period'] : null,
    $timezone,
);

$difficultyMeta = match ($difficulty) {
    'medium' => ['label' => 'Medium', 'icon' => 'target', 'classes' => 'border-brand-400/30 bg-brand-400/[0.08] text-brand-300'],
    'hard' => ['label' => 'Hard', 'icon' => 'swords', 'classes' => 'border-ember-400/35 bg-ember-500/[0.10] text-ember-300'],
    'epic' => ['label' => 'Epic', 'icon' => 'crown', 'classes' => 'border-brand-400/45 bg-brand-400/[0.14] text-brand-100'],
    default => ['label' => 'Easy', 'icon' => 'sparkles', 'classes' => 'border-slate-400/30 bg-slate-400/[0.07] text-slate-300'],
};

$statusMeta = match ($status) {
    'completed' => ['label' => 'Completed', 'icon' => 'circle-check', 'classes' => 'border-aqua-400/35 bg-aqua-500/[0.08] text-aqua-300'],
    'archived' => ['label' => 'Archived', 'icon' => 'archive', 'classes' => 'border-line bg-white/[0.03] text-slate-400'],
    default => ['label' => 'Active', 'icon' => 'target', 'classes' => 'border-ember-400/35 bg-ember-500/[0.08] text-ember-300'],
};

if ($isRecurring && $status === 'active' && $doneThisPeriod) {
    $statusMeta = ['label' => Recurrence::doneLabel($recurrence), 'icon' => 'circle-check', 'classes' => 'border-aqua-400/35 bg-aqua-500/[0.08] text-aqua-300'];
}

$createdAt = format_datetime((string) ($quest['created_at'] ?? ''), $timezone, 'j M Y, H:i');
$updatedAt = format_datetime((string) ($quest['updated_at'] ?? ''), $timezone, 'j M Y, H:i');
$completedLabel = format_datetime($completedAt, $timezone, 'j M Y, H:i');
$awardedLabel = $award !== null
    ? format_datetime((string) ($award['created_at'] ?? ''), $timezone, 'j M Y, H:i')
    : null;
?>
<section class="mx-auto max-w-5xl" data-reveal>
    <a href="<?= e(url('/quests')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        Back to quest log
    </a>

    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.14em] <?= e($statusMeta['classes']) ?>">
                    <i data-lucide="<?= e($statusMeta['icon']) ?>" class="h-3 w-3"></i>
                    <?= e($statusMeta['label']) ?>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.14em] <?= e($difficultyMeta['classes']) ?>">
                    <i data-lucide="<?= e($difficultyMeta['icon']) ?>" class="h-3 w-3"></i>
                    <?= e($difficultyMeta['label']) ?>
                </span>
                <?php if ($isRecurring): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border border-aqua-400/30 bg-aqua-500/[0.08] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.14em] text-aqua-300">
                        <i data-lucide="repeat" class="h-3 w-3"></i>
                        <?= e(Recurrence::label($recurrence)) ?>
                    </span>
                <?php endif; ?>
                <?php if (($quest['category_name'] ?? null) !== null): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.14em] text-slate-300">
                        <span class="h-1.5 w-1.5 rounded-sm" style="background-color: <?= e((string) $quest['category_color']) ?>"></span>
                        <i data-lucide="<?= e((string) $quest['category_icon']) ?>" class="h-3 w-3"></i>
                        <?= e((string) $quest['category_name']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="mt-4 break-words font-display text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl">
                <?= e((string) $quest['title']) ?>
            </h1>
            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="clock" class="h-3 w-3"></i>
                    Created <?= e($createdAt ?? 'just now') ?>
                </span>
                <?php if ($completedLabel !== null): ?>
                    <span class="inline-flex items-center gap-1.5 text-aqua-300">
                        <i data-lucide="circle-check" class="h-3 w-3"></i>
                        <?= $isRecurring ? 'Last completed' : 'Completed' ?> <?= e($completedLabel) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <?php if ($status === 'active' && !$isRestricted): ?>
                <a href="<?= e(url('/quests/' . $questId . '/edit')) ?>" class="btn btn-app-ghost">
                    <i data-lucide="pencil" class="h-4 w-4"></i>
                    Edit
                </a>
            <?php elseif ($isRestricted): ?>
                <span class="inline-flex items-center gap-2 rounded-sm border border-line bg-white/[0.02] px-3 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <i data-lucide="eye-off" class="h-3.5 w-3.5"></i>
                    Read-only
                </span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mx-auto mt-6 grid max-w-5xl gap-4 xl:grid-cols-[1.5fr_1fr]" data-reveal>
    <article class="app-panel overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                <i data-lucide="scroll-text" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Quest briefing</h2>
                <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">The definition of done you wrote for yourself</p>
            </div>
        </div>

        <div class="px-5 py-5">
            <p class="whitespace-pre-line break-words text-sm leading-relaxed text-slate-300"><?= e((string) $quest['description']) ?></p>
        </div>

        <div class="grid grid-cols-2 gap-3 border-t border-line px-5 py-4 sm:grid-cols-5">
            <div>
                <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Reward</p>
                <p class="mt-1 inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-brand-300">
                    <i data-lucide="zap" class="h-3.5 w-3.5"></i>
                    <?= e((string) $reward) ?> XP
                </p>
            </div>
            <div>
                <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Difficulty</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e($difficultyMeta['label']) ?></p>
            </div>
            <div>
                <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Repeats</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e(Recurrence::label($recurrence)) ?></p>
            </div>
            <div>
                <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Category</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e((string) ($quest['category_name'] ?? 'Uncategorised')) ?></p>
            </div>
            <div>
                <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Last updated</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e($updatedAt ?? 'just now') ?></p>
            </div>
        </div>
    </article>

    <aside class="flex flex-col gap-4">
        <?php if ($status === 'active' && $doneThisPeriod): ?>
            <div class="rounded-sm border border-aqua-400/30 bg-aqua-500/[0.06] p-5">
                <div class="flex items-center gap-2 text-aqua-300">
                    <i data-lucide="circle-check" class="h-4 w-4"></i>
                    <p class="font-mono text-[9px] font-semibold uppercase tracking-[0.16em]"><?= e(Recurrence::doneLabel($recurrence)) ?></p>
                </div>
                <p class="mt-3 font-display text-4xl font-semibold leading-none text-white">
                    +<?= e((string) ($award['amount'] ?? $reward)) ?> <span class="text-sm font-medium text-aqua-300">XP</span>
                </p>
                <?php if ($awardedLabel !== null): ?>
                    <p class="mt-2 font-mono text-[9px] uppercase tracking-[0.08em] text-muted">Awarded <?= e($awardedLabel) ?></p>
                <?php endif; ?>
                <p class="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-muted">
                    <i data-lucide="repeat" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-aqua-400/80"></i>
                    <?= e(Recurrence::resetLabel($recurrence)) ?> The quest stays active and pays again in the next period.
                </p>
            </div>
        <?php elseif ($status === 'active'): ?>
            <div class="relative overflow-hidden rounded-sm border border-brand-400/30 bg-brand-400/[0.05] p-5">
                <div class="pointer-events-none absolute inset-0 bg-grid opacity-40" aria-hidden="true"></div>
                <div class="relative">
                    <p class="app-stat-label">Ready to claim?</p>
                    <p class="mt-2 font-display text-4xl font-semibold leading-none text-white">
                        +<?= e((string) $reward) ?> <span class="text-sm font-medium text-brand-300">XP</span>
                    </p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        <?php if ($isRecurring): ?>
                            Completing this <?= e(strtolower(Recurrence::label($recurrence))) ?> quest awards XP once per <?= e(Recurrence::periodNoun($recurrence)) ?>. <?= e(Recurrence::resetLabel($recurrence)) ?>
                        <?php else: ?>
                            Completing this quest awards XP exactly once and recalculates your level and rank.
                        <?php endif; ?>
                    </p>
                    <?php if ($isRestricted): ?>
                        <p class="mt-4 inline-flex items-center gap-2 rounded-sm border border-line bg-white/[0.02] px-3 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted">
                            <i data-lucide="eye-off" class="h-3.5 w-3.5"></i>
                            Completion is disabled
                        </p>
                    <?php else: ?>
                        <form method="POST" action="<?= e(url('/quests/' . $questId . '/complete')) ?>" class="mt-4" data-quest-action>
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-app-primary w-full" data-loading-label="Claiming…">
                                <i data-lucide="check" class="h-4 w-4"></i>
                                Complete quest
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($isCompleted): ?>
            <div class="rounded-sm border border-aqua-400/30 bg-aqua-500/[0.06] p-5">
                <div class="flex items-center gap-2 text-aqua-300">
                    <i data-lucide="circle-check" class="h-4 w-4"></i>
                    <p class="font-mono text-[9px] font-semibold uppercase tracking-[0.16em]">Reward claimed</p>
                </div>
                <p class="mt-3 font-display text-4xl font-semibold leading-none text-white">
                    +<?= e((string) ($award['amount'] ?? $reward)) ?> <span class="text-sm font-medium text-aqua-300">XP</span>
                </p>
                <?php if ($awardedLabel !== null): ?>
                    <p class="mt-2 font-mono text-[9px] uppercase tracking-[0.08em] text-muted">Awarded <?= e($awardedLabel) ?></p>
                <?php endif; ?>
                <?php if ($award !== null && (int) $award['level_after'] > (int) $award['level_before']): ?>
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-sm border border-brand-400/30 bg-brand-400/[0.08] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.1em] text-brand-300">
                        <i data-lucide="trending-up" class="h-3 w-3"></i>
                        Level <?= e((string) $award['level_before']) ?> → <?= e((string) $award['level_after']) ?>
                    </p>
                <?php endif; ?>
                <p class="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-muted">
                    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-aqua-400/80"></i>
                    This quest has already paid out. Completing it again cannot add XP.
                </p>
            </div>
        <?php else: ?>
            <div class="rounded-sm border border-line bg-panel/30 p-5">
                <p class="app-stat-label">Archived</p>
                <p class="mt-2 text-sm leading-relaxed text-slate-400">
                    This quest is parked outside your active log. Restore it to make it active again.
                </p>
            </div>
        <?php endif; ?>

        <div class="app-panel p-5">
            <p class="app-stat-label">Manage quest</p>
            <div class="mt-3 space-y-2">
                <?php if ($isRestricted): ?>
                    <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-3 py-4 text-[11px] leading-relaxed text-muted">
                        This account is read-only. Editing, completing, archiving, restoring, and deleting are disabled
                        while a moderator reviews it.
                    </p>
                <?php else: ?>
                <?php if ($status !== 'archived'): ?>
                    <form
                        method="POST"
                        action="<?= e(url('/quests/' . $questId . '/archive')) ?>"
                        data-quest-action
                        data-confirm="<?= $isCompleted ? 'Archive this completed quest? Its XP stays on your account.' : 'Archive this quest? You can restore it later.' ?>"
                    >
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-app-ghost w-full justify-start" data-loading-label="Archiving…">
                            <i data-lucide="archive" class="h-4 w-4"></i>
                            Archive quest
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e(url('/quests/' . $questId . '/restore')) ?>" data-quest-action>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-app-ghost w-full justify-start" data-loading-label="Restoring…">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                            Restore quest
                        </button>
                    </form>

                    <?php if ($completedAt === null): ?>
                        <form
                            method="POST"
                            action="<?= e(url('/quests/' . $questId . '/delete')) ?>"
                            data-quest-action
                            data-confirm="Delete this quest permanently? This cannot be undone."
                        >
                            <?= csrf_field() ?>
                            <button type="submit" class="btn w-full justify-start border border-ember-400/30 bg-ember-500/[0.05] text-ember-200 hover:border-ember-400/45 hover:bg-ember-500/[0.10]" data-loading-label="Deleting…">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                                Delete permanently
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <p class="mt-4 border-t border-line pt-3 font-mono text-[9px] leading-relaxed tracking-[0.04em] text-muted-dim">
                <?= $isCompleted
                    ? 'Completed quests keep their XP ledger entry and cannot be deleted.'
                    : 'Only quests that have never paid XP can be deleted permanently.' ?>
            </p>
        </div>
    </aside>
</section>
