<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Administrator appeals queue.
 *
 * @var array{search: string, status: string} $filters
 * @var array{appeals: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>} $directory
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'accepted' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'denied' => 'border-line bg-white/[0.03] text-slate-400',
];
$statusLabels = ['open' => 'Open', 'accepted' => 'Accepted', 'denied' => 'Denied'];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Appeals queue</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Read-only accounts can ask for a review. Accepting an appeal restores full access; denying it keeps the
            limit. Both decisions are audited and shared with the account.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/users?status=restricted')) ?>" class="btn btn-app-ghost">
            <i data-lucide="eye-off" class="h-4 w-4"></i>
            Read-only accounts
        </a>
        <a href="<?= e(url('/admin/audit?target_type=appeal')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            Appeal audit trail
        </a>
    </div>
</section>

<section class="mt-6" data-reveal>
    <form method="GET" action="<?= e(url('/admin/appeals')) ?>" class="app-panel grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_auto]">
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
                    placeholder="Account, message, or decision note"
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="app-select py-2 text-xs">
                <option value="">All statuses</option>
                <?php foreach (['open', 'accepted', 'denied'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($statusLabels[$status]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Apply
            </button>
            <?php if ($query !== []): ?>
                <a href="<?= e(url('/admin/appeals')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-4">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Appeals</h2>
                <p class="text-[11px] text-muted"><?= e(number_format($directory['total'])) ?> matching appeal<?= $directory['total'] === 1 ? '' : 's' ?>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-[10px]">
                <?php foreach (['open', 'accepted', 'denied'] as $status): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-1 font-mono font-semibold uppercase tracking-[0.1em] <?= e($statusTones[$status]) ?>">
                        <?= e($statusLabels[$status]) ?>
                        <span class="text-[10px] opacity-80"><?= e(number_format((int) ($directory['statusCounts'][$status] ?? 0))) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($directory['appeals'] === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                    <i data-lucide="scale" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No appeals match these filters</p>
                <p class="mt-1.5 text-xs text-muted">Appeals from read-only accounts will appear here for review.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-line font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Account</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Appeal</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Created</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($directory['appeals'] as $appeal): ?>
                            <?php
                            $status = (string) $appeal['status'];
                            $created = format_datetime((string) $appeal['created_at'], 'UTC', 'j M Y');
                            $excerpt = mb_strlen((string) $appeal['message']) > 120
                                ? mb_substr((string) $appeal['message'], 0, 120) . '…'
                                : (string) $appeal['message'];
                            ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="min-w-0">
                                        <a href="<?= e(url('/admin/users/' . (int) $appeal['user_id'])) ?>" class="block max-w-[200px] truncate text-xs font-semibold text-slate-200 transition hover:text-brand-200">
                                            <?= e((string) ($appeal['user_display_name'] ?? $appeal['user_username'] ?? 'Deleted account')) ?>
                                        </a>
                                        <p class="mt-0.5 max-w-[200px] truncate text-[10px] text-muted-dim">@<?= e((string) ($appeal['user_username'] ?? '')) ?></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="max-w-[360px] truncate text-[11px] text-slate-300"><?= e($excerpt) ?></p>
                                    <p class="mt-0.5 font-mono text-[10px] text-brand-300/80">#<?= e((string) $appeal['id']) ?></p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['denied']) ?>">
                                        <?= e($statusLabels[$status] ?? ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-muted"><?= e($created ?? '—') ?></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= e(url('/admin/appeals/' . (int) $appeal['id'])) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
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
            $basePath = '/admin/appeals';
            require __DIR__ . '/../../../partials/admin-pagination.php';
            ?>
        <?php endif; ?>
    </article>
</section>
