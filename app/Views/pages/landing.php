<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Marketing landing page, set as an expedition ledger.
 *
 * @var string $title
 * @var string $description
 */

$features = [
    [
        'number' => '01',
        'icon' => 'swords',
        'title' => 'Quests that fit real life',
        'copy' => 'Create quests, set a difficulty, and give every task a clear XP value. Daily and weekly quests reset on your schedule.',
        'tone' => 'brand',
    ],
    [
        'number' => '02',
        'icon' => 'trending-up',
        'title' => 'XP that means something',
        'copy' => 'Every completion feeds one transparent XP ledger. Level up on a curve that always feels one quest away.',
        'tone' => 'aqua',
    ],
    [
        'number' => '03',
        'icon' => 'flame',
        'title' => 'Streaks without the guilt',
        'copy' => 'Streaks are calculated in your timezone and tracked across your history. Miss a day and start a new chapter.',
        'tone' => 'ember',
    ],
    [
        'number' => '04',
        'icon' => 'trophy',
        'title' => 'Achievements worth chasing',
        'copy' => 'Unlock badges for milestones, consistency, and bold pushes. Every badge is earned through real progress.',
        'tone' => 'brand',
    ],
    [
        'number' => '05',
        'icon' => 'crown',
        'title' => 'Leaderboards with context',
        'copy' => 'Compete on weekly, monthly, or all-time XP — and opt out whenever you want. Your quests stay yours.',
        'tone' => 'aqua',
    ],
    [
        'number' => '06',
        'icon' => 'line-chart',
        'title' => 'Progress you can read',
        'copy' => 'See XP trends, category breakdowns, and a completion heatmap so you know what is actually working.',
        'tone' => 'ember',
    ],
];

$tones = [
    'brand' => 'border-brand-400/35 bg-brand-400/[0.08] text-brand-300',
    'aqua' => 'border-aqua-400/35 bg-aqua-500/[0.08] text-aqua-300',
    'ember' => 'border-ember-400/35 bg-ember-500/[0.08] text-ember-300',
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
    ['rank' => 1, 'name' => 'Nova', 'initials' => 'N', 'xp' => '820', 'tone' => 'border-brand-400/45 bg-brand-400/15 text-brand-200'],
    ['rank' => 2, 'name' => 'Kestrel', 'initials' => 'K', 'xp' => '740', 'tone' => 'border-slate-400/35 bg-slate-400/10 text-slate-200'],
    ['rank' => 3, 'name' => 'Orin', 'initials' => 'O', 'xp' => '690', 'tone' => 'border-ember-400/40 bg-ember-500/10 text-ember-200'],
];

$highlights = [
    ['title' => 'A weekly XP trend', 'copy' => 'See the shape of your effort before the week disappears.'],
    ['title' => 'A streak calendar', 'copy' => 'Every completed day, mapped in your own timezone.'],
    ['title' => 'Category breakdowns', 'copy' => 'Know which kinds of work are actually moving you forward.'],
    ['title' => 'A leaderboard with an off switch', 'copy' => 'Compete when you want to. Stay private when you do not.'],
];
?>

