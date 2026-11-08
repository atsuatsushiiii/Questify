<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Admin quest detail and moderation actions.
 *
 * @var array{quest: array<string, mixed>, xpTransactions: list<array<string, mixed>>, audit: list<array<string, mixed>>} $detail
 */
$quest = $detail['quest'];
$status = (string) $quest['status'];
$ownerInitial = mb_strtoupper(mb_substr((string) $quest['owner_display_name'], 0, 1));
$ownerAvatar = avatar_url(isset($quest['owner_avatar_path']) ? (string) $quest['owner_avatar_path'] : null);
$created = format_datetime((string) $quest['created_at'], 'UTC', 'j M Y, H:i');
$updated = format_datetime((string) $quest['updated_at'], 'UTC', 'j M Y, H:i');
$completed = format_datetime(
    isset($quest['completed_at']) ? (string) $quest['completed_at'] : null,
    'UTC',
    'j M Y, H:i',
);
$statusTones = [
    'active' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'completed' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'archived' => 'border-line bg-white/[0.03] text-slate-400',
];
$statusTone = $statusTones[$status] ?? $statusTones['archived'];
?>
<section class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between" data-reveal>
    <div class="min-w-0">
        <a href="<?= e(url('/admin/quests')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Back to quests
        </a>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTone) ?>">
                <i data-lucide="<?= $status === 'completed' ? 'circle-check' : ($status === 'archived' ? 'archive' : 'target') ?>" class="h-3 w-3"></i>
                <?= e(ucfirst($status)) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <i data-lucide="flame" class="h-3 w-3"></i>
                <?= e(ucfirst((string) $quest['difficulty'])) ?>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <i data-lucide="sparkles" class="h-3 w-3"></i>
                <?= e((string) $quest['xp_reward']) ?> XP
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-sm border border-line bg-white/[0.03] px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                <i data-lucide="repeat" class="h-3 w-3"></i>
                <?= e(ucfirst((string) $quest['recurrence'])) ?>
            </span>
        </div>

        <h1 class="mt-3 break-words font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl"><?= e((string) $quest['title']) ?></h1>

        <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted">
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="folder" class="h-3.5 w-3.5"></i>
                <?= e((string) ($quest['category_name'] ?? 'Uncategorised')) ?>
            </span>
            <span class="text-muted-dim">·</span>
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="user-round" class="h-3.5 w-3.5"></i>
                <a href="<?= e(url('/admin/users/' . (int) $quest['user_id'])) ?>" class="text-slate-300 transition hover:text-brand-200">
                    <?= e((string) $quest['owner_display_name']) ?> (@<?= e((string) $quest['owner_username']) ?>)
                </a>
            </span>
        </p>

        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted-dim">
            <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar-days" class="h-3 w-3"></i> Created <?= e($created ?? '—') ?> UTC</span>
            <span class="inline-flex items-center gap-1.5"><i data-lucide="clock" class="h-3 w-3"></i> Updated <?= e($updated ?? '—') ?> UTC</span>
            <?php if ($completed !== null): ?>
                <span class="inline-flex items-center gap-1.5"><i data-lucide="circle-check" class="h-3 w-3"></i> Completed <?= e($completed) ?> UTC</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        <?php if ($status === 'archived'): ?>
            <form method="POST" action="<?= e(url('/admin/quests/' . (int) $quest['id'] . '/restore')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-app-primary px-3 py-2 text-xs">
                    <i data-lucide="archive-restore" class="h-3.5 w-3.5"></i>
                    Restore
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= e(url('/admin/quests/' . (int) $quest['id'] . '/archive')) ?>" data-confirm="Archive this quest? It disappears from the owner's board until an administrator restores it.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-app-ghost px-3 py-2 text-xs">
                    <i data-lucide="archive" class="h-3.5 w-3.5"></i>
                    Archive
                </button>
            </form>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('/admin/quests/' . (int) $quest['id'] . '/delete')) ?>" data-confirm="Delete this quest permanently? The XP ledger keeps its history, but the quest itself cannot be restored.">
            <?= csrf_field() ?>
            <button type="submit" class="btn px-3 py-2 text-xs text-ember-300 hover:bg-ember-500/10 hover:text-ember-200">
                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                Delete
            </button>
        </form>
    </div>
</section>

