<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Administrator reports queue.
 *
 * @var array{search: string, status: string, target_type: string, reason: string} $filters
 * @var array{reports: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>} $directory
 * @var array<string, string> $reasons
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'resolved' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'dismissed' => 'border-line bg-white/[0.03] text-slate-400',
];
$targetLabels = ['profile' => 'Profile', 'quest' => 'Quest'];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Reports queue</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            User-submitted flags against public profiles and quests. Open reports are listed first. Resolve or
            dismiss each report with a decision note; every action is written to the audit log.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/audit?target_type=report')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            Report audit trail
        </a>
    </div>
</section>

<section class="mt-6" data-reveal>
    <form method="GET" action="<?= e(url('/admin/reports')) ?>" class="app-panel grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1.6fr_1fr_1fr_1fr_auto]">
        <div>
            <label for="search" class="field-label">Search</label>
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="<?= e($filters['search']) ?>"
                    class="app-field py-2 pl-9 text-xs"
                    placeholder="Target, details, or reporter"
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="app-select py-2 text-xs">
                <option value="">All statuses</option>
                <?php foreach (['open', 'resolved', 'dismissed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="target_type" class="field-label">Target</label>
            <select id="target_type" name="target_type" class="app-select py-2 text-xs">
                <option value="">All targets</option>
                <?php foreach ($targetLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['target_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="reason" class="field-label">Reason</label>
            <select id="reason" name="reason" class="app-select py-2 text-xs">
                <option value="">All reasons</option>
                <?php foreach ($reasons as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['reason'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Apply
            </button>
            <?php if ($query !== []): ?>
                <a href="<?= e(url('/admin/reports')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-4">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Reports</h2>
                <p class="text-[11px] text-muted"><?= e(number_format($directory['total'])) ?> matching report<?= $directory['total'] === 1 ? '' : 's' ?>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-[10px]">
                <?php foreach (['open', 'resolved', 'dismissed'] as $status): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-1 font-mono font-semibold uppercase tracking-[0.1em] <?= e($statusTones[$status]) ?>">
                        <?= e(ucfirst($status)) ?>
                        <span class="text-[10px] opacity-80"><?= e(number_format((int) ($directory['statusCounts'][$status] ?? 0))) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($directory['reports'] === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                    <i data-lucide="flag" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No reports match these filters</p>
                <p class="mt-1.5 text-xs text-muted">New user reports will appear here for review.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-line font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Report</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Reporter</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Target</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Created</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($directory['reports'] as $report): ?>
                            <?php
                            $status = (string) $report['status'];
                            $reason = (string) $report['reason'];
                            $created = format_datetime((string) $report['created_at'], 'UTC', 'j M Y');
                            $reporterName = (string) ($report['reporter_display_name'] ?? 'Deleted account');
                            $reporterUsername = (string) ($report['reporter_username'] ?? '');
                            ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="min-w-0">
                                        <a href="<?= e(url('/admin/reports/' . (int) $report['id'])) ?>" class="block max-w-[300px] truncate text-xs font-semibold text-slate-200 transition hover:text-brand-200">
                                            <?= e($reasons[$reason] ?? ucfirst($reason)) ?>
                                        </a>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[10px] text-muted-dim">
                                            <span class="font-mono text-brand-300/80">#<?= e((string) $report['id']) ?></span>
                                            <span>·</span>
                                            <span><?= e($targetLabels[(string) $report['target_type']] ?? (string) $report['target_type']) ?></span>
                                            <?php if (trim((string) ($report['details'] ?? '')) !== ''): ?>
                                                <span>·</span>
                                                <span>Details attached</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <?php if ($report['reporter_user_id'] !== null): ?>
                                        <a href="<?= e(url('/admin/users/' . (int) $report['reporter_user_id'])) ?>" class="block max-w-[150px] truncate text-[11px] font-semibold text-slate-200 transition hover:text-brand-200">
                                            <?= e($reporterName) ?>
                                        </a>
                                        <p class="max-w-[150px] truncate text-[10px] text-muted-dim">@<?= e($reporterUsername) ?></p>
                                    <?php else: ?>
                                        <p class="text-[11px] text-muted">Deleted account</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="max-w-[220px] truncate text-[11px] font-semibold text-slate-300"><?= e((string) $report['target_label']) ?></p>
                                    <p class="text-[10px] text-muted-dim"><?= e($targetLabels[(string) $report['target_type']] ?? (string) $report['target_type']) ?> #<?= e((string) $report['target_id']) ?></p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['dismissed']) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-muted"><?= e($created ?? '—') ?></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= e(url('/admin/reports/' . (int) $report['id'])) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $pagination = [
                'page' => $directory['page'],
                'pages' => $directory['pages'],
                'total' => $directory['total'],
                'perPage' => $directory['perPage'],
            ];
            $basePath = '/admin/reports';
            require __DIR__ . '/../../../partials/admin-pagination.php';
            ?>
        <?php endif; ?>
    </article>
</section>
