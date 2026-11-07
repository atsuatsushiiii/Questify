<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authenticated command center, set as a field ledger.
 *
 * Quest statistics and XP activity come from the quest tables. Achievement and
 * leaderboard panels remain explicit empty states until their phases ship.
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
 * @var int $completedQuests
 * @var int $activeQuests
 * @var list<array{title: string, quest_id: int|null, xp: int, level_before: int, level_after: int, completed_at: string|null, iso: string|null}> $recentActivity
 * @var list<array<string, mixed>> $achievementPreview
 * @var array{total: int, unlocked: int, locked: int, percent: int} $achievementSummary
 * @var list<array<string, mixed>> $leaderboardEntries
 * @var int $leaderboardTotal
 * @var int|null $viewerRank
 * @var list<array{date: string, label: string, active: bool, today: bool}> $streakDays
 */
$circumference = 2 * M_PI * 52;
$ringOffset = $circumference * (1 - ((float) $progress / 100));
$timezoneLabel = str_replace('_', ' ', (string) ($user['timezone'] ?? 'UTC'));
$nextRank = (string) ($progression['next_rank'] ?? 'Mythic');
$nextRankLevel = $progression['next_rank_level'] ?? null;
$atMaxLevel = $progression['next_level'] === null;
$dossierNumber = str_pad((string) ($user['id'] ?? 0), 4, '0', STR_PAD_LEFT);
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <p class="eyebrow">
            <i data-lucide="compass" class="h-3.5 w-3.5" aria-hidden="true"></i>
            Field report · No. <?= e($dossierNumber) ?>
        </p>
        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
            Welcome back, <?= e((string) $user['display_name']) ?>.
        </h1>
        <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">
            <span><?= e($today) ?></span>
            <span class="h-3 w-px bg-line" aria-hidden="true"></span>
            <span><?= e($timezoneLabel) ?></span>
            <span class="h-3 w-px bg-line" aria-hidden="true"></span>
            <span class="inline-flex items-center gap-1.5 text-aqua-300">
                <i data-lucide="shield-check" class="h-3.5 w-3.5" aria-hidden="true"></i>
                Session secure
            </span>
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/quests/create')) ?>" class="btn btn-app-primary group">
            <i data-lucide="plus" class="h-4 w-4 transition-transform duration-200 group-hover:rotate-90"></i>
            New quest
        </a>
        <a href="<?= e(url('/quests')) ?>" class="btn btn-app-ghost">
            <i data-lucide="scroll-text" class="h-4 w-4"></i>
            Quest log
        </a>
    </div>
</section>

