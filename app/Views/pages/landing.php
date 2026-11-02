<?php
/**
 * Marketing landing page.
 *
 * @var string $title
 * @var string $description
 */

$features = [
    [
        'icon' => 'swords',
        'title' => 'Quests that fit real life',
        'copy' => 'Create quests, set a difficulty, and give every task a clear XP value. Daily and weekly quests reset on your schedule.',
        'tone' => 'brand',
    ],
    [
        'icon' => 'trending-up',
        'title' => 'XP that means something',
        'copy' => 'Every completion feeds one transparent XP ledger. Level up on a curve that always feels one quest away.',
        'tone' => 'aqua',
    ],
    [
        'icon' => 'flame',
        'title' => 'Streaks without the guilt',
        'copy' => 'Streaks are calculated in your timezone and tracked across your history. Miss a day and start a new chapter.',
        'tone' => 'amber',
    ],
    [
        'icon' => 'trophy',
        'title' => 'Achievements worth chasing',
        'copy' => 'Unlock badges for milestones, consistency, and bold pushes. Every badge is earned through real progress.',
        'tone' => 'brand',
    ],
    [
        'icon' => 'crown',
        'title' => 'Leaderboards with context',
        'copy' => 'Compete on weekly, monthly, or all-time XP — and opt out whenever you want. Your quests stay yours.',
        'tone' => 'aqua',
    ],
    [
        'icon' => 'line-chart',
        'title' => 'Progress you can read',
        'copy' => 'See XP trends, category breakdowns, and a completion heatmap so you know what is actually working.',
        'tone' => 'brand',
    ],
];

$tones = [
    'brand' => 'border-brand-400/20 bg-brand-500/10 text-brand-300',
    'aqua' => 'border-aqua-400/20 bg-aqua-500/10 text-aqua-300',
    'amber' => 'border-amber-400/20 bg-amber-500/10 text-amber-300',
];

$steps = [
    [
        'icon' => 'user-plus',
        'number' => '01',
        'title' => 'Create your character',
        'copy' => 'Sign up, pick a handle, and set your timezone. You start at level 1 with a clean slate and your first quest.',
    ],
    [
        'icon' => 'swords',
        'number' => '02',
        'title' => 'Forge your quests',
        'copy' => 'Turn real work into quests. Choose a difficulty and Questify calculates the XP before you start working.',
    ],
    [
        'icon' => 'trending-up',
        'number' => '03',
        'title' => 'Complete. Level up. Repeat.',
        'copy' => 'Check off quests, protect your streak, and unlock achievements as the numbers climb. Then do it again tomorrow.',
    ],
];

$bars = [
    ['day' => 'Mon', 'height' => 'h-[42%]', 'active' => false],
    ['day' => 'Tue', 'height' => 'h-[68%]', 'active' => false],
    ['day' => 'Wed', 'height' => 'h-[35%]', 'active' => false],
    ['day' => 'Thu', 'height' => 'h-[88%]', 'active' => false],
    ['day' => 'Fri', 'height' => 'h-[54%]', 'active' => false],
    ['day' => 'Sat', 'height' => 'h-[96%]', 'active' => true],
    ['day' => 'Sun', 'height' => 'h-[61%]', 'active' => false],
];

$leaders = [
    ['rank' => 1, 'name' => 'Nova', 'initials' => 'N', 'xp' => '820', 'tone' => 'border-amber-300/20 bg-amber-400/15 text-amber-300'],
    ['rank' => 2, 'name' => 'Kestrel', 'initials' => 'K', 'xp' => '740', 'tone' => 'border-white/10 bg-white/[0.06] text-slate-200'],
    ['rank' => 3, 'name' => 'Orin', 'initials' => 'O', 'xp' => '690', 'tone' => 'border-orange-300/20 bg-orange-400/10 text-orange-300'],
];

$highlights = [
    ['title' => 'A weekly XP trend', 'copy' => 'See the shape of your effort before the week disappears.'],
    ['title' => 'A streak calendar', 'copy' => 'Every completed day, mapped in your own timezone.'],
    ['title' => 'Category breakdowns', 'copy' => 'Know which kinds of work are actually moving you forward.'],
    ['title' => 'A leaderboard with an off switch', 'copy' => 'Compete when you want to. Stay private when you do not.'],
];
?>