<!-- Hero -->
<section class="relative overflow-hidden pb-16 pt-28 sm:pt-32 lg:pb-24 lg:pt-36" data-hero>
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-70 mask-fade-b"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-brand-400/20"></div>
    </div>

    <div class="container-page">
        <div class="grid items-start gap-14 lg:grid-cols-[1.02fr_0.98fr] lg:gap-16">
            <div class="max-w-xl lg:pt-4">
                <p data-hero-eyebrow class="eyebrow">
                    <i data-lucide="compass" class="h-3.5 w-3.5" aria-hidden="true"></i>
                    Expedition ledger · Vol. I
                </p>

                <h1 data-hero-title class="mt-6 font-display text-4xl font-semibold leading-[1.04] tracking-tight text-white sm:text-5xl lg:text-[3.6rem]">
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

                <div data-hero-proof class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 font-mono text-[9px] uppercase tracking-[0.14em] text-muted">
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="check" class="h-3.5 w-3.5 text-aqua-300" aria-hidden="true"></i>
                        Free to use
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5 text-aqua-300" aria-hidden="true"></i>
                        Your quests stay private
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <i data-lucide="zap" class="h-3.5 w-3.5 text-brand-300" aria-hidden="true"></i>
                        Two-minute setup
                    </span>
                </div>
            </div>

            <div data-hero-panel class="relative mx-auto mt-6 w-full max-w-md lg:mt-0 lg:max-w-none">
                <figure
                    class="relative rounded-sm border border-line bg-panel shadow-offset-lg"
                    role="img"
                    aria-label="Preview of the Questify dashboard: a level 12 player with 13,500 XP, a 14-day streak, and three daily quests."
                >
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                        <div class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-[0.18em] text-brand-300">
                            <span class="h-1.5 w-1.5 bg-brand-400"></span>
                            Field report
                        </div>
                        <span class="font-mono text-[9px] uppercase tracking-[0.14em] text-muted-dim">No. 012 · Today</span>
                    </div>

                    <div class="border-l-2 border-l-brand-400/30 p-5 pb-14">
                        <div class="flex items-center gap-4">
                            <div class="relative h-[4.5rem] w-[4.5rem] shrink-0">
                                <svg viewBox="0 0 72 72" class="h-full w-full -rotate-90" aria-hidden="true">
                                    <circle cx="36" cy="36" r="30" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="5"/>
                                    <circle
                                        data-meter="ring"
                                        data-value="61"
                                        cx="36"
                                        cy="36"
                                        r="30"
                                        fill="none"
                                        stroke="#C9993F"
                                        stroke-width="5"
                                        stroke-linecap="butt"
                                        stroke-dasharray="188.5"
                                        stroke-dashoffset="73.5"
                                    />
                                </svg>
                                <div class="absolute inset-0 grid place-items-center">
                                    <span class="font-display text-lg font-semibold text-white">12</span>
                                </div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="truncate font-display text-base font-semibold text-white">Shadow Runner</p>
                                    <p class="shrink-0 font-mono text-[10px] font-semibold text-brand-300">13,500 XP</p>
                                </div>
                                <p class="mt-1 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">900 XP to level 13</p>
                                <div class="mt-3 h-1.5 overflow-hidden bg-white/[0.06]">
                                    <div data-meter="width" data-value="61" class="h-full w-[61%] bg-brand-400"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between border-y border-line py-2.5">
                            <span class="inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.12em] text-slate-300">
                                <i data-lucide="flame" class="h-4 w-4 text-ember-400" aria-hidden="true"></i>
                                14-day streak
                            </span>
                            <span class="font-mono text-[9px] uppercase tracking-[0.12em] text-muted-dim">Personal best</span>
                        </div>

                        <p class="mt-5 font-mono text-[9px] font-medium uppercase tracking-[0.18em] text-muted">Today&rsquo;s ticket queue</p>

                        <ul class="mt-3 border-t border-line">
                            <li class="flex items-center gap-3 border-b border-dashed border-line py-2.5">
                                <span class="grid h-5 w-5 shrink-0 place-items-center border border-aqua-400/45 bg-aqua-500/10 text-aqua-300">
                                    <i data-lucide="check" class="h-3 w-3"></i>
                                </span>
                                <span class="flex-1 truncate text-xs text-muted line-through">Finish project proposal</span>
                                <span class="shrink-0 font-mono text-[10px] font-semibold text-aqua-300">+50 XP</span>
                            </li>
                            <li class="flex items-center gap-3 border-b border-dashed border-line py-2.5">
                                <span class="h-5 w-5 shrink-0 border border-line"></span>
                                <span class="flex-1 truncate text-xs text-slate-300">30-minute workout</span>
                                <span class="shrink-0 font-mono text-[10px] font-semibold text-brand-300">+25 XP</span>
                            </li>
                            <li class="flex items-center gap-3 py-2.5">
                                <span class="h-5 w-5 shrink-0 border border-line"></span>
                                <span class="flex-1 truncate text-xs text-slate-300">Read 20 pages</span>
                                <span class="shrink-0 font-mono text-[10px] font-semibold text-brand-300">+10 XP</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex items-center justify-between border-t border-line bg-ink/60 px-5 py-2.5 font-mono text-[8px] uppercase tracking-[0.16em] text-muted-dim">
                        <span>Ledger balanced</span>
                        <span class="inline-flex items-center gap-1.5 text-brand-300">
                            <i data-lucide="badge-check" class="h-3 w-3" aria-hidden="true"></i>
                            Verified
                        </span>
                    </div>

                    <div
                        data-hero-float
                        class="ledger-seal absolute -bottom-8 -left-4 h-24 w-24 -rotate-6 border-brand-400/55 bg-void/95 text-brand-300 shadow-offset sm:-left-8"
                    >
                        <span class="text-center">
                            <i data-lucide="trophy" class="mx-auto h-5 w-5" aria-hidden="true"></i>
                            <span class="mt-1 block font-mono text-[7px] font-semibold uppercase leading-tight tracking-[0.14em]">Week<br>Warrior</span>
                        </span>
                    </div>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- Loop strip -->
