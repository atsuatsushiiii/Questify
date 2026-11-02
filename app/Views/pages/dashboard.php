<?php
/**
 * Authenticated command center.
 *
 * Only values stored on the authenticated user are presented as data. Quest,
 * achievement and leaderboard panels are explicit empty states until their
 * phases ship.
 *
 * @var array<string, mixed> $user
 * @var array<string, mixed> $progression
 * @var int $level
 * @var float $progress
 * @var int $xpToNext
 * @var string $rank
 * @var int $streak
 * @var int $longestStreak
 * @var string $today
 * @var string|null $memberSince
 * @var string|null $lastLogin
 * @var int|null $completedQuests
 * @var int|null $activeQuests
 * @var array<int, mixed> $recentActivity
 * @var array<int, mixed> $achievementPreview
 * @var array<int, mixed> $leaderboardEntries
 */
$circumference = 2 * M_PI * 52;
$ringOffset = $circumference * (1 - ((float) $progress / 100));
$timezoneLabel = str_replace('_', ' ', (string) ($user['timezone'] ?? 'UTC'));
$nextRank = (string) ($progression['next_rank'] ?? 'Mythic');
$nextRankLevel = $progression['next_rank_level'] ?? null;
$atMaxLevel = $progression['next_level'] === null;
$leaderboardInitial = mb_strtoupper(mb_substr((string) $user['display_name'], 0, 1));
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <p class="inline-flex items-center gap-2 rounded-full border border-ember-400/25 bg-ember-500/[0.08] px-3.5 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-ember-200">
            <i data-lucide="compass" class="h-3.5 w-3.5"></i>
            Command center
        </p>
        <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
            Welcome back, <?= e((string) $user['display_name']) ?>.
        </h1>
        <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
            <span><?= e($today) ?></span>
            <span class="text-slate-700">&middot;</span>
            <span><?= e($timezoneLabel) ?></span>
            <span class="text-slate-700">&middot;</span>
            <span class="inline-flex items-center gap-1.5 text-slate-400">
                <i data-lucide="shield-check" class="h-3.5 w-3.5 text-emerald-400/80"></i>
                Session secure
            </span>
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a
            href="<?= e(url('/quests')) ?>"
            class="btn btn-app-primary group"
            data-quest-open
            aria-haspopup="dialog"
            aria-controls="quest-dialog"
        >
            <i data-lucide="plus" class="h-4 w-4 transition-transform duration-200 group-hover:rotate-90"></i>
            New quest
            <span class="rounded-full border border-black/20 bg-black/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white/80">Preview</span>
        </a>
        <a href="<?= e(url('/quests')) ?>" class="btn btn-app-ghost">
            <i data-lucide="scroll-text" class="h-4 w-4"></i>
            Quest log
        </a>
    </div>
</section>