<!-- Hero -->
<section class="relative overflow-hidden pb-16 pt-32 sm:pt-36 lg:pb-24 lg:pt-40" data-hero>
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-grid mask-fade-b opacity-50"></div>
        <div data-orb class="absolute -top-48 left-1/2 h-[560px] w-[900px] -translate-x-1/2 rounded-full bg-brand-600/20 blur-[130px]"></div>
        <div data-orb class="absolute -right-32 top-24 h-[420px] w-[420px] rounded-full bg-aqua-500/10 blur-[110px]"></div>
        <div data-orb class="absolute -left-40 bottom-0 h-[360px] w-[360px] rounded-full bg-brand-700/10 blur-[100px]"></div>
    </div>

    <div class="container-page">
        <div class="grid items-center gap-16 lg:grid-cols-[1.02fr_0.98fr] lg:gap-12">
            <div class="max-w-xl">
                <p data-hero-eyebrow class="eyebrow">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                    Gamified productivity, for real life
                </p>

                <h1 data-hero-title class="mt-6 font-display text-4xl font-bold leading-[1.06] tracking-tight text-white sm:text-5xl lg:text-[3.4rem]">
                    <span class="hero-line block">Turn your to-do list</span>
                    <span class="hero-line block">into a <span class="gradient-text">legendary</span></span>
                    <span class="hero-line block">quest log.</span>
                </h1>

                <p data-hero-copy class="mt-6 max-w-lg text-base leading-relaxed text-muted sm:text-lg">
                    Questify turns everyday tasks into quests you can complete, earn XP for,
                    and level up. Build streaks, unlock achievements, and make real progress
                    feel like play.
                </p>

                <div data-hero-actions class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="<?= e(url('/register')) ?>" class="btn btn-primary group">
                        Create your character
                        <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                    <a href="#how" class="btn btn-ghost">
                        See how it works
                    </a>
                </div>

                <div data-hero-proof class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="check" class="h-3.5 w-3.5 text-aqua-400"></i>
                        Free to use
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5 text-aqua-400"></i>
                        Your quests stay private
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="zap" class="h-3.5 w-3.5 text-aqua-400"></i>
                        Two-minute setup
                    </span>
                </div>
            </div>

            <div data-hero-panel class="relative mx-auto mt-14 w-full max-w-md lg:mt-0 lg:max-w-none">
                <div class="absolute inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-brand-500/25 to-aqua-400/10 blur-3xl" aria-hidden="true"></div>

                <figure
                    class="relative"
                    role="img"
                    aria-label="Preview of the Questify dashboard: a level 12 player with 13,500 XP, a 14-day streak, and three daily quests."
                >
                    <div class="overflow-hidden rounded-3xl border border-white/10 bg-[#0d0d18]/90 shadow-2xl shadow-black/60 backdrop-blur-xl">
                        <div class="flex items-center justify-between border-b border-white/[0.06] px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-aqua-400 shadow-[0_0_12px_rgba(34,211,238,0.8)]"></span>
                                <span class="text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400">Command center</span>
                            </div>
                            <span class="rounded-full border border-white/10 px-2.5 py-1 text-[10px] font-medium text-slate-400">Today</span>
                        </div>

                        <div class="p-5 pb-16">
                            <div class="flex items-center gap-4">
                                <div class="relative h-[4.5rem] w-[4.5rem] shrink-0">
                                    <svg viewBox="0 0 72 72" class="h-full w-full -rotate-90" aria-hidden="true">
                                        <circle cx="36" cy="36" r="30" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="6"/>
                                        <circle
                                            data-meter="ring"
                                            data-value="61"
                                            cx="36"
                                            cy="36"
                                            r="30"
                                            fill="none"
                                            stroke="url(#ring-gradient)"
                                            stroke-width="6"
                                            stroke-linecap="round"
                                            stroke-dasharray="188.5"
                                            stroke-dashoffset="73.5"
                                        />
                                        <defs>
                                            <linearGradient id="ring-gradient" x1="0" y1="0" x2="1" y2="1">
                                                <stop offset="0%" stop-color="#8B5CF6"/>
                                                <stop offset="100%" stop-color="#22D3EE"/>
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="absolute inset-0 grid place-items-center">
                                        <span class="font-display text-lg font-bold text-white">12</span>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate font-display text-base font-semibold text-white">Shadow Runner</p>
                                        <p class="shrink-0 text-[11px] font-medium text-brand-300">13,500 XP</p>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">900 XP to level 13</p>
                                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
                                        <div data-meter="width" data-value="61" class="h-full w-[61%] rounded-full bg-gradient-to-r from-brand-500 via-brand-400 to-aqua-400"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                                <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-300">
                                    <i data-lucide="flame" class="h-4 w-4 text-orange-400"></i>
                                    14-day streak
                                </span>
                                <span class="text-[10px] uppercase tracking-wider text-slate-500">Personal best</span>
                            </div>

                            <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Today&rsquo;s quests</p>

                            <ul class="mt-3 space-y-2">
                                <li class="flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                                    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md border border-aqua-400/40 bg-aqua-400/15 text-aqua-300">
                                        <i data-lucide="check" class="h-3 w-3"></i>
                                    </span>
                                    <span class="flex-1 truncate text-xs text-slate-500 line-through">Finish project proposal</span>
                                    <span class="shrink-0 text-[11px] font-semibold text-aqua-300">+50 XP</span>
                                </li>
                                <li class="flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                                    <span class="h-5 w-5 shrink-0 rounded-md border border-white/15"></span>
                                    <span class="flex-1 truncate text-xs text-slate-300">30-minute workout</span>
                                    <span class="shrink-0 text-[11px] font-semibold text-brand-300">+25 XP</span>
                                </li>
                                <li class="flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                                    <span class="h-5 w-5 shrink-0 rounded-md border border-white/15"></span>
                                    <span class="flex-1 truncate text-xs text-slate-300">Read 20 pages</span>
                                    <span class="shrink-0 text-[11px] font-semibold text-brand-300">+10 XP</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div
                        data-hero-float
                        class="absolute -bottom-7 left-3 w-[17rem] rounded-2xl border border-brand-400/25 bg-panel/95 p-4 shadow-[0_20px_60px_-20px_rgba(139,92,246,0.6)] backdrop-blur-xl sm:-left-6"
                    >
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-500/15 text-brand-300">
                                <i data-lucide="trophy" class="h-4 w-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-brand-300">Achievement unlocked</p>
                                <p class="truncate text-sm font-semibold text-white">Week Warrior</p>
                            </div>
                        </div>
                    </div>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- Loop strip -->
