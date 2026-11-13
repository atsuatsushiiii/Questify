<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Administrator appeal detail.
 *
 * @var array{appeal: array<string, mixed>, history: list<array<string, mixed>>, audit: list<array<string, mixed>>} $detail
 */
$appeal = $detail['appeal'];
$account = [
    'id' => (int) $appeal['user_id'],
    'username' => (string) ($appeal['user_username'] ?? ''),
    'display_name' => (string) ($appeal['user_display_name'] ?? $appeal['user_username'] ?? 'Adventurer'),
    'email' => (string) ($appeal['user_email'] ?? ''),
    'avatar_path' => $appeal['user_avatar_path'] ?? null,
    'status' => (string) ($appeal['user_status'] ?? ''),
    'restricted_at' => $appeal['user_restricted_at'] ?? null,
    'restricted_until' => $appeal['user_restricted_until'] ?? null,
];
$status = (string) $appeal['status'];
$isOpen = $status === 'open';
$isRestricted = account_restricted($account);
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'accepted' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'denied' => 'border-line bg-white/[0.03] text-slate-400',
];
$statusLabels = ['open' => 'Open', 'accepted' => 'Accepted', 'denied' => 'Denied'];
$avatarUrl = avatar_url(isset($account['avatar_path']) ? (string) $account['avatar_path'] : null);
$initial = mb_strtoupper(mb_substr($account['display_name'], 0, 1));
$created = format_datetime((string) $appeal['created_at'], 'UTC', 'j M Y, H:i');
$resolved = isset($appeal['resolved_at'])
    ? format_datetime((string) $appeal['resolved_at'], 'UTC', 'j M Y, H:i')
    : null;
$restrictedUntil = isset($account['restricted_until'])
    ? format_datetime((string) $account['restricted_until'], 'UTC', 'j M Y, H:i')
    : null;
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin/appeals')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Appeals queue
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Appeal #<?= e((string) $appeal['id']) ?></h1>
        <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted">
            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['denied']) ?>">
                <?= e($statusLabels[$status] ?? ucfirst($status)) ?>
            </span>
            <span>Submitted <?= e($created ?? '—') ?> UTC</span>
            <?php if ($resolved !== null): ?>
                <span>· Decided <?= e($resolved) ?> UTC</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/users/' . $account['id'])) ?>" class="btn btn-app-ghost">
            <i data-lucide="shield-check" class="h-4 w-4"></i>
            Open account record
        </a>
        <a href="<?= e(url('/admin/audit?target_type=appeal')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            Appeal audit trail
        </a>
    </div>
</section>