<section class="mt-6 grid gap-4 xl:grid-cols-[1.55fr_1fr]" data-reveal>
    <article class="relative overflow-hidden rounded-3xl border border-white/[0.07] bg-gradient-to-br from-white/[0.04] via-white/[0.02] to-transparent p-5 sm:p-6">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-ember-500/[0.13] blur-[80px]"></div>
            <div class="absolute -bottom-24 left-1/4 h-48 w-48 rounded-full bg-gold-400/[0.07] blur-[80px]"></div>
        </div>

        <div class="relative">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Level progress</p>
                    <h2 class="mt-1 font-display text-lg font-semibold text-white">Adventurer rank</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gold-300">
                    <i data-lucide="crown" class="h-3 w-3"></i>
                    <?= e($rank) ?>
                </span>
            </div>

            <div class="mt-5 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                <div class="relative h-32 w-32 shrink-0">
                    <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="8"/>
                        <circle
                            cx="60"
                            cy="60"
                            r="52"
                            fill="none"
                            stroke="url(#app-level-gradient)"
                            stroke-width="8"
                            stroke-linecap="round"
                            stroke-dasharray="<?= e((string) $circumference) ?>"
                            stroke-dashoffset="<?= e((string) $ringOffset) ?>"
                            data-meter="ring"
                            data-value="<?= e((string) $progress) ?>"
                        />
                        <defs>
                            <linearGradient id="app-level-gradient" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#F87171"/>
                                <stop offset="55%" stop-color="#EF4444"/>
                                <stop offset="100%" stop-color="#FACC15"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center">
                        <div class="text-center">
                            <p class="font-display text-3xl font-bold leading-none text-white"><?= e($level) ?></p>
                            <p class="mt-1 text-[9px] font-semibold uppercase tracking-[0.18em] text-gold-300/80">Level</p>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-display text-xl font-semibold text-white">
                            <span data-counter="<?= e((string) $progression['xp']) ?>" data-counter-format="comma"><?= e(number_format((int) $progression['xp'])) ?></span>
                            <span class="text-sm font-medium text-slate-500">XP total</span>
                        </p>
                        <p class="text-[11px] text-slate-500"><?= $atMaxLevel ? 'Max level reached' : e((string) round((float) $progress)) . '% to level ' . e((string) ($level + 1)) ?></p>
                    </div>

                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/[0.06]">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-ember-500 via-ember-400 to-gold-400 shadow-[0_0_18px_-2px_rgba(239,68,68,0.6)]"
                            style="width: <?= e((string) $progress) ?>%"
                            data-meter="width"
                            data-value="<?= e((string) $progress) ?>"
                        ></div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-slate-500">
                        <?php if ($xpToNext > 0): ?>
                            <span class="inline-flex items-center gap-1.5">
                                <i data-lucide="zap" class="h-3 w-3 text-gold-400"></i>
                                <?= e(number_format($xpToNext)) ?> XP to next level
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 text-gold-300">
                                <i data-lucide="badge-check" class="h-3 w-3"></i>
                                Maximum level reached
                            </span>
                        <?php endif; ?>

                        <?php if ($nextRankLevel !== null): ?>
                            <span class="inline-flex items-center gap-1.5">
                                <i data-lucide="trending-up" class="h-3 w-3 text-ember-300"></i>
                                <?= e($nextRank) ?> rank at level <?= e($nextRankLevel) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-white/[0.06] pt-4 sm:grid-cols-3">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-slate-600">Member since</p>
                    <p class="mt-1 text-xs font-medium text-slate-300"><?= e($memberSince ?? 'Today') ?></p>
                </div>
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-slate-600">Last sign-in</p>
                    <p class="mt-1 text-xs font-medium text-slate-300"><?= e($lastLogin ?? 'This session') ?></p>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-slate-600">Timezone</p>
                    <p class="mt-1 truncate text-xs font-medium text-slate-300"><?= e($timezoneLabel) ?></p>
                </div>
            </div>
        </div>
    </article>

    <article class="relative overflow-hidden rounded-3xl border border-white/[0.07] bg-gradient-to-br from-gold-400/[0.06] via-white/[0.02] to-transparent p-5 sm:p-6">
        <div class="pointer-events-none absolute -right-12 -top-16 h-48 w-48 rounded-full bg-gold-400/[0.10] blur-[70px]" aria-hidden="true"></div>

        <div class="relative flex h-full flex-col">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Daily streak</p>
                    <h2 class="mt-1 font-display text-lg font-semibold text-white">Keep the flame alive</h2>
                </div>
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-gold-400/25 bg-gold-400/10 text-gold-300">
                    <i data-lucide="flame" class="h-5 w-5"></i>
                </span>
            </div>

            <p class="mt-5 font-display text-5xl font-bold leading-none tracking-tight text-white">
                <span data-counter="<?= e((string) $streak) ?>"><?= e((string) $streak) ?></span>
                <span class="text-sm font-medium text-slate-500"><?= $streak === 1 ? 'day' : 'days' ?></span>
            </p>
            <p class="mt-2 text-xs leading-relaxed text-slate-500">
                <?php if ($streak === 0): ?>
                    No streak yet. Completing your first quest will start one — the streak engine arrives in Phase 3.
                <?php else: ?>
                    Longest streak: <?= e((string) $longestStreak) ?> <?= $longestStreak === 1 ? 'day' : 'days' ?>.
                <?php endif; ?>
            </p>

            <div class="mt-5 flex gap-1.5" aria-hidden="true">
                <?php for ($day = 1; $day <= 7; $day++): ?>
                    <span class="h-1.5 flex-1 rounded-full <?= $day <= min($streak, 7) ? 'bg-gradient-to-r from-ember-400 to-gold-400' : 'bg-white/[0.06]' ?>"></span>
                <?php endfor; ?>
            </div>

            <div class="mt-auto flex items-center justify-between gap-2 border-t border-white/[0.06] pt-4 text-[10px] text-slate-600">
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="calendar-days" class="h-3 w-3"></i>
                    Tracked in <?= e($timezoneLabel) ?>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="trophy" class="h-3 w-3 text-gold-400/70"></i>
                    Best <?= e((string) $longestStreak) ?>
                </span>
            </div>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Total XP</p>
            <span class="app-stat-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="zap" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value">
            <span data-counter="<?= e((string) $progression['xp']) ?>" data-counter-format="comma"><?= e(number_format((int) $progression['xp'])) ?></span>
        </p>
        <p class="app-stat-note">Earned across your account.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Completed quests</p>
            <span class="app-stat-icon border-emerald-400/20 bg-emerald-500/10 text-emerald-300">
                <i data-lucide="circle-check" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value" aria-label="Not available yet">
            <?= $completedQuests === null ? '—' : e(number_format($completedQuests)) ?>
        </p>
        <p class="app-stat-note"><?= $completedQuests === null ? 'No quest data yet — Phase 3.' : 'Quests completed.' ?></p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Active quests</p>
            <span class="app-stat-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="target" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value" aria-label="Not available yet">
            <?= $activeQuests === null ? '—' : e(number_format($activeQuests)) ?>
        </p>
        <p class="app-stat-note"><?= $activeQuests === null ? 'Quest engine arrives in Phase 3.' : 'Quests in progress.' ?></p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Longest streak</p>
            <span class="app-stat-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="flame" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value">
            <span data-counter="<?= e((string) $longestStreak) ?>"><?= e((string) $longestStreak) ?></span>
            <span class="text-sm font-medium text-slate-500"><?= $longestStreak === 1 ? 'day' : 'days' ?></span>
        </p>
        <p class="app-stat-note">Your personal best so far.</p>
    </article>
