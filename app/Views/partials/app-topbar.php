<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Authenticated top bar: mobile drawer trigger, context, XP chip and profile
 * menu.
 *
 * @var string $displayName
 * @var string $username
 * @var string $email
 * @var string $initial
 * @var string|null $avatarUrl
 * @var int $unreadNotifications
 * @var array<string, mixed> $progression
 * @var string $pageHeading
 */
?>
<header class="sticky top-0 z-30 border-b border-line bg-ink/90 backdrop-blur-md">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <button
            type="button"
            class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-line bg-panel/40 text-slate-300 transition hover:border-brand-400/50 hover:text-white lg:hidden"
            data-sidebar-open
            aria-controls="app-sidebar"
            aria-expanded="false"
            aria-label="Open navigation"
        >
            <i data-lucide="menu" class="h-5 w-5"></i>
        </button>

        <a href="<?= e(url('/dashboard')) ?>" class="flex items-center gap-2.5 lg:hidden" aria-label="Questify dashboard">
            <span class="grid h-8 w-8 place-items-center rounded-sm border border-brand-500 bg-brand-400 text-[#14100B]">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="font-display text-base font-semibold tracking-tight text-white">Questify</span>
        </a>

        <div class="hidden min-w-0 items-center gap-2 lg:flex">
            <span class="font-mono text-[9px] font-medium uppercase tracking-[0.2em] text-muted-dim">Field ledger</span>
            <span class="h-3 w-px bg-line" aria-hidden="true"></span>
            <span class="truncate text-xs font-medium text-slate-300"><?= e($pageHeading) ?></span>
        </div>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <a
                href="<?= e(url('/dashboard')) ?>"
                class="hidden items-center gap-2.5 rounded-sm border border-line bg-panel/40 py-1.5 pl-2 pr-3 transition hover:border-brand-400/40 hover:bg-panel sm:flex"
                title="Level <?= e($progression['level']) ?> · <?= e(number_format($progression['xp'])) ?> total XP"
            >
                <span class="grid h-7 w-7 place-items-center rounded-sm border border-brand-400/35 bg-brand-400/10 text-brand-300">
                    <i data-lucide="zap" class="h-3.5 w-3.5"></i>
                </span>
                <span class="leading-tight">
                    <span class="block font-mono text-[8px] font-medium uppercase tracking-[0.16em] text-muted-dim">Total XP</span>
                    <span class="block font-mono text-xs font-semibold tabular-nums text-slate-200"><?= e(number_format($progression['xp'])) ?></span>
                </span>
            </a>

            <div class="relative flex items-center gap-2 sm:gap-3" data-user-menu>
                <a
                    href="<?= e(url('/notifications')) ?>"
                    class="relative grid h-10 w-10 place-items-center rounded-sm border border-line bg-panel/40 text-slate-400 transition hover:border-brand-400/40 hover:text-brand-200"
                    aria-label="Notifications<?= $unreadNotifications > 0 ? ' ' . (int) $unreadNotifications . ' unread' : '' ?>"
                    title="Notifications"
                >
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="absolute -right-1.5 -top-1.5 grid h-5 min-w-[1.25rem] place-items-center rounded-sm border border-ink bg-ember-600 px-1 font-mono text-[9px] font-bold tabular-nums text-white" data-unread-count aria-hidden="true">
                            <?= e($unreadNotifications > 99 ? '99+' : (string) $unreadNotifications) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <button
                    type="button"
                    class="flex items-center gap-2.5 rounded-sm border border-line bg-panel/40 py-1.5 pl-1.5 pr-2.5 text-left transition hover:border-brand-400/40 hover:bg-panel sm:pr-3"
                    data-user-menu-toggle
                    aria-controls="user-menu"
                    aria-expanded="false"
                >
                    <?php if ($avatarUrl !== null): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="h-8 w-8 shrink-0 rounded-sm border border-line object-cover">
                    <?php else: ?>
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-sm border border-brand-500 bg-brand-400 font-mono text-[11px] font-bold text-[#14100B]">
                            <?= e($initial) ?>
                        </span>
                    <?php endif; ?>
                    <span class="hidden min-w-0 leading-tight sm:block">
                        <span class="block max-w-[9rem] truncate text-xs font-semibold text-slate-200"><?= e($displayName) ?></span>
                        <span class="block font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">Level <?= e($progression['level']) ?> · <?= e($progression['rank']) ?></span>
                    </span>
                    <i data-user-menu-chevron data-lucide="chevron-down" class="h-3.5 w-3.5 shrink-0 text-muted transition-transform duration-200"></i>
                </button>

                <div
                    id="user-menu"
                    data-user-menu-panel
                    class="absolute right-0 mt-2 w-72 origin-top-right overflow-hidden rounded-sm border border-line bg-surface shadow-offset-lg"
                    hidden
                >
                    <div class="border-b border-line px-4 py-3.5">
                        <div class="flex items-center gap-3">
                            <?php if ($avatarUrl !== null): ?>
                                <img src="<?= e($avatarUrl) ?>" alt="" class="h-10 w-10 shrink-0 rounded-sm border border-line object-cover">
                            <?php else: ?>
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-brand-500 bg-brand-400 font-mono text-sm font-bold text-[#14100B]">
                                    <?= e($initial) ?>
                                </span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white"><?= e($displayName) ?></p>
                                <p class="truncate font-mono text-[10px] text-muted"><?= e($email) ?></p>
                            </div>
                        </div>
                        <p class="mt-2.5 inline-flex items-center gap-1.5 rounded-sm border border-brand-400/30 bg-brand-400/[0.08] px-2 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.14em] text-brand-300">
                            <i data-lucide="shield-check" class="h-3 w-3"></i>
                            <?= e($progression['rank']) ?> · Level <?= e($progression['level']) ?>
                        </p>
                    </div>

                    <div class="p-1.5">
                        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                            <a href="<?= e(url('/admin')) ?>" class="flex items-center gap-3 rounded-sm px-3 py-2.5 text-sm text-brand-200 transition hover:bg-brand-400/[0.08] hover:text-brand-100">
                                <i data-lucide="shield-check" class="h-4 w-4 text-brand-400"></i>
                                <span class="flex-1">Admin console</span>
                                <span class="rounded-sm border border-brand-400/30 bg-brand-400/10 px-1.5 py-0.5 font-mono text-[8px] font-medium uppercase tracking-[0.12em] text-brand-300">Admin</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= e(url('/profile')) ?>" class="flex items-center gap-3 rounded-sm px-3 py-2.5 text-sm text-slate-300 transition hover:bg-brand-400/[0.06] hover:text-white">
                            <i data-lucide="user-round" class="h-4 w-4 text-muted"></i>
                            <span class="flex-1">My Profile</span>
                        </a>
                        <a href="<?= e(url('/reports')) ?>" class="flex items-center gap-3 rounded-sm px-3 py-2.5 text-sm text-slate-300 transition hover:bg-brand-400/[0.06] hover:text-white">
                            <i data-lucide="flag" class="h-4 w-4 text-muted"></i>
                            <span class="flex-1">My Reports</span>
                        </a>
                        <a href="<?= e(url('/settings')) ?>" class="flex items-center gap-3 rounded-sm px-3 py-2.5 text-sm text-slate-300 transition hover:bg-brand-400/[0.06] hover:text-white">
                            <i data-lucide="settings" class="h-4 w-4 text-muted"></i>
                            <span class="flex-1">Settings</span>
                        </a>
                    </div>

                    <div class="border-t border-line p-1.5">
                        <form method="POST" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                class="flex w-full items-center gap-3 rounded-sm px-3 py-2.5 text-sm text-slate-400 transition hover:bg-ember-500/10 hover:text-ember-200"
                            >
                                <i data-lucide="log-out" class="h-4 w-4"></i>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
