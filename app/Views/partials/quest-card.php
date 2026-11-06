<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One quest card. Inherits the list view's $user and $timezone.
 *
 * @var array<string, mixed> $quest
 * @var array<string, mixed> $user
 * @var string $timezone
 */
use App\Support\Recurrence;

$questId = (int) $quest['id'];
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
        'classes' => 'border-gold-400/25 bg-gold-400/[0.08] text-gold-300',
        'glow' => 'bg-gold-400/[0.10]',
    ],
    'hard' => [
        'label' => 'Hard',
        'icon' => 'swords',
        'classes' => 'border-ember-400/30 bg-ember-500/[0.10] text-ember-300',
        'glow' => 'bg-ember-500/[0.14]',
    ],
    'epic' => [
        'label' => 'Epic',
        'icon' => 'crown',
        'classes' => 'border-gold-400/35 bg-gradient-to-r from-ember-500/[0.14] to-gold-400/[0.12] text-gold-200',
        'glow' => 'bg-gold-400/[0.14]',
    ],
    default => [
        'label' => 'Easy',
        'icon' => 'sparkles',
        'classes' => 'border-slate-400/25 bg-slate-400/[0.07] text-slate-300',
        'glow' => 'bg-slate-400/[0.08]',
    ],
};

$statusMeta = match ($status) {
    'completed' => [
        'label' => 'Completed',
        'icon' => 'circle-check',
        'classes' => 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-300',
    ],
    'archived' => [
        'label' => 'Archived',
        'icon' => 'archive',
        'classes' => 'border-slate-400/20 bg-slate-400/[0.06] text-slate-400',
    ],
    default => [
        'label' => 'Active',
        'icon' => 'target',
        'classes' => 'border-ember-400/25 bg-ember-500/[0.08] text-ember-300',
    ],
};

if ($isRecurring && $status === 'active' && $doneThisPeriod) {
    $statusMeta = [
        'label' => Recurrence::doneLabel($recurrence),
        'icon' => 'circle-check',
        'classes' => 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-300',
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
    class="quest-card group relative flex flex-col overflow-hidden rounded-2xl border border-white/[0.07] bg-white/[0.025] p-5"
    data-quest-card
    data-difficulty="<?= e($difficulty) ?>"
>
    <div class="pointer-events-none absolute -right-10 -top-12 h-32 w-32 rounded-full <?= e($difficultyMeta['glow']) ?> blur-[55px]" aria-hidden="true"></div>

    <div class="relative flex items-start justify-between gap-3">
        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.14em] <?= e($statusMeta['classes']) ?>">
            <i data-lucide="<?= e($statusMeta['icon']) ?>" class="h-3 w-3"></i>
            <?= e($statusMeta['label']) ?>
        </span>

        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-2.5 py-1 text-[10px] font-bold text-gold-300">
            <i data-lucide="zap" class="h-3 w-3"></i>
            <?= $isCompleted || $doneThisPeriod ? 'Earned' : 'Reward' ?> <?= e((string) $reward) ?> XP
        </span>
    </div>

    <div class="relative mt-3 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider <?= e($difficultyMeta['classes']) ?>">
                <i data-lucide="<?= e($difficultyMeta['icon']) ?>" class="h-3 w-3"></i>
                <?= e($difficultyMeta['label']) ?>
            </span>

            <?php if ($isRecurring): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/20 bg-emerald-500/[0.06] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-300">
                    <i data-lucide="repeat" class="h-3 w-3"></i>
                    <?= e(Recurrence::label($recurrence)) ?>
                </span>
            <?php endif; ?>

            <?php if (($quest['category_name'] ?? null) !== null): ?>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.03] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-slate-300">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= e((string) $quest['category_color']) ?>"></span>
                    <i data-lucide="<?= e((string) $quest['category_icon']) ?>" class="h-3 w-3 text-slate-400"></i>
                    <?= e((string) $quest['category_name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <h2 class="mt-2.5 break-words font-display text-lg font-semibold leading-snug text-white">
            <a href="<?= e(url('/quests/' . $questId)) ?>" class="transition hover:text-gold-200 focus-visible:text-gold-200">
                <?= e((string) $quest['title']) ?>
            </a>
        </h2>

        <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-muted">
            <?= e((string) $quest['description']) ?>
        </p>
    </div>

    <div class="relative mt-4 border-t border-white/[0.06] pt-3">
        <?php if ($formattedDate !== null): ?>
            <p class="flex items-center gap-1.5 text-[10px] text-muted-dim">
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
            <p class="mt-1.5 flex items-center gap-1.5 text-[10px] <?= $doneThisPeriod ? 'text-emerald-400/80' : 'text-muted-dim' ?>">
                <i data-lucide="<?= $doneThisPeriod ? 'circle-check' : 'rotate-ccw' ?>" class="h-3 w-3"></i>
                <?= e($recurrenceCopy) ?>
            </p>
        <?php endif; ?>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="<?= e(url('/quests/' . $questId)) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                View
            </a>

            <?php if ($status === 'active' && $doneThisPeriod): ?>
                <button type="button" class="btn btn-app-ghost ml-auto cursor-default px-2.5 py-1.5 text-[11px] opacity-70" disabled>
                    <i data-lucide="circle-check" class="h-3.5 w-3.5"></i>
                    <?= e(Recurrence::doneLabel($recurrence)) ?>
                </button>
            <?php elseif ($status === 'active'): ?>
                <a href="<?= e(url('/quests/' . $questId . '/edit')) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                    Edit
                </a>
                <form method="POST" action="<?= e(url('/quests/' . $questId . '/complete')) ?>" data-quest-action class="ml-auto">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-app-primary px-2.5 py-1.5 text-[11px]" data-loading-label="Claiming…">
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
                    <button type="submit" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]" data-loading-label="Archiving…">
                        <i data-lucide="archive" class="h-3.5 w-3.5"></i>
                        Archive
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="<?= e(url('/quests/' . $questId . '/restore')) ?>" data-quest-action>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]" data-loading-label="Restoring…">
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
                        <button type="submit" class="btn px-2.5 py-1.5 text-[11px] text-rose-300 hover:bg-rose-500/10 hover:text-rose-200" data-loading-label="Deleting…">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            Delete
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</article>
