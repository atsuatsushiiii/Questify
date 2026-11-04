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
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 transition hover:text-gold-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">Achievement definitions</h1>
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Catalogue</h2>
                <p class="text-[11px] text-slate-500"><?= e((string) count($achievements)) ?> definitions · <?= e((string) count($categories)) ?> categories · <?= e((string) count($criteriaTypes)) ?> criteria types</p>
            </div>
        </div>

        <?php if ($achievements === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-white/[0.07] bg-white/[0.03] text-slate-500">
                    <i data-lucide="award" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No achievement definitions</p>
                <p class="mt-1.5 text-xs text-slate-500">Create the first badge for adventurers to unlock.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/[0.06] text-[9px] uppercase tracking-[0.14em] text-slate-600">
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
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gold-400/20 bg-gold-400/[0.08] text-gold-300">
                                            <i data-lucide="<?= e((string) $achievement['icon']) ?>" class="h-4 w-4"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $achievement['name']) ?></p>
                                            <p class="truncate text-[10px] text-slate-600"><span class="font-mono"><?= e((string) $achievement['achievement_key']) ?></span> · <?= e((string) $achievement['description']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-400"><?= e($categories[(string) $achievement['category']] ?? (string) $achievement['category']) ?></td>
                                <td class="px-4 py-3.5 text-slate-400">
                                    <?= e($criteriaLabels[(string) $achievement['criteria_type']] ?? (string) $achievement['criteria_type']) ?>
                                    <span class="text-slate-600">≥ <?= e((string) $achievement['threshold']) ?></span>
                                </td>
                                <td class="px-4 py-3.5 tabular-nums text-slate-400"><?= e((string) $unlocks) ?></td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider <?= (int) $achievement['is_active'] === 1
                                        ? 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-300'
                                        : 'border-white/10 bg-white/[0.03] text-slate-400' ?>">
                                        <span class="h-1.5 w-1.5 rounded-full <?= (int) $achievement['is_active'] === 1 ? 'bg-emerald-400' : 'bg-slate-500' ?>"></span>
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
                                                <button type="submit" class="btn px-2.5 py-1.5 text-[11px] text-rose-300 hover:bg-rose-500/10 hover:text-rose-200">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="px-2 text-[10px] text-slate-600" title="Unlocked achievements must be deactivated instead of deleted.">Locked</span>
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
