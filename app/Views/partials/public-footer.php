<?php
$productLinks = [
    ['label' => 'Features', 'href' => '#features'],
    ['label' => 'How it works', 'href' => '#how'],
    ['label' => 'Preview', 'href' => '#preview'],
];
?>
<footer class="border-t border-white/[0.06] bg-ink/40">
    <div class="container-page py-14">
        <div class="grid gap-10 md:grid-cols-[1.6fr_1fr_1fr]">
            <div>
                <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-2.5" aria-label="Questify home">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                        </svg>
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight text-white">Questify</span>
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-muted">
                    A gamified productivity platform for people who like their progress visible.
                    Complete quests, protect your streak, and level up the work that matters.
                </p>
                <p class="mt-5 inline-flex items-center gap-2 text-xs text-slate-500">
                    <i data-lucide="shield-check" class="h-4 w-4 text-aqua-400"></i>
                    Secure sessions, validated input, prepared statements.
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Product</p>
                <ul class="mt-4 space-y-3">
                    <?php foreach ($productLinks as $link): ?>
                        <li>
                            <a href="<?= e($link['href']) ?>" class="text-sm text-slate-400 transition hover:text-white">
                                <?= e($link['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Account</p>
                <ul class="mt-4 space-y-3">
                    <li>
                        <a href="<?= e(url('/login')) ?>" class="text-sm text-slate-400 transition hover:text-white">Sign in</a>
                    </li>
                    <li>
                        <a href="<?= e(url('/register')) ?>" class="text-sm text-slate-400 transition hover:text-white">Create an account</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/[0.06] pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= e(date('Y')) ?> Questify. Built for people who like their progress visible.</p>
            <p>PHP 8.2 &middot; MySQL &middot; Tailwind CSS &middot; GSAP</p>
        </div>
    </div>
</footer>