<section class="mt-6 grid gap-4 xl:grid-cols-[1.55fr_1fr]" data-reveal>
    <article class="app-panel relative overflow-hidden p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="app-stat-label">Dossier / level progress</p>
                <h2 class="mt-1 font-display text-lg font-semibold text-white">Rank and level</h2>
            </div>
            <span class="rotate-2 border border-brand-400/40 px-2.5 py-1 font-mono text-[8px] font-semibold uppercase tracking-[0.14em] text-brand-300">
                <?= e($rank) ?>
            </span>
        </div>

        <div class="mt-5 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <div class="relative h-32 w-32 shrink-0">
                <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" aria-hidden="true">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="7"/>
                    <circle
                        cx="60"
                        cy="60"
                        r="52"
                        fill="none"
                        stroke="#C9993F"
                        stroke-width="7"
                        stroke-linecap="butt"
                        stroke-dasharray="<?= e((string) $circumference) ?>"
                        stroke-dashoffset="<?= e((string) $ringOffset) ?>"
                        data-meter="ring"
                        data-value="<?= e((string) $progress) ?>"
                    />
                </svg>
                <div class="absolute inset-0 grid place-items-center">
                    <div class="text-center">
                        <p class="font-display text-3xl font-semibold leading-none text-white"><?= e((string) $level) ?></p>
                        <p class="mt-1 font-mono text-[8px] font-medium uppercase tracking-[0.2em] text-brand-300">Level</p>
                    </div>
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-display text-xl font-semibold text-white">
                        <span data-counter="<?= e((string) $progression['xp']) ?>" data-counter-format="comma"><?= e(number_format((int) $progression['xp'])) ?></span>
                        <span class="font-mono text-xs font-medium uppercase tracking-[0.12em] text-muted">XP total</span>
                    </p>
                    <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-muted"><?= $atMaxLevel ? 'Max level reached' : e((string) round((float) $progress)) . '% to level ' . e((string) ($level + 1)) ?></p>
                </div>

                <div class="mt-3 h-2 overflow-hidden bg-white/[0.06]">
                    <div
                        class="h-full bg-brand-400"
                        style="width: <?= e((string) $progress) ?>%"
                        data-meter="width"
                        data-value="<?= e((string) $progress) ?>"
                    ></div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 font-mono text-[9px] uppercase tracking-[0.1em] text-muted">
                    <?php if ($xpToNext > 0): ?>
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="zap" class="h-3 w-3 text-brand-300" aria-hidden="true"></i>
                            <?= e(number_format($xpToNext)) ?> XP to next level
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 text-brand-300">
                            <i data-lucide="badge-check" class="h-3 w-3" aria-hidden="true"></i>
                            Maximum level reached
                        </span>
                    <?php endif; ?>

                    <?php if ($nextRankLevel !== null): ?>
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="trending-up" class="h-3 w-3 text-ember-300" aria-hidden="true"></i>
                            <?= e($nextRank) ?> rank at level <?= e((string) $nextRankLevel) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4 sm:grid-cols-3">
            <div>
                <p class="font-mono text-[8px] font-medium uppercase tracking-[0.16em] text-muted-dim">Member since</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e($memberSince ?? 'Today') ?></p>
            </div>
            <div>
                <p class="font-mono text-[8px] font-medium uppercase tracking-[0.16em] text-muted-dim">Last sign-in</p>
                <p class="mt-1 text-xs font-medium text-slate-300"><?= e($lastLogin ?? 'This session') ?></p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <p class="font-mono text-[8px] font-medium uppercase tracking-[0.16em] text-muted-dim">Timezone</p>
                <p class="mt-1 truncate text-xs font-medium text-slate-300"><?= e($timezoneLabel) ?></p>
            </div>
        </div>
    </article>

    <article class="app-panel flex flex-col p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="app-stat-label">Streak register</p>
                <h2 class="mt-1 font-display text-lg font-semibold text-white">Keep the flame alive</h2>
            </div>
            <span class="grid h-10 w-10 place-items-center rounded-sm border border-ember-400/35 bg-ember-500/10 text-ember-300">
                <i data-lucide="flame" class="h-5 w-5" aria-hidden="true"></i>
            </span>
        </div>

        <p class="mt-5 font-display text-5xl font-semibold leading-none tracking-tight text-white">
            <span data-counter="<?= e((string) $streak) ?>"><?= e((string) $streak) ?></span>
            <span class="font-mono text-sm font-medium uppercase tracking-[0.12em] text-muted"><?= $streak === 1 ? 'day' : 'days' ?></span>
        </p>
        <p class="mt-2 text-xs leading-relaxed text-muted">
            <?php if ($streak === 0): ?>
                Complete a quest today to light the flame. A streak counts one qualifying quest per local day.
            <?php else: ?>
                Longest streak: <?= e((string) $longestStreak) ?> <?= $longestStreak === 1 ? 'day' : 'days' ?>.
                Complete a quest today to keep it alive.
            <?php endif; ?>
        </p>

        <div class="mt-5 grid grid-cols-7 gap-1.5" role="group" aria-label="Last seven days of quest activity">
            <?php foreach ($streakDays as $day): ?>
                <span class="flex flex-col items-center gap-1.5">
                    <span
                        class="h-9 w-full border <?= $day['active']
                            ? 'border-ember-400/60 bg-ember-500/70'
                            : 'border-line bg-white/[0.03]' ?> <?= $day['today'] ? 'ring-1 ring-brand-300' : '' ?>"
                        role="img"
                        aria-label="<?= e($day['label']) ?><?= $day['active'] ? ', quest completed' : ', no quest' ?>"
                        title="<?= e($day['label']) ?><?= $day['active'] ? ' — quest completed' : ' — no quest' ?>"
                    ></span>
                    <span class="font-mono text-[8px] uppercase tracking-[0.08em] text-muted-dim"><?= e(mb_substr($day['label'], 0, 1)) ?></span>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 border-t border-line pt-4 font-mono text-[8px] uppercase tracking-[0.1em] text-muted-dim">
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="calendar-days" class="h-3 w-3" aria-hidden="true"></i>
                <?= e($timezoneLabel) ?>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="trophy" class="h-3 w-3 text-brand-400" aria-hidden="true"></i>
                Best <?= e((string) $longestStreak) ?>
            </span>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Total XP</p>
            <span class="app-stat-icon border-brand-400/35 bg-brand-400/10 text-brand-300">
                <i data-lucide="zap" class="h-4 w-4" aria-hidden="true"></i>
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
            <span class="app-stat-icon border-aqua-400/35 bg-aqua-500/10 text-aqua-300">
                <i data-lucide="circle-check" class="h-4 w-4" aria-hidden="true"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($completedQuests)) ?></p>
        <p class="app-stat-note"><?= $completedQuests === 0 ? 'Complete a quest to start your record.' : 'Quests completed and rewarded.' ?></p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Active quests</p>
            <span class="app-stat-icon border-ember-400/35 bg-ember-500/10 text-ember-300">
                <i data-lucide="target" class="h-4 w-4" aria-hidden="true"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($activeQuests)) ?></p>
        <p class="app-stat-note"><?= $activeQuests === 0 ? 'Your active log is clear.' : 'Quests in progress.' ?></p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Longest streak</p>
            <span class="app-stat-icon border-brand-400/35 bg-brand-400/10 text-brand-300">
                <i data-lucide="flame" class="h-4 w-4" aria-hidden="true"></i>
            </span>
        </div>
        <p class="app-stat-value">
            <span data-counter="<?= e((string) $longestStreak) ?>"><?= e((string) $longestStreak) ?></span>
            <span class="font-mono text-sm font-medium uppercase tracking-[0.1em] text-muted"><?= $longestStreak === 1 ? 'day' : 'days' ?></span>
        </p>
        <p class="app-stat-note">Your personal best so far.</p>
    </article>
