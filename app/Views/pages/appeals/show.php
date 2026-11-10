<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Read-only account appeal form and decision history.
 *
 * @var array<string, mixed>|null $openAppeal
 * @var list<array<string, mixed>> $history
 * @var int $minMessage
 * @var int $maxMessage
 */
$statusTones = [
    'open' => 'border-ember-400/30 bg-ember-500/[0.08] text-ember-300',
    'accepted' => 'border-aqua-400/30 bg-aqua-500/[0.08] text-aqua-300',
    'denied' => 'border-line bg-white/[0.03] text-slate-400',
];
$statusLabels = ['open' => 'Waiting for review', 'accepted' => 'Accepted', 'denied' => 'Denied'];
?>
<section class="mx-auto max-w-4xl" data-reveal>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="<?= e(url('/dashboard')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
            Back to dashboard
        </a>
        <a href="<?= e(url('/notifications')) ?>" class="inline-flex items-center gap-1.5 font-mono text-[10px] font-semibold uppercase tracking-[0.1em] text-muted transition hover:text-brand-300">
            <i data-lucide="bell" class="h-3.5 w-3.5"></i>
            Notifications
        </a>
    </div>

    <div class="mt-4">
        <p class="eyebrow">
            <i data-lucide="scale" class="h-3.5 w-3.5"></i>
            Read-only review
        </p>
        <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">Request a review</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-muted">
            A moderator limited your account to read-only access. Explain what you think should be reconsidered and
            an administrator will reply in your notifications. You can keep browsing and reviewing your quests
            while you wait.
        </p>
    </div>
</section>

<?php if ($openAppeal !== null): ?>
    <section class="mx-auto mt-6 max-w-4xl" data-reveal>
        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                    <i data-lucide="clock" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Your appeal is waiting for review</h2>
                    <p class="text-[11px] text-muted">
                        Submitted <?= e(format_datetime((string) $openAppeal['created_at'], 'UTC', 'j M Y, H:i') ?? '—') ?> UTC.
                        You will receive a notification when a moderator decides.
                    </p>
                </div>
            </div>
            <div class="p-5">
                <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Your message</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-300"><?= e((string) $openAppeal['message']) ?></p>
            </div>
        </article>
    </section>
<?php else: ?>
    <form method="POST" action="<?= e(url('/appeal')) ?>" class="mx-auto mt-6 grid max-w-4xl gap-4 xl:grid-cols-[1.4fr_0.6fr]">
        <?= csrf_field() ?>

        <article class="app-panel p-5 sm:p-6">
            <div class="flex items-center gap-3 border-b border-line pb-4">
                <span class="app-panel-icon border-ember-400/30 bg-ember-500/10 text-ember-300">
                    <i data-lucide="scale" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">What should the moderator know?</h2>
                    <p class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">At least <?= e((string) $minMessage) ?> characters</p>
                </div>
            </div>

            <div class="mt-5">
                <label for="message" class="field-label">Your appeal <span class="text-ember-300">*</span></label>
                <textarea
                    id="message"
                    name="message"
                    rows="7"
                    class="app-field resize-y"
                    placeholder="Explain the context, what you have changed, or why the limit should be reconsidered."
                    minlength="<?= e((string) $minMessage) ?>"
                    maxlength="<?= e((string) $maxMessage) ?>"
                    required
                    aria-describedby="message-help"
                ></textarea>
                <p id="message-help" class="mt-1.5 text-[11px] text-muted-dim">
                    Keep it factual. Do not include passwords, private data, or information about other people.
                </p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-line pt-5">
                <button type="submit" class="btn btn-app-primary px-4 py-2 text-xs">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                    Submit appeal
                </button>
                <a href="<?= e(url('/dashboard')) ?>" class="btn btn-app-ghost px-4 py-2 text-xs">Cancel</a>
            </div>
        </article>

        <aside class="space-y-4">
            <article class="rounded-sm border border-line bg-panel/50 p-5">
                <p class="app-stat-label">What happens next</p>
                <ul class="mt-3 space-y-2 text-[11px] leading-relaxed text-muted">
                    <li class="flex gap-2"><i data-lucide="clock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> A moderator reads your appeal.</li>
                    <li class="flex gap-2"><i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> You get a notification with the decision.</li>
                    <li class="flex gap-2"><i data-lucide="history" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-300"></i> Every decision is written to the audit log.</li>
                </ul>
            </article>

            <article class="rounded-sm border border-line bg-panel/50 p-5">
                <p class="app-stat-label">Still available</p>
                <p class="mt-2 text-[11px] leading-relaxed text-muted">
                    Browsing, achievements, leaderboards, notifications, and account settings stay open while your
                    account is read-only.
                </p>
                <a href="<?= e(url('/quests')) ?>" class="btn btn-app-ghost mt-4 w-full px-3 py-2 text-xs">
                    <i data-lucide="scroll-text" class="h-3.5 w-3.5"></i>
                    Review my quests
                </a>
            </article>
        </aside>
    </form>
<?php endif; ?>

<?php if ($history !== []): ?>
    <section class="mx-auto mt-6 max-w-4xl" data-reveal>
        <article class="app-panel overflow-hidden">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <span class="app-panel-icon border-brand-400/30 bg-brand-400/10 text-brand-300">
                    <i data-lucide="history" class="h-4 w-4"></i>
                </span>
                <div>
                    <h2 class="font-display text-sm font-semibold text-white">Appeal history</h2>
                    <p class="text-[11px] text-muted">Your previous requests and the moderator's decisions.</p>
                </div>
            </div>
            <ol class="divide-y divide-white/[0.04]">
                <?php foreach ($history as $appeal): ?>
                    <?php $status = (string) $appeal['status']; ?>
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-sm border px-2 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.12em] <?= e($statusTones[$status] ?? $statusTones['denied']) ?>">
                                <?= e($statusLabels[$status] ?? ucfirst($status)) ?>
                            </span>
                            <span class="font-mono text-[9px] uppercase tracking-[0.08em] text-muted-dim">
                                <?= e(format_datetime((string) $appeal['created_at'], 'UTC', 'j M Y, H:i') ?? '—') ?> UTC
                            </span>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-[11px] leading-relaxed text-slate-300"><?= e((string) $appeal['message']) ?></p>
                        <?php if ($status !== 'open' && trim((string) ($appeal['resolution_note'] ?? '')) !== ''): ?>
                            <div class="mt-2 rounded-sm border border-line bg-white/[0.02] px-3 py-2">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-dim">Moderator note</p>
                                <p class="mt-1 whitespace-pre-line text-[11px] leading-relaxed text-muted"><?= e((string) $appeal['resolution_note']) ?></p>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
    </section>
<?php endif; ?>
