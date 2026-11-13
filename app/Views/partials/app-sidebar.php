<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authenticated sidebar. On desktop it collapses to an icon rail; below the lg
 * breakpoint the same element becomes an off-canvas drawer.
 *
 * @var array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string, phase: int|null, exact?: bool}>}> $appNav
 * @var array<string, mixed> $currentUser
 * @var string $displayName
 * @var string $username
 * @var string $initial
 * @var array<string, mixed> $progression
 * @var string|null $avatarUrl
 * @var string $sidebarState
 */
?>
<aside
    id="app-sidebar"
    data-sidebar-panel
    class="app-sidebar border-r border-line bg-ink"
    aria-label="Primary navigation"
>
    <div class="flex h-full min-h-0 flex-col">
        <div class="app-sidebar-head flex h-16 shrink-0 items-center justify-between gap-2 border-b border-line px-4">
            <a href="<?= e(url('/dashboard')) ?>" class="app-sidebar-brand flex min-w-0 items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-sm border border-brand-500 bg-brand-400 shadow-offset">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-[#14100B]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                    </svg>
                </span>
                <span data-sidebar-label class="min-w-0">
                    <span class="block truncate font-display text-[17px] font-semibold leading-tight tracking-tight text-white">Questify</span>
                    <span class="block font-mono text-[8px] font-medium uppercase tracking-[0.24em] text-brand-300/90">Field ledger</span>
                </span>
            </a>

            <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-sm text-slate-400 transition hover:bg-brand-400/[0.06] hover:text-white lg:hidden"
                data-sidebar-close
                aria-label="Close navigation"
            >
                <i data-lucide="x" class="h-[18px] w-[18px]"></i>
            </button>
        </div>

        <button
            type="button"
            class="app-sidebar-toggle hidden lg:grid"
            data-sidebar-collapse
            aria-label="<?= $sidebarState === 'collapsed' ? 'Expand sidebar' : 'Collapse sidebar' ?>"
        >
            <span data-when-expanded><i data-lucide="chevron-left" class="h-3.5 w-3.5"></i></span>
            <span data-when-collapsed><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></span>
        </button>

        <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4" aria-label="Main">
            <?php foreach ($appNav as $section): ?>
                <div class="mb-5 last:mb-0">
                    <p data-sidebar-label class="mb-2 border-b border-line px-3 pb-1.5 font-mono text-[8px] font-medium uppercase tracking-[0.22em] text-muted-dim">
                        <?= e($section['label']) ?>
                    </p>

                    <ul class="space-y-1">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php
                            $active = nav_active($item['href'], (bool) ($item['exact'] ?? false));
                            $phase = $item['phase'];
                            $tooltip = $item['label'] . ($phase !== null ? ' — planned for Phase ' . $phase : '');
                            ?>
                            <li>
                                <a
                                    href="<?= e(url($item['href'])) ?>"
                                    class="app-nav-link group <?= $active
                                        ? 'border-brand-400/35 bg-brand-400/[0.08] text-white'
                                        : 'border-transparent text-slate-400 hover:border-line hover:bg-white/[0.03] hover:text-white' ?>"
                                    title="<?= e($tooltip) ?>"
                                    <?= $active ? 'aria-current="page"' : '' ?>
                                >
                                    <span class="app-nav-indicator <?= $active ? 'bg-brand-400' : '' ?>" aria-hidden="true"></span>

                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-sm border transition-colors <?= $active
                                        ? 'border-brand-400/35 bg-brand-400/10 text-brand-200'
                                        : 'border-transparent text-muted group-hover:text-slate-200' ?>">
                                        <i data-lucide="<?= e($item['icon']) ?>" class="h-[18px] w-[18px]"></i>
                                    </span>

                                    <span data-sidebar-label class="truncate"><?= e($item['label']) ?></span>

                                    <?php if ($phase !== null): ?>
                                        <span
                                            data-sidebar-badge
                                            class="ml-auto rounded-sm border border-line bg-white/[0.03] px-1.5 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.12em] text-muted"
                                        >
                                            Soon
                                        </span>
                                    <?php elseif ($item['href'] === '/notifications' && isset($unreadNotifications) && $unreadNotifications > 0): ?>
                                        <span
                                            data-sidebar-badge
                                            data-unread-count
                                            class="ml-auto rounded-sm border border-ember-400/40 bg-ember-500/15 px-1.5 py-0.5 font-mono text-[9px] font-bold tabular-nums text-ember-200"
                                        >
                                            <?= e($unreadNotifications > 99 ? '99+' : (string) $unreadNotifications) ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="shrink-0 border-t border-line p-3">
            <div data-sidebar-label class="mb-3 rounded-sm border border-line bg-panel/50 px-3 py-2.5">
                <div class="flex items-baseline justify-between gap-2 font-mono text-[9px] uppercase tracking-[0.12em]">
                    <span class="font-medium text-muted">Level <?= e($progression['level']) ?></span>
                    <span class="text-muted-dim"><?= e(number_format($progression['xp'])) ?> XP</span>
                </div>
                <div class="mt-2 h-1 overflow-hidden bg-white/[0.06]">
                    <div
                        class="h-full bg-brand-400"
                        style="width: <?= e((string) $progression['progress']) ?>%"
                    ></div>
                </div>
                <p class="mt-1.5 font-mono text-[9px] uppercase tracking-[0.12em] text-muted-dim"><?= e($progression['rank']) ?> rank</p>
            </div>

            <div class="app-sidebar-user-row flex items-center gap-2">
                <a
                    href="<?= e(url('/profile')) ?>"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-sm border border-line bg-panel/40 p-2 transition hover:border-brand-400/40 hover:bg-panel"
                    title="<?= e($displayName . ' — profile') ?>"
                >
                    <?php if (isset($avatarUrl)): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="h-8 w-8 shrink-0 rounded-sm border border-line object-cover">
                    <?php else: ?>
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-sm border border-brand-500 bg-brand-400 font-mono text-[11px] font-bold text-[#14100B]">
                            <?= e($initial) ?>
                        </span>
                    <?php endif; ?>
                    <span data-sidebar-label class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-slate-200"><?= e($displayName) ?></span>
                        <span class="block truncate font-mono text-[9px] text-muted-dim">@<?= e($username) ?></span>
                    </span>
                    <i data-sidebar-label data-lucide="chevron-right" class="h-3.5 w-3.5 shrink-0 text-muted-dim"></i>
                </a>

                <form method="POST" action="<?= e(url('/logout')) ?>" class="shrink-0">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="grid h-9 w-9 place-items-center rounded-sm border border-line bg-panel/40 text-muted transition hover:border-ember-400/40 hover:bg-ember-500/10 hover:text-ember-200"
                        title="Sign out"
                        aria-label="Sign out"
                    >
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </button>
                </form>
            </div>

            <p data-sidebar-label class="mt-2.5 flex items-center gap-1.5 px-1 font-mono text-[8px] uppercase tracking-[0.12em] text-muted-dim">
                <i data-lucide="info" class="h-3 w-3 shrink-0"></i>
                Phase 6 · admin controls live
            </p>
        </div>
    </div>
</aside>
