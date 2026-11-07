<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Owner-only account settings.
 *
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $sessions
 * @var array<string, mixed>|null $pendingEmail
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 * @var string|null $form
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$displayName = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
$username = (string) ($user['username'] ?? '');
$email = (string) ($user['email'] ?? '');
$memberSince = format_datetime(isset($user['created_at']) ? (string) $user['created_at'] : null, $timezone, 'j M Y');
$lastLogin = format_datetime(isset($user['last_login_at']) ? (string) $user['last_login_at'] : null, $timezone, 'j M Y, H:i');
$emailVerified = !empty($user['email_verified_at']);
$oldEmail = (string) ($old['new_email'] ?? '');
$otherSessions = array_values(array_filter($sessions, static fn (array $session): bool => !(bool) ($session['is_current'] ?? false)));
$pendingExpires = $pendingEmail !== null
    ? format_datetime((string) $pendingEmail['expires_at'], $timezone, 'j M Y, H:i')
    : null;

$pref = static fn (string $key): bool => (int) ($user[$key] ?? 1) === 1;

$errorFor = static fn (string $section, string $field): ?string => $form === $section && isset($errors[$field])
    ? (string) $errors[$field]
    : null;

$renderError = static function (string $section, string $field, string $inputId) use ($errorFor): void {
    $text = $errorFor($section, $field);

    if ($text === null) {
        return;
    }

    echo '<p id="' . e($inputId) . '-error" class="mt-1.5 flex items-start gap-1.5 text-xs text-ember-300">'
        . '<i data-lucide="alert-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>'
        . '<span>' . e($text) . '</span></p>';
};

