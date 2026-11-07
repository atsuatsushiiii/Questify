<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Brand panel shown beside authentication forms on large screens.
 */
?>
<aside class="relative hidden overflow-hidden border-r border-line bg-ink lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-grid opacity-70 mask-fade-b"></div>
        <div class="absolute left-12 right-12 top-24 border-t border-brand-400/20"></div>
    </div>

    <div class="relative">
        <a href="<?= e(url('/')) ?>" class="inline-flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-sm border border-brand-500 bg-brand-400 shadow-offset">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-[#14100B]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/>
                </svg>
            </span>
            <span class="min-w-0">
                <span class="block font-display text-xl font-semibold tracking-tight text-white">Questify</span>
                <span class="block font-mono text-[9px] uppercase tracking-[0.24em] text-brand-300/90">Expedition ledger</span>
            </span>
        </a>
    </div>

    <div class="relative max-w-md">
        <p class="eyebrow">
            <i data-lucide="compass" class="h-3.5 w-3.5" aria-hidden="true"></i>
            Your next chapter
        </p>

        <h2 class="mt-6 font-display text-4xl font-semibold leading-[1.06] tracking-tight text-white xl:text-[2.9rem]">
            Every quest<br>
            <span class="gradient-text">counts.</span>
        </h2>

        <p class="mt-5 max-w-sm text-sm leading-relaxed text-muted">
            Turn the work you already have to do into progress you can see.
            One account, one quest log, one clear next step.
        </p>

        <ul class="mt-9 space-y-4">
            <li class="flex items-start gap-3 border-b border-line pb-4">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 font-mono text-[10px] font-semibold text-brand-300">
                    01
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Quests with real rewards</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Every task carries an XP value before you start.</p>
                </div>
            </li>
            <li class="flex items-start gap-3 border-b border-line pb-4">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-sm border border-ember-400/30 bg-ember-500/10 font-mono text-[10px] font-semibold text-ember-300">
                    02
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Streaks in your timezone</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Your day is your day — not a server&rsquo;s idea of one.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-sm border border-aqua-400/30 bg-aqua-500/10 font-mono text-[10px] font-semibold text-aqua-300">
                    03
                </span>
                <div>
                    <p class="text-sm font-semibold text-white">Private by default</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">Your quests stay yours. Leaderboards are opt-in.</p>
                </div>
            </li>
        </ul>
    </div>

    <div class="relative flex items-center gap-3 font-mono text-[9px] uppercase tracking-[0.14em] text-muted-dim">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-sm bg-aqua-400"></span>
            System operational
        </span>
        <span class="text-muted-dim">&middot;</span>
        <span>PHP 8.2 &middot; MySQL &middot; Tailwind</span>
    </div>
</aside>
