<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Guest-facing public profile.
 *
 * @var array<string, mixed> $account
 * @var array<string, mixed> $progression
 * @var int $completedQuests
 * @var list<array<string, mixed>> $achievements
 * @var array{total: int, unlocked: int, locked: int, percent: int} $achievementSummary
 * @var array{current: int, longest: int} $streak
 * @var string|null $memberSince
 */
$displayName = (string) ($account['display_name'] ?? $account['username'] ?? 'Adventurer');
$username = (string) ($account['username'] ?? '');
$avatarPath = isset($account['avatar_path']) ? (string) $account['avatar_path'] : null;
$avatarUrl = avatar_url($avatarPath);
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$timezone = 'UTC';
$stats = [
    ['label' => 'Total XP', 'value' => number_format((int) $progression['xp']), 'tone' => 'text-white'],
    ['label' => 'Level', 'value' => (string) (int) $progression['level'], 'tone' => 'text-gold-300'],
    ['label' => 'Quests completed', 'value' => number_format($completedQuests), 'tone' => 'text-white'],
    ['label' => 'Current streak', 'value' => (string) $streak['current'] . 'd', 'tone' => 'text-ember-300'],
    ['label' => 'Longest streak', 'value' => (string) $streak['longest'] . 'd', 'tone' => 'text-white'],
    ['label' => 'Achievements', 'value' => (string) $achievementSummary['unlocked'], 'tone' => 'text-gold-300'],
];
?>
<section class="relative overflow-hidden pb-20 pt-32 sm:pt-36" data-reveal>
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-48 left-1/2 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-gold-400/[0.08] blur-[130px]"></div>
        <div class="absolute -right-32 top-32 h-[360px] w-[360px] rounded-full bg-ember-500/[0.10] blur-[110px]"></div>
    </div>

    <div class="container-page">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2 text-xs font-medium text-slate-400 transition hover:text-white">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                Questify
            </a>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-gold-300">
                <i data-lucide="globe" class="h-3 w-3"></i>
                Public profile
            </span>
        </div>

        <article class="relative mt-5 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.02]">
            <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-ember-500/[0.10] blur-[90px]" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:p-8">
                <?php if ($avatarUrl !== null): ?>
                    <img
                        src="<?= e($avatarUrl) ?>"
                        alt="<?= e($displayName) ?>"
                        class="h-24 w-24 shrink-0 rounded-2xl border border-gold-400/25 object-cover"
                    >
                <?php else: ?>
                    <span class="grid h-24 w-24 shrink-0 place-items-center rounded-2xl border border-gold-400/25 bg-gradient-to-br from-ember-500 to-gold-400 font-display text-3xl font-bold text-white" aria-hidden="true">
                        <?= e($initial) ?>
                    </span>
                <?php endif; ?>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display text-3xl font-bold tracking-tight text-white sm:text-4xl"><?= e($displayName) ?></h1>
                    <?php if ($username !== ''): ?>
                        <p class="mt-1 truncate text-sm text-muted">@<?= e($username) ?></p>
                    <?php endif; ?>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-gold-300">
                            <i data-lucide="shield-check" class="h-3 w-3"></i>
                            <?= e((string) $progression['rank']) ?> · Level <?= e((string) (int) $progression['level']) ?>
                        </span>
                        <?php if ($memberSince !== null): ?>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-slate-400">
                                <i data-lucide="calendar-days" class="h-3 w-3"></i>
                                Member since <?= e($memberSince) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <dl class="relative grid grid-cols-2 gap-px border-t border-white/[0.06] bg-white/[0.06] sm:grid-cols-3 lg:grid-cols-6">
                <?php foreach ($stats as $stat): ?>
                    <div class="bg-[#0a0a12] px-5 py-4">
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.14em] text-muted-dim"><?= e($stat['label']) ?></dt>
                        <dd class="mt-1 font-display text-xl font-bold tabular-nums <?= e($stat['tone']) ?>"><?= e($stat['value']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </article>

        <div class="mt-5 grid gap-4 lg:grid-cols-[1.55fr_0.45fr]">
            <section class="rounded-2xl border border-white/[0.07] bg-white/[0.02] p-5 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Trophy case</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Unlocked achievements</h2>
                    </div>
                    <p class="text-[11px] text-muted">
                        <?= e((string) $achievementSummary['unlocked']) ?> of <?= e((string) $achievementSummary['total']) ?> collected
                    </p>
                </div>

                <?php if ($achievements === []): ?>
                    <div class="mt-5 rounded-xl border border-dashed border-white/10 bg-white/[0.01] px-5 py-10 text-center">
                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl border border-white/[0.07] bg-white/[0.03] text-muted">
                            <i data-lucide="trophy" class="h-5 w-5"></i>
                        </span>
                        <p class="mt-3 font-display text-sm font-semibold text-slate-200">No trophies yet</p>
                        <p class="mt-1 text-xs text-muted">The first completed quest starts the collection.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <?php foreach ($achievements as $achievement): ?>
                            <?php require __DIR__ . '/../../partials/achievement-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-gold-400/20 bg-gradient-to-br from-gold-400/[0.08] via-white/[0.02] to-transparent p-5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-gold-300/80">Level progress</p>
                    <p class="mt-2 font-display text-3xl font-bold text-white"><?= e((string) (int) $progression['level']) ?></p>
                    <p class="text-[11px] text-muted"><?= e((string) $progression['rank']) ?></p>

                    <div
                        class="mt-4 h-2 overflow-hidden rounded-full bg-white/[0.08]"
                        role="progressbar"
                        aria-label="Progress to level <?= e((string) (int) ($progression['next_level'] ?? $progression['level'])) ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="<?= e((string) (int) round((float) $progression['progress'])) ?>"
                    >
                        <div class="h-full rounded-full bg-gradient-to-r from-ember-500 via-ember-400 to-gold-400" style="width: <?= e((string) (int) round((float) $progression['progress'])) ?>%"></div>
                    </div>

                    <?php if ($progression['next_level'] !== null): ?>
                        <p class="mt-2 text-[11px] leading-relaxed text-muted">
                            <?= e(number_format((int) $progression['xp_to_next'])) ?> XP to level <?= e((string) (int) $progression['next_level']) ?>.
                        </p>
                    <?php else: ?>
                        <p class="mt-2 text-[11px] leading-relaxed text-muted">Maximum level reached.</p>
                    <?php endif; ?>

                    <?php if ($progression['next_rank_level'] !== null): ?>
                        <p class="mt-3 flex items-center gap-1.5 border-t border-white/[0.06] pt-3 text-[10px] text-muted">
                            <i data-lucide="sparkles" class="h-3 w-3 text-gold-300"></i>
                            Next rank: <?= e((string) $progression['next_rank']) ?> at level <?= e((string) (int) $progression['next_rank_level']) ?>
                        </p>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-white/[0.07] bg-white/[0.02] p-5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">About this page</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        This adventurer chose to share their progress publicly. Quests, email, timezone, and account settings are never shown.
                    </p>
                    <a href="<?= e(url('/register')) ?>" class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">
                        Start your own quest log
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                </section>
            </aside>
        </div>

        <p class="mt-6 flex items-start gap-2 text-[11px] leading-relaxed text-muted-dim">
            <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
            Public profiles show only display name, username, avatar, progression, streak counts, and unlocked achievements.
        </p>
    </div>
</section>
