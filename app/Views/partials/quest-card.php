<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One quest card, styled as a ledger ticket.
 *
 * @var array<string, mixed> $quest
 * @var array<string, mixed> $user
 * @var string $timezone
 */
use App\Support\Recurrence;

$questId = (int) $quest['id'];
$isRestricted = account_restricted($user);
$status = (string) $quest['status'];
$difficulty = (string) $quest['difficulty'];
$reward = (int) $quest['xp_reward'];
$recurrence = (string) ($quest['recurrence'] ?? Recurrence::NONE);
$isRecurring = Recurrence::isRecurring($recurrence);
$isCompleted = $status === 'completed';
$isArchived = $status === 'archived';
$completedAt = isset($quest['completed_at']) ? (string) $quest['completed_at'] : null;
$doneThisPeriod = Recurrence::isDoneForCurrentPeriod(
    $recurrence,
    isset($quest['last_completed_period']) ? (string) $quest['last_completed_period'] : null,
    $timezone,
);
$recurrenceCopy = $doneThisPeriod
    ? Recurrence::doneLabel($recurrence) . ' — ' . Recurrence::resetLabel($recurrence)
    : 'Repeats ' . strtolower(Recurrence::options()[$recurrence] ?? 'once') . '.';

$difficultyMeta = match ($difficulty) {
    'medium' => [
        'label' => 'Medium',
        'icon' => 'target',
        'classes' => 'border-brand-400/30 bg-brand-400/[0.08] text-brand-300',
    ],
    'hard' => [
        'label' => 'Hard',
        'icon' => 'swords',
        'classes' => 'border-ember-400/35 bg-ember-500/[0.10] text-ember-300',
    ],
    'epic' => [
        'label' => 'Epic',
        'icon' => 'crown',
        'classes' => 'border-brand-400/45 bg-brand-400/[0.14] text-brand-100',
    ],
    default => [
        'label' => 'Easy',
        'icon' => 'sparkles',
        'classes' => 'border-slate-400/30 bg-slate-400/[0.07] text-slate-300',
    ],
};

$statusMeta = match ($status) {
    'completed' => [
        'label' => 'Completed',
        'icon' => 'circle-check',
        'classes' => 'border-aqua-400/35 bg-aqua-500/[0.08] text-aqua-300',
    ],
    'archived' => [
        'label' => 'Archived',
        'icon' => 'archive',
        'classes' => 'border-line bg-white/[0.03] text-slate-400',
    ],
    default => [
        'label' => 'Active',
        'icon' => 'target',
        'classes' => 'border-ember-400/35 bg-ember-500/[0.08] text-ember-300',
    ],
};

if ($isRecurring && $status === 'active' && $doneThisPeriod) {
    $statusMeta = [
        'label' => Recurrence::doneLabel($recurrence),
        'icon' => 'circle-check',
        'classes' => 'border-aqua-400/35 bg-aqua-500/[0.08] text-aqua-300',
    ];
}

$dateLabel = $isCompleted
    ? 'Completed'
    : ($isArchived
        ? 'Updated'
        : ($isRecurring && $completedAt !== null ? 'Last completed' : 'Created'));
$dateValue = $isCompleted
    ? $completedAt
    : ($isArchived
        ? (string) ($quest['updated_at'] ?? null)
        : ($isRecurring && $completedAt !== null ? $completedAt : (string) ($quest['created_at'] ?? null)));
$formattedDate = format_datetime($dateValue, $timezone);
$isoDate = iso_datetime($dateValue);
?>
<article
    class="ledger-ticket group relative flex flex-col overflow-hidden rounded-sm p-5 transition-colors hover:border-brand-400/45"
    data-quest-card
    data-difficulty="<?= e($difficulty) ?>"
