<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Create / edit quest form.
 *
 * @var string $mode create|edit
 * @var array<string, mixed>|null $quest
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 * @var array<string, int> $rewards
 * @var list<array<string, mixed>> $categories
 */
use App\Support\Recurrence;

$isEdit = $mode === 'edit' && $quest !== null;
$action = $isEdit ? '/quests/' . (int) $quest['id'] : '/quests';
$selectedDifficulty = (string) ($old['difficulty'] ?? 'easy');
$selectedCategory = (string) ($old['category_id'] ?? '');
$selectedRecurrence = (string) ($old['recurrence'] ?? Recurrence::NONE);
$selectedReward = $rewards[$selectedDifficulty] ?? ($rewards['easy'] ?? 0);
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$errorClass = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$summary = $message ?? ($errors !== [] ? 'Please fix the highlighted fields below.' : null);

$difficultyCopy = [
    'easy' => 'A small, quick win. Good for a single task.',
    'medium' => 'Focused effort that takes a real block of time.',
    'hard' => 'Deep work with a meaningful finish line.',
    'epic' => 'A major objective worth celebrating.',
];
$difficultyIcon = [
    'easy' => 'sparkles',
    'medium' => 'target',
    'hard' => 'swords',
    'epic' => 'crown',
];
?>
<section class="mx-auto max-w-5xl" data-reveal>
    <a href="<?= e(url($isEdit ? '/quests/' . (int) $quest['id'] : '/quests')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-gold-300">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        <?= $isEdit ? 'Back to quest' : 'Back to quest log' ?>
    </a>

    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="inline-flex items-center gap-2 rounded-full border border-gold-400/25 bg-gold-400/[0.08] px-3.5 py-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-gold-300">
                <i data-lucide="<?= $isEdit ? 'pencil' : 'plus' ?>" class="h-3.5 w-3.5"></i>
                <?= $isEdit ? 'Edit quest' : 'New quest' ?>
            </p>
            <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
                <?= $isEdit ? 'Sharpen the objective.' : 'What will you conquer?' ?>
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
                <?= $isEdit
                    ? 'Only active quests can be edited. Saving recalculates the reward from the difficulty you choose.'
                    : 'Give the quest a clear finish line, choose a difficulty, and the server will assign the XP reward.' ?>
            </p>
        </div>
    </div>
</section>

<?php if ($summary !== null): ?>
    <div class="mx-auto mt-6 max-w-5xl">
        <?php
        $type = 'error';
        $message = $summary;
        require __DIR__ . '/../../partials/alert.php';
        ?>
    </div>
<?php endif; ?>

<form
    method="POST"
    action="<?= e(url($action)) ?>"
    class="mx-auto mt-6 grid max-w-5xl gap-4 xl:grid-cols-[1.5fr_1fr]"
    data-quest-form
    data-quest-action
