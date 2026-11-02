<?php
/**
 * Coming-soon destination for later phases.
 *
 * @var string $eyebrow
 * @var string $heading
 * @var string $description
 * @var string $phase
 * @var string $icon
 * @var array<int, string> $features
 */
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <div class="relative overflow-hidden rounded-3xl border border-white/[0.07] bg-gradient-to-br from-white/[0.035] via-white/[0.015] to-transparent p-6 sm:p-9">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-ember-500/[0.12] blur-[90px]"></div>
            <div class="absolute -bottom-28 -left-16 h-56 w-56 rounded-full bg-gold-400/[0.08] blur-[90px]"></div>
            <div class="absolute inset-0 bg-grid opacity-[0.15] mask-fade-b"></div>
        </div>

        <div class="relative">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="grid h-14 w-14 place-items-center rounded-2xl border border-ember-400/25 bg-ember-500/10 text-gold-300 shadow-[0_0_40px_-12px_rgba(239,68,68,0.5)]">
                    <i data-lucide="<?= e($icon) ?>" class="h-6 w-6"></i>
                </span>

                <span class="inline-flex items-center gap-2 rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-3.5 py-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-gold-300">
                    <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                    Planned for <?= e($phase) ?>
                </span>
            </div>

            <p class="mt-7 text-[10px] font-semibold uppercase tracking-[0.2em] text-ember-300"><?= e($eyebrow) ?></p>
            <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl"><?= e($heading) ?></h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted"><?= e($description) ?></p>

            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <?php foreach ($features as $feature): ?>
                    <div class="flex items-start gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3.5 py-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-gold-400/10 text-gold-300">
                            <i data-lucide="check" class="h-3 w-3"></i>
                        </span>
                        <p class="text-xs leading-relaxed text-slate-400"><?= e($feature) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 flex items-start gap-3 rounded-2xl border border-gold-400/20 bg-gold-400/[0.05] p-4">
                <i data-lucide="construction" class="mt-0.5 h-4 w-4 shrink-0 text-gold-300"></i>
                <p class="text-xs leading-relaxed text-slate-400">
                    <span class="font-semibold text-gold-200">This module is not active yet.</span>
                    Nothing on this page is live data. Your dashboard only reports values stored on your account today.
                </p>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="<?= e(url('/dashboard')) ?>" class="btn btn-app-primary">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    Back to dashboard
                </a>
                <span class="inline-flex items-center justify-center gap-2 rounded-xl border border-dashed border-white/10 px-5 py-3 text-xs font-medium text-slate-600">
                    <i data-lucide="lock" class="h-3.5 w-3.5"></i>
                    Unlocks in <?= e($phase) ?>
                </span>
            </div>
        </div>
    </div>
</section>