$deviceLabel = static function (array $session): string {
    $agent = (string) ($session['user_agent'] ?? '');

    if ($agent === '') {
        return 'Unknown device';
    }

    $browser = match (true) {
        str_contains($agent, 'Edg/') => 'Edge',
        str_contains($agent, 'OPR/'), str_contains($agent, 'Opera') => 'Opera',
        str_contains($agent, 'Firefox/') => 'Firefox',
        str_contains($agent, 'Chrome/') => 'Chrome',
        str_contains($agent, 'Safari/') => 'Safari',
        default => 'Browser',
    };

    $platform = match (true) {
        str_contains($agent, 'iPhone'), str_contains($agent, 'iPad'), str_contains($agent, 'iPod') => 'iOS',
        str_contains($agent, 'Android') => 'Android',
        str_contains($agent, 'Windows') => 'Windows',
        str_contains($agent, 'Mac OS X'), str_contains($agent, 'Macintosh') => 'macOS',
        str_contains($agent, 'Linux') => 'Linux',
        default => 'Unknown OS',
    };

    return $browser . ' on ' . $platform;
};
?>
<section class="mx-auto max-w-6xl" data-reveal data-settings-page>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">
                <i data-lucide="settings" class="h-3.5 w-3.5"></i>
                Account controls
            </p>
            <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Settings</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">
                Secure your sign-in, choose what Questify notifies you about, review active sessions, and control your data.
            </p>
        </div>
        <a href="<?= e(url('/profile')) ?>" class="btn-app-ghost btn inline-flex text-xs">
            <i data-lucide="user-round" class="h-3.5 w-3.5"></i>
            Back to profile
        </a>
    </div>

    <?php if ($message !== null): ?>
        <?php $type = 'error'; $alertMessage = $message; require __DIR__ . '/../../partials/alert.php'; ?>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <div class="min-w-0 space-y-4">
            <article id="security" class="app-panel scroll-mt-24 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Security</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Change password</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">Updating your password signs out every other device and revokes all remember-me tokens.</p>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-ember-400/30 bg-ember-500/10 text-ember-300">
                        <i data-lucide="key-round" class="h-4 w-4"></i>
                    </span>
                </div>

                <form method="POST" action="<?= e(url('/settings/password')) ?>" class="mt-5 space-y-4" data-auth-form>
                    <?= csrf_field() ?>

                    <div>
                        <label for="password_current" class="field-label">Current password</label>
                        <div class="relative">
                            <input
                                id="password_current"
                                name="current_password"
                                type="password"
                                class="app-field pr-12 <?= $errorFor('password', 'current_password') !== null ? 'is-invalid' : '' ?>"
                                required
                                maxlength="200"
                                autocomplete="current-password"
                                aria-invalid="<?= $errorFor('password', 'current_password') !== null ? 'true' : 'false' ?>"
                                aria-describedby="<?= $errorFor('password', 'current_password') !== null ? 'password_current-error' : '' ?>"
                            >
                            <button type="button" hidden class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.06] hover:text-white" data-password-toggle="password_current" aria-label="Show password" aria-pressed="false">
                                <i data-lucide="eye" class="h-4 w-4" data-eye-open></i>
                                <i data-lucide="eye-off" class="hidden h-4 w-4" data-eye-off></i>
                            </button>
                        </div>
                        <?php $renderError('password', 'current_password', 'password_current'); ?>
                    </div>

                    <div>
                        <label for="password_new" class="field-label">New password</label>
                        <div class="relative">
                            <input
                                id="password_new"
                                name="password"
                                type="password"
                                class="app-field pr-12 <?= $errorFor('password', 'password') !== null ? 'is-invalid' : '' ?>"
                                required
                                minlength="<?= e((string) config('auth.min_password_length', 10)) ?>"
                                maxlength="<?= e((string) config('auth.max_password_length', 72)) ?>"
                                autocomplete="new-password"
                                data-strength-input
                                aria-invalid="<?= $errorFor('password', 'password') !== null ? 'true' : 'false' ?>"
                                aria-describedby="password_new-help<?= $errorFor('password', 'password') !== null ? ' password_new-error' : '' ?>"
                            >
                            <button type="button" hidden class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.06] hover:text-white" data-password-toggle="password_new" aria-label="Show password" aria-pressed="false">
                                <i data-lucide="eye" class="h-4 w-4" data-eye-open></i>
                                <i data-lucide="eye-off" class="hidden h-4 w-4" data-eye-off></i>
                            </button>
                        </div>
                        <div class="mt-2 hidden" data-strength aria-live="polite">
                            <div class="flex gap-1.5">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <span class="h-1 flex-1 bg-white/[0.08]" data-strength-segment></span>
                                <?php endfor; ?>
                            </div>
                            <p class="mt-1.5 text-[11px] text-muted" data-strength-label>Too short</p>
                        </div>
                        <p id="password_new-help" class="mt-1.5 text-[11px] text-muted-dim">At least <?= e((string) config('auth.min_password_length', 10)) ?> characters. Mix letters, numbers, and symbols.</p>
                        <?php $renderError('password', 'password', 'password_new'); ?>
                    </div>

                    <div>
                        <label for="password_confirmation" class="field-label">Confirm new password</label>
                        <div class="relative">
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="app-field pr-12 <?= $errorFor('password', 'password_confirmation') !== null ? 'is-invalid' : '' ?>"
                                required
                                maxlength="<?= e((string) config('auth.max_password_length', 72)) ?>"
                                autocomplete="new-password"
                                aria-invalid="<?= $errorFor('password', 'password_confirmation') !== null ? 'true' : 'false' ?>"
                                aria-describedby="<?= $errorFor('password', 'password_confirmation') !== null ? 'password_confirmation-error' : '' ?>"
                            >
                            <button type="button" hidden class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.06] hover:text-white" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                                <i data-lucide="eye" class="h-4 w-4" data-eye-open></i>
                                <i data-lucide="eye-off" class="hidden h-4 w-4" data-eye-off></i>
                            </button>
                        </div>
                        <?php $renderError('password', 'password_confirmation', 'password_confirmation'); ?>
                    </div>

                    <button type="submit" class="btn btn-app-primary w-full text-sm" data-submit>
                        <i data-lucide="lock" class="h-4 w-4"></i>
                        <span data-submit-label>Update password</span>
                        <i data-lucide="loader-2" class="hidden h-4 w-4 animate-spin" data-submit-spinner></i>
                    </button>
                </form>
            </article>

            <article id="email" class="app-panel scroll-mt-24 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Identity</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Email address</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">A confirmation link is sent to the new address. The change completes only after you open it.</p>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 text-brand-300">
                        <i data-lucide="mail" class="h-4 w-4"></i>
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 rounded-sm border border-line bg-white/[0.02] px-3.5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">Current email</p>
                        <p class="mt-0.5 truncate text-xs font-semibold text-slate-200"><?= e($email) ?></p>
                    </div>
                    <?php if ($emailVerified): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-sm border border-aqua-400/30 bg-aqua-500/[0.08] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-aqua-300">
                            <i data-lucide="badge-check" class="h-3 w-3"></i>
                            Verified
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 rounded-sm border border-brand-400/30 bg-brand-400/[0.08] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-brand-300">
                            <i data-lucide="mail" class="h-3 w-3"></i>
                            Unverified
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($pendingEmail !== null): ?>
                    <div class="mt-3 rounded-sm border border-brand-400/30 bg-brand-400/[0.06] px-3.5 py-3">
                        <p class="flex items-start gap-2 text-[11px] leading-relaxed text-brand-100">
                            <i data-lucide="clock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i>
                            <span>
                                Waiting for confirmation at <strong class="font-semibold text-white"><?= e((string) $pendingEmail['new_email']) ?></strong>.
                                <?php if ($pendingExpires !== null): ?>The link expires <?= e($pendingExpires) ?> (<?= e($timezone) ?>).<?php endif; ?>
                                Open the link in the message to finish the change.
                            </span>
                        </p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(url('/settings/email')) ?>" class="mt-4 space-y-4" data-auth-form>
                    <?= csrf_field() ?>

                    <div>
                        <label for="email_new" class="field-label">New email address</label>
                        <input
                            id="email_new"
                            name="new_email"
                            type="email"
                            class="app-field <?= $errorFor('email', 'new_email') !== null ? 'is-invalid' : '' ?>"
                            value="<?= e($oldEmail) ?>"
                            required
                            maxlength="190"
                            autocomplete="email"
                            placeholder="you@example.com"
                            aria-invalid="<?= $errorFor('email', 'new_email') !== null ? 'true' : 'false' ?>"
                            aria-describedby="<?= $errorFor('email', 'new_email') !== null ? 'email_new-error' : 'email_new-help' ?>"
                        >
                        <p id="email_new-help" class="mt-1.5 text-[11px] text-muted-dim">Use an address you control. It becomes your sign-in email after confirmation.</p>
                        <?php $renderError('email', 'new_email', 'email_new'); ?>
                    </div>

                    <div>
                        <label for="email_current" class="field-label">Current password</label>
                        <div class="relative">
                            <input
                                id="email_current"
                                name="current_password"
                                type="password"
                                class="app-field pr-12 <?= $errorFor('email', 'current_password') !== null ? 'is-invalid' : '' ?>"
                                required
                                maxlength="200"
                                autocomplete="current-password"
                                aria-invalid="<?= $errorFor('email', 'current_password') !== null ? 'true' : 'false' ?>"
                                aria-describedby="<?= $errorFor('email', 'current_password') !== null ? 'email_current-error' : '' ?>"
                            >
                            <button type="button" hidden class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.06] hover:text-white" data-password-toggle="email_current" aria-label="Show password" aria-pressed="false">
                                <i data-lucide="eye" class="h-4 w-4" data-eye-open></i>
                                <i data-lucide="eye-off" class="hidden h-4 w-4" data-eye-off></i>
                            </button>
                        </div>
                        <?php $renderError('email', 'current_password', 'email_current'); ?>
                    </div>

                    <button type="submit" class="btn btn-app-ghost w-full text-sm" data-submit>
                        <i data-lucide="mail" class="h-4 w-4"></i>
                        <span data-submit-label>Send confirmation link</span>
                        <i data-lucide="loader-2" class="hidden h-4 w-4 animate-spin" data-submit-spinner></i>
                    </button>
                </form>
            </article>

            <article id="notifications" class="app-panel scroll-mt-24 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Inbox</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Notification preferences</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">Choose which adventure events create in-app notifications. Security and profile messages are always delivered.</p>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 text-brand-300">
                        <i data-lucide="bell" class="h-4 w-4"></i>
                    </span>
                </div>

                <form method="POST" action="<?= e(url('/settings/notifications')) ?>" class="mt-5 space-y-2.5">
                    <?= csrf_field() ?>

                    <?php
                    $preferences = [
                        ['name' => 'notify_achievements', 'label' => 'Achievement unlocks', 'copy' => 'Tell me when a new trophy enters my collection.'],
                        ['name' => 'notify_level_ups', 'label' => 'Level ups', 'copy' => 'Tell me when quest XP pushes me into a new level.'],
                        ['name' => 'notify_streaks', 'label' => 'Streak milestones', 'copy' => 'Tell me when I reach a new streak milestone.'],
                        ['name' => 'notify_leaderboard', 'label' => 'Leaderboard movement', 'copy' => 'Tell me when my public rank changes.'],
                    ];
                    ?>
                    <?php foreach ($preferences as $preference): ?>
                        <label class="flex cursor-pointer items-start gap-3 rounded-sm border border-line bg-white/[0.02] p-3.5 transition hover:border-brand-400/30 hover:bg-white/[0.03]">
                            <input
                                type="checkbox"
                                name="<?= e($preference['name']) ?>"
                                value="1"
                                class="mt-0.5 h-4 w-4 rounded-sm border-line bg-white/[0.03] accent-ember-500"
                                <?= $pref($preference['name']) ? 'checked' : '' ?>
                            >
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold text-slate-200"><?= e($preference['label']) ?></span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-muted"><?= e($preference['copy']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-app-ghost w-full text-sm">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        Save preferences
                    </button>
                </form>
            </article>
        </div>

        <div class="min-w-0 space-y-4">
            <article class="app-panel relative overflow-hidden p-5">
                <div class="pointer-events-none absolute inset-0 bg-grid opacity-40" aria-hidden="true"></div>
                <div class="relative">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="app-stat-label">Signed in as</p>
                            <h2 class="mt-1 truncate font-display text-lg font-semibold text-white"><?= e($displayName) ?></h2>
                            <?php if ($username !== ''): ?>
                                <p class="mt-0.5 truncate font-mono text-[10px] text-muted">@<?= e($username) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 text-brand-300">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                        </span>
                    </div>

                    <dl class="mt-4 space-y-2.5 text-[11px]">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted-dim">Email</dt>
                            <dd class="min-w-0 break-all text-right font-medium text-slate-300"><?= e($email) ?></dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted-dim">Member since</dt>
                            <dd class="text-right font-medium text-slate-300"><?= e($memberSince ?? 'Today') ?></dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted-dim">Last sign-in</dt>
                            <dd class="text-right font-medium text-slate-300"><?= e($lastLogin ?? 'This session') ?></dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted-dim">Timezone</dt>
                            <dd class="text-right font-medium text-slate-300"><?= e(str_replace('_', ' ', $timezone)) ?></dd>
                        </div>
                    </dl>

                    <a href="<?= e(url('/profile')) ?>" class="mt-4 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">
                        Edit profile details
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                </div>
            </article>

            <article id="sessions" class="app-panel scroll-mt-24 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Devices</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Active sessions</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">
                            <?= e((string) count($sessions)) ?> active <?= count($sessions) === 1 ? 'session' : 'sessions' ?>. Signing out a device ends its session immediately.
                        </p>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-line bg-white/[0.03] text-slate-300">
                        <i data-lucide="monitor" class="h-4 w-4"></i>
                    </span>
                </div>

                <ul class="mt-4 space-y-2.5">
                    <?php foreach ($sessions as $session): ?>
                        <?php $isCurrent = (bool) ($session['is_current'] ?? false); ?>
                        <li class="rounded-sm border <?= $isCurrent ? 'border-aqua-400/30 bg-aqua-500/[0.05]' : 'border-line bg-white/[0.02]' ?> p-3.5">
                            <div class="flex items-start gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-sm border <?= $isCurrent ? 'border-aqua-400/30 bg-aqua-500/10 text-aqua-300' : 'border-line bg-white/[0.03] text-slate-400' ?>">
                                    <i data-lucide="<?= !empty($session['is_mobile']) ? 'smartphone' : 'monitor' ?>" class="h-4 w-4"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="truncate text-xs font-semibold text-slate-200"><?= e($deviceLabel($session)) ?></p>
                                        <?php if ($isCurrent): ?>
                                            <span class="rounded-sm border border-aqua-400/30 bg-aqua-500/[0.08] px-1.5 py-0.5 font-mono text-[8px] font-semibold uppercase tracking-[0.12em] text-aqua-300">This device</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-0.5 font-mono text-[9px] tracking-[0.04em] text-muted-dim">
                                        <?= e((string) ($session['ip_address'] ?: 'Unknown IP')) ?>
                                        · Last active <?= e(format_datetime((string) ($session['last_activity_at'] ?? ''), $timezone, 'j M Y, H:i') ?? 'recently') ?>
                                    </p>
                                </div>
                                <?php if (!$isCurrent): ?>
                                    <form method="POST" action="<?= e(url('/settings/sessions/' . (string) $session['id'] . '/revoke')) ?>" class="shrink-0" data-confirm="Sign out this session?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="grid h-8 w-8 place-items-center rounded-sm border border-line text-muted transition hover:border-ember-400/30 hover:bg-ember-500/10 hover:text-ember-200" title="Sign out this session" aria-label="Sign out this session">
                                            <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($otherSessions !== []): ?>
                    <form method="POST" action="<?= e(url('/settings/sessions/revoke-others')) ?>" class="mt-3" data-confirm="Sign out every other session?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn w-full border border-ember-400/30 bg-ember-500/[0.06] text-xs text-ember-200 transition hover:bg-ember-500/[0.12]">
                            <i data-lucide="log-out" class="h-3.5 w-3.5"></i>
                            Sign out all other sessions
                        </button>
                    </form>
                <?php else: ?>
                    <p class="mt-3 rounded-sm border border-dashed border-line bg-panel/30 px-3 py-2.5 text-center font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">This device is the only active session.</p>
                <?php endif; ?>
            </article>

            <article id="export" class="app-panel scroll-mt-24 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="app-stat-label">Your data</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Export account</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">Download a portable JSON copy of your Questify history.</p>
                    </div>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/10 text-brand-300">
                        <i data-lucide="download" class="h-4 w-4"></i>
                    </span>
                </div>

                <ul class="mt-4 grid gap-1.5 text-[11px] text-muted">
                    <li class="flex items-start gap-2"><i data-lucide="check" class="mt-0.5 h-3 w-3 shrink-0 text-aqua-400"></i>Profile, quests, XP ledger, achievements, streaks, notifications, and session metadata.</li>
                    <li class="flex items-start gap-2"><i data-lucide="shield-check" class="mt-0.5 h-3 w-3 shrink-0 text-brand-400"></i>Never includes password hashes, reset tokens, session payloads, or remember-me secrets.</li>
                </ul>

                <form method="POST" action="<?= e(url('/settings/export')) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-app-ghost btn w-full text-xs">
                        <i data-lucide="download" class="h-3.5 w-3.5"></i>
                        Download JSON export
                    </button>
                </form>
            </article>
        </div>
    </div>

    <article id="danger" class="mt-4 scroll-mt-24 overflow-hidden rounded-sm border border-ember-400/30 bg-ember-500/[0.04]" data-reveal>
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-ember-400/20 px-5 py-4">
            <div>
                <p class="font-mono text-[9px] font-semibold uppercase tracking-[0.16em] text-ember-300/80">Danger zone</p>
                <h2 class="mt-1 font-display text-lg font-semibold text-white">Delete account</h2>
                <p class="mt-1 max-w-2xl text-[11px] leading-relaxed text-ember-100/70">
                    Permanently removes your profile, quests, XP ledger, achievements, streaks, notifications, sessions, and avatar. This cannot be undone and there is no recovery window.
                </p>
            </div>
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-sm border border-ember-400/30 bg-ember-500/10 text-ember-300">
                <i data-lucide="shield-alert" class="h-4 w-4"></i>
            </span>
        </div>

        <form method="POST" action="<?= e(url('/settings/delete')) ?>" class="grid gap-4 px-5 py-4 sm:grid-cols-2" data-delete-form>
            <?= csrf_field() ?>

            <div>
                <label for="delete_current" class="field-label">Current password</label>
                <div class="relative">
                    <input
                        id="delete_current"
                        name="current_password"
                        type="password"
                        class="app-field pr-12 <?= $errorFor('delete', 'current_password') !== null ? 'is-invalid' : '' ?>"
                        required
                        maxlength="200"
                        autocomplete="current-password"
                        aria-invalid="<?= $errorFor('delete', 'current_password') !== null ? 'true' : 'false' ?>"
                        aria-describedby="<?= $errorFor('delete', 'current_password') !== null ? 'delete_current-error' : '' ?>"
                    >
                    <button type="button" hidden class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-sm text-muted transition hover:bg-white/[0.06] hover:text-white" data-password-toggle="delete_current" aria-label="Show password" aria-pressed="false">
                        <i data-lucide="eye" class="h-4 w-4" data-eye-open></i>
                        <i data-lucide="eye-off" class="hidden h-4 w-4" data-eye-off></i>
                    </button>
                </div>
                <?php $renderError('delete', 'current_password', 'delete_current'); ?>
            </div>

            <div>
                <label for="delete_confirmation" class="field-label">Type <span class="font-mono text-ember-200">DELETE MY ACCOUNT</span></label>
                <input
                    id="delete_confirmation"
                    name="confirmation"
                    type="text"
                    class="app-field font-mono uppercase tracking-wide <?= $errorFor('delete', 'confirmation') !== null ? 'is-invalid' : '' ?>"
                    required
                    maxlength="40"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="DELETE MY ACCOUNT"
                    data-delete-phrase
                    aria-invalid="<?= $errorFor('delete', 'confirmation') !== null ? 'true' : 'false' ?>"
                    aria-describedby="delete_confirmation-help<?= $errorFor('delete', 'confirmation') !== null ? ' delete_confirmation-error' : '' ?>"
                >
                <p id="delete_confirmation-help" class="mt-1.5 text-[11px] text-ember-100/60" data-delete-status>This phrase is exact and case-sensitive.</p>
                <?php $renderError('delete', 'confirmation', 'delete_confirmation'); ?>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="btn w-full border border-ember-400/40 bg-ember-500/15 text-sm text-ember-100 transition hover:bg-ember-500/25 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto" data-delete-submit>
                    <i data-lucide="shield-alert" class="h-4 w-4"></i>
                    Permanently delete my account
                </button>
            </div>
        </form>
    </article>
</section>
