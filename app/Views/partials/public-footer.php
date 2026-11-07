<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
$productLinks = [
    ['label' => 'Features', 'href' => url('/') . '#features'],
    ['label' => 'How it works', 'href' => url('/') . '#how'],
    ['label' => 'Preview', 'href' => url('/') . '#preview'],
];
?>
<footer class="border-t border-line bg-ink/60">
    <div class="container-page py-14">
        <div class="grid gap-10 md:grid-cols-[1.6fr_1fr_1fr]">
            <div>
                <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify home">
                    <span class="grid h-9 w-9 place-items-center rounded-sm border border-brand-500 bg-brand-400 shadow-offset">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-[#14100B]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                        </svg>
                    </span>
                    <span class="font-display text-lg font-semibold tracking-tight text-white">Questify</span>
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-muted">
                    A gamified productivity platform for people who like their progress visible.
                    Complete quests, protect your streak, and level up the work that matters.
                </p>
                <p class="mt-5 inline-flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.12em] text-muted">
                    <i data-lucide="shield-check" class="h-4 w-4 text-aqua-300" aria-hidden="true"></i>
                    Secure sessions, validated input, prepared statements.
                </p>
            </div>

            <div>
                <p class="border-b border-line pb-2 font-mono text-[10px] font-medium uppercase tracking-[0.18em] text-brand-300">Product</p>
                <ul class="mt-4 space-y-3">
                    <?php foreach ($productLinks as $link): ?>
                        <li>
                            <a href="<?= e($link['href']) ?>" class="text-sm text-slate-400 transition hover:text-brand-200">
                                <?= e($link['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <p class="border-b border-line pb-2 font-mono text-[10px] font-medium uppercase tracking-[0.18em] text-brand-300">Account</p>
                <ul class="mt-4 space-y-3">
                    <?php if (auth_check()): ?>
                        <li>
                            <a href="<?= e(url('/dashboard')) ?>" class="text-sm text-slate-400 transition hover:text-brand-200">Your dashboard</a>
                        </li>
                        <li>
                            <a href="<?= e(url('/')) ?>" class="text-sm text-slate-400 transition hover:text-brand-200">Public site</a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?= e(url('/login')) ?>" class="text-sm text-slate-400 transition hover:text-brand-200">Sign in</a>
                        </li>
                        <li>
                            <a href="<?= e(url('/register')) ?>" class="text-sm text-slate-400 transition hover:text-brand-200">Create an account</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-line pt-6 font-mono text-[9px] tracking-[0.06em] text-muted-dim sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= e(date('Y')) ?> Questify. Code © 2026 Kevin Cyril Maraño, MIT license.</p>
            <p class="uppercase tracking-[0.14em]">PHP 8.2 &middot; MySQL &middot; Tailwind CSS &middot; GSAP</p>
        </div>
    </div>
</footer>
