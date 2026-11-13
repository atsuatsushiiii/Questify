<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Flash / inline alert.
 *
 * @var string $type success|error|info
 * @var string $message
 */
$tone = match ($type) {
    'success' => [
        'wrap' => 'border-aqua-400/30 border-l-2 border-l-aqua-400 bg-aqua-500/[0.07] text-aqua-100',
        'icon' => 'check-circle-2',
        'iconTone' => 'text-aqua-300',
    ],
    'error' => [
        'wrap' => 'border-ember-400/35 border-l-2 border-l-ember-400 bg-ember-500/[0.07] text-ember-100',
        'icon' => 'alert-circle',
        'iconTone' => 'text-ember-300',
    ],
    'info' => [
        'wrap' => 'border-brand-400/30 border-l-2 border-l-brand-400 bg-brand-400/[0.06] text-brand-100',
        'icon' => 'info',
        'iconTone' => 'text-brand-300',
    ],
    default => [
        'wrap' => 'border-line border-l-2 border-l-brand-400 bg-panel/50 text-slate-200',
        'icon' => 'info',
        'iconTone' => 'text-brand-300',
    ],
};
$role = $type === 'error' ? 'alert' : 'status';
?>
<div
    class="mb-6 flex items-start gap-3 rounded-sm border px-4 py-3 text-sm leading-relaxed <?= e($tone['wrap']) ?>"
    role="<?= e($role) ?>"
>
    <i data-lucide="<?= e($tone['icon']) ?>" class="mt-0.5 h-4 w-4 shrink-0 <?= e($tone['iconTone']) ?>"></i>
    <p class="min-w-0 flex-1"><?= e($message) ?></p>
</div>