>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="app-panel p-5 sm:p-6">
        <div class="flex items-center gap-3 border-b border-white/[0.06] pb-4">
            <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="scroll-text" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Quest details</h2>
                <p class="text-[11px] text-muted">Required fields are marked with an asterisk.</p>
            </div>
        </div>

        <div class="mt-5">
            <label for="title" class="field-label">Quest title <span class="text-ember-300">*</span></label>
            <input
                id="title"
                name="title"
                type="text"
                class="app-field<?= e($errorClass('title')) ?>"
                value="<?= e($old['title'] ?? '') ?>"
                placeholder="e.g. Ship the first draft of the launch plan"
                maxlength="120"
                autocomplete="off"
                required
                aria-invalid="<?= $invalid('title') ?>"
                aria-describedby="<?= isset($errors['title']) ? 'title-error' : 'title-help' ?>"
            >
            <p id="title-help" class="mt-1.5 text-[11px] text-muted-dim">Keep it concrete enough to know when it is done.</p>
            <?php $field = 'title'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <div class="mt-5">
            <div class="flex items-baseline justify-between gap-3">
                <label for="description" class="field-label">Description <span class="text-ember-300">*</span></label>
                <span class="text-[10px] tabular-nums text-muted-dim"><span data-description-count><?= e((string) mb_strlen((string) ($old['description'] ?? ''))) ?></span>/2000</span>
            </div>
            <textarea
                id="description"
                name="description"
                rows="6"
                class="app-field resize-y<?= e($errorClass('description')) ?>"
                placeholder="What does done look like? Add the steps, scope, or definition of victory."
                maxlength="2000"
                required
                aria-invalid="<?= $invalid('description') ?>"
                aria-describedby="<?= isset($errors['description']) ? 'description-error' : 'description-help' ?>"
                data-quest-description
            ><?= e($old['description'] ?? '') ?></textarea>
            <p id="description-help" class="mt-1.5 text-[11px] text-muted-dim">At least 10 characters. Plain text only.</p>
            <?php $field = 'description'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <div class="mt-5">
            <label for="category_id" class="field-label">Category</label>
            <select
                id="category_id"
                name="category_id"
                class="app-select<?= e($errorClass('category_id')) ?>"
                aria-invalid="<?= $invalid('category_id') ?>"
                aria-describedby="<?= isset($errors['category_id']) ? 'category_id-error' : 'category_id-help' ?>"
            >
                <option value="">Uncategorised</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= $selectedCategory === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p id="category_id-help" class="mt-1.5 text-[11px] text-muted-dim">Optional. Categories are managed by admins.</p>
            <?php $field = 'category_id'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <div class="mt-5">
            <label for="recurrence" class="field-label">Repeat cadence</label>
            <select
                id="recurrence"
                name="recurrence"
                class="app-select<?= e($errorClass('recurrence')) ?>"
                aria-invalid="<?= $invalid('recurrence') ?>"
                aria-describedby="<?= isset($errors['recurrence']) ? 'recurrence-error' : 'recurrence-help' ?>"
            >
                <?php foreach (Recurrence::options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedRecurrence === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p id="recurrence-help" class="mt-1.5 text-[11px] text-muted-dim">
                Recurring quests stay active and pay XP again each new period. One-off quests complete permanently.
            </p>
            <?php $field = 'recurrence'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </div>

        <fieldset class="mt-6">
            <legend class="field-label">Difficulty <span class="text-ember-300">*</span></legend>
            <div class="grid gap-2.5 sm:grid-cols-2">
                <?php foreach ($rewards as $key => $reward): ?>
                    <?php $checked = $selectedDifficulty === $key; ?>
                    <label class="relative block cursor-pointer">
                        <input
                            type="radio"
                            name="difficulty"
                            value="<?= e($key) ?>"
                            class="peer sr-only"
                            data-xp-option
                            data-xp="<?= e((string) $reward) ?>"
                            data-difficulty-label="<?= e(ucfirst($key)) ?>"
                            <?= $checked ? 'checked' : '' ?>
                            required
                        >
                        <span class="flex h-full flex-col gap-2 rounded-xl border border-white/10 bg-white/[0.02] p-3.5 transition peer-checked:border-gold-400/45 peer-checked:bg-gold-400/[0.07] peer-checked:shadow-[0_0_0_1px_rgba(250,204,21,0.12)] peer-focus-visible:ring-2 peer-focus-visible:ring-ember-400/60 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-[#07060B] hover:border-white/20">
                            <span class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-200">
                                    <i data-lucide="<?= e($difficultyIcon[$key] ?? 'target') ?>" class="h-3.5 w-3.5 text-gold-300"></i>
                                    <?= e(ucfirst($key)) ?>
                                </span>
                                <span class="rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2 py-0.5 text-[10px] font-bold text-gold-300">
                                    <?= e((string) $reward) ?> XP
                                </span>
                            </span>
                            <span class="text-[11px] leading-relaxed text-muted">
                                <?= e($difficultyCopy[$key] ?? 'Choose this difficulty.') ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php $field = 'difficulty'; require __DIR__ . '/../../partials/field-error.php'; ?>
        </fieldset>

        <div class="mt-6 flex flex-col gap-2 border-t border-white/[0.06] pt-5 sm:flex-row sm:items-center sm:justify-end">
            <a href="<?= e(url($isEdit ? '/quests/' . (int) $quest['id'] : '/quests')) ?>" class="btn btn-app-ghost w-full sm:w-auto">
                Cancel
            </a>
            <button type="submit" class="btn btn-app-primary w-full sm:w-auto" data-loading-label="<?= $isEdit ? 'Saving…' : 'Creating…' ?>">
                <i data-lucide="<?= $isEdit ? 'check' : 'plus' ?>" class="h-4 w-4"></i>
                <?= $isEdit ? 'Save changes' : 'Create quest' ?>
            </button>
        </div>
    </div>

    <aside class="flex flex-col gap-4">
        <div class="relative overflow-hidden rounded-2xl border border-gold-400/20 bg-gradient-to-br from-gold-400/[0.08] via-white/[0.02] to-transparent p-5">
            <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-gold-400/[0.12] blur-[60px]" aria-hidden="true"></div>
            <div class="relative">
                <div class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-xl border border-gold-400/25 bg-gold-400/10 text-gold-300">
                        <i data-lucide="zap" class="h-4 w-4"></i>
                    </span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Reward preview</p>
                        <p class="font-display text-sm font-semibold text-white" data-xp-preview-difficulty><?= e(ucfirst($selectedDifficulty)) ?> quest</p>
                    </div>
                </div>

                <p class="mt-5 font-display text-5xl font-bold leading-none tracking-tight text-white">
                    <span data-xp-preview><?= e((string) $selectedReward) ?></span>
                    <span class="text-sm font-medium text-gold-300">XP</span>
                </p>
                <p class="mt-2 text-[11px] leading-relaxed text-muted">
                    <?php if ($selectedRecurrence === Recurrence::NONE): ?>
                        Awarded once, when you mark this quest complete. The server assigns this value from
                    <?php else: ?>
                        Awarded once per <?= e(Recurrence::periodNoun($selectedRecurrence)) ?> while the quest stays active. The server assigns this value from
                    <?php endif; ?>
                    <code class="rounded bg-white/[0.05] px-1 py-0.5 text-[10px] text-slate-400">config/game.php</code>.
                </p>
            </div>
        </div>

        <div class="app-panel p-5">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">How rewards work</p>
            <ul class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-muted">
                <li class="flex items-start gap-2">
                    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-400/80"></i>
                    <span>XP is calculated on the server; any submitted reward is ignored.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="check-circle-2" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gold-400/80"></i>
                    <span>A quest pays out exactly once per period, even across repeated or concurrent requests.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i data-lucide="trending-up" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ember-300"></i>
                    <span>Level and rank are recalculated from total XP after every award.</span>
                </li>
            </ul>
        </div>

        <?php if ($isEdit): ?>
            <div class="rounded-2xl border border-white/[0.07] bg-white/[0.02] p-4">
                <p class="flex items-start gap-2 text-[11px] leading-relaxed text-muted">
                    <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted"></i>
                    <span>Editing an active quest changes the reward it will pay when completed. Changing the cadence clears the current period so the quest can be claimed again. Completed one-off quests keep the XP they already awarded.</span>
                </p>
            </div>
        <?php endif; ?>
    </aside>
</form>
