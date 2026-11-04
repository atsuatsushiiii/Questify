<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Development-only exception details.
 *
 * @var Throwable|null $exception
 */
?>
<?php if ($exception instanceof Throwable): ?>
    <div class="mt-8 text-left">
        <p class="rounded-t-xl border border-b-0 border-white/10 bg-white/[0.03] px-4 py-2 text-xs font-semibold text-rose-300">
            <?= e($exception::class) ?>: <?= e($exception->getMessage()) ?>
        </p>
        <pre class="max-h-64 overflow-auto rounded-b-xl border border-white/10 bg-black/40 p-4 text-[11px] leading-relaxed text-slate-400"><?= e($exception->getFile()) ?>:<?= e($exception->getLine()) ?>

<?= e($exception->getTraceAsString()) ?></pre>
    </div>
<?php endif; ?>
