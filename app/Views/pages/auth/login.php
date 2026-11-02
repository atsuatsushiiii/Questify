<?php
/**
 * Sign-in form.
 *
 * @var string $identifier
 * @var array<string, string> $errors
 * @var string|null $message
 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$describedBy = static fn (string $field): string => isset($errors[$field]) ? $field . '-error' : '';
?>
<div class="mb-8">
    <p class="eyebrow">
        <i data-lucide="key-round" class="h-3.5 w-3.5"></i>
        Player sign-in
    </p>
    <h1 class="mt-5 font-display text-3xl font-bold tracking-tight text-white sm:text-[2.15rem]">
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

<form method="POST" action="<?= e(url('/login')) ?>" class="space-y-5" data-auth-form>
    <?= csrf_field() ?>

    <div>
        <label for="identifier" class="field-label">Email or username</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600">
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
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600">
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
                class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-500 transition hover:bg-white/[0.05] hover:text-slate-200"
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
            class="h-4 w-4 rounded border-white/15 bg-white/[0.03] text-brand-500 focus:ring-brand-500/30 focus:ring-offset-0"
        >
        Keep me signed in for 30 days
    </label>

    <button type="submit" class="btn btn-primary w-full" data-submit>
        <span data-submit-label>Sign in</span>
        <i data-lucide="loader-2" data-submit-spinner class="hidden h-4 w-4 animate-spin"></i>
    </button>
</form>

<div class="mt-8 border-t border-white/[0.07] pt-6 text-center text-sm text-slate-500">
    New to Questify?
    <a href="<?= e(url('/register')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
        Create your account
    </a>
</div>

<p class="mt-6 flex items-start gap-2 text-[11px] leading-relaxed text-slate-600">
    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-aqua-400/70"></i>
    Sign-in attempts are rate limited. Passwords are hashed with bcrypt and never stored in plain text.
</p>
