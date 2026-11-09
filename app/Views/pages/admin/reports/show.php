<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin report detail and decision form.
 *
 * @var array{report: array<string, mixed>, target: array<string, mixed>|null, audit: list<array<string, mixed>>} $detail
 * @var array<string, string> $reasons
 */
$report = $detail['report'];
$target = $detail['target'];
$status = (string) $report['status'];
$reason = (string) $report['reason'];
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'resolved' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'dismissed' => 'border-line bg-white/[0.03] text-slate-400',
];
$targetLabels = ['profile' => 'Profile', 'quest' => 'Quest'];
$statusTone = $statusTones[$status] ?? $statusTones['dismissed'];
$created = format_datetime((string) $report['created_at'], 'UTC', 'j M Y, H:i');
$updated = format_datetime((string) $report['updated_at'], 'UTC', 'j M Y, H:i');
$resolved = format_datetime(
    isset($report['resolved_at']) ? (string) $report['resolved_at'] : null,
    'UTC',
    'j M Y, H:i',
);
$reporterName = (string) ($report['reporter_display_name'] ?? 'Deleted account');
$reporterUsername = (string) ($report['reporter_username'] ?? '');
$reporterInitial = mb_strtoupper(mb_substr($reporterName, 0, 1));
$reporterAvatar = avatar_url(isset($report['reporter_avatar_path']) ? (string) $report['reporter_avatar_path'] : null);
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin/reports')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Back to reports
        </a>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTone) ?>">
                <i data-lucide="<?= $status === 'open' ? 'flag' : ($status === 'resolved' ? 'circle-check' : 'circle-slash') ?>" class="h-3 w-3"></i>
                <?= e(ucfirst($status)) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <i data-lucide="tag" class="h-3 w-3"></i>
                <?= e($reasons[$reason] ?? ucfirst($reason)) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <i data-lucide="<?= (string) $report['target_type'] === 'profile' ? 'user-round' : 'scroll-text' ?>" class="h-3 w-3"></i>
                <?= e($targetLabels[(string) $report['target_type']] ?? (string) $report['target_type']) ?>
            </span>
        </div>

        <h1 class="mt-3 break-words font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Report #<?= e((string) $report['id']) ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Against <span class="font-semibold text-slate-300"><?= e((string) $report['target_label']) ?></span>.
            Submitted <?= e($created ?? '—') ?> UTC by
            <?php if ($report['reporter_user_id'] !== null): ?>
                <a href="<?= e(url('/admin/users/' . (int) $report['reporter_user_id'])) ?>" class="text-brand-300 transition hover:text-brand-200"><?= e($reporterName) ?></a>.
            <?php else: ?>
                a deleted account.
            <?php endif; ?>
        </p>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        <a href="<?= e(url('/admin/audit?target_type=report')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">
            <i data-lucide="history" class="h-3.5 w-3.5"></i>
            Audit trail
        </a>
    </div>
</section>

