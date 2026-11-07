<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin audit log.
 *
 * @var list<array<string, mixed>> $entries
 * @var array{action: string, target_type: string, actor: string} $filters
 * @var list<string> $actions
 * @var list<string> $targetTypes
 * @var array{page: int, pages: int, total: int, perPage: int} $pagination
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
$targetLabels = ['user' => 'User', 'category' => 'Category', 'achievement' => 'Achievement'];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Audit log</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Append-only record of every privileged action. Entries include the actor, target, summary, and a JSON
            snapshot of what changed.
        </p>
    </div>

    <span class="inline-flex items-center gap-2 rounded-sm border border-brand-400/30 bg-brand-400/[0.08] px-3 py-1.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-brand-300">
        <i data-lucide="lock" class="h-3.5 w-3.5"></i>
        <?= e(number_format($pagination['total'])) ?> entries
    </span>
</section>

<section class="mt-6" data-reveal>
    <form method="GET" action="<?= e(url('/admin/audit')) ?>" class="app-panel grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1.2fr_1.2fr_1.2fr_auto]">
        <div>
            <label for="action" class="field-label">Action</label>
            <select id="action" name="action" class="app-select py-2 text-xs">
                <option value="">All actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= e($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="target_type" class="field-label">Target type</label>
            <select id="target_type" name="target_type" class="app-select py-2 text-xs">
                <option value="">All targets</option>
                <?php foreach ($targetTypes as $target): ?>
                    <option value="<?= e($target) ?>" <?= $filters['target_type'] === $target ? 'selected' : '' ?>><?= e($targetLabels[$target] ?? ucfirst($target)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="actor" class="field-label">Actor</label>
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
                <input
                    id="actor"
                    name="actor"
                    type="search"
                    value="<?= e($filters['actor']) ?>"
                    class="app-field py-2 pl-9 text-xs"
                    placeholder="Username or display name"
                    autocomplete="off"
                >
            </div>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Apply
            </button>
            <?php if ($query !== []): ?>
                <a href="<?= e(url('/admin/audit')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-4">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Privileged actions</h2>
                <p class="text-[11px] text-muted">Newest first. Metadata is stored as JSON and shown verbatim.</p>
            </div>
        </div>

        <?php if ($entries === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                    <i data-lucide="history" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No audit entries match</p>
                <p class="mt-1.5 text-xs text-muted">Moderation and catalogue changes will be recorded here.</p>
            </div>
        <?php else: ?>
            <ol class="divide-y divide-white/[0.04]">
                <?php foreach ($entries as $entry): ?>
                    <li class="px-5 py-4 transition hover:bg-white/[0.02]">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-sm border border-brand-400/30 bg-brand-400/[0.08] text-brand-300">
                                    <i data-lucide="shield-check" class="h-4 w-4"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="break-words text-xs font-semibold text-slate-200"><?= e((string) $entry['summary']) ?></p>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] text-muted-dim">
                                        <span class="rounded border border-brand-400/30 bg-brand-400/[0.06] px-1.5 py-0.5 font-mono text-brand-300/90"><?= e((string) $entry['action']) ?></span>
                                        <span class="rounded border border-line bg-white/[0.03] px-1.5 py-0.5 text-slate-400">
                                            <?= e($targetLabels[(string) $entry['target_type']] ?? (string) $entry['target_type']) ?>
                                            <?= $entry['target_id'] !== null ? '#' . e((string) $entry['target_id']) : '' ?>
                                        </span>
                                        <span>by <?= e((string) ($entry['actor_display_name'] ?? 'System')) ?></span>
                                        <span>·</span>
                                        <span><?= e((string) ($entry['ip_address'] ?? 'unknown IP')) ?></span>
                                        <span>·</span>
                                        <time datetime="<?= e((string) iso_datetime((string) $entry['created_at'])) ?>">
                                            <?= e((string) format_datetime((string) $entry['created_at'], 'UTC', 'j M Y, H:i:s')) ?> UTC
                                        </time>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (is_array($entry['metadata'] ?? null) && $entry['metadata'] !== []): ?>
                            <details class="mt-3 rounded-sm border border-line bg-white/[0.015] px-3 py-2">
                                <summary class="cursor-pointer font-mono text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Metadata</summary>
                                <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-words font-mono text-[10px] leading-relaxed text-slate-400"><?= e((string) json_encode($entry['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php
            $basePath = '/admin/audit';
            require __DIR__ . '/../../../partials/admin-pagination.php';
            ?>
        <?php endif; ?>
    </article>
</section>
