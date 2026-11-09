<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Shared admin pagination footer.
 *
 * @var array{page: int, pages: int, total: int, perPage: int} $pagination
 * @var string $basePath
 * @var array<string, string> $query
 */
$page = (int) $pagination['page'];
$pages = (int) $pagination['pages'];
$total = (int) $pagination['total'];
$perPage = (int) $pagination['perPage'];
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);
$link = static function (int $target) use ($basePath, $query): string {
    $params = array_filter(array_merge($query, ['page' => (string) $target]), static fn (string $value): bool => $value !== '');

    return url($basePath . '?' . http_build_query($params));
};
?>
<div class="flex flex-col gap-3 border-t border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-[11px] text-muted">
        Showing <span class="font-semibold text-slate-300"><?= e((string) $from) ?>–<?= e((string) $to) ?></span>
        of <span class="font-semibold text-slate-300"><?= e(number_format($total)) ?></span>
    </p>

    <nav class="flex items-center gap-2" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a href="<?= e($link($page - 1)) ?>" class="btn btn-app-ghost px-3 py-1.5 text-[11px]">
                <i data-lucide="chevron-left" class="h-3.5 w-3.5"></i>
                Previous
            </a>
        <?php else: ?>
            <span class="btn btn-app-ghost cursor-not-allowed border-dashed px-3 py-1.5 text-[11px] text-muted" aria-disabled="true">
                <i data-lucide="chevron-left" class="h-3.5 w-3.5"></i>
                Previous
            </span>
        <?php endif; ?>

        <span class="px-1 text-[11px] tabular-nums text-muted">Page <?= e((string) $page) ?> / <?= e((string) $pages) ?></span>

        <?php if ($page < $pages): ?>
            <a href="<?= e($link($page + 1)) ?>" class="btn btn-app-ghost px-3 py-1.5 text-[11px]">
                Next
                <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
            </a>
        <?php else: ?>
            <span class="btn btn-app-ghost cursor-not-allowed border-dashed px-3 py-1.5 text-[11px] text-muted" aria-disabled="true">
                Next
                <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
            </span>
        <?php endif; ?>
    </nav>
</div>
