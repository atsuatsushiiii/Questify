<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Mobile primary navigation. Settings and the full account section remain in
 * the drawer.
 *
 * @var array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string, phase: int|null}>}> $appNav
 */
$bottomItems = [];

foreach ($appNav as $section) {
    foreach ($section['items'] as $item) {
        if (in_array($item['href'], ['/dashboard', '/quests', '/achievements', '/leaderboard', '/profile'], true)) {
            $bottomItems[] = $item;
        }
    }
}
?>
<nav class="app-bottom-nav lg:hidden" aria-label="Primary mobile navigation">
    <div class="grid grid-cols-5 gap-1 px-2 pb-2 pt-1.5">
        <?php foreach ($bottomItems as $item): ?>
            <?php $active = nav_active($item['href']); ?>
            <a
                href="<?= e(url($item['href'])) ?>"
                class="relative flex flex-col items-center gap-1 rounded-xl px-0.5 py-1.5 text-[9px] font-medium tracking-tight transition <?= $active
                    ? 'text-gold-300'
                    : 'text-slate-500 hover:bg-white/[0.04] hover:text-slate-200' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>
                title="<?= e($item['label'] . ($item['phase'] !== null ? ' — Phase ' . $item['phase'] : '')) ?>"
            >
                <span class="<?= $active ? 'text-gold-300' : '' ?>">
                    <i data-lucide="<?= e($item['icon']) ?>" class="h-[19px] w-[19px]"></i>
                </span>
                <span class="max-w-full truncate"><?= e($item['label'] === 'My Profile' ? 'Profile' : $item['label']) ?></span>
                <?php if ($active): ?>
                    <span class="absolute -top-1.5 h-0.5 w-6 rounded-full bg-gradient-to-r from-ember-400 to-gold-400" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
