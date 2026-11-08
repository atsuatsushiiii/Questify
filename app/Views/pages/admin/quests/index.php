<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin quest moderation directory.
 *
 * @var array{search: string, status: string, difficulty: string, category: string} $filters
 * @var array{quests: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>, categories: list<array<string, mixed>>} $directory
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
$statusTones = [
    'active' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'completed' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'archived' => 'border-line bg-white/[0.03] text-slate-400',
];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Quest moderation</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Every quest across the platform, newest first. Review the owner, inspect the definition, then archive
            abusive content or delete it. Deleting a quest keeps its XP ledger history.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/audit?target_type=quest')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            Quest audit trail
        </a>
    </div>
</section>

<section class="mt-6" data-reveal>
    <form method="GET" action="<?= e(url('/admin/quests')) ?>" class="app-panel grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1.6fr_1fr_1fr_1.4fr_auto]">
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
                    placeholder="Title, description, or owner"
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="app-select py-2 text-xs">
                <option value="">All statuses</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>

        <div>
            <label for="difficulty" class="field-label">Difficulty</label>
            <select id="difficulty" name="difficulty" class="app-select py-2 text-xs">
                <option value="">All difficulties</option>
                <option value="easy" <?= $filters['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $filters['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $filters['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                <option value="epic" <?= $filters['difficulty'] === 'epic' ? 'selected' : '' ?>>Epic</option>
            </select>
        </div>

        <div>
            <label for="category" class="field-label">Category</label>
            <select id="category" name="category" class="app-select py-2 text-xs">
                <option value="">All categories</option>
                <?php foreach ($directory['categories'] as $category): ?>
                    <option value="<?= e((string) $category['slug']) ?>" <?= $filters['category'] === (string) $category['slug'] ? 'selected' : '' ?>>
                        <?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Apply
            </button>
            <?php if ($query !== []): ?>
                <a href="<?= e(url('/admin/quests')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-4">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Quests</h2>
                <p class="text-[11px] text-muted"><?= e(number_format($directory['total'])) ?> matching quest<?= $directory['total'] === 1 ? '' : 's' ?>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-[10px]">
                <?php foreach (['active', 'completed', 'archived'] as $status): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-1 font-mono font-semibold uppercase tracking-[0.1em] <?= e($statusTones[$status]) ?>">
                        <?= e(ucfirst($status)) ?>
                        <span class="text-[10px] opacity-80"><?= e(number_format((int) ($directory['statusCounts'][$status] ?? 0))) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($directory['quests'] === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                    <i data-lucide="scroll-text" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No quests match these filters</p>
                <p class="mt-1.5 text-xs text-muted">Try a broader search or reset the filters.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-line font-mono text-[8px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Quest</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Owner</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Difficulty</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Created</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($directory['quests'] as $quest): ?>
                            <?php
                            $status = (string) $quest['status'];
                            $ownerInitial = mb_strtoupper(mb_substr((string) $quest['owner_display_name'], 0, 1));
                            $ownerAvatar = avatar_url(isset($quest['owner_avatar_path']) ? (string) $quest['owner_avatar_path'] : null);
                            $created = format_datetime((string) $quest['created_at'], 'UTC', 'j M Y');
                            ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="min-w-0">
                                        <a href="<?= e(url('/admin/quests/' . (int) $quest['id'])) ?>" class="block max-w-[320px] truncate text-xs font-semibold text-slate-200 transition hover:text-brand-200">
                                            <?= e((string) $quest['title']) ?>
                                        </a>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[10px] text-muted-dim">
                                            <span><?= e((string) ($quest['category_name'] ?? 'Uncategorised')) ?></span>
                                            <span>·</span>
                                            <span><?= e(ucfirst((string) $quest['recurrence'])) ?></span>
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <?php if ($ownerAvatar !== null): ?>
                                            <img src="<?= e($ownerAvatar) ?>" alt="" class="h-8 w-8 shrink-0 rounded-sm border border-line object-cover">
                                        <?php else: ?>
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-sm bg-brand-400 text-[10px] font-semibold text-[#14100B]">
                                                <?= e($ownerInitial) ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <a href="<?= e(url('/admin/users/' . (int) $quest['user_id'])) ?>" class="block max-w-[160px] truncate text-[11px] font-semibold text-slate-200 transition hover:text-brand-200">
                                                <?= e((string) $quest['owner_display_name']) ?>
                                            </a>
                                            <p class="max-w-[160px] truncate text-[10px] text-muted-dim">@<?= e((string) $quest['owner_username']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['archived']) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-slate-300"><?= e(ucfirst((string) $quest['difficulty'])) ?></p>
                                    <p class="text-[10px] text-muted-dim"><?= e((string) $quest['xp_reward']) ?> XP</p>
                                </td>
                                <td class="px-4 py-3.5 text-muted"><?= e($created ?? '—') ?></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= e(url('/admin/quests/' . (int) $quest['id'])) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
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
            $basePath = '/admin/quests';
            require __DIR__ . '/../../../partials/admin-pagination.php';
            ?>
        <?php endif; ?>
    </article>
</section>
