<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * One notification row.
 *
 * The read/unread presentation lives in .notification-card component classes so
 * the notifications module can mark an item read without a page reload.
 *
 * @var array<string, mixed> $notification
 * @var string $timezone
 * @var bool $showAction
 */
$unread = ($notification['read_at'] ?? null) === null;
$rawUrl = isset($notification['url']) ? (string) $notification['url'] : '';
$href = $rawUrl !== '' && str_starts_with($rawUrl, '/') ? url($rawUrl) : null;
$createdAt = format_datetime(
    isset($notification['created_at']) ? (string) $notification['created_at'] : null,
    $timezone,
    'j M Y, H:i',
);
$showAction = $showAction ?? true;
?>
<article
    class="notification-card <?= $unread ? 'is-unread' : '' ?> flex items-start gap-3 rounded-sm border px-4 py-3.5 transition"
    data-notification
    data-notification-id="<?= e((string) $notification['id']) ?>"
>
    <span class="notification-icon grid h-10 w-10 shrink-0 place-items-center rounded-sm border">
        <i data-lucide="<?= e((string) ($notification['icon'] ?? 'bell')) ?>" class="h-4 w-4"></i>
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-semibold text-slate-100">
                    <?php if ($unread): ?>
                        <span class="notification-dot h-1.5 w-1.5 shrink-0 rounded-sm bg-brand-400" aria-hidden="true"></span>
                        <span class="sr-only" data-unread-label>Unread: </span>
                    <?php endif; ?>
                    <?php if ($href !== null): ?>
                        <a href="<?= e($href) ?>" class="truncate transition hover:text-brand-200" data-notification-title data-notification-link><?= e((string) $notification['title']) ?></a>
                    <?php else: ?>
                        <span class="truncate" data-notification-title><?= e((string) $notification['title']) ?></span>
                    <?php endif; ?>
                </p>
                <p class="mt-1 text-[11px] leading-relaxed text-muted" data-notification-message><?= e((string) $notification['message']) ?></p>
            </div>

            <div class="flex shrink-0 items-start gap-1.5">
                <button
                    type="button"
                    data-notification-open
                    class="inline-flex items-center gap-1 rounded-sm border border-line bg-white/[0.03] px-2 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400 transition hover:border-brand-400/30 hover:text-brand-200"
                    aria-label="Enlarge notification: <?= e((string) $notification['title']) ?>"
                >
                    <i data-lucide="maximize-2" class="h-3 w-3"></i>
                </button>

                <?php if ($unread && $showAction): ?>
                    <form method="POST" action="<?= e(url('/notifications/' . (int) $notification['id'] . '/read')) ?>" data-notification-read>
                        <?= csrf_field() ?>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1 rounded-sm border border-line bg-white/[0.03] px-2 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400 transition hover:border-brand-400/30 hover:text-brand-200"
                        >
                            <i data-lucide="check" class="h-3 w-3"></i>
                            Mark read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($createdAt !== null): ?>
            <p class="mt-2 font-mono text-[9px] text-muted-dim">
                <time datetime="<?= e((string) iso_datetime((string) $notification['created_at'])) ?>" data-notification-time><?= e($createdAt) ?></time>
            </p>
        <?php endif; ?>
    </div>
</article>
