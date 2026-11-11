<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Signup email verification. The token is validated before the page renders
 * and again on submit.
 *
 * @var string $email
 * @var string $displayName
 * @var string $token
 * @var bool $signedIn
 */
?>
<div class="text-center">
    <span class="mx-auto grid h-14 w-14 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 text-brand-300">
        <i data-lucide="mail-check" class="h-6 w-6"></i>
    </span>
    <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-white">
        Confirm your email.
    </h1>
    <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-muted">
        This link was issued to <span class="font-medium text-slate-300"><?= e($displayName) ?></span>.
        Confirm the address below to finish setting up your account.
    </p>
</div>

<div class="mt-6 rounded-sm border border-brand-400/35 bg-brand-400/[0.08] px-4 py-3 text-center">
    <p class="font-mono text-[8px] font-semibold uppercase tracking-[0.16em] text-brand-300/80">Email address</p>
    <p class="mt-1 break-all text-sm font-semibold text-white"><?= e($email) ?></p>
</div>

<div class="mt-4 flex items-start gap-2 rounded-sm border border-line bg-white/[0.02] px-3.5 py-3 text-[11px] leading-relaxed text-muted">
    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i>
    <p>Verification links are single-use and expire after 24 hours. Confirming marks the address as verified; it does not change your sign-in details.</p>
</div>

<form method="POST" action="<?= e(url('/verify-email')) ?>" class="mt-5 space-y-3">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <button type="submit" class="btn btn-primary w-full">
        <i data-lucide="badge-check" class="h-4 w-4"></i>
        Verify my email
    </button>
</form>

<div class="mt-7 border-t border-line pt-6 text-center text-sm text-muted">
    <a href="<?= e(url($signedIn ? '/settings' : '/login')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
        <?= $signedIn ? 'Back to settings' : 'Back to sign in' ?>
    </a>
</div>