<section class="mt-6 grid gap-4 xl:grid-cols-[1.5fr_1fr]" data-reveal>
    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-ember-400/20 bg-ember-500/10 text-ember-300">
                <i data-lucide="scroll-text" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Quest definition</h2>
                <p class="text-[11px] text-muted">Exactly what the owner sees on their quest card.</p>
            </div>
        </div>

        <div class="p-5">
            <?php if (trim((string) $quest['description']) === ''): ?>
                <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-5 py-8 text-center text-xs text-muted">
                    This quest has no description.
                </p>
            <?php else: ?>
                <p class="whitespace-pre-line break-words text-sm leading-relaxed text-slate-300"><?= e((string) $quest['description']) ?></p>
            <?php endif; ?>

            <dl class="mt-5 grid gap-4 border-t border-line pt-5 text-[11px] sm:grid-cols-2">
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Quest ID</dt>
                    <dd class="mt-1 font-mono text-slate-300">#<?= e((string) $quest['id']) ?></dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Owner ID</dt>
                    <dd class="mt-1 font-mono text-slate-300">#<?= e((string) $quest['user_id']) ?></dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Last completed period</dt>
                    <dd class="mt-1 font-mono text-slate-300"><?= e((string) ($quest['last_completed_period'] ?? '—')) ?></dd>
                </div>
                <div>
                    <dt class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Category slug</dt>
                    <dd class="mt-1 font-mono text-slate-300"><?= e((string) ($quest['category_slug'] ?? '—')) ?></dd>
                </div>
            </dl>
        </div>
    </article>

    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                <i data-lucide="user-round" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Owner</h2>
                <p class="text-[11px] text-muted">The account that created this quest.</p>
            </div>
        </div>

        <div class="p-5">
            <div class="flex items-center gap-3">
                <?php if ($ownerAvatar !== null): ?>
                    <img src="<?= e($ownerAvatar) ?>" alt="" class="h-12 w-12 shrink-0 rounded-sm border border-line object-cover">
                <?php else: ?>
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-sm bg-brand-400 text-base font-semibold text-[#14100B]">
                        <?= e($ownerInitial) ?>
                    </span>
                <?php endif; ?>
                <div class="min-w-0">
                    <a href="<?= e(url('/admin/users/' . (int) $quest['user_id'])) ?>" class="block truncate text-sm font-semibold text-slate-200 transition hover:text-brand-200">
                        <?= e((string) $quest['owner_display_name']) ?>
                    </a>
                    <p class="truncate text-[11px] text-muted">@<?= e((string) $quest['owner_username']) ?></p>
                    <p class="truncate text-[10px] text-muted-dim"><?= e((string) $quest['owner_email']) ?></p>
                </div>
            </div>

            <a href="<?= e(url('/admin/users/' . (int) $quest['user_id'])) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                Open account record
            </a>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-4 xl:grid-cols-2" data-reveal>
    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-aqua-400/20 bg-aqua-500/10 text-aqua-300">
                <i data-lucide="sparkles" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">XP ledger</h2>
                <p class="text-[11px] text-muted">Newest 20 awards tied to this quest.</p>
            </div>
        </div>

        <div class="p-4">
            <?php if ($detail['xpTransactions'] === []): ?>
                <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">No XP has been awarded for this quest.</p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($detail['xpTransactions'] as $transaction): ?>
                        <li class="flex items-center justify-between gap-3 rounded-sm border border-line bg-white/[0.015] px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold text-slate-300">
                                    +<?= e((string) $transaction['amount']) ?> XP
                                    <span class="font-normal text-muted-dim">· <?= e((string) $transaction['period_key']) ?></span>
                                </p>
                                <p class="mt-0.5 text-[10px] text-muted-dim">
                                    Level <?= e((string) $transaction['level_before']) ?> → <?= e((string) $transaction['level_after']) ?>
                                    · <?= e((string) format_datetime((string) $transaction['created_at'], 'UTC', 'j M Y, H:i')) ?> UTC
                                </p>
                            </div>
                            <span class="shrink-0 rounded-sm border border-aqua-400/30 bg-aqua-500/[0.06] px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] text-aqua-300">
                                Awarded
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </article>

    <article class="app-panel min-w-0 overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                <i data-lucide="history" class="h-4 w-4"></i>
            </span>
            <div>
                <h2 class="font-display text-sm font-semibold text-white">Moderation history</h2>
                <p class="text-[11px] text-muted">Privileged actions taken against this quest.</p>
            </div>
        </div>

        <div class="p-4">
            <?php if ($detail['audit'] === []): ?>
                <p class="rounded-sm border border-dashed border-line bg-white/[0.01] px-4 py-6 text-center text-[11px] text-muted">No moderation history.</p>
            <?php else: ?>
                <ol class="space-y-2">
                    <?php foreach ($detail['audit'] as $entry): ?>
                        <li class="rounded-sm border border-line bg-white/[0.015] px-3 py-2.5">
                            <p class="text-[11px] font-medium text-slate-300"><?= e((string) $entry['summary']) ?></p>
                            <p class="mt-0.5 text-[10px] text-muted-dim">
                                <span class="font-mono text-brand-300/80"><?= e((string) $entry['action']) ?></span> ·
                                <?= e((string) ($entry['actor_display_name'] ?? 'System')) ?> ·
                                <?= e((string) format_datetime((string) $entry['created_at'], 'UTC', 'j M, H:i')) ?> UTC
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </article>
</section>
