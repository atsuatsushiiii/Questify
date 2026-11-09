<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Report submission form.
 *
 * @var array{type: string, id: int, label: string, handle: string|null, record: array<string, mixed>} $target
 * @var array<string, mixed>|null $existing
 * @var array<string, string> $reasons
 * @var array{target_type: string, target_id: string, reason: string, details: string} $old
 * @var array<string, string> $errors
 */
$displayName = (string) ($target['record']['display_name'] ?? $target['record']['username'] ?? $target['label']);
$username = (string) ($target['record']['username'] ?? '');
$avatarUrl = avatar_url(isset($target['record']['avatar_path']) ? (string) $target['record']['avatar_path'] : null);
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$backPath = $target['type'] === 'profile' && $target['handle'] !== null
    ? '/u/' . rawurlencode((string) $target['handle'])
    : '/dashboard';
$invalid = static fn (string $field): string => isset($errors[$field]) ? 'true' : 'false';
$errorClass = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$statusLabels = ['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <a href="<?= e(url($backPath)) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
        Back to <?= $target['type'] === 'profile' ? 'profile' : 'dashboard' ?>
    </a>

    <div class="mt-4">
        <p class="eyebrow">
            <i data-lucide="flag" class="h-3.5 w-3.5"></i>
            Report
        </p>
        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Flag a <?= e($target['type']) ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            Reports go to the administrator queue. Describe what happened as clearly as you can; the reported
            account is never told who filed the report.
        </p>
    </div>
</section>

<?php if ($existing !== null): ?>
    <div class="mx-auto mt-6 max-w-4xl">
        <?php
        $type = 'info';
        $message = 'You already reported this ' . $target['type'] . '. Its status is '
            . ($statusLabels[(string) $existing['status']] ?? (string) $existing['status']) . '.';
        require __DIR__ . '/../../partials/alert.php';
        ?>
        <a href="<?= e(url($backPath)) ?>" class="btn btn-app-ghost mt-4 px-4 py-2 text-xs">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Return to <?= $target['type'] === 'profile' ? 'profile' : 'dashboard' ?>
        </a>
    </div>
<?php else: ?>
    <form method="POST" action="<?= e(url('/reports')) ?>" class="mx-auto mt-6 grid max-w-4xl gap-4 xl:grid-cols-[1.4fr_0.6fr]">
        <?= csrf_field() ?>
        <input type="hidden" name="target_type" value="<?= e($old['target_type']) ?>">
        <input type="hidden" name="target_id" value="<?= e($old['target_id']) ?>">

        <article class="app-panel p-5 sm:p-6">
            <div class="flex items-center gap-3 border-b border-line pb-4">
                <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                    <i data-lucide="flag" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">What should we review?</h2>
                    <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">Required fields are marked with an asterisk</p>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="mt-4">
                    <?php
                    $type = 'error';
                    $message = 'Please fix the highlighted fields below.';
                    require __DIR__ . '/../../partials/alert.php';
                    ?>
                </div>
            <?php endif; ?>

            <div class="mt-5">
                <label for="reason" class="field-label">Reason <span class="text-ember-300">*</span></label>
                <select
                    id="reason"
                    name="reason"
                    class="app-select<?= e($errorClass('reason')) ?>"
                    required
                    aria-invalid="<?= $invalid('reason') ?>"
                    aria-describedby="<?= isset($errors['reason']) ? 'reason-error' : 'reason-help' ?>"
                >
                    <option value="">Choose a reason</option>
                    <?php foreach ($reasons as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $old['reason'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="reason-help" class="mt-1.5 text-[11px] text-muted-dim">Pick the closest match. Administrators read every report.</p>
                <?php $field = 'reason'; require __DIR__ . '/../../partials/field-error.php'; ?>
            </div>

            <div class="mt-5">
                <div class="flex items-baseline justify-between gap-3">
                    <label for="details" class="field-label">Details</label>
                    <span class="text-[10px] tabular-nums text-muted-dim"><span data-report-count><?= e((string) mb_strlen($old['details'])) ?></span>/1000</span>
                </div>
                <textarea
                    id="details"
                    name="details"
                    rows="6"
                    class="app-field resize-y<?= e($errorClass('details')) ?>"
                    placeholder="What did this account do? Include context that helps an administrator verify the report."
                    maxlength="1000"
                    aria-invalid="<?= $invalid('details') ?>"
                    aria-describedby="<?= isset($errors['details']) ? 'details-error' : 'details-help' ?>"
                    data-report-details
                ><?= e($old['details']) ?></textarea>
                <p id="details-help" class="mt-1.5 text-[11px] text-muted-dim">Required when you choose “Something else”. Do not include passwords or private data.</p>
                <?php $field = 'details'; require __DIR__ . '/../../partials/field-error.php'; ?>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-line pt-5">
                <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                    Submit report
                </button>
                <a href="<?= e(url($backPath)) ?>" class="btn btn-app-ghost px-4 py-2 text-xs">Cancel</a>
            </div>
        </article>

        <aside class="space-y-4">
            <article class="app-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <p class="app-stat-label">Reported <?= e($target['type']) ?></p>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-3">
                        <?php if ($avatarUrl !== null): ?>
                            <img src="<?= e($avatarUrl) ?>" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-line object-cover">
                        <?php else: ?>
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-sm bg-brand-400 text-base font-semibold text-[#14100B]" aria-hidden="true">
                                <?= e($initial) ?>
                            </span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white"><?= e($displayName) ?></p>
                            <?php if ($username !== ''): ?>
                                <p class="truncate font-mono text-[10px] text-muted">@<?= e($username) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a href="<?= e(url($backPath)) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                        Open profile
                    </a>
                </div>
            </article>

            <article class="rounded-sm border border-line bg-panel/50 p-5">
                <p class="app-stat-label">What happens next</p>
                <ul class="mt-3 space-y-2 text-[11px] leading-relaxed text-muted">
                    <li class="flex gap-2"><i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> An administrator reviews the report.</li>
                    <li class="flex gap-2"><i data-lucide="eye-off" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> The reported account does not see your identity.</li>
                    <li class="flex gap-2"><i data-lucide="history" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> Every decision is written to the audit log.</li>
                </ul>
            </article>
        </aside>
    </form>
<?php endif; ?>
