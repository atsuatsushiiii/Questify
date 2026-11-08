<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Sign-in form.
 *
 * @var string $identifier
 * @var array<string, string> $errors
 * @var string|null $message
 * @var bool $googleEnabled
 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$describedBy = static fn (string $field): string => isset($errors[$field]) ? $field . '-error' : '';
?>
<div class="mb-8">
    <p class="eyebrow">
        <i data-lucide="key-round" class="h-3.5 w-3.5"></i>
        Player sign-in
    </p>
    <h1 class="mt-5 font-display text-3xl font-semibold tracking-tight text-white sm:text-[2.15rem]">
        Welcome back, adventurer.
    </h1>
    <p class="mt-3 text-sm leading-relaxed text-muted">
        Your quest log is exactly where you left it.
    </p>
</div>

<?php if (is_string($message) && $message !== ''): ?>
    <?php
    $type = 'error';
    require __DIR__ . '/../../partials/alert.php';
    ?>
<?php endif; ?>

<?php if (!empty($googleEnabled)): ?>
    <form method="POST" action="<?= e(url('/auth/google')) ?>" class="mb-6">
        <?= csrf_field() ?>
        <input type="hidden" name="timezone" value="UTC" data-timezone-input>
        <button type="submit" class="btn btn-ghost w-full">
            <svg viewBox="0 0 48 48" class="h-4 w-4" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.6 5.1C9.6 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.2-4.1 5.6l6.2 5.2C36.9 40.3 44 35 44 24c0-1.3-.1-2.3-.4-3.5z"/>
            </svg>
            Continue with Google
        </button>
        <p class="mt-2 text-center text-[10px] leading-relaxed text-muted-dim">
            We only receive your Google name and email address.
        </p>
    </form>
    <div class="mb-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
        <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted-dim">or use email</span>
        <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e(url('/login')) ?>" class="space-y-5" data-auth-form>
    <?= csrf_field() ?>

    <div>
        <label for="identifier" class="field-label">Email or username</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                <i data-lucide="user" class="h-4 w-4"></i>
            </span>
            <input
                id="identifier"
                name="identifier"
                type="text"
                value="<?= e($identifier) ?>"
                class="field-input pl-10 <?= isset($errors['identifier']) ? 'is-invalid' : '' ?>"
                placeholder="you@example.com"
                autocomplete="username"
                autofocus
                required
                aria-invalid="<?= $invalid('identifier') ?>"
                aria-describedby="<?= e($describedBy('identifier')) ?>"
            >
        </div>
        <?php $field = 'identifier'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <div>
        <div class="mb-2 flex items-baseline justify-between gap-3">
            <label for="password" class="field-label mb-0">Password</label>
            <a href="<?= e(url('/forgot-password')) ?>" class="text-xs font-medium text-brand-300 transition hover:text-brand-200">
                Forgot password?
            </a>
        </div>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                <i data-lucide="lock" class="h-4 w-4"></i>
            </span>
            <input
                id="password"
                name="password"
                type="password"
                class="field-input px-10 <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                placeholder="Your password"
                autocomplete="current-password"
                required
                aria-invalid="<?= $invalid('password') ?>"
                aria-describedby="<?= e($describedBy('password')) ?>"
            >
            <button
                type="button"
                class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.05] hover:text-slate-200"
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
        <?php $field = 'password'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-400 select-none">
        <input
            type="checkbox"
            name="remember"
            value="1"
            class="h-4 w-4 rounded-sm border-line bg-white/[0.03] text-brand-500 focus:ring-brand-500/30 focus:ring-offset-0"
        >
        Keep me signed in for 30 days
    </label>

    <button type="submit" class="btn btn-primary w-full" data-submit>
        <span data-submit-label>Sign in</span>
        <i data-lucide="loader-2" data-submit-spinner class="hidden h-4 w-4 animate-spin"></i>
    </button>
</form>

<div class="mt-8 border-t border-line pt-6 text-center text-sm text-muted">
    New to Questify?
    <a href="<?= e(url('/register')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
        Create your account
    </a>
</div>

<p class="mt-6 flex items-start gap-2 text-[11px] leading-relaxed text-muted-dim">
    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-aqua-400/70"></i>
    Sign-in attempts are rate limited. Passwords are hashed with bcrypt and never stored in plain text.
</p>