<div class="border-y border-white/[0.05] bg-white/[0.012]">
    <div class="container-page flex flex-wrap items-center justify-center gap-x-6 gap-y-2 py-5 text-[11px] font-medium uppercase tracking-[0.18em] text-slate-500">
        <span>Plan the work</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-slate-700"></i>
        <span>Complete the quest</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-slate-700"></i>
        <span>Earn the XP</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-slate-700"></i>
        <span class="text-brand-300">Level up</span>
    </div>
</div>

<!-- Features -->
<section id="features" class="section">
    <div class="container-page">
        <div class="max-w-2xl" data-reveal>
            <p class="eyebrow">The system</p>
            <h2 class="section-heading mt-5">
                Everything you need to make
                <span class="gradient-text">follow-through</span> feel good.
            </h2>
            <p class="section-copy">
                Not cheap dopamine. A clear loop of quest, reward, and visible progress —
                built to keep you moving after the novelty wears off.
            </p>
        </div>

        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($features as $feature): ?>
                <article data-reveal class="card card-hover group p-6">
                    <span class="icon-tile <?= e($tones[$feature['tone']]) ?>">
                        <i data-lucide="<?= e($feature['icon']) ?>" class="h-5 w-5"></i>
                    </span>
                    <h3 class="mt-5 font-display text-lg font-semibold text-white">
                        <?= e($feature['title']) ?>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        <?= e($feature['copy']) ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How it works -->
<section id="how" class="section border-y border-white/[0.05] bg-white/[0.012]">
    <div class="container-page">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between" data-reveal>
            <div class="max-w-2xl">
                <p class="eyebrow">How it works</p>
                <h2 class="section-heading mt-5">
                    Three steps from
                    <span class="gradient-text">to-do</span> to done.
                </h2>
            </div>
            <p class="max-w-sm text-sm leading-relaxed text-muted">
                No new productivity religion. Keep the tasks you already have — just give
                them a reward structure that makes finishing them feel worth it.
            </p>
        </div>

        <div class="mt-12 grid gap-4 lg:grid-cols-3">
            <?php foreach ($steps as $step): ?>
                <article data-reveal class="card relative overflow-hidden p-6">
                    <span class="pointer-events-none absolute -right-2 -top-4 font-display text-7xl font-bold text-white/[0.03]">
                        <?= e($step['number']) ?>
                    </span>
                    <span class="icon-tile border-brand-400/20 bg-brand-500/10 text-brand-300">
                        <i data-lucide="<?= e($step['icon']) ?>" class="h-5 w-5"></i>
                    </span>
                    <h3 class="mt-5 font-display text-lg font-semibold text-white">
                        <?= e($step['title']) ?>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        <?= e($step['copy']) ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Product preview -->