<div class="border-y border-line bg-ink/50">
    <div class="container-page flex flex-wrap items-center justify-center gap-x-5 gap-y-2 py-4 font-mono text-[9px] font-medium uppercase tracking-[0.18em] text-muted">
        <span>Plan the work</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-brand-400/70" aria-hidden="true"></i>
        <span>Complete the quest</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-brand-400/70" aria-hidden="true"></i>
        <span>Earn the XP</span>
        <i data-lucide="chevron-right" class="h-3 w-3 text-brand-400/70" aria-hidden="true"></i>
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

        <div class="mt-12 grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-3" data-reveal>
            <?php foreach ($features as $feature): ?>
                <article class="group bg-ink p-6 transition-colors hover:bg-panel/60">
                    <div class="flex items-start justify-between gap-3">
                        <span class="icon-tile <?= e($tones[$feature['tone']]) ?>">
                            <i data-lucide="<?= e($feature['icon']) ?>" class="h-5 w-5"></i>
                        </span>
                        <span class="font-mono text-[9px] font-medium tracking-[0.18em] text-muted-dim"><?= e($feature['number']) ?></span>
                    </div>
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
<section id="how" class="section border-y border-line bg-ink/40">
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

        <ol class="mt-12 grid gap-8 lg:grid-cols-3 lg:gap-10" data-reveal>
            <?php foreach ($steps as $index => $step): ?>
                <li class="relative border-t border-brand-400/35 pt-6">
                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-2 font-mono text-[9px] font-medium uppercase tracking-[0.2em] text-brand-300">
                            <i data-lucide="<?= e($step['icon']) ?>" class="h-4 w-4" aria-hidden="true"></i>
                            Step <?= e($step['number']) ?>
                        </span>
                        <span class="font-display text-3xl font-semibold text-brand-400/70" aria-hidden="true"><?= e($step['number']) ?></span>
                    </div>
                    <h3 class="mt-4 font-display text-xl font-semibold text-white">
                        <?= e($step['title']) ?>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        <?= e($step['copy']) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ol>
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

            <ul class="mt-8 border-t border-line">
                <?php foreach ($highlights as $highlight): ?>
                    <li class="flex gap-3 border-b border-line py-4">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center border border-brand-400/35 bg-brand-400/10 text-brand-300">
                            <i data-lucide="check" class="h-3 w-3" aria-hidden="true"></i>
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
            <div class="rounded-sm border border-line bg-panel shadow-offset-lg">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <p class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted">Week 45 · XP ledger</p>
                        <p class="mt-1 font-display text-2xl font-semibold text-white">
                            1,480 <span class="font-mono text-sm font-medium text-brand-300">XP</span>
                        </p>
                    </div>
                    <span class="rotate-2 border border-aqua-400/40 px-2 py-1 font-mono text-[8px] font-semibold uppercase tracking-[0.16em] text-aqua-300">
                        Preview
                    </span>
                </div>

                <div class="px-5 pt-6">
                    <div class="flex h-32 items-end gap-2.5 border-b border-line pb-0">
                        <?php foreach ($bars as $bar): ?>
                            <div class="flex h-full flex-1 items-end">
                                <div class="w-full <?= e($bar['height']) ?> <?= $bar['active'] ? 'bg-brand-400' : 'bg-brand-400/30' ?>"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-2 flex justify-between font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">
                        <?php foreach ($bars as $bar): ?>
                            <span class="flex-1 text-center"><?= e($bar['day']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-6 border-t border-line px-5 py-5">
                    <div class="flex items-center justify-between">
                        <p class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted">Weekly leaderboard</p>
                        <span class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">Top 3</span>
                    </div>

                    <ul class="mt-3 border-t border-line">
                        <?php foreach ($leaders as $leader): ?>
                            <li class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0">
                                <span class="w-4 text-center font-mono text-[10px] font-semibold text-muted"><?= e((string) $leader['rank']) ?></span>
                                <span class="grid h-7 w-7 place-items-center rounded-sm border font-mono text-[10px] font-bold <?= e($leader['tone']) ?>">
                                    <?= e($leader['initials']) ?>
                                </span>
                                <span class="flex-1 text-xs font-medium text-slate-300"><?= e($leader['name']) ?></span>
                                <span class="font-mono text-[10px] font-semibold text-slate-400"><?= e($leader['xp']) ?> XP</span>
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
        <div class="relative overflow-hidden rounded-sm border border-line bg-ink shadow-offset-lg">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute inset-0 bg-grid opacity-50"></div>
                <div class="absolute inset-y-0 left-0 w-2 bg-brand-400/70"></div>
            </div>

            <div class="relative px-6 py-16 text-center sm:px-16">
                <p class="eyebrow">Your adventure starts now</p>
                <h2 class="mx-auto mt-5 max-w-2xl font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-5xl">
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

                <p class="mt-5 font-mono text-[9px] uppercase tracking-[0.14em] text-muted">Free to use &middot; No credit card &middot; Your quests stay private</p>
            </div>
        </div>
    </div>
</section>
