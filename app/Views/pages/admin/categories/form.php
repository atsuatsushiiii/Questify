<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Create / edit category.
 *
 * @var array<string, mixed>|null $category
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 */
$isEdit = $category !== null;
$action = $isEdit ? '/admin/categories/' . (int) $category['id'] : '/admin/categories';
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$errorClass = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$summary = $message ?? ($errors !== [] ? 'Please fix the highlighted fields below.' : null);
$isActive = ($old['is_active'] ?? '') !== '';
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <a href="<?= e(url('/admin/categories')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        Back to categories
    </a>

    <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">
        <?= $isEdit ? 'Edit category' : 'New category' ?>
    </h1>
    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
        Name, colour, and icon shape how quests are grouped on the board. Inactive categories stay attached to
        existing quests but no longer appear in the create form or filter.
    </p>
</section>

<?php if ($summary !== null): ?>
    <div class="mx-auto mt-6 max-w-4xl">
        <?php
        $type = 'error';
        $message = $summary;
        require __DIR__ . '/../../../partials/alert.php';
        ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e(url($action)) ?>" class="mx-auto mt-6 grid max-w-4xl gap-4 lg:grid-cols-[1.5fr_1fr]" data-category-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="app-panel p-5 sm:p-6">
        <div class="flex items-center gap-3 border-b border-line pb-4">
            <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="folder-tree" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Category details</h2>
                <p class="text-[11px] text-muted">All fields except status are required.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="field-label">Name <span class="text-ember-300">*</span></label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    class="app-field<?= e($errorClass('name')) ?>"
                    value="<?= e($old['name'] ?? '') ?>"
                    maxlength="40"
                    required
                    aria-invalid="<?= $invalid('name') ?>"
                >
                <?php $field = 'name'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="slug" class="field-label">Slug</label>
                <input
                    id="slug"
                    name="slug"
                    type="text"
                    class="app-field<?= e($errorClass('slug')) ?>"
                    value="<?= e($old['slug'] ?? '') ?>"
                    maxlength="50"
                    placeholder="auto-from-name"
                    aria-invalid="<?= $invalid('slug') ?>"
                >
                <p class="mt-1.5 text-[11px] text-muted-dim">Used in filter URLs. Leave blank to generate.</p>
                <?php $field = 'slug'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="color" class="field-label">Colour <span class="text-ember-300">*</span></label>
                <div class="flex items-center gap-3">
                    <input
                        id="color"
                        type="color"
                        class="h-11 w-14 cursor-pointer rounded-sm border border-line bg-white/[0.03] p-1"
                        value="<?= e($old['color'] ?? '#FACC15') ?>"
                        data-color-picker
                        aria-label="Colour picker"
                    >
                    <input
                        id="color_hex"
                        name="color"
                        type="text"
                        class="app-field<?= e($errorClass('color')) ?> font-mono uppercase"
                        value="<?= e($old['color'] ?? '#FACC15') ?>"
                        maxlength="7"
                        pattern="#[0-9A-Fa-f]{6}"
                        required
                        data-color-text
                        aria-label="Hex colour value"
                        aria-invalid="<?= $invalid('color') ?>"
                    >
                </div>
                <?php $field = 'color'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="icon" class="field-label">Lucide icon <span class="text-ember-300">*</span></label>
                <input
                    id="icon"
                    name="icon"
                    type="text"
                    class="app-field<?= e($errorClass('icon')) ?> font-mono"
                    value="<?= e($old['icon'] ?? 'folder') ?>"
                    maxlength="40"
                    placeholder="briefcase"
                    aria-invalid="<?= $invalid('icon') ?>"
                >
                <p class="mt-1.5 text-[11px] text-muted-dim">Lowercase Lucide name, e.g. briefcase, book-open.</p>
                <?php $field = 'icon'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="sort_order" class="field-label">Sort order <span class="text-ember-300">*</span></label>
                <input
                    id="sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    max="65535"
                    class="app-field<?= e($errorClass('sort_order')) ?>"
                    value="<?= e($old['sort_order'] ?? '0') ?>"
                    required
                    aria-invalid="<?= $invalid('sort_order') ?>"
                >
                <?php $field = 'sort_order'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div class="flex items-end">
                <label class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-sm border border-line bg-white/[0.02] px-4 py-3">
                    <span>
                        <span class="block text-xs font-semibold text-slate-200">Active</span>
                        <span class="block text-[10px] text-muted">Show on the quest board.</span>
                    </span>
                    <input type="hidden" name="is_active" value="0">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="h-4 w-4 rounded-sm border-line bg-white/[0.05] text-brand-400 focus:ring-brand-400/50"
                        <?= $isActive ? 'checked' : '' ?>
                    >
                </label>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="<?= e(url('/admin/categories')) ?>" class="btn btn-app-ghost w-full sm:w-auto">Cancel</a>
            <button type="submit" class="btn btn-app-primary w-full sm:w-auto">
                <i data-lucide="<?= $isEdit ? 'check' : 'plus' ?>" class="h-4 w-4"></i>
                <?= $isEdit ? 'Save category' : 'Create category' ?>
            </button>
        </div>
    </div>

    <aside class="flex flex-col gap-4">
        <div class="app-panel p-5">
            <p class="app-stat-label">Preview</p>
            <div class="mt-4 rounded-sm border border-line bg-white/[0.02] p-4">
                <span class="inline-flex items-center gap-2 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 text-[11px] font-semibold text-slate-200">
                    <span
                        class="h-2 w-2 rounded-sm"
                        style="background-color: <?= e($old['color'] ?? '#FACC15') ?>"
                        data-category-preview-dot
                    ></span>
                    <span data-category-preview-name><?= e(($old['name'] ?? '') !== '' ? $old['name'] : 'Category name') ?></span>
                </span>
                <p class="mt-3 text-[11px] text-muted" data-category-preview-slug>
                    /<?= e(($old['slug'] ?? '') !== '' ? $old['slug'] : 'slug') ?>
                </p>
            </div>
        </div>

        <div class="rounded-sm border border-line bg-white/[0.02] p-4">
            <p class="flex items-start gap-2 text-[11px] leading-relaxed text-muted">
                <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted"></i>
                <span>Deleting a category is safe: quests keep their history and become uncategorised. Prefer deactivating if you may reuse the category.</span>
            </p>
        </div>
    </aside>
</form>
