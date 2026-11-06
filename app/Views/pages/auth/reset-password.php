<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Choose a new password. The token is validated before the form renders and
 * again on submit.
 *
 * @var string $token
 * @var bool $invalidToken
 * @var array<string, string> $errors
 * @var string|null $message
 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$describedBy = static fn (string $field): string => isset($errors[$field]) ? $field . '-error' : '';
?>
<?php if ($invalidToken): ?>
    <div class="text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-rose-400/20 bg-rose-500/10 text-rose-300">
            <i data-lucide="key-round" class="h-6 w-6"></i>
        </span>
        <h1 class="mt-6 font-display text-3xl font-bold tracking-tight text-white">
            This link has expired.
        </h1>
        <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-muted">
            Password reset links are single-use and expire after one hour. Request a fresh
            link and we&rsquo;ll get you back into your quest log.
        </p>
        <a href="<?= e(url('/forgot-password')) ?>" class="btn btn-primary mt-7 w-full">
            Request a new link
        </a>
        <a href="<?= e(url('/login')) ?>" class="btn btn-ghost mt-3 w-full">
            Back to sign in
        </a>
    </div>
<?php else: ?>
    <div class="mb-8">
        <p class="eyebrow">
            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
            Account recovery
        </p>
        <h1 class="mt-5 font-display text-3xl font-bold tracking-tight text-white sm:text-[2.15rem]">
            Choose a new key.
        </h1>
        <p class="mt-3 text-sm leading-relaxed text-muted">
            Pick a password you haven&rsquo;t used before. Signing in elsewhere will require
            the new password.
        </p>
    </div>

    <?php if (is_string($message) && $message !== ''): ?>
        <?php
        $type = 'error';
        require __DIR__ . '/../../partials/alert.php';
        ?>
    <?php endif; ?>

    <form method="POST" action="<?= e(url('/reset-password')) ?>" class="space-y-4" data-auth-form>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div>
            <label for="password" class="field-label">New password</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                    <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="field-input px-10 <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                    placeholder="At least 10 characters"
                    autocomplete="new-password"
                    minlength="10"
                    maxlength="72"
                    required
                    autofocus
                    aria-invalid="<?= $invalid('password') ?>"
                    aria-describedby="<?= e($describedBy('password')) ?>"
                    data-strength-input
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-muted transition hover:bg-white/[0.05] hover:text-slate-200"
                    data-password-toggle="password"
                    aria-controls="password"
                    aria-pressed="false"
                    aria-label="Show password"
                    hidden
                >
                    <span data-eye-open><i data-lucide="eye" class="h-4 w-4"></i></span>
                    <span data-eye-off class="hidden"><i data-lucide="eye-off" class="h-4 w-4"></i></span>
                </button>
            </div>

            <div class="mt-2 hidden" data-strength aria-live="polite">
                <div class="flex gap-1.5">
                    <span class="h-1 flex-1 rounded-full bg-white/[0.08]" data-strength-segment="1"></span>
                    <span class="h-1 flex-1 rounded-full bg-white/[0.08]" data-strength-segment="2"></span>
                    <span class="h-1 flex-1 rounded-full bg-white/[0.08]" data-strength-segment="3"></span>
                    <span class="h-1 flex-1 rounded-full bg-white/[0.08]" data-strength-segment="4"></span>
                </div>
                <p class="mt-1.5 text-[11px] text-muted" data-strength-label></p>
            </div>

            <?php $field = 'password'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <div>
            <label for="password_confirmation" class="field-label">Confirm new password</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                    <i data-lucide="lock" class="h-4 w-4"></i>
                </span>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="field-input px-10 <?= isset($errors['password_confirmation']) ? 'is-invalid' : '' ?>"
                    placeholder="Repeat your password"
                    autocomplete="new-password"
                    minlength="10"
                    maxlength="72"
                    required
                    aria-invalid="<?= $invalid('password_confirmation') ?>"
                    aria-describedby="<?= e($describedBy('password_confirmation')) ?>"
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-muted transition hover:bg-white/[0.05] hover:text-slate-200"
                    data-password-toggle="password_confirmation"
                    aria-controls="password_confirmation"
                    aria-pressed="false"
                    aria-label="Show password"
                    hidden
                >
                    <span data-eye-open><i data-lucide="eye" class="h-4 w-4"></i></span>
                    <span data-eye-off class="hidden"><i data-lucide="eye-off" class="h-4 w-4"></i></span>
                </button>
            </div>
            <?php $field = 'password_confirmation'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <?php if (isset($errors['token'])): ?>
            <?php
            $type = 'error';
            $message = $errors['token'];
            require __DIR__ . '/../../partials/alert.php';
            ?>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary w-full" data-submit>
            <span data-submit-label>Update password</span>
            <i data-lucide="loader-2" data-submit-spinner class="hidden h-4 w-4 animate-spin"></i>
        </button>
    </form>

    <div class="mt-7 border-t border-white/[0.07] pt-6 text-center text-sm text-muted">
        <a href="<?= e(url('/login')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
            Back to sign in
        </a>
    </div>
<?php endif; ?>
