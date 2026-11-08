<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Registration form.
 *
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 * @var array<int, string> $timezones
 * @var bool $googleEnabled
 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$describedBy = static fn (string $field): string => isset($errors[$field]) ? $field . '-error' : '';
?>
<div class="mb-7">
    <p class="eyebrow">
        <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
        New player
    </p>
    <h1 class="mt-5 font-display text-3xl font-semibold tracking-tight text-white sm:text-[2.15rem]">
        Create your character.
    </h1>
    <p class="mt-3 text-sm leading-relaxed text-muted">
        Two minutes now. A quest log that actually rewards the work.
    </p>
</div>

<?php if (is_string($message) && $message !== ''): ?>
    <?php
    $type = 'error';
    require __DIR__ . '/../../partials/alert.php';
    ?>
<?php endif; ?>

<?php if (!empty($googleEnabled)): ?>
    <form method="POST" action="<?= e(url('/auth/google')) ?>" class="mb-5">
        <?= csrf_field() ?>
        <input type="hidden" name="timezone" value="UTC" data-timezone-input>
        <button type="submit" class="btn btn-ghost w-full">
            <svg viewBox="0 0 48 48" class="h-4 w-4" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.3 0-9.7-3.3-11.3-8l-6.6 5.1C9.6 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.2-4.1 5.6l6.2 5.2C36.9 40.3 44 35 44 24c0-1.3-.1-2.3-.4-3.5z"/>
            </svg>
            Sign up with Google
        </button>
        <p class="mt-2 text-center text-[10px] leading-relaxed text-muted-dim">
            Your Questify account is created automatically. We only receive your Google name and email address.
        </p>
    </form>
    <div class="mb-5 flex items-center gap-3">
        <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
        <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-muted-dim">or use email</span>
        <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e(url('/register')) ?>" class="space-y-4" data-auth-form>
    <?= csrf_field() ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="display_name" class="field-label">Display name</label>
            <input
                id="display_name"
                name="display_name"
                type="text"
                value="<?= e($old['display_name'] ?? '') ?>"
                class="field-input <?= isset($errors['display_name']) ? 'is-invalid' : '' ?>"
                placeholder="Shadow Runner"
                autocomplete="nickname"
                maxlength="50"
                required
                autofocus
                aria-invalid="<?= $invalid('display_name') ?>"
                aria-describedby="<?= e($describedBy('display_name')) ?>"
            >
            <?php $field = 'display_name'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <div>
            <label for="username" class="field-label">Username</label>
            <input
                id="username"
                name="username"
                type="text"
                value="<?= e($old['username'] ?? '') ?>"
                class="field-input <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                placeholder="shadow_runner"
                autocomplete="username"
                maxlength="30"
                pattern="[A-Za-z0-9_]{3,30}"
                required
                aria-invalid="<?= $invalid('username') ?>"
                aria-describedby="<?= e($describedBy('username')) ?>"
            >
            <?php $field = 'username'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>
    </div>

    <div>
        <label for="email" class="field-label">Email address</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                <i data-lucide="mail" class="h-4 w-4"></i>
            </span>
            <input
                id="email"
                name="email"
                type="email"
                value="<?= e($old['email'] ?? '') ?>"
                class="field-input pl-10 <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                placeholder="you@example.com"
                autocomplete="email"
                maxlength="190"
                required
                aria-invalid="<?= $invalid('email') ?>"
                aria-describedby="<?= e($describedBy('email')) ?>"
            >
        </div>
        <?php $field = 'email'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <div>
        <label for="timezone" class="field-label">Timezone</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-dim">
                <i data-lucide="clock" class="h-4 w-4"></i>
            </span>
            <select
                id="timezone"
                name="timezone"
                class="field-input appearance-none pl-10 pr-10 <?= isset($errors['timezone']) ? 'is-invalid' : '' ?>"
                data-timezone
                required
                aria-invalid="<?= $invalid('timezone') ?>"
                aria-describedby="<?= e($describedBy('timezone')) ?>"
            >
                <?php foreach ($timezones as $timezone): ?>
                    <option value="<?= e($timezone) ?>" <?= ($old['timezone'] ?? 'UTC') === $timezone ? 'selected' : '' ?>>
                        <?= e(str_replace('_', ' ', $timezone)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-muted">
                <i data-lucide="chevron-down" class="h-4 w-4"></i>
            </span>
        </div>
        <p class="mt-1.5 text-[11px] text-muted-dim">Used for streaks and daily quest resets. Detected automatically.</p>
        <?php $field = 'timezone'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <div>
        <label for="password" class="field-label">Password</label>
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
                aria-invalid="<?= $invalid('password') ?>"
                aria-describedby="<?= e($describedBy('password')) ?>"
                data-strength-input
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

        <div class="mt-2 hidden" data-strength aria-live="polite">
            <div class="flex gap-1.5">
                <span class="h-1 flex-1 bg-white/[0.08]" data-strength-segment="1"></span>
                <span class="h-1 flex-1 bg-white/[0.08]" data-strength-segment="2"></span>
                <span class="h-1 flex-1 bg-white/[0.08]" data-strength-segment="3"></span>
                <span class="h-1 flex-1 bg-white/[0.08]" data-strength-segment="4"></span>
            </div>
            <p class="mt-1.5 text-[11px] text-muted" data-strength-label></p>
        </div>

        <p class="mt-1.5 text-[11px] text-muted-dim">At least 10 characters, including a letter and a number.</p>
        <?php $field = 'password'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <div>
        <label for="password_confirmation" class="field-label">Confirm password</label>
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
                class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.05] hover:text-slate-200"
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

    <div class="pt-1">
        <label class="flex cursor-pointer items-start gap-2.5 text-xs leading-relaxed text-slate-400 select-none">
            <input
                type="checkbox"
                name="terms"
                value="1"
                class="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-line bg-white/[0.03] text-brand-500 focus:ring-brand-500/30 focus:ring-offset-0"
                <?= isset($old['terms']) ? 'checked' : '' ?>
                required
            >
            <span>
                I&rsquo;ll use Questify for my own goals and keep my account credentials secure.
            </span>
        </label>
        <?php $field = 'terms'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full" data-submit>
        <span data-submit-label>Create my account</span>
        <i data-lucide="loader-2" data-submit-spinner class="hidden h-4 w-4 animate-spin"></i>
    </button>
</form>

<div class="mt-7 border-t border-line pt-6 text-center text-sm text-muted">
    Already have an account?
    <a href="<?= e(url('/login')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
        Sign in
    </a>
</div>

<p class="mt-5 text-center text-[11px] leading-relaxed text-muted-dim">
    Passwords are hashed with bcrypt. Questify never stores or emails plain-text passwords.
</p>
