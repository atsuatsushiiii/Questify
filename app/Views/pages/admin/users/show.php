<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin user detail.
 *
 * @var array<string, mixed> $detail
 * @var array<string, string> $suspensionOptions
 */
$account = $detail['user'];
$questCounts = $detail['questCounts'];
$viewer = auth_user() ?? [];
$isSelf = (int) ($viewer['id'] ?? 0) === (int) $account['id'];
$initial = mb_strtoupper(mb_substr((string) $account['display_name'], 0, 1));
$avatar = avatar_url(isset($account['avatar_path']) ? (string) $account['avatar_path'] : null);
$joined = format_datetime((string) $account['created_at'], 'UTC', 'j M Y, H:i');
$lastLogin = format_datetime(
    isset($account['last_login_at']) ? (string) $account['last_login_at'] : null,
    'UTC',
    'j M Y, H:i',
);
$timezone = (string) ($account['timezone'] ?? 'UTC');
$suspendedUntil = isset($account['suspended_until']) && $account['suspended_until'] !== null
    ? format_datetime((string) $account['suspended_until'], 'UTC', 'j M Y, H:i')
    : null;
$isRestricted = account_restricted($account);
$restrictedAt = isset($account['restricted_at']) && $account['restricted_at'] !== null
    ? format_datetime((string) $account['restricted_at'], 'UTC', 'j M Y, H:i')
    : null;
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin/users')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Back to users
        </a>

        <div class="mt-4 flex items-start gap-4">
            <?php if ($avatar !== null): ?>
                <img src="<?= e($avatar) ?>" alt="" class="h-16 w-16 shrink-0 rounded-sm border border-line object-cover">
            <?php else: ?>
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-sm bg-brand-400 text-xl font-semibold text-[#14100B]">
                    <?= e($initial) ?>
                </span>
            <?php endif; ?>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= $account['status'] === 'active'
                        ? 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300'
                        : 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300' ?>">
                        <span class="h-1.5 w-1.5 rounded-sm <?= $account['status'] === 'active' ? 'bg-aqua-400' : 'bg-ember-400' ?>"></span>
                        <?= e(ucfirst((string) $account['status'])) ?>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= $account['role'] === 'admin'
                        ? 'border-brand-400/30 bg-brand-400/[0.08] text-brand-300'
                        : 'border-line bg-white/[0.03] text-slate-400' ?>">
                        <i data-lucide="<?= $account['role'] === 'admin' ? 'shield-check' : 'user-round' ?>" class="h-3 w-3"></i>
                        <?= $account['role'] === 'admin' ? 'Administrator' : 'User' ?>
                    </span>
                    <?php if ($isRestricted): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-sm border border-ember-400/30 bg-ember-500/[0.08] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-ember-300">
                            <i data-lucide="eye-off" class="h-3 w-3"></i>
                            Read-only
                        </span>
                    <?php endif; ?>
                    <?php if ($isSelf): ?>
                        <span class="rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">You</span>
                    <?php endif; ?>
                </div>

                <h1 class="mt-2 break-words font-display text-3xl font-semibold tracking-tight text-white"><?= e((string) $account['display_name']) ?></h1>
                <p class="mt-1 text-sm text-muted">@<?= e((string) $account['username']) ?> · <?= e((string) $account['email']) ?></p>
                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted-dim">
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar-days" class="h-3 w-3"></i> Joined <?= e($joined ?? '—') ?> UTC</span>
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="clock" class="h-3 w-3"></i> Last login <?= e($lastLogin ?? 'never') ?></span>
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="globe" class="h-3 w-3"></i> <?= e($timezone) ?></span>
                    <?php if ($account['status'] === 'suspended'): ?>
                        <span class="inline-flex items-center gap-1.5 text-ember-300">
                            <i data-lucide="clock" class="h-3 w-3"></i>
                            <?= $suspendedUntil !== null ? 'Suspended until ' . e($suspendedUntil) . ' UTC' : 'Suspended indefinitely' ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($isRestricted): ?>
                        <span class="inline-flex items-center gap-1.5 text-ember-300">
                            <i data-lucide="eye-off" class="h-3 w-3"></i>
                            Read-only since <?= e($restrictedAt ?? '—') ?> UTC
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        <?php if (!$isSelf): ?>
            <?php if ($account['status'] === 'active'): ?>
                <form method="POST" action="<?= e(url('/admin/users/' . (int) $account['id'] . '/suspend')) ?>" class="flex flex-wrap items-end gap-2" data-confirm="Suspend this account? Every active session and remember-me token will be revoked.">
                    <?= csrf_field() ?>
                    <label class="block">
                        <span class="sr-only">Suspension length</span>
                        <select name="duration" class="app-field py-2 text-xs">
                            <?php foreach ($suspensionOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $value === 'permanent' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn px-3 py-2 text-xs text-ember-300 hover:bg-ember-500/10 hover:text-ember-200">
                        <i data-lucide="ban" class="h-3.5 w-3.5"></i>
                        Suspend
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="<?= e(url('/admin/users/' . (int) $account['id'] . '/activate')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-app-primary px-3 py-2 text-xs">
                        <i data-lucide="user-check" class="h-3.5 w-3.5"></i>
                        Activate
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($account['role'] === 'admin'): ?>
                <form method="POST" action="<?= e(url('/admin/users/' . (int) $account['id'] . '/demote')) ?>" data-confirm="Remove administrator access from this account?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-app-ghost px-3 py-2 text-xs">
                        <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i>
                        Demote
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="<?= e(url('/admin/users/' . (int) $account['id'] . '/promote')) ?>" data-confirm="Give this account full administrator access?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-app-ghost px-3 py-2 text-xs">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                        Promote
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <span class="inline-flex items-center gap-2 rounded-sm border border-line bg-white/[0.02] px-3 py-2 text-[11px] text-muted">
                <i data-lucide="info" class="h-3.5 w-3.5"></i>
                Self-moderation is disabled
            </span>
        <?php endif; ?>
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-reveal>
    <article class="app-stat-card">
        <p class="app-stat-label">Level</p>
        <p class="app-stat-value"><?= e((string) $account['level']) ?></p>
        <p class="app-stat-note"><?= e(number_format((int) $account['xp_total'])) ?> cached XP</p>
    </article>
    <article class="app-stat-card">
        <p class="app-stat-label">Ledger XP</p>
        <p class="app-stat-value"><?= e(number_format($detail['ledgerXp'])) ?></p>
        <p class="app-stat-note">Sum of XP transactions.</p>
    </article>
    <article class="app-stat-card">
        <p class="app-stat-label">Completed</p>
        <p class="app-stat-value"><?= e((string) $questCounts['completed']) ?></p>
        <p class="app-stat-note"><?= e((string) array_sum($questCounts)) ?> quests in total.</p>
    </article>
    <article class="app-stat-card">
        <p class="app-stat-label">Badges</p>
        <p class="app-stat-value"><?= e((string) $detail['achievementsUnlocked']) ?></p>
        <p class="app-stat-note">Achievements unlocked.</p>
    </article>
</section>

<section class="mt-4 grid gap-4 xl:grid-cols-[1.4fr_1fr]" data-reveal>
    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                    <i data-lucide="scroll-text" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Recent quests</h2>
                    <p class="text-[11px] text-muted">Newest 12 definitions owned by this account.</p>
                </div>
            </div>
            <div class="flex gap-2 text-[10px] text-muted">
                <span><?= e((string) $questCounts['active']) ?> active</span>
                <span>·</span>
                <span><?= e((string) $questCounts['archived']) ?> archived</span>
            </div>
        </div>

        <div class="p-4">
            <?php if ($detail['recentQuests'] === []): ?>
                <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-5 py-8 text-center text-xs text-muted">
                    This account has not created any quests yet.
                </p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($detail['recentQuests'] as $quest): ?>
                        <?php
                        $statusTone = match ((string) $quest['status']) {
                            'completed' => 'border-aqua-400/30 bg-aqua-500/[0.06] text-aqua-300',
                            'archived' => 'border-line bg-white/[0.03] text-slate-400',
                            default => 'border-ember-400/30 bg-ember-500/[0.06] text-ember-300',
                        };
                        ?>
                        <li class="flex items-center gap-3 rounded-sm border border-line bg-white/[0.015] px-3 py-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-sm border <?= e($statusTone) ?>">
                                <i data-lucide="<?= $quest['status'] === 'completed' ? 'circle-check' : ($quest['status'] === 'archived' ? 'archive' : 'target') ?>" class="h-3.5 w-3.5"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $quest['title']) ?></p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[10px] text-muted-dim">
                                    <span><?= e(ucfirst((string) $quest['difficulty'])) ?></span>
                                    <span>·</span>
                                    <span><?= e((string) $quest['xp_reward']) ?> XP</span>
                                    <span>·</span>
                                    <span><?= e((string) ($quest['category_name'] ?? 'Uncategorised')) ?></span>
                                    <span>·</span>
                                    <span><?= e((string) format_datetime((string) $quest['created_at'], 'UTC', 'j M Y')) ?></span>
                                </p>
                            </div>
                            <span class="shrink-0 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTone) ?>">
                                <?= e(ucfirst((string) $quest['status'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>

    <div class="flex min-w-0 flex-col gap-4">
        <?php if (!$isSelf): ?>
            <article class="app-panel overflow-hidden">
                <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                    <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                        <i data-lucide="eye-off" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h2 class="font-display text-sm font-semibold text-white">Read-only access</h2>
                        <p class="text-[11px] text-muted">
                            <?= $isRestricted
                                ? 'This account can browse but cannot change quests or submit reports.'
                                : 'Limit this account without revoking its sessions.' ?>
                        </p>
                    </div>
                </div>
                <form
                    method="POST"
                    action="<?= e(url('/admin/users/' . (int) $account['id'] . ($isRestricted ? '/unrestrict' : '/restrict'))) ?>"
                    class="space-y-3 p-4"
                    <?= $isRestricted ? '' : 'data-confirm="Limit this account to read-only access? It keeps its sessions but cannot create, edit, complete, archive, restore, or delete quests or submit reports."' ?>
                >
                    <?= csrf_field() ?>
                    <div>
                        <label for="restrict_message" class="field-label">Message to the account (optional)</label>
                        <textarea id="restrict_message" name="message" rows="2" maxlength="500" class="app-field resize-y" placeholder="<?= $isRestricted ? 'Explain that full access is restored.' : 'Explain the limit to the account.' ?>"></textarea>
                    </div>
                    <button type="submit" class="btn <?= $isRestricted ? 'btn-app-primary' : '' ?> px-4 py-2 text-xs <?= $isRestricted ? '' : 'text-ember-300 hover:bg-ember-500/10 hover:text-ember-200' ?>">
                        <i data-lucide="<?= $isRestricted ? 'shield-check' : 'eye-off' ?>" class="h-3.5 w-3.5"></i>
                        <?= $isRestricted ? 'Restore full access' : 'Limit to read-only' ?>
                    </button>
                </form>
            </article>
        <?php endif; ?>

        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="monitor" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Active sessions</h2>
                    <p class="text-[11px] text-muted"><?= e((string) count($detail['sessions'])) ?> unexpired session<?= count($detail['sessions']) === 1 ? '' : 's' ?>.</p>
                </div>
            </div>
            <div class="p-4">
                <?php if ($detail['sessions'] === []): ?>
                    <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">No active sessions.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($detail['sessions'] as $session): ?>
                            <li class="rounded-sm border border-line bg-white/[0.015] px-3 py-2.5">
                                <p class="truncate text-[11px] font-medium text-slate-300"><?= e(mb_substr((string) ($session['user_agent'] ?? 'Unknown device'), 0, 80)) ?></p>
                                <p class="mt-0.5 text-[10px] text-muted-dim">
                                    <?= e((string) ($session['ip_address'] ?? '—')) ?> ·
                                    last seen <?= e((string) format_datetime((string) $session['last_activity'], 'UTC', 'j M, H:i')) ?> UTC
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>

        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="history" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Audit history</h2>
                    <p class="text-[11px] text-muted">Privileged actions taken against this account.</p>
                </div>
            </div>
            <div class="p-4">
                <?php if ($detail['audit'] === []): ?>
                    <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">No moderation history.</p>
                <?php else: ?>
                    <ol class="space-y-2">
                        <?php foreach ($detail['audit'] as $entry): ?>
                            <li class="rounded-sm border border-line bg-white/[0.015] px-3 py-2.5">
                                <p class="text-[11px] font-medium text-slate-300"><?= e((string) $entry['summary']) ?></p>
                                <p class="mt-0.5 text-[10px] text-muted-dim">
                                    <span class="font-mono text-brand-300/80"><?= e((string) $entry['action']) ?></span> ·
                                    <?= e((string) ($entry['actor_display_name'] ?? 'System')) ?> ·
                                    <?= e((string) format_datetime((string) $entry['created_at'], 'UTC', 'j M, H:i')) ?> UTC
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<section class="mt-4" data-reveal>
    <article class="app-panel p-5">
        <h2 class="font-display text-sm font-semibold text-white">Account record</h2>
        <dl class="mt-4 grid gap-4 text-[11px] sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">User ID</dt>
                <dd class="mt-1 font-mono text-slate-300">#<?= e((string) $account['id']) ?></dd>
            </div>
            <div>
                <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Email verified</dt>
                <dd class="mt-1 text-slate-300"><?= !empty($account['email_verified_at']) ? 'Yes' : 'No' ?></dd>
            </div>
            <div>
                <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Leaderboard</dt>
                <dd class="mt-1 text-slate-300"><?= (int) ($account['show_on_leaderboard'] ?? 0) === 1 ? 'Public' : 'Hidden' ?></dd>
            </div>
            <div>
                <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Public profile</dt>
                <dd class="mt-1 text-slate-300"><?= (int) ($account['show_profile'] ?? 1) === 1 ? 'Published' : 'Hidden' ?></dd>
            </div>
            <div>
                <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Streak</dt>
                <dd class="mt-1 text-slate-300"><?= e((string) $account['current_streak']) ?> current · <?= e((string) $account['longest_streak']) ?> best</dd>
            </div>
        </dl>
    </article>
</section>