<section id="preview" class="section">
    <div class="container-page grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
        <div data-reveal>
            <p class="eyebrow">Inside the app</p>
            <h2 class="section-heading mt-5">
                Momentum you can
                <span class="gradient-text">measure</span>.
            </h2>
            <p class="section-copy">
                The dashboard answers one question every day: is this working?
                No vanity metrics — just the work you finished and the progress it produced.
            </p>

            <ul class="mt-8 space-y-4">
                <?php foreach ($highlights as $highlight): ?>
                    <li class="flex gap-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-aqua-500/15 text-aqua-300">
                            <i data-lucide="check" class="h-3 w-3"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-white"><?= e($highlight['title']) ?></p>
                            <p class="mt-0.5 text-sm leading-relaxed text-muted"><?= e($highlight['copy']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <figure data-reveal class="relative">
            <div class="absolute inset-8 -z-10 rounded-full bg-brand-600/15 blur-3xl" aria-hidden="true"></div>

            <div class="rounded-3xl border border-white/10 bg-panel/80 p-5 shadow-2xl shadow-black/50 backdrop-blur-xl sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.16em] text-slate-500">This week</p>
                        <p class="mt-1 font-display text-2xl font-bold text-white">
                            1,480 <span class="text-sm font-medium text-brand-300">XP</span>
                        </p>
                    </div>
                    <span class="rounded-full border border-aqua-400/20 bg-aqua-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-aqua-300">
                        Preview
                    </span>
                </div>

                <div class="mt-6 flex h-32 items-end gap-2.5">
                    <?php foreach ($bars as $bar): ?>
                        <div class="flex h-full flex-1 items-end">
                            <div class="w-full rounded-t-md <?= e($bar['height']) ?> <?= $bar['active'] ? 'bg-gradient-to-t from-aqua-500/50 to-aqua-300 shadow-[0_0_20px_rgba(34,211,238,0.35)]' : 'bg-gradient-to-t from-brand-700/40 to-brand-400/80' ?>"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-2 flex justify-between text-[10px] uppercase tracking-wider text-slate-600">
                    <?php foreach ($bars as $bar): ?>
                        <span class="flex-1 text-center"><?= e($bar['day']) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="mt-6 border-t border-white/[0.06] pt-5">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Weekly leaderboard</p>
                        <span class="text-[10px] text-slate-600">Top 3</span>
                    </div>

                    <ul class="mt-3 space-y-2">
                        <?php foreach ($leaders as $leader): ?>
                            <li class="flex items-center gap-3 rounded-xl border border-white/[0.05] bg-white/[0.02] px-3 py-2">
                                <span class="w-4 text-center text-[11px] font-semibold text-slate-500"><?= e($leader['rank']) ?></span>
                                <span class="grid h-7 w-7 place-items-center rounded-lg border text-[10px] font-bold <?= e($leader['tone']) ?>">
                                    <?= e($leader['initials']) ?>
                                </span>
                                <span class="flex-1 text-xs font-medium text-slate-300"><?= e($leader['name']) ?></span>
                                <span class="text-[11px] font-semibold text-slate-400"><?= e($leader['xp']) ?> XP</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </figure>
    </div>
</section>

<!-- Final CTA -->
<section class="pb-24 pt-4 sm:pb-28">
    <div class="container-page">
        <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-brand-600/20 via-panel to-aqua-500/10 px-6 py-16 text-center sm:px-16">
            <div class="pointer-events-none absolute inset-0 -z-0" aria-hidden="true">
                <div class="absolute left-1/2 top-0 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-brand-500/20 blur-[100px]"></div>
                <div class="absolute inset-0 bg-grid opacity-20 mask-fade-b"></div>
            </div>

            <div class="relative">
                <p class="eyebrow">Your adventure starts now</p>
                <h2 class="mx-auto mt-5 max-w-2xl font-display text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Your next level is one quest away.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-muted">
                    Turn the work you already have to do into progress you can see.
                    Your first quest takes less time than scrolling past this page.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="<?= e(url('/register')) ?>" class="btn btn-primary group w-full sm:w-auto">
                        Create your account
                        <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                    <a href="<?= e(url('/login')) ?>" class="btn btn-ghost w-full sm:w-auto">I already have one</a>
                </div>

                <p class="mt-5 text-xs text-slate-500">Free to use &middot; No credit card &middot; Your quests stay private</p>
            </div>
        </div>
    </div>
</section>
