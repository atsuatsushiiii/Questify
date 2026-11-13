<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Reporter-facing report history.
 *
 * @var array{items: list<array<string, mixed>>, total: int, page: int, pages: int, perPage: int} $directory
 * @var array{open: int, resolved: int, dismissed: int} $statusCounts
 * @var array<string, string> $reasons
 */
$reports = $directory['items'];
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'resolved' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'dismissed' => 'border-line bg-white/[0.03] text-slate-400',
];
$targetLabels = ['profile' => 'Profile', 'quest' => 'Quest'];
$page = (int) $directory['page'];
$pages = (int) $directory['pages'];
$link = static fn (int $target): string => url('/reports?page=' . $target);
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow">
                <i data-lucide="flag" class="h-3.5 w-3.5"></i>
                Account
            </p>
            <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">My reports</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">
                Reports you have submitted, newest first. The reported account is never told who filed a report,
                and administrator notes stay private.
            </p>
        </div>

        <a href="<?= e(url('/leaderboard')) ?>" class="btn btn-app-ghost px-4 py-2 text-xs">
            <i data-lucide="users" class="h-3.5 w-3.5"></i>
            Find a profile
        </a>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-2" data-reveal>
        <?php foreach (['open', 'resolved', 'dismissed'] as $status): ?>
            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status]) ?>">
                <?= e(ucfirst($status)) ?>
                <span class="tabular-nums opacity-80"><?= e((string) $statusCounts[$status]) ?></span>
            </span>
        <?php endforeach; ?>
        <span class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">
            <?= e(number_format((int) $directory['total'])) ?> total
        </span>
    </div>

    <?php if ($reports === []): ?>
        <div class="mt-4 rounded-sm border border-dashed border-line bg-panel/30 px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                <i data-lucide="flag" class="h-5 w-5"></i>
            </span>
            <p class="mt-4 font-display text-sm font-semibold text-slate-200">No reports yet</p>
            <p class="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-muted">
                If you see a public profile that breaks the community rules, open it and use the
                “Report profile” action. Your reports will appear here.
            </p>
            <a href="<?= e(url('/leaderboard')) ?>" class="mt-4 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">
                Browse public profiles
                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>
    <?php else: ?>
        <ol class="mt-4 space-y-2">
            <?php foreach ($reports as $report): ?>
                <?php
                $status = (string) $report['status'];
                $reason = (string) $report['reason'];
                $created = format_datetime((string) $report['created_at'], 'UTC', 'j M Y');
                $closed = format_datetime(
                    isset($report['resolved_at']) ? (string) $report['resolved_at'] : null,
                    'UTC',
                    'j M Y',
                );
                ?>
                <li class="app-panel p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                                <i data-lucide="<?= $status === 'open' ? 'flag' : ($status === 'resolved' ? 'circle-check' : 'circle-slash') ?>" class="h-4 w-4"></i>
                            </span>
                            <div class="min-w-0">
                                <h2 class="truncate text-sm font-semibold text-slate-200">
                                    <?= e($reasons[$reason] ?? ucfirst($reason)) ?>
                                </h2>
                                <p class="mt-0.5 break-words text-[11px] text-muted">
                                    Against <span class="font-medium text-slate-300"><?= e((string) $report['target_label']) ?></span>
                                    <span class="text-muted-dim">· <?= e($targetLabels[(string) $report['target_type']] ?? (string) $report['target_type']) ?></span>
                                </p>
                                <p class="mt-1 font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">
                                    Report #<?= e((string) $report['id']) ?> · submitted <?= e($created ?? '—') ?>
                                    <?php if ($closed !== null): ?> · closed <?= e($closed) ?><?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['dismissed']) ?>">
                            <?= e(ucfirst($status)) ?>
                        </span>
                    </div>

                    <?php if ($status === 'open'): ?>
                        <p class="mt-3 border-t border-line pt-3 text-[11px] text-muted">
                            An administrator has not reviewed this report yet. You will get a notification when it is closed.
                        </p>
                    <?php elseif ($status === 'resolved'): ?>
                        <p class="mt-3 border-t border-line pt-3 text-[11px] text-muted">
                            An administrator reviewed this report and marked it resolved. No further action is needed from you.
                        </p>
                    <?php else: ?>
                        <p class="mt-3 border-t border-line pt-3 text-[11px] text-muted">
                            An administrator reviewed this report and took no further action. You can report new behaviour separately.
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Report pagination">
            <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">
                Page <?= e((string) $page) ?> of <?= e((string) $pages) ?>
            </p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= e($link($page - 1)) ?>" class="btn btn-app-ghost px-3 py-2 text-xs" rel="prev">
                        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                        Newer
                    </a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a href="<?= e($link($page + 1)) ?>" class="btn btn-app-ghost px-3 py-2 text-xs" rel="next">
                        Older
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
