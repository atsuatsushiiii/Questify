<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Quest journal: active, completed and archived tabs.
 *
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $quests
 * @var array{active: int, completed: int, archived: int} $counts
 * @var string $status
 * @var string $difficulty
 * @var string $category
 * @var list<array<string, mixed>> $categories
 * @var array<string, int> $rewards
 * @var int $questXp
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$tabs = [
    'active' => ['label' => 'Active', 'icon' => 'target'],
    'completed' => ['label' => 'Completed', 'icon' => 'circle-check'],
    'archived' => ['label' => 'Archived', 'icon' => 'archive'],
];

$emptyStates = [
    'active' => [
        'title' => 'No active quests',
        'copy' => 'Your quest log is clear. Add an objective, choose a difficulty, and turn finished work into XP.',
        'cta' => 'Create your first quest',
        'href' => '/quests/create',
    ],
    'completed' => [
        'title' => 'Nothing completed yet',
        'copy' => 'Finish an active quest and it will move here with the XP you earned. Only a completed quest can bank a reward.',
        'cta' => 'View active quests',
        'href' => '/quests?status=active',
    ],
    'archived' => [
        'title' => 'Archive is empty',
        'copy' => 'Archived quests wait here without cluttering your active log. XP already earned stays on your account.',
        'cta' => 'View active quests',
        'href' => '/quests?status=active',
    ],
];

$empty = $emptyStates[$status] ?? $emptyStates['active'];
$activeTab = $tabs[$status] ?? $tabs['active'];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <p class="eyebrow">
            <i data-lucide="scroll-text" class="h-3.5 w-3.5"></i>
            Quest journal
        </p>
        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
            My quests
        </h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Every quest is a promise to yourself. Pick a difficulty, finish the work, and claim the reward.
            One-off quests complete permanently; recurring quests return each day, week, or month.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/quests/create')) ?>" class="btn btn-app-primary group">
            <i data-lucide="plus" class="h-4 w-4 transition-transform duration-200 group-hover:rotate-90"></i>
            New quest
        </a>
        <a href="<?= e(url('/dashboard')) ?>" class="btn btn-app-ghost">
            <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
            Dashboard
        </a>
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Active</p>
            <span class="app-stat-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="target" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e((string) $counts['active']) ?></p>
        <p class="app-stat-note">Ready to be completed.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Completed</p>
            <span class="app-stat-icon border-aqua-400/30 bg-aqua-500/10 text-aqua-300">
                <i data-lucide="circle-check" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e((string) $counts['completed']) ?></p>
        <p class="app-stat-note">Finished and rewarded.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Archived</p>
            <span class="app-stat-icon border-slate-400/20 bg-slate-400/10 text-slate-300">
                <i data-lucide="archive" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e((string) $counts['archived']) ?></p>
        <p class="app-stat-note">Parked, not deleted.</p>
    </article>

    <article class="app-stat-card">
        <div class="flex items-center justify-between gap-3">
            <p class="app-stat-label">Quest XP</p>
            <span class="app-stat-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                <i data-lucide="zap" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="app-stat-value"><?= e(number_format($questXp)) ?></p>
        <p class="app-stat-note">Earned from completed quests.</p>
    </article>
</section>

<section class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between" data-reveal>
    <nav class="quest-tabs" aria-label="Quest status">
        <?php foreach ($tabs as $key => $tab): ?>
            <?php $isActive = $key === $status; ?>
            <a
                href="<?= e(url('/quests?status=' . $key
                    . ($difficulty !== '' ? '&difficulty=' . rawurlencode($difficulty) : '')
                    . ($category !== '' ? '&category=' . rawurlencode($category) : ''))) ?>"
                class="quest-tab <?= $isActive ? 'is-active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="<?= e($tab['icon']) ?>" class="h-3.5 w-3.5"></i>
                <span><?= e($tab['label']) ?></span>
                <span class="quest-tab-count"><?= e((string) $counts[$key]) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="GET" action="<?= e(url('/quests')) ?>" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php if ($categories !== []): ?>
            <label for="category-filter" class="sr-only">Filter by category</label>
            <div class="relative">
                <i data-lucide="folder-tree" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted"></i>
                <select
                    id="category-filter"
                    name="category"
                    class="app-select py-2 pl-9 pr-8 text-xs"
                    data-quest-filter
                >
                    <option value="">All categories</option>
                    <?php foreach ($categories as $option): ?>
                        <option value="<?= e((string) $option['slug']) ?>" <?= $category === (string) $option['slug'] ? 'selected' : '' ?>>
                            <?= e((string) $option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <label for="difficulty-filter" class="sr-only">Filter by difficulty</label>
        <div class="relative">
            <i data-lucide="filter" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted"></i>
            <select
                id="difficulty-filter"
                name="difficulty"
                class="app-select py-2 pl-9 pr-8 text-xs"
                data-quest-filter
            >
                <option value="">All difficulties</option>
                <?php foreach ($rewards as $key => $reward): ?>
                    <option value="<?= e($key) ?>" <?= $difficulty === $key ? 'selected' : '' ?>>
                        <?= e(ucfirst($key)) ?> · <?= e((string) $reward) ?> XP
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-app-ghost px-3 py-2 text-xs">Apply</button>
        <?php if ($difficulty !== '' || $category !== ''): ?>
            <a href="<?= e(url('/quests?status=' . $status)) ?>" class="text-[11px] font-semibold text-muted transition hover:text-brand-300">
                Clear
            </a>
        <?php endif; ?>
    </form>
</section>

<section class="mt-5">
    <?php if ($quests === []): ?>
        <div class="relative overflow-hidden rounded-sm border border-dashed border-line bg-panel/30 px-6 py-12 text-center sm:py-16" data-reveal>
            <div class="pointer-events-none absolute inset-0 bg-grid opacity-40" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-md">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-sm border border-line bg-white/[0.03] text-brand-300">
                    <i data-lucide="<?= e($activeTab['icon']) ?>" class="h-6 w-6"></i>
                </span>
                <h2 class="mt-5 font-display text-xl font-semibold text-white"><?= e($empty['title']) ?></h2>
                <p class="mt-2 text-sm leading-relaxed text-muted"><?= e($empty['copy']) ?></p>
                <a href="<?= e(url($empty['href'])) ?>" class="btn btn-app-primary mt-5 inline-flex">
                    <i data-lucide="<?= $empty['href'] === '/quests/create' ? 'plus' : 'target' ?>" class="h-4 w-4"></i>
                    <?= e($empty['cta']) ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <?php foreach ($quests as $quest): ?>
                <?php require __DIR__ . '/../../partials/quest-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
