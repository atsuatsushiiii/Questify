<?php
/**
 * Authenticated account home.
 *
 * @var array<string, mixed> $user
 * @var int $level
 * @var float $progress
 * @var int $xpToNext
 * @var string $rank
 * @var string|null $memberSince
 * @var string|null $lastLogin
 */
$circumference = 2 * M_PI * 52;
$offset = $circumference * (1 - ($progress / 100));
$streak = (int) $user['current_streak'];
$longest = (int) $user['longest_streak'];
?>
<section class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between" data-reveal>
    <div>
        <p class="eyebrow">
            <i data-lucide="compass" class="h-3.5 w-3.5"></i>
            <?= e($rank) ?> &middot; Level <?= e($level) ?>
        </p>
        <h1 class="mt-5 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
            Welcome back, <?= e((string) $user['display_name']) ?>.
        </h1>
        <p class="mt-3 max-w-xl text-sm leading-relaxed text-muted">
            Your account is live and your session is secure.
            <?php if ($lastLogin !== null): ?>
                Last sign-in on <?= e($lastLogin) ?>.
            <?php endif; ?>
        </p>
    </div>

    <a href="<?= e(url('/')) ?>" class="btn btn-ghost btn-sm self-start lg:self-auto">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        View public site
    </a>
</section>

<section class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal>
    <article class="card relative overflow-hidden p-6">
        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-500/10 blur-2xl" aria-hidden="true"></div>
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Level</p>
            <span class="rounded-full border border-brand-400/20 bg-brand-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand-300">
                <?= e($rank) ?>
            </span>
        </div>

        <div class="mt-5 flex items-center gap-5">
            <div class="relative h-28 w-28 shrink-0">
                <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" aria-hidden="true">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="8"/>
                    <circle
                        cx="60"
                        cy="60"
                        r="52"
                        fill="none"
                        stroke="url(#level-gradient)"
                        stroke-width="8"
                        stroke-linecap="round"
                        stroke-dasharray="<?= e((string) $circumference) ?>"
                        stroke-dashoffset="<?= e((string) $offset) ?>"
                    />
                    <defs>
                        <linearGradient id="level-gradient" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#8B5CF6"/>
                            <stop offset="100%" stop-color="#22D3EE"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="absolute inset-0 grid place-items-center">
                    <div class="text-center">
                        <p class="font-display text-2xl font-bold leading-none text-white"><?= e($level) ?></p>
                        <p class="mt-1 text-[9px] uppercase tracking-[0.16em] text-slate-500">Level</p>
                    </div>
                </div>
            </div>

            <div class="min-w-0">
                <p class="font-display text-lg font-semibold text-white"><?= e(number_format((int) $user['xp_total'])) ?> XP</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    <?= $xpToNext > 0 ? e(number_format($xpToNext)) . ' XP to level ' . e($level + 1) : 'Maximum level reached' ?>
                </p>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-300">
                    <?= e((string) round($progress)) ?>% complete
                </p>
            </div>
        </div>
    </article>

    <article class="card relative overflow-hidden p-6">
        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-aqua-500/10 blur-2xl" aria-hidden="true"></div>
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total XP</p>
            <span class="grid h-9 w-9 place-items-center rounded-xl border border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                <i data-lucide="trending-up" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="mt-5 font-display text-4xl font-bold tracking-tight text-white">
            <?= e(number_format((int) $user['xp_total'])) ?>
        </p>
        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            Every completed quest adds to a single, transparent ledger.
        </p>
        <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 via-brand-400 to-aqua-400" style="width: <?= e((string) $progress) ?>%"></div>
        </div>
    </article>

    <article class="card relative overflow-hidden p-6 sm:col-span-2 lg:col-span-1">
        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-orange-500/10 blur-2xl" aria-hidden="true"></div>
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Streak</p>
            <span class="grid h-9 w-9 place-items-center rounded-xl border border-orange-400/20 bg-orange-500/10 text-orange-300">
                <i data-lucide="flame" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="mt-5 font-display text-4xl font-bold tracking-tight text-white">
            <?= e($streak) ?>
            <span class="text-sm font-medium text-slate-500"><?= $streak === 1 ? 'day' : 'days' ?></span>
        </p>
        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            Longest streak: <?= e($longest) ?> <?= $longest === 1 ? 'day' : 'days' ?>.
            Streaks are calculated in <?= e(str_replace('_', ' ', (string) $user['timezone'])) ?>.
        </p>
        <div class="mt-5 flex gap-1.5" aria-hidden="true">
            <?php for ($day = 1; $day <= 7; $day++): ?>
                <span class="h-1.5 flex-1 rounded-full <?= $day <= min($streak, 7) ? 'bg-orange-400/80' : 'bg-white/[0.06]' ?>"></span>
            <?php endfor; ?>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-4 lg:grid-cols-2" data-reveal>
    <article class="card p-6">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl border border-brand-400/20 bg-brand-500/10 text-brand-300">
                <i data-lucide="user" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-base font-semibold text-white">Account details</h2>
                <p class="text-xs text-slate-500">The identity behind your quest log.</p>
            </div>
        </div>

        <dl class="mt-6 divide-y divide-white/[0.06] text-sm">
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Display name</dt>
                <dd class="text-right font-medium text-slate-200"><?= e((string) $user['display_name']) ?></dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Username</dt>
                <dd class="text-right font-medium text-slate-200">@<?= e((string) $user['username']) ?></dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Email</dt>
                <dd class="max-w-[60%] truncate text-right font-medium text-slate-200"><?= e((string) $user['email']) ?></dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Timezone</dt>
                <dd class="text-right font-medium text-slate-200"><?= e(str_replace('_', ' ', (string) $user['timezone'])) ?></dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Member since</dt>
                <dd class="text-right font-medium text-slate-200"><?= e($memberSince ?? 'Today') ?></dd>
            </div>
        </dl>
    </article>

    <article class="card p-6">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl border border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-base font-semibold text-white">Security</h2>
                <p class="text-xs text-slate-500">How this account is protected.</p>
            </div>
        </div>

        <ul class="mt-6 space-y-4 text-sm">
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <i data-lucide="check" class="h-3 w-3"></i>
                </span>
                <div>
                    <p class="font-medium text-slate-200">Password hashed with bcrypt</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Plain-text passwords are never stored or logged.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <i data-lucide="check" class="h-3 w-3"></i>
                </span>
                <div>
                    <p class="font-medium text-slate-200">Server-side sessions</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Session IDs are hashed at rest and expire after inactivity.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <i data-lucide="check" class="h-3 w-3"></i>
                </span>
                <div>
                    <p class="font-medium text-slate-200">Remember-me tokens are hashed</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">A database leak cannot be replayed as a valid sign-in.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <i data-lucide="check" class="h-3 w-3"></i>
                </span>
                <div>
                    <p class="font-medium text-slate-200">Login throttling active</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Repeated failed attempts are rate limited per account and network.</p>
                </div>
            </li>
        </ul>
    </article>
</section>
