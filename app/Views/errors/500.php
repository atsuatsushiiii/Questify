<p class="font-display text-[88px] font-bold leading-none text-brand-500 sm:text-[110px]">500</p>
<h1 class="mt-4 font-display text-2xl font-bold tracking-tight text-white sm:text-3xl">The server dropped the quest.</h1>
<p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-muted">
    Something went wrong on our side. The error has been logged. Try again in a moment, or return to the homepage.
</p>

<?php require __DIR__ . '/../partials/debug-trace.php'; ?>

<div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
    <a href="<?= e(url('/')) ?>" class="btn btn-primary">Return home</a>
</div>
