<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Inline field error.
 *
 * @var string $field
 * @var array<string, string> $errors
 */
if (empty($errors[$field])) {
    return;
}
?>
<p id="<?= e($field) ?>-error" class="mt-1.5 flex items-start gap-1.5 text-xs text-rose-300">
    <i data-lucide="alert-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
    <span><?= e($errors[$field]) ?></span>
</p>
