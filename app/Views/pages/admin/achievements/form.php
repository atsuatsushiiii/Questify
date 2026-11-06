<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Create / edit achievement definition.
 *
 * @var array<string, mixed>|null $achievement
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 * @var array<string, string> $categories
 * @var list<string> $criteriaTypes
 */
$isEdit = $achievement !== null;
$action = $isEdit ? '/admin/achievements/' . (int) $achievement['id'] : '/admin/achievements';
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$errorClass = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$summary = $message ?? ($errors !== [] ? 'Please fix the highlighted fields below.' : null);
$isActive = ($old['is_active'] ?? '') !== '';
$criteriaLabels = [
    'quests_completed' => 'Quests completed',
    'xp_total' => 'Total XP',
    'level' => 'Level',
    'epic_quests' => 'Epic quests completed',
    'longest_streak' => 'Longest streak',
];
?>
<section class="mx-auto max-w-5xl" data-reveal>
    <a href="<?= e(url('/admin/achievements')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-gold-300">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        Back to achievements
    </a>

    <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
        <?= $isEdit ? 'Edit achievement' : 'New achievement' ?>
    </h1>
    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
        A definition is a criteria type plus a threshold. The evaluator compares the user's live metrics against
        the threshold after every quest completion.
    </p>
</section>

<?php if ($summary !== null): ?>
    <div class="mx-auto mt-6 max-w-5xl">
        <?php
        $type = 'error';
        $message = $summary;
        require __DIR__ . '/../../../partials/alert.php';
        ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e(url($action)) ?>" class="mx-auto mt-6 grid max-w-5xl gap-4 xl:grid-cols-[1.5fr_1fr]">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="app-panel p-5 sm:p-6">
        <div class="flex items-center gap-3 border-b border-white/[0.06] pb-4">
            <span class="app-panel-icon border-gold-400/20 bg-gold-400/10 text-gold-300">
                <i data-lucide="award" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Definition</h2>
                <p class="text-[11px] text-muted">Keys are permanent identifiers; avoid renaming them.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="achievement_key" class="field-label">Key <span class="text-ember-300">*</span></label>
                <input
                    id="achievement_key"
                    name="achievement_key"
                    type="text"
                    class="app-field<?= e($errorClass('achievement_key')) ?> font-mono"
                    value="<?= e($old['achievement_key'] ?? '') ?>"
                    maxlength="64"
                    placeholder="first_steps"
                    required
                    aria-invalid="<?= $invalid('achievement_key') ?>"
                >
                <?php $field = 'achievement_key'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="name" class="field-label">Name <span class="text-ember-300">*</span></label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    class="app-field<?= e($errorClass('name')) ?>"
                    value="<?= e($old['name'] ?? '') ?>"
                    maxlength="80"
                    required
                    aria-invalid="<?= $invalid('name') ?>"
                >
                <?php $field = 'name'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>
        </div>

        <div class="mt-4">
            <label for="description" class="field-label">Description <span class="text-ember-300">*</span></label>
            <textarea
                id="description"
                name="description"
                rows="3"
                class="app-field resize-y<?= e($errorClass('description')) ?>"
                maxlength="255"
                required
                aria-invalid="<?= $invalid('description') ?>"
            ><?= e($old['description'] ?? '') ?></textarea>
            <?php $field = 'description'; require __DIR__ . '/../../../partials/field-error.php'; ?>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="category" class="field-label">Category <span class="text-ember-300">*</span></label>
                <select id="category" name="category" class="app-select<?= e($errorClass('category')) ?>" aria-invalid="<?= $invalid('category') ?>">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($old['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $field = 'category'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="icon" class="field-label">Lucide icon <span class="text-ember-300">*</span></label>
                <input
                    id="icon"
                    name="icon"
                    type="text"
                    class="app-field<?= e($errorClass('icon')) ?> font-mono"
                    value="<?= e($old['icon'] ?? 'trophy') ?>"
                    maxlength="40"
                    required
                    aria-invalid="<?= $invalid('icon') ?>"
                >
                <?php $field = 'icon'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="criteria_type" class="field-label">Criteria type <span class="text-ember-300">*</span></label>
                <select id="criteria_type" name="criteria_type" class="app-select<?= e($errorClass('criteria_type')) ?>" aria-invalid="<?= $invalid('criteria_type') ?>">
                    <?php foreach ($criteriaTypes as $criteria): ?>
                        <option value="<?= e($criteria) ?>" <?= ($old['criteria_type'] ?? '') === $criteria ? 'selected' : '' ?>>
                            <?= e($criteriaLabels[$criteria] ?? ucfirst(str_replace('_', ' ', $criteria))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php $field = 'criteria_type'; require __DIR__ . '/../../../partials/field-error.php'; ?>
            </div>

            <div>
                <label for="threshold" class="field-label">Threshold <span class="text-ember-300">*</span></label>
                <input
                    id="threshold"
                    name="threshold"
                    type="number"
                    min="1"
                    class="app-field<?= e($errorClass('threshold')) ?>"
                    value="<?= e($old['threshold'] ?? '1') ?>"
                    required
                    aria-invalid="<?= $invalid('threshold') ?>"
                >
                <?php $field = 'threshold'; require __DIR__ . '/../../../partials/field-error.php'; ?>
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
                <label class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-4 py-3">
                    <span>
                        <span class="block text-xs font-semibold text-slate-200">Active</span>
                        <span class="block text-[10px] text-muted">Visible to adventurers.</span>
                    </span>
                    <input type="hidden" name="is_active" value="0">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        class="h-4 w-4 rounded border-white/20 bg-white/[0.05] text-gold-400 focus:ring-gold-400/50"
                        <?= $isActive ? 'checked' : '' ?>
                    >
                </label>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 border-t border-white/[0.06] pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="<?= e(url('/admin/achievements')) ?>" class="btn btn-app-ghost w-full sm:w-auto">Cancel</a>
            <button type="submit" class="btn btn-app-primary w-full sm:w-auto">
                <i data-lucide="<?= $isEdit ? 'check' : 'plus' ?>" class="h-4 w-4"></i>
                <?= $isEdit ? 'Save achievement' : 'Create achievement' ?>
            </button>
        </div>
    </div>

    <aside class="flex flex-col gap-4">
        <div class="relative overflow-hidden rounded-2xl border border-gold-400/20 bg-gradient-to-br from-gold-400/[0.08] via-white/[0.02] to-transparent p-5">
            <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-gold-400/[0.12] blur-[60px]" aria-hidden="true"></div>
            <div class="relative flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl border border-gold-400/25 bg-gold-400/10 text-gold-300">
                    <i data-lucide="<?= e(($old['icon'] ?? '') !== '' ? $old['icon'] : 'trophy') ?>" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <p class="truncate font-display text-sm font-semibold text-white"><?= e(($old['name'] ?? '') !== '' ? $old['name'] : 'Achievement name') ?></p>
                    <p class="text-[10px] text-muted"><?= e($categories[$old['category'] ?? ''] ?? 'Category') ?></p>
                </div>
            </div>
            <p class="relative mt-4 text-[11px] leading-relaxed text-muted"><?= e(($old['description'] ?? '') !== '' ? $old['description'] : 'The description shown in the trophy hall.') ?></p>
        </div>

        <div class="app-panel p-5">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Evaluation notes</p>
            <ul class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-muted">
                <li class="flex items-start gap-2">
                    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400/80"></i>
                    <span>Only the five whitelisted criteria types can be selected.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="lock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gold-400/80"></i>
                    <span>Definitions with unlocks cannot be deleted; deactivate them to keep history.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="zap" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ember-300"></i>
                    <span>Threshold changes apply to future evaluations, not retroactively.</span>
                </li>
            </ul>
        </div>
    </aside>
</form>
