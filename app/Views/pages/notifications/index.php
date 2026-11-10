<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Notification inbox.
 *
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $notifications
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $filter
 * @var int $unread
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$query = static fn (array $overrides = []): string => url('/notifications?' . http_build_query(array_merge(
    ['filter' => $filter],
    $overrides,
)));
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">
                <i data-lucide="bell" class="h-3.5 w-3.5"></i>
                Signal log
            </p>
            <h1 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Notifications</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">
                Achievements, level-ups, streak milestones, profile events, and report updates, newest first. Select a notification to read it enlarged.
            </p>
        </div>

        <?php if ($unread > 0): ?>
            <form method="POST" action="<?= e(url('/notifications/read-all')) ?>" data-notifications-read-all>
                <?= csrf_field() ?>
                <button type="submit" class="btn-app-ghost btn inline-flex text-xs">
                    <i data-lucide="check-check" class="h-3.5 w-3.5"></i>
                    Mark all read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="quest-tabs" role="navigation" aria-label="Notification filter">
            <a
                href="<?= e(url('/notifications')) ?>"
                class="quest-tab <?= $filter === 'all' ? 'is-active' : '' ?>"
                <?= $filter === 'all' ? 'aria-current="page"' : '' ?>
            >
                All
                <span class="quest-tab-count"><?= e((string) $total) ?></span>
            </a>
            <a
                href="<?= e(url('/notifications?filter=unread')) ?>"
                class="quest-tab <?= $filter === 'unread' ? 'is-active' : '' ?>"
                <?= $filter === 'unread' ? 'aria-current="page"' : '' ?>
            >
                Unread
                <span class="quest-tab-count"><?= e((string) $unread) ?></span>
            </a>
        </div>
        <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">
            <?= e((string) $unread) ?> unread · times shown in <?= e(str_replace('_', ' ', $timezone)) ?>
        </p>
    </div>

    <?php if ($notifications === []): ?>
        <div class="mt-4 rounded-sm border border-dashed border-line bg-panel/30 px-5 py-16 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-sm border border-line bg-white/[0.03] text-muted">
                <i data-lucide="bell-off" class="h-5 w-5"></i>
            </span>
            <p class="mt-4 font-display text-sm font-semibold text-slate-200">
                <?= $filter === 'unread' ? 'No unread notifications' : 'Nothing here yet' ?>
            </p>
            <p class="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-muted">
                <?= $filter === 'unread'
                    ? 'You are all caught up. Switch to All to review earlier events.'
                    : 'Complete a quest to start earning achievements, level-ups, and streak milestones.' ?>
            </p>
            <a href="<?= e(url('/quests')) ?>" class="mt-4 inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200">
                Go to quest log
                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>
    <?php else: ?>
        <div class="mt-4 space-y-2" data-notification-list>
            <?php foreach ($notifications as $notification): ?>
                <?php $showAction = true; require __DIR__ . '/../../partials/notification-item.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Notification pagination">
            <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted-dim">Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= e($query(['page' => $page - 1])) ?>" class="btn-app-ghost btn inline-flex text-xs" rel="prev">
                        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                        Newer
                    </a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a href="<?= e($query(['page' => $page + 1])) ?>" class="btn-app-ghost btn inline-flex text-xs" rel="next">
                        Older
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<?php if ($notifications !== []): ?>
    <dialog
        class="notification-reader"
        data-notification-reader
        aria-labelledby="notification-reader-title"
        aria-describedby="notification-reader-message"
    >
        <div class="p-6 sm:p-7" data-notification-reader-panel>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="eyebrow">
                        <i data-lucide="bell" class="h-3.5 w-3.5"></i>
                        Notification
                    </p>
                    <h2 id="notification-reader-title" data-notification-reader-title class="mt-4 font-display text-2xl font-semibold tracking-tight text-white"></h2>
                </div>
                <button
                    type="button"
                    data-notification-reader-close
                    class="inline-flex items-center gap-1 rounded-sm border border-line bg-white/[0.03] px-2.5 py-2 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400 transition hover:border-brand-400/30 hover:text-brand-200"
                >
                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    Close
                </button>
            </div>

            <p id="notification-reader-message" data-notification-reader-message class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-200"></p>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted-dim">
                    <time data-notification-reader-time></time>
                </p>
                <a href="#" data-notification-reader-link class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-brand-300 transition hover:text-brand-200" hidden>
                    Open related page
                    <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                </a>
            </div>
        </div>
    </dialog>
<?php endif; ?>