</section>

<section class="mt-4 grid gap-4 xl:grid-cols-[1.55fr_1fr]" data-reveal>
    <article class="app-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                    <i data-lucide="activity" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Recent activity</h2>
                    <p class="text-[11px] text-slate-500">Your quest feed, once it exists.</p>
                </div>
            </div>
            <span class="rounded-full border border-white/10 bg-white/[0.02] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-slate-500">Phase 3</span>
        </div>

        <div class="p-5">
            <?php if ($recentActivity === []): ?>
                <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/10 bg-white/[0.01] px-5 py-10 text-center">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl border border-white/[0.07] bg-white/[0.03] text-slate-500">
                        <i data-lucide="list-checks" class="h-5 w-5"></i>
                    </span>
                    <p class="mt-4 font-display text-sm font-semibold text-slate-200">No activity yet</p>
                    <p class="mt-1.5 max-w-sm text-xs leading-relaxed text-slate-500">
                        Completed quests, level-ups and streak milestones will appear here after the quest engine ships.
                        Nothing is being recorded today.
                    </p>
                    <a href="<?= e(url('/quests')) ?>" class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">
                        See what Phase 3 adds
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="app-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="trophy" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Achievement preview</h2>
                    <p class="text-[11px] text-slate-500">Locked slots, no invented badges.</p>
                </div>
            </div>
            <span class="rounded-full border border-white/10 bg-white/[0.02] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-slate-500">Phase 4</span>
        </div>

        <div class="p-5">
            <?php if ($achievementPreview === []): ?>
                <div class="grid grid-cols-2 gap-2.5">
                    <?php for ($slot = 1; $slot <= 4; $slot++): ?>
                        <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-white/10 bg-white/[0.01] px-3 py-5 text-center">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/[0.03] text-slate-600">
                                <i data-lucide="lock" class="h-4 w-4"></i>
                            </span>
                            <p class="text-[10px] font-medium uppercase tracking-wider text-slate-600">Locked slot</p>
                        </div>
                    <?php endfor; ?>
                </div>
                <p class="mt-3 text-center text-[11px] leading-relaxed text-slate-600">
                    No achievements are defined yet. The trophy hall opens in Phase 4.
                </p>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="mt-4" data-reveal>
    <article class="app-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="crown" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Leaderboard preview</h2>
                    <p class="text-[11px] text-slate-500">Your row, before rankings exist.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full border border-white/10 bg-white/[0.02] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-slate-500">Phase 5</span>
                <a href="<?= e(url('/leaderboard')) ?>" class="text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">Learn more</a>
            </div>
        </div>

        <div class="px-5 py-5">
            <div class="grid grid-cols-[2.5rem_1fr_auto] items-center gap-3 rounded-xl border border-white/[0.06] bg-gradient-to-r from-ember-500/[0.07] to-transparent px-3 py-3 sm:grid-cols-[3rem_1fr_5rem_5rem]">
                <span class="grid h-8 w-8 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] text-[11px] font-semibold text-slate-500" aria-label="Unranked">
                    —
                </span>
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-ember-500 to-gold-400 text-[11px] font-bold text-white">
                        <?= e($leaderboardInitial) ?>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-slate-200">
                            <?= e((string) $user['display_name']) ?>
                            <span class="ml-1 rounded border border-gold-400/20 bg-gold-400/[0.08] px-1 py-0.5 text-[8px] font-bold uppercase tracking-wider text-gold-300">You</span>
                        </p>
                        <p class="truncate text-[10px] text-slate-600">Unranked · leaderboards not calculated yet</p>
                    </div>
                </div>
                <div class="hidden text-right sm:block">
                    <p class="text-[9px] uppercase tracking-wider text-slate-600">Level</p>
                    <p class="text-xs font-semibold text-slate-300"><?= e((string) $level) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-[9px] uppercase tracking-wider text-slate-600">XP</p>
                    <p class="text-xs font-semibold text-slate-300"><?= e(number_format((int) $progression['xp'])) ?></p>
                </div>
            </div>

            <p class="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-slate-600">
                <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                No other adventurers are shown because ranking calculations do not exist yet. This is your own account row only.
            </p>
        </div>
    </article>
