<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Registration form.
 *
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 * @var array<int, string> $timezones
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