>
    <div class="relative flex items-start justify-between gap-3">
        <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-1 font-mono text-[8px] font-medium uppercase tracking-[0.16em] <?= e($statusMeta['classes']) ?>">
            <i data-lucide="<?= e($statusMeta['icon']) ?>" class="h-3 w-3"></i>
            <?= e($statusMeta['label']) ?>
        </span>

        <span class="text-right">
            <span class="block font-mono text-[8px] uppercase tracking-[0.16em] text-muted-dim">Ticket no. <?= e((string) $questId) ?></span>
            <span class="mt-0.5 inline-flex items-center gap-1.5 font-mono text-xs font-bold tabular-nums text-brand-300">
                <i data-lucide="zap" class="h-3 w-3"></i>
                <?= $isCompleted || $doneThisPeriod ? 'Earned' : 'Reward' ?> <?= e((string) $reward) ?> XP
            </span>
        </span>
    </div>

    <div class="relative mt-3 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.14em] <?= e($difficultyMeta['classes']) ?>">
                <i data-lucide="<?= e($difficultyMeta['icon']) ?>" class="h-3 w-3"></i>
                <?= e($difficultyMeta['label']) ?>
            </span>

            <?php if ($isRecurring): ?>
                <span class="inline-flex items-center gap-1.5 rounded-sm border border-aqua-400/30 bg-aqua-500/[0.06] px-2 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.14em] text-aqua-300">
                    <i data-lucide="repeat" class="h-3 w-3"></i>
                    <?= e(Recurrence::label($recurrence)) ?>
                </span>
            <?php endif; ?>

            <?php if (($quest['category_name'] ?? null) !== null): ?>
                <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.14em] text-slate-300">
                    <span class="h-1.5 w-1.5" style="background-color: <?= e((string) $quest['category_color']) ?>"></span>
                    <i data-lucide="<?= e((string) $quest['category_icon']) ?>" class="h-3 w-3 text-slate-400"></i>
                    <?= e((string) $quest['category_name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <h2 class="mt-3 break-words font-display text-lg font-semibold leading-snug text-white">
            <a href="<?= e(url('/quests/' . $questId)) ?>" class="transition hover:text-brand-200 focus-visible:text-brand-200">
                <?= e((string) $quest['title']) ?>
            </a>
        </h2>

        <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-muted">
            <?= e((string) $quest['description']) ?>
        </p>
    </div>

    <div class="relative mt-4 border-t border-dashed border-line pt-3">
        <?php if ($formattedDate !== null): ?>
            <p class="flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">
                <i data-lucide="clock" class="h-3 w-3"></i>
                <?= e($dateLabel) ?>
                <?php if ($isoDate !== null): ?>
                    <time datetime="<?= e($isoDate) ?>" title="<?= e($dateValue ?? '') ?>"><?= e($formattedDate) ?></time>
                <?php else: ?>
                    <span><?= e($formattedDate) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($isRecurring && $status === 'active'): ?>
            <p class="mt-1.5 flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.1em] <?= $doneThisPeriod ? 'text-aqua-300' : 'text-muted-dim' ?>">
                <i data-lucide="<?= $doneThisPeriod ? 'circle-check' : 'rotate-ccw' ?>" class="h-3 w-3"></i>
                <?= e($recurrenceCopy) ?>
            </p>
        <?php endif; ?>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="<?= e(url('/quests/' . $questId)) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[10px]">
                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                View
            </a>

            <?php if ($isRestricted): ?>
                <span class="ml-auto inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.02] px-2.5 py-1.5 font-mono text-[9px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <i data-lucide="eye-off" class="h-3.5 w-3.5"></i>
                    Read-only
                </span>
            <?php else: ?>
                <?php if ($status === 'active' && $doneThisPeriod): ?>
                    <button type="button" class="btn btn-app-ghost ml-auto cursor-default px-2.5 py-1.5 text-[10px] opacity-70" disabled>
                        <i data-lucide="circle-check" class="h-3.5 w-3.5"></i>
                        <?= e(Recurrence::doneLabel($recurrence)) ?>
                    </button>
                <?php elseif ($status === 'active'): ?>
                    <a href="<?= e(url('/quests/' . $questId . '/edit')) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[10px]">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                        Edit
                    </a>
                    <form method="POST" action="<?= e(url('/quests/' . $questId . '/complete')) ?>" data-quest-action class="ml-auto">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-app-primary px-2.5 py-1.5 text-[10px]" data-loading-label="Claiming…">
                            <i data-lucide="check" class="h-3.5 w-3.5"></i>
                            Complete
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($status !== 'archived'): ?>
                    <form
                        method="POST"
                        action="<?= e(url('/quests/' . $questId . '/archive')) ?>"
                        data-quest-action
                        data-confirm="<?= $isCompleted ? 'Archive this completed quest? Its XP stays on your account.' : 'Archive this quest? You can restore it later.' ?>"
                        class="<?= $status === 'completed' ? 'ml-auto' : '' ?>"
                    >
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-app-ghost px-2.5 py-1.5 text-[10px]" data-loading-label="Archiving…">
                            <i data-lucide="archive" class="h-3.5 w-3.5"></i>
                            Archive
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e(url('/quests/' . $questId . '/restore')) ?>" data-quest-action>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-app-ghost px-2.5 py-1.5 text-[10px]" data-loading-label="Restoring…">
                            <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                            Restore
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
                            <button type="submit" class="btn px-2.5 py-1.5 text-[10px] text-ember-300 hover:bg-ember-500/10 hover:text-ember-200" data-loading-label="Deleting…">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                Delete
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</article>
