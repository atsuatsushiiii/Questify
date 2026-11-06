<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Brand panel shown beside authentication forms on large screens.
 */
?>
<aside class="relative hidden overflow-hidden border-r border-white/[0.06] bg-ink lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-40 mask-fade-b"></div>
        <div class="absolute -left-32 top-10 h-[26rem] w-[26rem] rounded-full bg-brand-600/20 blur-[110px]"></div>
        <div class="absolute -bottom-24 right-0 h-[22rem] w-[22rem] rounded-full bg-aqua-500/10 blur-[100px]"></div>
        <div class="absolute right-16 top-1/3 h-px w-40 bg-gradient-to-r from-transparent via-brand-400/40 to-transparent"></div>
    </div>

    <div class="relative">
        <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-3" aria-label="Questify home">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 via-brand-600 to-aqua-500 shadow-[0_0_30px_-6px_rgba(139,92,246,0.85)]">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="font-display text-xl font-bold tracking-tight text-white">Questify</span>
        </a>
    </div>

    <div class="relative max-w-md">
        <p class="eyebrow">
            <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
            Your next chapter
        </p>

        <h2 class="mt-6 font-display text-4xl font-bold leading-[1.08] tracking-tight text-white xl:text-[2.9rem]">
            Every quest<br>
            <span class="gradient-text">counts.</span>
        </h2>

        <p class="mt-5 max-w-sm text-sm leading-relaxed text-muted">
            Turn the work you already have to do into progress you can see.
            One account, one quest log, one clear next step.
        </p>

        <ul class="mt-9 space-y-4">
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-brand-400/20 bg-brand-500/10 text-brand-300">
                    <i data-lucide="swords" class="h-3.5 w-3.5"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Quests with real rewards</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Every task carries an XP value before you start.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                    <i data-lucide="flame" class="h-3.5 w-3.5"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Streaks in your timezone</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Your day is your day — not a server&rsquo;s idea of one.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-emerald-400/20 bg-emerald-500/10 text-emerald-300">
                    <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Private by default</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Your quests stay yours. Leaderboards are opt-in.</p>
                </div>
            </li>
        </ul>
    </div>

    <div class="relative flex items-center gap-3 text-[11px] text-muted-dim">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            System operational
        </span>
        <span class="text-muted-dim">&middot;</span>
        <span>PHP 8.2 &middot; MySQL &middot; Tailwind</span>
    </div>
</aside>
