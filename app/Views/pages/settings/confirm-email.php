<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Email change confirmation.
 *
 * @var array<string, mixed> $user
 * @var string $token
 * @var string $newEmail
 */
$currentEmail = (string) ($user['email'] ?? '');
$displayName = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
?>
<section class="mx-auto max-w-2xl" data-reveal>
    <div class="mb-6">
        <p class="eyebrow">
            <i data-lucide="mail" class="h-3.5 w-3.5"></i>
            Email verification
        </p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Confirm your new email</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
            This link was issued to <span class="font-medium text-slate-300"><?= e($displayName) ?></span>. Confirm only if you requested this change.
        </p>
    </div>

    <article class="app-panel relative overflow-hidden p-6 sm:p-8">
        <div class="pointer-events-none absolute -right-20 -top-24 h-56 w-56 rounded-full bg-gold-400/[0.08] blur-[90px]" aria-hidden="true"></div>

        <div class="relative">
            <div class="space-y-3">
                <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-600">Current address</p>
                    <p class="mt-1 break-all text-sm font-semibold text-slate-400 line-through decoration-slate-600"><?= e($currentEmail) ?></p>
                </div>

                <div class="flex justify-center text-slate-600" aria-hidden="true">
                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                </div>

                <div class="rounded-xl border border-gold-400/25 bg-gold-400/[0.08] px-4 py-3">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-gold-300/80">New address</p>
                    <p class="mt-1 break-all text-sm font-semibold text-white"><?= e($newEmail) ?></p>
                </div>
            </div>

            <div class="mt-5 flex items-start gap-2 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3.5 py-3 text-[11px] leading-relaxed text-slate-500">
                <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gold-300"></i>
                <p>Confirmation links are single-use and expire after one hour. After the change, sign in with the new address. Your current session stays active.</p>
            </div>

            <form method="POST" action="<?= e(url('/settings/email/confirm')) ?>" class="mt-5 space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <button type="submit" class="btn btn-app-primary w-full text-sm">
                    <i data-lucide="badge-check" class="h-4 w-4"></i>
                    Confirm email change
                </button>

                <a href="<?= e(url('/settings')) ?>" class="btn-app-ghost btn w-full text-sm">
                    Cancel and return to settings
                </a>
            </form>
        </div>
    </article>
</section>