</section>

<dialog
    id="quest-dialog"
    data-quest-dialog
    class="app-dialog"
    aria-labelledby="quest-dialog-title"
    aria-describedby="quest-dialog-description"
>
    <div class="app-dialog-panel" data-dialog-panel>
        <div class="flex items-start justify-between gap-4 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-ember-400/25 bg-ember-500/10 text-gold-300">
                    <i data-lucide="swords" class="h-5 w-5"></i>
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h2 id="quest-dialog-title" class="whitespace-nowrap font-display text-[15px] font-semibold text-white sm:text-base">Create a quest</h2>
                        <span class="rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-gold-300">Preview</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Quest creation is a Phase 3 feature.</p>
                </div>
            </div>

            <form method="dialog">
                <button type="submit" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 transition hover:bg-white/[0.06] hover:text-white" aria-label="Close dialog">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </form>
        </div>

        <div class="px-5 py-5">
            <p id="quest-dialog-description" class="rounded-xl border border-gold-400/20 bg-gold-400/[0.05] px-3.5 py-3 text-xs leading-relaxed text-slate-400">
                This is a non-functional preview. Nothing you type is saved, and no XP is awarded until the quest engine ships.
            </p>

            <div class="mt-4 space-y-3">
                <div>
                    <label for="preview-quest-title" class="field-label">Quest title</label>
                    <input id="preview-quest-title" type="text" class="field-input" placeholder="e.g. Finish the launch outline" disabled>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="preview-quest-difficulty" class="field-label">Difficulty</label>
                        <select id="preview-quest-difficulty" class="field-input appearance-none" disabled>
                            <option>Easy · 10 XP</option>
                        </select>
                    </div>
                    <div>
                        <label for="preview-quest-due" class="field-label">Due</label>
                        <input id="preview-quest-due" type="text" class="field-input" placeholder="Today" disabled>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2 border-t border-white/[0.06] px-5 py-4 sm:flex-row sm:justify-end">
            <form method="dialog">
                <button type="submit" class="btn btn-app-ghost w-full sm:w-auto">Got it</button>
            </form>
            <a href="<?= e(url('/quests')) ?>" class="btn btn-app-primary w-full sm:w-auto">
                See the Phase 3 plan
                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>
    </div>
</dialog>
