<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin category catalogue.
 *
 * @var list<array<string, mixed>> $categories
 */
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-gold-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Admin overview
        </a>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">Categories</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Categories group quests and power the quest-board filter. Deleting a category keeps every quest and
            simply marks it uncategorised.
        </p>
    </div>

    <a href="<?= e(url('/admin/categories/create')) ?>" class="btn btn-app-primary">
        <i data-lucide="plus" class="h-4 w-4"></i>
        New category
    </a>
</section>

<section class="mt-6">
    <article class="app-panel overflow-hidden" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Catalogue</h2>
                <p class="text-[11px] text-muted"><?= e((string) count($categories)) ?> categories</p>
            </div>
        </div>

        <?php if ($categories === []): ?>
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-white/[0.07] bg-white/[0.03] text-muted">
                    <i data-lucide="folder-tree" class="h-5 w-5"></i>
                </span>
                <p class="mt-4 font-display text-sm font-semibold text-slate-200">No categories yet</p>
                <p class="mt-1.5 text-xs text-muted">Create one so quests can be grouped and filtered.</p>
                <a href="<?= e(url('/admin/categories/create')) ?>" class="btn btn-app-primary mt-4 inline-flex">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    New category
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead>
                        <tr class="border-b border-white/[0.06] text-[9px] uppercase tracking-[0.14em] text-muted-dim">
                            <th scope="col" class="px-5 py-3 font-semibold">Category</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Quests</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.04]">
                        <?php foreach ($categories as $category): ?>
                            <tr class="transition hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-white/10 bg-white/[0.03]" style="color: <?= e((string) $category['color']) ?>">
                                            <i data-lucide="<?= e((string) $category['icon']) ?>" class="h-4 w-4"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $category['name']) ?></p>
                                            <p class="truncate text-[10px] text-muted-dim">
                                                /<?= e((string) $category['slug']) ?> ·
                                                <span class="font-mono uppercase"><?= e((string) $category['color']) ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-400"><?= e((string) $category['quest_count']) ?></td>
                                <td class="px-4 py-3.5 tabular-nums text-muted"><?= e((string) $category['sort_order']) ?></td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider <?= (int) $category['is_active'] === 1
                                        ? 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-300'
                                        : 'border-white/10 bg-white/[0.03] text-slate-400' ?>">
                                        <span class="h-1.5 w-1.5 rounded-full <?= (int) $category['is_active'] === 1 ? 'bg-emerald-400' : 'bg-slate-500' ?>"></span>
                                        <?= (int) $category['is_active'] === 1 ? 'Active' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= e(url('/admin/categories/' . (int) $category['id'] . '/edit')) ?>" class="btn btn-app-ghost px-2.5 py-1.5 text-[11px]">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                            Edit
                                        </a>
                                        <form method="POST" action="<?= e(url('/admin/categories/' . (int) $category['id'] . '/delete')) ?>" data-confirm="Delete this category? Its quests will become uncategorised.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn px-2.5 py-1.5 text-[11px] text-rose-300 hover:bg-rose-500/10 hover:text-rose-200">
                                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