<?php if ($status === 'open'): ?>
    <section class="mt-6" data-reveal>
        <form method="POST" action="<?= e(url('/admin/reports/' . (int) $report['id'] . '/resolve')) ?>" class="app-panel p-5 sm:p-6">
            <?= csrf_field() ?>
            <div class="flex items-center gap-3 border-b border-line pb-4">
                <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                    <i data-lucide="gavel" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Record a decision</h2>
                    <p class="text-[11px] text-muted">The note is optional, but it keeps the audit trail useful for future moderators.</p>
                </div>
            </div>

            <div class="mt-4">
                <label for="resolution_note" class="field-label">Resolution note</label>
                <textarea
                    id="resolution_note"
                    name="resolution_note"
                    rows="3"
                    class="app-field resize-y"
                    maxlength="1000"
                    placeholder="What did you decide, and why? Do not include private information."
                ></textarea>
                <p class="mt-1.5 text-[11px] text-muted-dim">Maximum 1000 characters. The note is stored with the decision.</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-5">
                <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                    <i data-lucide="circle-check" class="h-3.5 w-3.5"></i>
                    Resolve report
                </button>
                <button type="submit" formaction="<?= e(url('/admin/reports/' . (int) $report['id'] . '/dismiss')) ?>" class="btn btn-app-ghost px-4 py-2 text-xs">
                    <i data-lucide="circle-slash" class="h-3.5 w-3.5"></i>
                    Dismiss report
                </button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="mt-6 grid gap-4 xl:grid-cols-[1.4fr_1fr]" data-reveal>
    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="flag" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Report details</h2>
                <p class="text-[11px] text-muted">What the reporter submitted.</p>
            </div>
        </div>

        <div class="p-5">
            <dl class="grid gap-4 text-[11px] sm:grid-cols-2">
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Reason</dt>
                    <dd class="mt-1 font-semibold text-slate-200"><?= e($reasons[$reason] ?? ucfirst($reason)) ?></dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Target</dt>
                    <dd class="mt-1 text-slate-300"><?= e($targetLabels[(string) $report['target_type']] ?? (string) $report['target_type']) ?> #<?= e((string) $report['target_id']) ?></dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Submitted</dt>
                    <dd class="mt-1 text-slate-300"><?= e($created ?? '—') ?> UTC</dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Last updated</dt>
                    <dd class="mt-1 text-slate-300"><?= e($updated ?? '—') ?> UTC</dd>
                </div>
            </dl>

            <div class="mt-5 border-t border-line pt-5">
                <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Reporter’s details</p>
                <?php if (trim((string) ($report['details'] ?? '')) === ''): ?>
                    <p class="mt-2 rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-5 text-center text-[11px] text-muted">No additional details were provided.</p>
                <?php else: ?>
                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-300"><?= e((string) $report['details']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <div class="space-y-4">
        <article class="app-panel min-w-0 overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="<?= (string) $report['target_type'] === 'profile' ? 'user-round' : 'scroll-text' ?>" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Reported <?= e($targetLabels[(string) $report['target_type']] ?? 'target') ?></h2>
                    <p class="text-[11px] text-muted">Current record, if it still exists.</p>
                </div>
            </div>

            <div class="p-5">
                <?php if ($target === null): ?>
                    <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">This target no longer exists. The report keeps the snapshot label from submission time.</p>
                <?php elseif ($target['type'] === 'profile'): ?>
                    <?php
                    $account = $target['record'];
                    $targetName = (string) ($account['display_name'] ?? $account['username'] ?? 'Adventurer');
                    $targetInitial = mb_strtoupper(mb_substr($targetName, 0, 1));
                    $targetAvatar = avatar_url(isset($account['avatar_path']) ? (string) $account['avatar_path'] : null);
                    ?>
                    <div class="flex items-center gap-3">
                        <?php if ($targetAvatar !== null): ?>
                            <img src="<?= e($targetAvatar) ?>" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-line object-cover">
                        <?php else: ?>
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-sm bg-brand-400 text-base font-semibold text-[#14100B]" aria-hidden="true"><?= e($targetInitial) ?></span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <a href="<?= e(url((string) $target['admin_url'])) ?>" class="block truncate text-sm font-semibold text-slate-200 transition hover:text-brand-200"><?= e($targetName) ?></a>
                            <p class="truncate text-[11px] text-muted">@<?= e((string) $account['username']) ?></p>
                            <p class="truncate text-[10px] text-muted-dim"><?= e((string) $account['email']) ?></p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="rounded-sm border border-line bg-white/[0.03] px-2 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-slate-400"><?= e(ucfirst((string) $account['status'])) ?></span>
                        <span class="rounded-sm border border-line bg-white/[0.03] px-2 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-slate-400"><?= (int) $account['show_profile'] === 1 ? 'Profile public' : 'Profile hidden' ?></span>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <a href="<?= e(url((string) $target['admin_url'])) ?>" class="btn btn-app-ghost w-full px-3 py-2 text-xs">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                            Open account record
                        </a>
                        <?php if ($target['profile_url'] !== null): ?>
                            <a href="<?= e(url((string) $target['profile_url'])) ?>" class="btn btn-app-ghost w-full px-3 py-2 text-xs">
                                <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                View public profile
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php $quest = $target['record']; ?>
                    <p class="text-sm font-semibold text-slate-200"><?= e((string) $quest['title']) ?></p>
                    <p class="mt-1 text-[11px] text-muted">Owned by @<?= e((string) $quest['owner_username']) ?> · <?= e(ucfirst((string) $quest['status'])) ?></p>
                    <a href="<?= e(url((string) $target['admin_url'])) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                        <i data-lucide="scroll-text" class="h-3.5 w-3.5"></i>
                        Open quest record
                    </a>
                <?php endif; ?>
            </div>
        </article>

        <article class="app-panel min-w-0 overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                    <i data-lucide="user-round" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Reporter</h2>
                    <p class="text-[11px] text-muted">Kept private from the reported account.</p>
                </div>
            </div>

            <div class="p-5">
                <?php if ($report['reporter_user_id'] === null): ?>
                    <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">This reporter’s account has been deleted.</p>
                <?php else: ?>
                    <div class="flex items-center gap-3">
                        <?php if ($reporterAvatar !== null): ?>
                            <img src="<?= e($reporterAvatar) ?>" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-line object-cover">
                        <?php else: ?>
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-sm bg-brand-400 text-base font-semibold text-[#14100B]" aria-hidden="true"><?= e($reporterInitial) ?></span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <a href="<?= e(url('/admin/users/' . (int) $report['reporter_user_id'])) ?>" class="block truncate text-sm font-semibold text-slate-200 transition hover:text-brand-200"><?= e($reporterName) ?></a>
                            <p class="truncate text-[11px] text-muted">@<?= e($reporterUsername) ?></p>
                            <p class="truncate text-[10px] text-muted-dim"><?= e((string) ($report['reporter_email'] ?? '')) ?></p>
                        </div>
                    </div>
                    <a href="<?= e(url('/admin/users/' . (int) $report['reporter_user_id'])) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                        <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                        Open reporter record
                    </a>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<?php if ($status !== 'open'): ?>
    <section class="mt-4" data-reveal>
        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                    <i data-lucide="circle-check" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Decision</h2>
                    <p class="text-[11px] text-muted">Closed reports are immutable.</p>
                </div>
            </div>
            <div class="p-5">
                <dl class="grid gap-4 text-[11px] sm:grid-cols-2">
                    <div>
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Outcome</dt>
                        <dd class="mt-1 font-semibold text-slate-200"><?= e(ucfirst($status)) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Decided by</dt>
                        <dd class="mt-1 text-slate-300"><?= e((string) ($report['resolver_display_name'] ?? 'Deleted administrator')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Decided at</dt>
                        <dd class="mt-1 text-slate-300"><?= e($resolved ?? '—') ?> UTC</dd>
                    </div>
                </dl>
                <div class="mt-5 border-t border-line pt-5">
                    <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Resolution note</p>
                    <?php if (trim((string) ($report['resolution_note'] ?? '')) === ''): ?>
                        <p class="mt-2 text-[11px] text-muted">No note was recorded.</p>
                    <?php else: ?>
                        <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-300"><?= e((string) $report['resolution_note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </section>
<?php endif; ?>

<section class="mt-4" data-reveal>
    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                <i data-lucide="history" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Moderation history</h2>
                <p class="text-[11px] text-muted">Privileged actions taken against this report.</p>
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
</section>
