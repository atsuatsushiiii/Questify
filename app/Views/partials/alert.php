<?php
/**
 * Flash / inline alert.
 *
 * @var string $type success|error|info
 * @var string $message
 */
$tone = match ($type ?? 'info') {
    'success' => [
        'wrap' => 'border-emerald-400/25 bg-emerald-500/[0.08] text-emerald-100',
        'icon' => 'check-circle-2',
        'iconTone' => 'text-emerald-300',
    ],
    'error' => [
        'wrap' => 'border-rose-400/25 bg-rose-500/[0.08] text-rose-100',
        'icon' => 'alert-circle',
        'iconTone' => 'text-rose-300',
    ],
    'info' => [
        'wrap' => 'border-gold-400/25 bg-gold-400/[0.08] text-gold-100',
        'icon' => 'info',
        'iconTone' => 'text-gold-300',
    ],
    default => [
        'wrap' => 'border-aqua-400/25 bg-aqua-500/[0.08] text-aqua-100',
        'icon' => 'info',
        'iconTone' => 'text-aqua-300',
    ],
};
$role = ($type ?? 'info') === 'error' ? 'alert' : 'status';
?>
<div
    class="mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed <?= e($tone['wrap']) ?>"
    role="<?= e($role) ?>"
>
    <i data-lucide="<?= e($tone['icon']) ?>" class="mt-0.5 h-4 w-4 shrink-0 <?= e($tone['iconTone']) ?>"></i>
    <p class="min-w-0 flex-1"><?= e($message ?? '') ?></p>
</div>
