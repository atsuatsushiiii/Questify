<?php
/**
 * Request a password reset link.
 *
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$describedBy = static fn (string $field): string => isset($errors[$field]) ? $field . '-error' : '';
?>
<div class="mb-8">
    <p class="eyebrow">
        <i data-lucide="key-round" class="h-3.5 w-3.5"></i>
        Account recovery
    </p>
    <h1 class="mt-5 font-display text-3xl font-bold tracking-tight text-white sm:text-[2.15rem]">
        Lost your key?
    </h1>
    <p class="mt-3 text-sm leading-relaxed text-muted">
        Enter the email address linked to your account and we&rsquo;ll send a reset link.
        The link expires in one hour.
    </p>
</div>

<?php if (is_string($message) && $message !== ''): ?>
    <?php
    $type = 'error';
    require __DIR__ . '/../../partials/alert.php';
    ?>
<?php endif; ?>

<form method="POST" action="<?= e(url('/forgot-password')) ?>" class="space-y-5" data-auth-form>
    <?= csrf_field() ?>

    <div>
        <label for="email" class="field-label">Email address</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600">
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
                autofocus
                aria-invalid="<?= $invalid('email') ?>"
                aria-describedby="<?= e($describedBy('email')) ?>"
            >
        </div>
        <?php $field = 'email'; require __DIR__ . '/../../partials/field-error.php'; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full" data-submit>
        <span data-submit-label>Send reset link</span>
        <i data-lucide="loader-2" data-submit-spinner class="hidden h-4 w-4 animate-spin"></i>
    </button>
</form>

<?php if (config('app.env') === 'local'): ?>
    <div class="mt-6 rounded-xl border border-white/[0.07] bg-white/[0.02] p-4">
        <p class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
            <i data-lucide="info" class="h-3.5 w-3.5"></i>
            Local development
        </p>
        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            Email delivery is not configured yet. Reset links are written to
            <code class="rounded bg-black/40 px-1 py-0.5 text-[11px] text-brand-200">storage/logs/mail.log</code>.
        </p>
    </div>
<?php endif; ?>

<div class="mt-7 border-t border-white/[0.07] pt-6 text-center text-sm text-slate-500">
    Remembered it?
    <a href="<?= e(url('/login')) ?>" class="font-semibold text-brand-300 transition hover:text-brand-200">
        Back to sign in
    </a>
</div>
