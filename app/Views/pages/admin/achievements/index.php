<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin achievement catalogue.
 *
 * @var list<array<string, mixed>> $achievements
 * @var array<string, string> $categories
 * @var list<string> $criteriaTypes
 */
$criteriaLabels = [
    'quests_completed' => 'Quests completed',
    'xp_total' => 'Total XP',
    'level' => 'Level',
    'epic_quests' => 'Epic quests completed',
    'longest_streak' => 'Longest streak',
];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Achievement definitions</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Definitions are evaluated server-side after every quest completion. Only criteria types the engine
            understands can be saved, so a new badge can never silently fail.
        </p>
    </div>

    <a href="<?= e(url('/admin/achievements/create')) ?>" class="btn btn-app-primary">
        <i data-lucide="plus" class="h-4 w-4"></i>
        New achievement
    </a>
</section>

<section class="mt-6">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Catalogue</h2>
                <p class="text-[11px] text-muted"><?= e((string) count($achievements)) ?> definitions · <?= e((string) count($categories)) ?> categories · <?= e((string) count($criteriaTypes)) ?> criteria types</p>
            </div>
        </div>

        <?php if ($achievements === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                    <i data-lucide="award" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No achievement definitions</p>
                <p class="mt-1.5 text-xs text-muted">Create the first badge for adventurers to unlock.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-line font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Achievement</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Category</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Criteria</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Unlocks</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($achievements as $achievement): ?>
                            <?php $unlocks = (int) $achievement['unlock_count']; ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/[0.08] text-brand-300">
                                            <i data-lucide="<?= e((string) $achievement['icon']) ?>" class="h-4 w-4"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $achievement['name']) ?></p>
                                            <p class="truncate text-[10px] text-muted-dim"><span class="font-mono"><?= e((string) $achievement['achievement_key']) ?></span> · <?= e((string) $achievement['description']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-400"><?= e($categories[(string) $achievement['category']] ?? (string) $achievement['category']) ?></td>
                                <td class="px-4 py-3.5 text-slate-400">
                                    <?= e($criteriaLabels[(string) $achievement['criteria_type']] ?? (string) $achievement['criteria_type']) ?>
                                    <span class="text-muted-dim">≥ <?= e((string) $achievement['threshold']) ?></span>
                                </td>
                                <td class="px-4 py-3.5 tabular-nums text-slate-400"><?= e((string) $unlocks) ?></td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= (int) $achievement['is_active'] === 1
                                        ? 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300'
                                        : 'border-line bg-white/[0.03] text-slate-400' ?>">
                                        <span class="h-1.5 w-1.5 rounded-sm <?= (int) $achievement['is_active'] === 1 ? 'bg-aqua-400' : 'bg-slate-500' ?>"></span>
                                        <?= (int) $achievement['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= e(url('/admin/achievements/' . (int) $achievement['id'] . '/edit')) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                            Edit
                                        </a>
                                        <?php if ($unlocks === 0): ?>
                                            <form method="POST" action="<?= e(url('/admin/achievements/' . (int) $achievement['id'] . '/delete')) ?>" data-confirm="Delete this achievement definition? This cannot be undone.">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn px-2.5 py-1.5 text-[11px] text-ember-300 hover:bg-ember-500/10 hover:text-ember-200">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="px-2 text-[10px] text-muted-dim" title="Unlocked achievements must be deactivated instead of deleted.">Locked</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
