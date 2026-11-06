<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin user directory.
 *
 * @var array{search: string, role: string, status: string} $filters
 * @var array{users: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int} $directory
 */
$query = array_filter($filters, static fn (string $value): bool => $value !== '');
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-gold-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">User management</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Search the directory, review account state, and moderate access. Suspending an account revokes its
            sessions and remember-me tokens immediately.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <a href="<?= e(url('/admin/audit?target_type=user')) ?>" class="btn btn-app-ghost">
            <i data-lucide="history" class="h-4 w-4"></i>
            User audit trail
        </a>
    </div>
</section>

<section class="mt-6" data-reveal>
    <form method="GET" action="<?= e(url('/admin/users')) ?>" class="app-panel grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1.6fr_1fr_1fr_auto]">
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
                    placeholder="Username, email, or display name"
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="role" class="field-label">Role</label>
            <select id="role" name="role" class="app-select py-2 text-xs">
                <option value="">All roles</option>
                <option value="user" <?= $filters['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
        </div>

        <div>
            <label for="status" class="field-label">Status</label>
            <select id="status" name="status" class="app-select py-2 text-xs">
                <option value="">All statuses</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                Apply
            </button>
            <?php if ($query !== []): ?>
                <a href="<?= e(url('/admin/users')) ?>" class="btn btn-app-ghost px-3 py-2 text-xs">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-4">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Accounts</h2>
                <p class="text-[11px] text-muted"><?= e(number_format($directory['total'])) ?> matching account<?= $directory['total'] === 1 ? '' : 's' ?>.</p>
            </div>
        </div>

        <?php if ($directory['users'] === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-white/[0.07] bg-white/[0.03] text-muted">
                    <i data-lucide="users" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No accounts match these filters</p>
                <p class="mt-1.5 text-xs text-muted">Try a broader search or reset the filters.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/[0.06] text-[9px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Adventurer</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Progress</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Joined</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($directory['users'] as $account): ?>
                            <?php
                            $initial = mb_strtoupper(mb_substr((string) $account['display_name'], 0, 1));
                            $avatar = avatar_url(isset($account['avatar_path']) ? (string) $account['avatar_path'] : null);
                            $joined = format_datetime((string) $account['created_at'], 'UTC', 'j M Y');
                            ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <?php if ($avatar !== null): ?>
                                            <img src="<?= e($avatar) ?>" alt="" class="h-9 w-9 shrink-0 rounded-lg border border-white/10 object-cover">
                                        <?php else: ?>
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-ember-500 to-gold-400 text-[11px] font-bold text-white">
                                                <?= e($initial) ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <a href="<?= e(url('/admin/users/' . (int) $account['id'])) ?>" class="block truncate text-xs font-semibold text-slate-200 transition hover:text-gold-200">
                                                <?= e((string) $account['display_name']) ?>
                                            </a>
                                            <p class="truncate text-[10px] text-muted-dim">@<?= e((string) $account['username']) ?> · <?= e((string) $account['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider <?= $account['role'] === 'admin'
                                        ? 'border-gold-400/25 bg-gold-400/[0.08] text-gold-300'
                                        : 'border-white/10 bg-white/[0.03] text-slate-400' ?>">
                                        <i data-lucide="<?= $account['role'] === 'admin' ? 'shield-check' : 'user-round' ?>" class="h-3 w-3"></i>
                                        <?= $account['role'] === 'admin' ? 'Admin' : 'User' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider <?= $account['status'] === 'active'
                                        ? 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-300'
                                        : 'border-rose-400/25 bg-rose-500/[0.08] text-rose-300' ?>">
                                        <span class="h-1.5 w-1.5 rounded-full <?= $account['status'] === 'active' ? 'bg-emerald-400' : 'bg-rose-400' ?>"></span>
                                        <?= e(ucfirst((string) $account['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-slate-300">Level <?= e((string) $account['level']) ?></p>
                                    <p class="text-[10px] text-muted-dim"><?= e(number_format((int) $account['xp_total'])) ?> XP · <?= e((string) $account['current_streak']) ?>d streak</p>
                                </td>
                                <td class="px-4 py-3.5 text-muted"><?= e($joined ?? '—') ?></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= e(url('/admin/users/' . (int) $account['id'])) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
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
            $basePath = '/admin/users';
            require __DIR__ . '/../../../partials/admin-pagination.php';
            ?>
        <?php endif; ?>
    </article>
</section>