</section>

<section class="mt-4 grid gap-4 xl:grid-cols-[1.55fr_1fr]" data-reveal>
    <article class="app-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-ember-400/35 bg-ember-500/10 text-ember-300">
                    <i data-lucide="activity" class="h-4 w-4" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Recent activity</h2>
                    <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">XP awarded from completed quests</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 border border-aqua-400/35 px-2 py-1 font-mono text-[8px] font-semibold uppercase tracking-[0.14em] text-aqua-300">
                <span class="h-1.5 w-1.5 bg-aqua-400"></span>
                Live
            </span>
        </div>

        <div class="px-5 py-2">
            <?php if ($recentActivity === []): ?>
                <div class="my-3 flex flex-col items-center justify-center border border-dashed border-line px-5 py-10 text-center">
                    <span class="grid h-12 w-12 place-items-center border border-line bg-white/[0.03] text-muted">
                        <i data-lucide="list-checks" class="h-5 w-5" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-sm font-semibold text-slate-200">No activity yet</p>
                    <p class="mt-1.5 max-w-sm text-xs leading-relaxed text-muted">
                        Complete a quest and its XP award will appear here with the time it was claimed.
                        Nothing is fabricated while your log is empty.
                    </p>
                    <a href="<?= e(url('/quests/create')) ?>" class="mt-4 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">
                        Create your first quest
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
                    </a>
                </div>
            <?php else: ?>
                <ol class="divide-y divide-line">
                    <?php foreach ($recentActivity as $entry): ?>
                        <li class="flex items-center gap-3 py-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center border border-aqua-400/35 bg-aqua-500/10 text-aqua-300">
                                <i data-lucide="circle-check" class="h-4 w-4" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <?php if ($entry['quest_id'] !== null): ?>
                                    <a href="<?= e(url('/quests/' . $entry['quest_id'])) ?>" class="block truncate text-xs font-semibold text-slate-200 transition hover:text-brand-200">
                                        <?= e($entry['title']) ?>
                                    </a>
                                <?php else: ?>
                                    <p class="truncate text-xs font-semibold text-slate-200"><?= e($entry['title']) ?></p>
                                <?php endif; ?>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">
                                    <?php if ($entry['completed_at'] !== null): ?>
                                        <?php if ($entry['iso'] !== null): ?>
                                            <time datetime="<?= e($entry['iso']) ?>"><?= e($entry['completed_at']) ?></time>
                                        <?php else: ?>
                                            <span><?= e($entry['completed_at']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($entry['level_after'] > $entry['level_before']): ?>
                                        <span class="text-brand-300">· Level <?= e((string) $entry['level_before']) ?> → <?= e((string) $entry['level_after']) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="shrink-0 border border-brand-400/35 bg-brand-400/[0.08] px-2 py-1 font-mono text-[10px] font-bold tabular-nums text-brand-300">
                                +<?= e((string) $entry['xp']) ?> XP
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <a href="<?= e(url('/quests?status=completed')) ?>" class="my-3 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
                    View completed quests
                    <i data-lucide="arrow-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>
    </article>

    <article class="app-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-brand-400/35 bg-brand-400/10 text-brand-300">
                    <i data-lucide="trophy" class="h-4 w-4" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Achievements</h2>
                    <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">
                        <?= e((string) $achievementSummary['unlocked']) ?> of <?= e((string) $achievementSummary['total']) ?> unlocked
                    </p>
                </div>
            </div>
            <a href="<?= e(url('/achievements')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">
                View all
                <i data-lucide="arrow-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
            </a>
        </div>

        <div class="px-5 py-2">
            <?php if ($achievementPreview === []): ?>
                <div class="my-3 flex flex-col items-center justify-center border border-dashed border-line px-5 py-8 text-center">
                    <span class="grid h-10 w-10 place-items-center border border-line bg-white/[0.03] text-muted-dim">
                        <i data-lucide="trophy" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-xs font-semibold text-slate-300">No achievement definitions</p>
                    <p class="mt-1 text-[11px] text-muted">Definitions are seeded with the database.</p>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-line">
                    <?php foreach ($achievementPreview as $achievement): ?>
                        <?php
                        $isUnlocked = (bool) ($achievement['unlocked'] ?? false);
                        $percent = (int) ($achievement['percent'] ?? 0);
                        ?>
                        <li class="py-3">
                            <div class="flex items-center gap-3">
                                <span class="ledger-seal h-9 w-9 shrink-0 <?= $isUnlocked
                                    ? 'border-brand-400/55 bg-brand-400/[0.06] text-brand-300'
                                    : 'border-line text-muted' ?>">
                                    <i data-lucide="<?= e((string) ($achievement['icon'] ?? 'trophy')) ?>" class="h-3.5 w-3.5" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $achievement['name']) ?></p>
                                    <p class="truncate font-mono text-[9px] text-muted-dim"><?= e((string) $achievement['description']) ?></p>
                                </div>
                                <span class="shrink-0 font-mono text-[9px] font-semibold uppercase tracking-[0.1em] <?= $isUnlocked ? 'text-brand-300' : 'text-muted' ?>">
                                    <?= $isUnlocked ? 'Earned' : e((string) $percent) . '%' ?>
                                </span>
                            </div>
                            <div class="mt-2 h-1 overflow-hidden bg-white/[0.06]">
                                <div
                                    class="h-full <?= $isUnlocked ? 'bg-brand-400' : 'bg-ember-500' ?>"
                                    style="width: <?= e((string) $percent) ?>%"
                                ></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= e(url('/achievements')) ?>" class="my-3 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
                    <?= e((string) $achievementSummary['locked']) ?> still locked
                    <i data-lucide="arrow-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="mt-4" data-reveal>
    <article class="app-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-brand-400/35 bg-brand-400/10 text-brand-300">
                    <i data-lucide="crown" class="h-4 w-4" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Leaderboard</h2>
                    <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">All-time XP · public adventurers only</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($viewerRank !== null): ?>
                    <span class="hidden rotate-2 border border-brand-400/40 px-2 py-1 font-mono text-[8px] font-semibold uppercase tracking-[0.14em] text-brand-300 sm:inline-flex">
                        You are #<?= e((string) $viewerRank) ?>
                    </span>
                <?php endif; ?>
                <a href="<?= e(url('/leaderboard')) ?>" class="font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">View board</a>
            </div>
        </div>

        <div class="p-4">
            <?php if ($leaderboardEntries === []): ?>
                <div class="border border-dashed border-line px-5 py-8 text-center">
                    <span class="mx-auto grid h-10 w-10 place-items-center border border-line bg-white/[0.03] text-muted-dim">
                        <i data-lucide="crown" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-xs font-semibold text-slate-300">No ranked adventurers yet</p>
                    <p class="mt-1 text-[11px] text-muted">
                        <?= $viewerRank === null
                            ? 'Complete a quest to claim the first spot on the board.'
                            : 'Your row will appear as soon as another public adventurer earns XP.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($leaderboardEntries as $entry): ?>
                        <?php
                        $isViewer = (int) $entry['id'] === (int) $user['id'];
                        $metricLabel = 'Total XP';
                        require __DIR__ . '/../partials/leaderboard-row.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-muted-dim">
                <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true"></i>
                <?= e(number_format($leaderboardTotal)) ?> public <?= $leaderboardTotal === 1 ? 'adventurer is' : 'adventurers are' ?> currently ranked.
                Update visibility from your profile at any time.
            </p>
        </div>
    </article>
</section>
