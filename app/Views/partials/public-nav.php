<?php
/**
 * Public navigation. Anchor links only target sections on the landing page.
 */
$links = [
    ['label' => 'Features', 'href' => '#features'],
    ['label' => 'How it works', 'href' => '#how'],
    ['label' => 'Preview', 'href' => '#preview'],
];
$navUser = auth_user();
$navName = $navUser !== null
    ? (string) ($navUser['display_name'] ?? $navUser['username'] ?? 'Adventurer')
    : '';
?>
<header class="fixed inset-x-0 top-0 z-50" data-nav>
    <div class="container-page">
        <div class="nav-shell mt-4 flex h-16 items-center justify-between gap-4 rounded-2xl border border-white/[0.06] bg-ink/60 px-4 backdrop-blur-xl sm:px-6">
            <a href="<?= e(url('/')) ?>" class="group inline-flex items-center gap-2.5" aria-label="Questify home">
                <span class="relative grid h-9 w-9 place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500 shadow-[0_0_24px_-6px_rgba(139,92,246,0.8)]">
                    <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                    </svg>
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-white">Questify</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Primary">
                <?php foreach ($links as $link): ?>
                    <a
                        href="<?= e($link['href']) ?>"
                        class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-400 transition hover:bg-white/[0.04] hover:text-white"
                    >
                        <?= e($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                <?php if ($navUser !== null): ?>
                    <span class="max-w-[9rem] truncate px-2 text-xs font-medium text-slate-400">
                        <?= e($navName) ?>
                    </span>
                    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary btn-sm group">
                        Dashboard
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                <?php else: ?>
                    <a
                        href="<?= e(url('/login')) ?>"
                        class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-300 transition hover:text-white"
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
                class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 text-slate-300 transition hover:bg-white/[0.05] hover:text-white md:hidden"
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
                <div class="mt-2 rounded-2xl border border-white/[0.08] bg-ink/95 p-3 shadow-2xl shadow-black/50 backdrop-blur-xl">
                    <?php foreach ($links as $link): ?>
                        <a
                            href="<?= e($link['href']) ?>"
                            class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/[0.05] hover:text-white"
                        >
                            <?= e($link['label']) ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="mt-2 grid gap-2 border-t border-white/[0.06] pt-3">
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