<section class="mt-6 grid gap-4 xl:grid-cols-[1.5fr_1fr]" data-reveal>
    <div class="min-w-0 space-y-4">
        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                    <i data-lucide="scale" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">The appeal</h2>
                    <p class="text-[11px] text-muted">Written by the account and visible only to moderators.</p>
                </div>
            </div>
            <div class="p-5">
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-300"><?= e((string) $appeal['message']) ?></p>
            </div>
        </article>

        <?php if ($isOpen): ?>
            <article class="app-panel overflow-hidden">
                <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                    <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                        <i data-lucide="gavel" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h2 class="font-display text-sm font-semibold text-white">Decision</h2>
                        <p class="text-[11px] text-muted">
                            <?= $isRestricted
                                ? 'Accepting restores full access immediately. Denying keeps the read-only limit.'
                                : 'This account already has full access; accepting closes the appeal without changing access.' ?>
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 p-5 lg:grid-cols-2">
                    <form method="POST" action="<?= e(url('/admin/appeals/' . (int) $appeal['id'] . '/accept')) ?>" class="space-y-3" data-confirm="Accept this appeal and restore full access?">
                        <?= csrf_field() ?>
                        <div>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Accept the appeal</p>
                            <p class="mt-1 text-[11px] text-muted">Lifts the read-only limit and notifies the account.</p>
                        </div>
                        <div>
                            <label for="accept_note" class="field-label">Note to the account (optional)</label>
                            <textarea id="accept_note" name="resolution_note" rows="2" maxlength="1000" class="app-field resize-y" placeholder="Explain the decision. This is shared with the account."></textarea>
                        </div>
                        <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                            Accept and restore access
                        </button>
                    </form>

                    <form method="POST" action="<?= e(url('/admin/appeals/' . (int) $appeal['id'] . '/deny')) ?>" class="space-y-3 border-t border-line pt-5 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0" data-confirm="Deny this appeal? The read-only limit stays in place.">
                        <?= csrf_field() ?>
                        <div>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Deny the appeal</p>
                            <p class="mt-1 text-[11px] text-muted">Keeps the limit and tells the account why.</p>
                        </div>
                        <div>
                            <label for="deny_note" class="field-label">Note to the account (optional)</label>
                            <textarea id="deny_note" name="resolution_note" rows="2" maxlength="1000" class="app-field resize-y" placeholder="Explain what still needs to change."></textarea>
                        </div>
                        <button type="submit" class="btn px-4 py-2 text-xs text-ember-300 hover:bg-ember-500/10 hover:text-ember-200">
                            <i data-lucide="gavel" class="h-3.5 w-3.5"></i>
                            Deny appeal
                        </button>
                    </form>
                </div>
            </article>
        <?php else: ?>
            <article class="app-panel overflow-hidden">
                <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                    <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                        <i data-lucide="gavel" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <h2 class="font-display text-sm font-semibold text-white">Decision</h2>
                        <p class="text-[11px] text-muted">Closed appeals are immutable.</p>
                    </div>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-slate-300">
                        <?= $status === 'accepted' ? 'Full access was restored.' : 'The read-only limit stayed in place.' ?>
                    </p>
                    <p class="font-mono text-[10px] uppercase tracking-[0.08em] text-muted-dim">
                        Reviewed by <?= e((string) ($appeal['reviewer_display_name'] ?? 'a deleted account')) ?>
                        <?php if ($resolved !== null): ?> · <?= e($resolved) ?> UTC<?php endif; ?>
                    </p>
                    <?php if (trim((string) ($appeal['resolution_note'] ?? '')) !== ''): ?>
                        <div class="rounded-sm border border-line bg-white/[0.02] px-4 py-3">
                            <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Note to the account</p>
                            <p class="mt-1 whitespace-pre-line text-[11px] leading-relaxed text-muted"><?= e((string) $appeal['resolution_note']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <div class="min-w-0 space-y-4">
        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="user-round" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Account</h2>
                    <p class="text-[11px] text-muted">The read-only account that filed this appeal.</p>
                </div>
            </div>

            <div class="p-5">
                <div class="flex items-center gap-3">
                    <?php if ($avatarUrl !== null): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-line object-cover">
                    <?php else: ?>
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-sm bg-brand-400 text-base font-semibold text-[#14100B]" aria-hidden="true"><?= e($initial) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <a href="<?= e(url('/admin/users/' . $account['id'])) ?>" class="block truncate text-sm font-semibold text-slate-200 transition hover:text-brand-200"><?= e($account['display_name']) ?></a>
                        <p class="truncate text-[11px] text-muted">@<?= e($account['username']) ?></p>
                        <p class="truncate text-[10px] text-muted-dim"><?= e($account['email']) ?></p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-sm border border-line bg-white/[0.03] px-2 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-slate-400"><?= e(ucfirst($account['status'])) ?></span>
                    <?php if ($isRestricted): ?>
                        <span class="rounded-sm border border-ember-400/30 bg-ember-500/[0.08] px-2 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-ember-300">
                            Read-only <?= $restrictedUntil !== null ? 'until ' . e($restrictedUntil) . ' UTC' : 'indefinitely' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="<?= e(url('/admin/users/' . $account['id'])) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                    <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                    Open account record
                </a>
            </div>
        </article>

        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="history" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Appeal history</h2>
                    <p class="text-[11px] text-muted">Every request from this account.</p>
                </div>
            </div>
            <ol class="divide-y divide-white/[0.04]">
                <?php foreach ($detail['history'] as $entry): ?>
                    <?php $entryStatus = (string) $entry['status']; ?>
                    <li class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a href="<?= e(url('/admin/appeals/' . (int) $entry['id'])) ?>" class="font-mono text-[10px] font-semibold text-brand-300/80 transition hover:text-brand-200">#<?= e((string) $entry['id']) ?></a>
                            <span class="inline-flex items-center gap-1.5 rounded-sm border px-1.5 py-0.5 font-mono text-[8px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$entryStatus] ?? $statusTones['denied']) ?>">
                                <?= e($statusLabels[$entryStatus] ?? ucfirst($entryStatus)) ?>
                            </span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-[11px] text-muted"><?= e(mb_substr((string) $entry['message'], 0, 160)) ?></p>
                        <p class="mt-1 font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">
                            <?= e(format_datetime((string) $entry['created_at'], 'UTC', 'j M Y, H:i') ?? '—') ?> UTC
                        </p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
    </div>
</section>

<section class="mt-4" data-reveal>
    <article class="app-panel overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                <i data-lucide="history" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Appeal audit</h2>
                <p class="text-[11px] text-muted">Privileged actions tied to this appeal.</p>
            </div>
        </div>

        <?php if ($detail['audit'] === []): ?>
            <div class="px-5 py-10 text-center text-[11px] text-muted">No audit entries yet.</div>
        <?php else: ?>
            <ol class="divide-y divide-white/[0.04]">
                <?php foreach ($detail['audit'] as $entry): ?>
                    <li class="px-5 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-200"><?= e((string) $entry['summary']) ?></p>
                            <time class="font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim" datetime="<?= e((string) iso_datetime((string) $entry['created_at'])) ?>">
                                <?= e(format_datetime((string) $entry['created_at'], 'UTC', 'j M, H:i') ?? '—') ?> UTC
                            </time>
                        </div>
                        <p class="mt-0.5 font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">
                            <span class="text-brand-300/80"><?= e((string) $entry['action']) ?></span>
                            · <?= e((string) ($entry['actor_display_name'] ?? 'System')) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
</section>
