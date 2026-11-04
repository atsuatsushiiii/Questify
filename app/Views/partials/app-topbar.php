<?php
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
<header class="sticky top-0 z-30 border-b border-white/[0.06] bg-[#08070D]/80 backdrop-blur-xl">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <button
            type="button"
            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/[0.07] bg-white/[0.03] text-slate-300 transition hover:border-gold-400/25 hover:text-white lg:hidden"
            data-sidebar-open
            aria-controls="app-sidebar"
            aria-expanded="false"
            aria-label="Open navigation"
        >
            <i data-lucide="menu" class="h-5 w-5"></i>
        </button>

        <a href="<?= e(url('/dashboard')) ?>" class="flex items-center gap-2.5 lg:hidden" aria-label="Questify dashboard">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-ember-500 to-gold-400 text-white">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="font-display text-base font-bold tracking-tight text-white">Questify</span>
        </a>

        <div class="hidden min-w-0 items-center gap-2 lg:flex">
            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-600">Command center</span>
            <i data-lucide="chevron-right" class="h-3 w-3 text-slate-700"></i>
            <span class="truncate text-xs font-medium text-slate-300"><?= e($pageHeading) ?></span>
        </div>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <a
                href="<?= e(url('/dashboard')) ?>"
                class="hidden items-center gap-2.5 rounded-xl border border-white/[0.07] bg-white/[0.025] py-1.5 pl-2 pr-3 transition hover:border-gold-400/25 hover:bg-white/[0.04] sm:flex"
                title="Level <?= e($progression['level']) ?> · <?= e(number_format($progression['xp'])) ?> total XP"
            >
                <span class="grid h-7 w-7 place-items-center rounded-lg border border-gold-400/20 bg-gold-400/10 text-gold-300">
                    <i data-lucide="zap" class="h-3.5 w-3.5"></i>
                </span>
                <span class="leading-tight">
                    <span class="block text-[9px] font-semibold uppercase tracking-[0.14em] text-slate-600">Total XP</span>
                    <span class="block text-xs font-semibold text-slate-200"><?= e(number_format($progression['xp'])) ?></span>
                </span>
            </a>

            <div class="relative" data-user-menu>
                <a
                    href="<?= e(url('/notifications')) ?>"
                    class="relative grid h-10 w-10 place-items-center rounded-xl border border-white/[0.07] bg-white/[0.025] text-slate-400 transition hover:border-gold-400/25 hover:bg-white/[0.04] hover:text-gold-200"
                    aria-label="Notifications<?= $unreadNotifications > 0 ? ' (' . (int) $unreadNotifications . ' unread)' : '' ?>"
                    title="Notifications"
                >
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="absolute -right-1 -top-1 grid h-5 min-w-[1.25rem] place-items-center rounded-full border border-[#0A0910] bg-ember-500 px-1 text-[9px] font-bold tabular-nums text-white" data-unread-count>
                            <?= e($unreadNotifications > 99 ? '99+' : (string) $unreadNotifications) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <button
                    type="button"
                    class="flex items-center gap-2.5 rounded-xl border border-white/[0.07] bg-white/[0.025] py-1.5 pl-1.5 pr-2.5 text-left transition hover:border-gold-400/25 hover:bg-white/[0.04] sm:pr-3"
                    data-user-menu-toggle
                    aria-controls="user-menu"
                    aria-expanded="false"
                >
                    <?php if ($avatarUrl !== null): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="h-8 w-8 shrink-0 rounded-lg border border-white/10 object-cover">
                    <?php else: ?>
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-ember-500 to-gold-400 text-[11px] font-bold text-white">
                            <?= e($initial) ?>
                        </span>
                    <?php endif; ?>
                    <span class="hidden min-w-0 leading-tight sm:block">
                        <span class="block max-w-[9rem] truncate text-xs font-semibold text-slate-200"><?= e($displayName) ?></span>
                        <span class="block text-[10px] text-slate-600">Level <?= e($progression['level']) ?> · <?= e($progression['rank']) ?></span>
                    </span>
                    <i data-user-menu-chevron data-lucide="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200"></i>
                </button>

                <div
                    id="user-menu"
                    data-user-menu-panel
                    class="absolute right-0 mt-2 w-72 origin-top-right overflow-hidden rounded-2xl border border-white/10 bg-[#0E0D15] shadow-[0_30px_80px_-25px_rgba(0,0,0,0.9)]"
                    hidden
                >
                    <div class="border-b border-white/[0.06] bg-gradient-to-br from-ember-500/[0.10] to-transparent px-4 py-3.5">
                        <div class="flex items-center gap-3">
                            <?php if ($avatarUrl !== null): ?>
                                <img src="<?= e($avatarUrl) ?>" alt="" class="h-10 w-10 shrink-0 rounded-xl border border-white/10 object-cover">
                            <?php else: ?>
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-ember-500 to-gold-400 text-sm font-bold text-white">
                                    <?= e($initial) ?>
                                </span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white"><?= e($displayName) ?></p>
                                <p class="truncate text-[11px] text-slate-500"><?= e($email) ?></p>
                            </div>
                        </div>
                        <p class="mt-2.5 inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em] text-gold-300">
                            <i data-lucide="shield-check" class="h-3 w-3"></i>
                            <?= e($progression['rank']) ?> · Level <?= e($progression['level']) ?>
                        </p>
                    </div>

                    <div class="p-1.5">
                        <a href="<?= e(url('/profile')) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-white/[0.05] hover:text-white">
                            <i data-lucide="user-round" class="h-4 w-4 text-slate-500"></i>
                            <span class="flex-1">My Profile</span>
                        </a>
                        <a href="<?= e(url('/settings')) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-white/[0.05] hover:text-white">
                            <i data-lucide="settings" class="h-4 w-4 text-slate-500"></i>
                            <span class="flex-1">Settings</span>
                        </a>
                    </div>

                    <div class="border-t border-white/[0.06] p-1.5">
                        <form method="POST" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-400 transition hover:bg-rose-500/10 hover:text-rose-200"
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
