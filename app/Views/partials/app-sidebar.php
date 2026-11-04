<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authenticated sidebar. On desktop it collapses to an icon rail; below the lg
 * breakpoint the same element becomes an off-canvas drawer.
 *
 * @var array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string, phase: int|null}>}> $appNav
 * @var array<string, mixed> $currentUser
 * @var string $displayName
 * @var string $username
 * @var string $initial
 * @var array<string, mixed> $progression
 */
?>
<aside
    id="app-sidebar"
    data-sidebar-panel
    class="app-sidebar border-r border-white/[0.06] bg-[#0A0910]/95 backdrop-blur-xl"
    aria-label="Primary navigation"
>
    <div class="flex h-full min-h-0 flex-col">
        <div class="app-sidebar-head flex h-16 shrink-0 items-center justify-between gap-2 px-4">
            <a href="<?= e(url('/dashboard')) ?>" class="app-sidebar-brand flex min-w-0 items-center gap-3" aria-label="Questify dashboard">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-ember-500 via-ember-600 to-gold-400 shadow-[0_0_26px_-6px_rgba(239,68,68,0.75)]">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                    </svg>
                </span>
                <span data-sidebar-label class="min-w-0">
                    <span class="block truncate font-display text-[17px] font-bold leading-tight tracking-tight text-white">Questify</span>
                    <span class="block text-[9px] font-semibold uppercase tracking-[0.22em] text-gold-400/80">Player console</span>
                </span>
            </a>

            <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-white/[0.06] hover:text-white lg:hidden"
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
                    <p data-sidebar-label class="mb-2 px-3 text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-600">
                        <?= e($section['label']) ?>
                    </p>

                    <ul class="space-y-1">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php
                            $active = nav_active($item['href']);
                            $phase = $item['phase'];
                            $tooltip = $item['label'] . ($phase !== null ? ' — planned for Phase ' . $phase : '');
                            ?>
                            <li>
                                <a
                                    href="<?= e(url($item['href'])) ?>"
                                    class="app-nav-link group <?= $active
                                        ? 'border-ember-400/25 bg-gradient-to-r from-ember-500/[0.16] via-ember-500/[0.06] to-transparent text-white'
                                        : 'border-transparent text-slate-400 hover:border-white/[0.07] hover:bg-white/[0.04] hover:text-white' ?>"
                                    title="<?= e($tooltip) ?>"
                                    <?= $active ? 'aria-current="page"' : '' ?>
                                >
                                    <span class="app-nav-indicator <?= $active ? 'bg-gradient-to-b from-ember-400 to-gold-400' : '' ?>" aria-hidden="true"></span>

                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border transition-colors <?= $active
                                        ? 'border-ember-400/25 bg-ember-500/10 text-gold-300'
                                        : 'border-transparent text-slate-500 group-hover:text-slate-200' ?>">
                                        <i data-lucide="<?= e($item['icon']) ?>" class="h-[18px] w-[18px]"></i>
                                    </span>

                                    <span data-sidebar-label class="truncate"><?= e($item['label']) ?></span>

                                    <?php if ($phase !== null): ?>
                                        <span
                                            data-sidebar-badge
                                            class="ml-auto rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-gold-300"
                                        >
                                            Soon
                                        </span>
                                    <?php elseif ($item['href'] === '/notifications' && isset($unreadNotifications) && $unreadNotifications > 0): ?>
                                        <span
                                            data-sidebar-badge
                                            data-unread-count
                                            class="ml-auto rounded-full border border-ember-400/30 bg-ember-500/15 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-ember-200"
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

        <div class="shrink-0 border-t border-white/[0.06] p-3">
            <div data-sidebar-label class="mb-3 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5">
                <div class="flex items-baseline justify-between gap-2 text-[10px]">
                    <span class="font-semibold uppercase tracking-[0.14em] text-slate-500">Level <?= e($progression['level']) ?></span>
                    <span class="text-slate-600"><?= e(number_format($progression['xp'])) ?> XP</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/[0.06]">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-ember-500 to-gold-400"
                        style="width: <?= e((string) $progression['progress']) ?>%"
                    ></div>
                </div>
                <p class="mt-1.5 text-[10px] text-slate-600"><?= e($progression['rank']) ?> rank</p>
            </div>

            <div class="app-sidebar-user-row flex items-center gap-2">
                <a
                    href="<?= e(url('/profile')) ?>"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] p-2 transition hover:border-gold-400/20 hover:bg-white/[0.04]"
                    title="<?= e($displayName . ' — profile') ?>"
                >
                    <?php if (isset($avatarUrl) && $avatarUrl !== null): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="h-8 w-8 shrink-0 rounded-lg border border-white/10 object-cover">
                    <?php else: ?>
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-ember-500 to-gold-400 text-[11px] font-bold text-white">
                            <?= e($initial) ?>
                        </span>
                    <?php endif; ?>
                    <span data-sidebar-label class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-slate-200"><?= e($displayName) ?></span>
                        <span class="block truncate text-[10px] text-slate-600">@<?= e($username) ?></span>
                    </span>
                    <i data-sidebar-label data-lucide="chevron-right" class="h-3.5 w-3.5 shrink-0 text-slate-600"></i>
                </a>

                <form method="POST" action="<?= e(url('/logout')) ?>" class="shrink-0">
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-white/[0.06] bg-white/[0.02] text-slate-500 transition hover:border-rose-400/25 hover:bg-rose-500/10 hover:text-rose-200"
                        title="Sign out"
                        aria-label="Sign out"
                    >
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </button>
                </form>
            </div>

            <p data-sidebar-label class="mt-2.5 flex items-center gap-1.5 px-1 text-[9px] text-slate-700">
                <i data-lucide="info" class="h-3 w-3 shrink-0"></i>
                Phase 6 · admin controls live
            </p>
        </div>
    </div>
</aside>
