<!-- Code © 2026 Kevin Cyril Maraño, MIT license. -->

<p class="font-display text-[88px] font-semibold leading-none text-brand-500 sm:text-[110px]"><?= e($status) ?></p>
<h1 class="mt-4 font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">The quest hit a snag.</h1>
<p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-muted">
    <?= e($message ?? 'Something went wrong. Please try again.') ?>
</p>

<?php require __DIR__ . '/../partials/debug-trace.php'; ?>

<div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
    <a href="<?= e(url('/')) ?>" class="btn btn-primary">Return home</a>
</div>
