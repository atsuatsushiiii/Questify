<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Public navigation. Anchor links only target sections on the landing page.
 */
$links = [
    ['label' => 'Features', 'href' => url('/') . '#features'],
    ['label' => 'How it works', 'href' => url('/') . '#how'],
    ['label' => 'Preview', 'href' => url('/') . '#preview'],
];
$navUser = auth_user();
$navName = $navUser !== null
    ? (string) ($navUser['display_name'] ?? $navUser['username'] ?? 'Adventurer')
    : '';
?>
<header class="fixed inset-x-0 top-0 z-50" data-nav>
    <div class="container-page">
        <div class="nav-shell mt-4 flex h-16 items-center justify-between gap-4 rounded-sm border border-line bg-ink/85 px-4 backdrop-blur-md sm:px-6">
            <a href="<?= e(url('/')) ?>" class="group inline-flex items-center gap-2.5" aria-label="Questify home">
                <span class="grid h-9 w-9 place-items-center rounded-sm border border-brand-500 bg-brand-400 shadow-offset transition-colors group-hover:bg-brand-300">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-[#14100B]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                    </svg>
                </span>
                <span class="font-display text-lg font-semibold tracking-tight text-white">Questify</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Primary">
                <?php foreach ($links as $link): ?>
                    <a
                        href="<?= e($link['href']) ?>"
                        class="rounded-sm px-3 py-2 font-mono text-[10px] font-medium uppercase tracking-[0.16em] text-slate-400 transition hover:bg-brand-400/[0.06] hover:text-brand-200"
                    >
                        <?= e($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <?php if ($navUser !== null): ?>
                    <span class="max-w-[9rem] truncate font-mono text-[10px] uppercase tracking-[0.12em] text-slate-400">
                        <?= e($navName) ?>
                    </span>
                    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary btn-sm group">
                        Dashboard
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                <?php else: ?>
                    <a
                        href="<?= e(url('/login')) ?>"
                        class="rounded-sm px-3 py-2 font-mono text-[10px] font-medium uppercase tracking-[0.16em] text-slate-300 transition hover:text-brand-200"
                    >
                        Sign in
                    </a>
                    <a href="<?= e(url('/register')) ?>" class="btn btn-primary btn-sm group">
                        Get started
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                <?php endif; ?>
            </div>

            <button
                type="button"
                data-nav-toggle
                class="grid h-10 w-10 place-items-center rounded-sm border border-line text-slate-300 transition hover:border-brand-400/50 hover:text-white md:hidden"
                aria-expanded="false"
                aria-controls="mobile-menu"
                aria-label="Open menu"
            >
                <span data-icon-open class="grid place-items-center">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </span>
                <span data-icon-close class="hidden">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </span>
            </button>
        </div>

        <div id="mobile-menu" data-mobile-menu class="mobile-menu md:hidden" aria-hidden="true">
            <div class="overflow-hidden">
                <div class="mt-2 rounded-sm border border-line bg-ink p-3 shadow-offset-lg">
                    <?php foreach ($links as $link): ?>
                        <a
                            href="<?= e($link['href']) ?>"
                            class="block rounded-sm px-3 py-2.5 font-mono text-[10px] font-medium uppercase tracking-[0.16em] text-slate-300 transition hover:bg-brand-400/[0.06] hover:text-white"
                        >
                            <?= e($link['label']) ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="mt-2 grid gap-2 border-t border-line pt-3">
                        <?php if ($navUser !== null): ?>
                            <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary w-full">Open dashboard</a>
                        <?php else: ?>
                            <a href="<?= e(url('/login')) ?>" class="btn btn-ghost w-full">Sign in</a>
                            <a href="<?= e(url('/register')) ?>" class="btn btn-primary w-full">Get started</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
