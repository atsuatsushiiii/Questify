<?php
/**
 * Administrator overview.
 *
 * @var array{kpis: array<string, int>, signupSeries: list<array{date: string, total: int}>, completionSeries: list<array{date: string, total: int}>, recentAudit: list<array<string, mixed>>} $dashboard
 */
$kpis = $dashboard['kpis'];
$signupTotal = array_sum(array_column($dashboard['signupSeries'], 'total'));
$completionTotal = array_sum(array_column($dashboard['completionSeries'], 'total'));
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <p class="inline-flex items-center gap-2 rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-3.5 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-gold-300">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Administrator
        </p>
        <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">Platform overview</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Live counts from the database, growth over the last 14 days, and the newest privileged actions.
            Nothing on this page is simulated.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/users')) ?>" class="btn btn-app-primary">
            <i data-lucide="users" class="h-4 w-4"></i>
            Manage users
        </a>
        <a href="<?= e(url('/admin/audit')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            Audit log
        </a>
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Accounts</p>
            <span class="app-stat-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="users" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['users'])) ?></p>
        <p class="app-stat-note"><?= e(number_format($kpis['active_users'])) ?> active · <?= e(number_format($kpis['suspended_users'])) ?> suspended</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Administrators</p>
            <span class="app-stat-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['admins'])) ?></p>
        <p class="app-stat-note">Privileged accounts with full access.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Quests</p>
            <span class="app-stat-icon border-emerald-400/20 bg-emerald-500/10 text-emerald-300">
                <i data-lucide="scroll-text" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['quests'])) ?></p>
        <p class="app-stat-note"><?= e(number_format($kpis['completed_quests'])) ?> completed · <?= e(number_format($completionTotal)) ?> rewards in 14 days</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">XP awarded</p>
            <span class="app-stat-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="zap" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['xp_awarded'])) ?></p>
        <p class="app-stat-note">Across every account balance.</p>
    </article>
</section>

<section class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Signups (14d)</p>
            <span class="app-stat-icon border-emerald-400/20 bg-emerald-500/10 text-emerald-300">
                <i data-lucide="user-plus" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($signupTotal)) ?></p>
        <p class="app-stat-note">New accounts in the current window.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Badges unlocked</p>
            <span class="app-stat-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="award" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['achievements_unlocked'])) ?></p>
        <p class="app-stat-note"><?= e(number_format($kpis['achievements'])) ?> active definitions.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Categories</p>
            <span class="app-stat-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="folder-tree" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($kpis['categories'])) ?></p>
        <p class="app-stat-note">Active quest categories.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Completions (14d)</p>
            <span class="app-stat-icon border-emerald-400/20 bg-emerald-500/10 text-emerald-300">
                <i data-lucide="circle-check" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($completionTotal)) ?></p>
        <p class="app-stat-note">XP awards recorded in the ledger.</p>
    </article>
</section>

<section class="mt-4 grid gap-4 xl:grid-cols-[1.5fr_1fr]" data-reveal>
    <article class="app-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="trending-up" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Growth and activity</h2>
                    <p class="text-[11px] text-slate-500">Signups and quest rewards over the last 14 days.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 text-[10px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-gold-400"></span> Signups</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-ember-400"></span> Completions</span>
            </div>
        </div>

        <div class="p-5">
            <div class="relative min-h-[260px]">
                <canvas
                    data-admin-growth-chart
                    data-endpoint="<?= e(url('/api/admin/stats')) ?>"
                    aria-label="Signups and quest completions over the last 14 days"
                    role="img"
                ></canvas>
                <p data-admin-chart-status class="mt-3 hidden text-[11px] text-slate-500"></p>
            </div>
            <noscript>
                <p class="mt-3 text-[11px] text-slate-500">Charts require JavaScript. The KPI cards above remain accurate without it.</p>
            </noscript>
        </div>
    </article>

    <article class="app-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="history" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Recent audit entries</h2>
                    <p class="text-[11px] text-slate-500">The latest privileged changes.</p>
                </div>
            </div>
            <a href="<?= e(url('/admin/audit')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">
                View all
                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>

        <div class="p-4">
            <?php if ($dashboard['recentAudit'] === []): ?>
                <div class="rounded-xl border border-dashed border-white/10 bg-white/[0.01] px-5 py-10 text-center">
                    <span class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-white/[0.03] text-slate-600">
                        <i data-lucide="history" class="h-4 w-4"></i>
                    </span>
                    <p class="mt-3 text-xs font-semibold text-slate-300">No admin actions yet</p>
                    <p class="mt-1 text-[11px] text-slate-500">Moderation and catalogue changes will appear here.</p>
                </div>
            <?php else: ?>
                <ol class="space-y-2">
                    <?php foreach ($dashboard['recentAudit'] as $entry): ?>
                        <li class="rounded-xl border border-white/[0.06] bg-white/[0.015] px-3 py-2.5">
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-gold-400/20 bg-gold-400/[0.08] text-gold-300">
                                    <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[11px] font-semibold text-slate-200"><?= e((string) $entry['summary']) ?></p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-600">
                                        <span class="font-mono text-gold-300/80"><?= e((string) $entry['action']) ?></span>
                                        <span>·</span>
                                        <span><?= e((string) ($entry['actor_display_name'] ?? 'System')) ?></span>
                                        <span>·</span>
                                        <time datetime="<?= e((string) iso_datetime((string) $entry['created_at'])) ?>">
                                            <?= e((string) format_datetime((string) $entry['created_at'], 'UTC', 'j M, H:i')) ?> UTC
                                        </time>
                                    </p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-reveal>
    <a href="<?= e(url('/admin/users')) ?>" class="app-panel group flex items-center gap-4 p-5 transition hover:border-gold-400/25">
        <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
            <i data-lucide="users" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-white">User management</p>
            <p class="mt-0.5 text-[11px] text-slate-500">Search, suspend, activate, promote, or demote.</p>
        </div>
        <i data-lucide="arrow-right" class="h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-gold-300"></i>
    </a>

    <a href="<?= e(url('/admin/categories')) ?>" class="app-panel group flex items-center gap-4 p-5 transition hover:border-gold-400/25">
        <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
            <i data-lucide="folder-tree" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-white">Category catalogue</p>
            <p class="mt-0.5 text-[11px] text-slate-500">Control how quests are grouped and filtered.</p>
        </div>
        <i data-lucide="arrow-right" class="h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-gold-300"></i>
    </a>

    <a href="<?= e(url('/admin/achievements')) ?>" class="app-panel group flex items-center gap-4 p-5 transition hover:border-gold-400/25">
        <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
            <i data-lucide="award" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-white">Achievement definitions</p>
            <p class="mt-0.5 text-[11px] text-slate-500">Tune thresholds and publish new badges.</p>
        </div>
        <i data-lucide="arrow-right" class="h-4 w-4 shrink-0 text-slate-600 transition group-hover:text-gold-300"></i>
    </a>
</section>
