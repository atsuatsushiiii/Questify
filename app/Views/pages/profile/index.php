<?php
/**
 * Owner-only profile page.
 *
 * @var array<string, mixed> $user
 * @var array<string, mixed> $progression
 * @var int $completedQuests
 * @var array{summary: array{total: int, unlocked: int, locked: int, percent: int}} $overview
 * @var list<array<string, mixed>> $achievementShowcase
 * @var array{current: int, longest: int, heatmap: list<list<array<string, mixed>>>, days: list<array<string, mixed>>} $streak
 * @var array<int, string> $timezones
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $message
 */
$timezone = (string) ($user['timezone'] ?? 'UTC');
$memberSince = format_datetime(isset($user['created_at']) ? (string) $user['created_at'] : null, $timezone, 'j M Y');
$displayName = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
$username = (string) ($user['username'] ?? '');
$avatarPath = isset($user['avatar_path']) ? (string) $user['avatar_path'] : null;
$avatarUrl = avatar_url($avatarPath);
$summary = $overview['summary'];
$visible = ($old['show_on_leaderboard'] ?? '1') === '1';
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
?>
<section class="mx-auto max-w-6xl" data-reveal>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">
                <i data-lucide="user-round" class="h-3.5 w-3.5"></i>
                Adventurer card
            </p>
            <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">My profile</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
                Your identity, public leaderboard settings, streak history, and earned trophies.
            </p>
        </div>
        <a href="<?= e(url('/leaderboard')) ?>" class="btn-app-ghost btn inline-flex text-xs">
            <i data-lucide="crown" class="h-3.5 w-3.5"></i>
            View leaderboard
        </a>
    </div>

    <?php if ($message !== null): ?>
        <?php $type = $errors !== [] ? 'error' : 'info'; $alertMessage = $message; require __DIR__ . '/../../partials/alert.php'; ?>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[0.95fr_1.05fr]">
        <div class="min-w-0 space-y-4">
            <article class="app-panel relative overflow-hidden p-6">
                <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-ember-500/[0.10] blur-[90px]" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-center gap-4">
                    <div class="relative">
                        <?php if ($avatarUrl !== null): ?>
                            <img
                                src="<?= e($avatarUrl) ?>"
                                alt=""
                                class="h-24 w-24 rounded-2xl border border-gold-400/25 object-cover"
                                data-avatar-preview
                            >
                        <?php else: ?>
                            <span class="grid h-24 w-24 place-items-center rounded-2xl border border-gold-400/25 bg-gradient-to-br from-ember-500 to-gold-400 font-display text-3xl font-bold text-white" data-avatar-fallback aria-hidden="true">
                                <?= e($initial) ?>
                            </span>
                            <img src="" alt="" class="hidden h-24 w-24 rounded-2xl border border-gold-400/25 object-cover" data-avatar-preview>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-display text-2xl font-bold tracking-tight text-white"><?= e($displayName) ?></h2>
                        <?php if ($username !== ''): ?>
                            <p class="mt-1 truncate text-sm text-slate-500">@<?= e($username) ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-400/20 bg-gold-400/[0.08] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-gold-300">
                                <i data-lucide="shield-check" class="h-3 w-3"></i>
                                <?= e((string) $progression['rank']) ?> · Level <?= e((string) $progression['level']) ?>
                            </span>
                            <?php if ($visible): ?>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/20 bg-emerald-500/[0.08] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-emerald-300">
                                    <i data-lucide="eye" class="h-3 w-3"></i>
                                    Public
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.03] px-2.5 py-1 text-[9px] font-semibold uppercase tracking-wider text-slate-400">
                                    <i data-lucide="eye-off" class="h-3 w-3"></i>
                                    Hidden
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($memberSince !== null): ?>
                            <p class="mt-3 inline-flex items-center gap-1.5 text-[10px] text-slate-600">
                                <i data-lucide="calendar-days" class="h-3 w-3"></i>
                                Member since <?= e($memberSince) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Total XP</p>
                        <p class="mt-1 font-display text-lg font-bold text-white"><?= e(number_format((int) $progression['xp'])) ?></p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Level</p>
                        <p class="mt-1 font-display text-lg font-bold text-gold-300"><?= e((string) $progression['level']) ?></p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Completed</p>
                        <p class="mt-1 font-display text-lg font-bold text-white"><?= e((string) $completedQuests) ?></p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Current streak</p>
                        <p class="mt-1 font-display text-lg font-bold text-ember-300"><?= e((string) $streak['current']) ?></p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Longest streak</p>
                        <p class="mt-1 font-display text-lg font-bold text-white"><?= e((string) $streak['longest']) ?></p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3">
                        <p class="text-[9px] uppercase tracking-wider text-slate-600">Achievements</p>
                        <p class="mt-1 font-display text-lg font-bold text-gold-300"><?= e((string) $summary['unlocked']) ?><span class="text-xs text-slate-600">/<?= e((string) $summary['total']) ?></span></p>
                    </div>
                </div>
            </article>

            <article class="app-panel p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Showcase</p>
                        <h2 class="mt-1 font-display text-sm font-semibold text-white">Recent achievements</h2>
                    </div>
                    <a href="<?= e(url('/achievements')) ?>" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gold-300 transition hover:text-gold-200">
                        All trophies
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </a>
                </div>

                <?php if ($achievementShowcase === []): ?>
                    <div class="mt-4 rounded-xl border border-dashed border-white/10 bg-white/[0.01] px-4 py-6 text-center">
                        <p class="text-xs font-semibold text-slate-300">No badges yet</p>
                        <p class="mt-1 text-[11px] text-slate-500">Complete a quest and your first achievement will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <?php foreach ($achievementShowcase as $achievement): ?>
                            <a href="<?= e(url('/achievements')) ?>" class="flex items-center gap-3 rounded-xl border border-gold-400/15 bg-gold-400/[0.05] px-3 py-2.5 transition hover:border-gold-400/30 hover:bg-gold-400/[0.08]">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-gold-400/25 bg-gold-400/10 text-gold-300">
                                    <i data-lucide="<?= e((string) $achievement['icon']) ?>" class="h-4 w-4"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-200"><?= e((string) $achievement['name']) ?></p>
                                    <p class="truncate text-[10px] text-slate-500"><?= e((string) format_datetime((string) $achievement['unlocked_at'], $timezone, 'j M Y')) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="app-panel p-5">
                <?php
                $heatmap = $streak['heatmap'];
                $current = (int) $streak['current'];
                $longest = (int) $streak['longest'];
                require __DIR__ . '/../../partials/streak-heatmap.php';
                ?>
            </article>
        </div>

        <div class="min-w-0 space-y-4">
            <article class="app-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Identity</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Profile details</h2>
                    </div>
                    <span class="grid h-10 w-10 place-items-center rounded-xl border border-ember-400/20 bg-ember-500/10 text-ember-300">
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                    </span>
                </div>

                <form method="POST" action="<?= e(url('/profile')) ?>" class="mt-5 space-y-5">
                    <?= csrf_field() ?>

                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label for="display_name" class="field-label mb-2">Display name</label>
                            <span class="mb-2 text-[10px] text-slate-600"><span data-display-name-count><?= e((string) mb_strlen((string) ($old['display_name'] ?? ''))) ?></span>/50</span>
                        </div>
                        <input
                            id="display_name"
                            name="display_name"
                            type="text"
                            class="app-field <?= isset($errors['display_name']) ? 'is-invalid' : '' ?>"
                            value="<?= e((string) ($old['display_name'] ?? '')) ?>"
                            minlength="2"
                            maxlength="50"
                            required
                            autocomplete="nickname"
                            data-display-name
                            aria-invalid="<?= isset($errors['display_name']) ? 'true' : 'false' ?>"
                            aria-describedby="<?= isset($errors['display_name']) ? 'display_name-error' : 'display_name-help' ?>"
                        >
                        <p id="display_name-help" class="mt-1.5 text-[11px] text-slate-600">Letters, numbers, spaces, apostrophes, hyphens, periods, and underscores.</p>
                        <?php $field = 'display_name'; require __DIR__ . '/../../partials/field-error.php'; ?>
                    </div>

                    <div>
                        <label for="timezone" class="field-label">Timezone</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600">
                                <i data-lucide="clock" class="h-4 w-4"></i>
                            </span>
                            <select
                                id="timezone"
                                name="timezone"
                                class="app-select appearance-none pl-10 pr-10 <?= isset($errors['timezone']) ? 'is-invalid' : '' ?>"
                                required
                                aria-invalid="<?= isset($errors['timezone']) ? 'true' : 'false' ?>"
                                aria-describedby="<?= isset($errors['timezone']) ? 'timezone-error' : 'timezone-help' ?>"
                            >
                                <?php foreach ($timezones as $zone): ?>
                                    <option value="<?= e($zone) ?>" <?= ($old['timezone'] ?? 'UTC') === $zone ? 'selected' : '' ?>>
                                        <?= e(str_replace('_', ' ', $zone)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500">
                                <i data-lucide="chevron-down" class="h-4 w-4"></i>
                            </span>
                        </div>
                        <p id="timezone-help" class="mt-1.5 text-[11px] text-slate-600">Used for streak days and daily quest timing. Past streak history is not rewritten.</p>
                        <?php $field = 'timezone'; require __DIR__ . '/../../partials/field-error.php'; ?>
                    </div>

                    <div>
                        <span class="field-label">Leaderboard visibility</span>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-white/[0.02] p-3.5 transition hover:border-gold-400/20 hover:bg-white/[0.03]">
                            <input
                                type="checkbox"
                                name="show_on_leaderboard"
                                value="1"
                                class="mt-0.5 h-4 w-4 rounded border-white/20 bg-white/[0.03] accent-ember-500"
                                <?= $visible ? 'checked' : '' ?>
                            >
                            <span>
                                <span class="block text-xs font-semibold text-slate-200">Show me on public leaderboards</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">Your display name, username, avatar, level, rank, and XP become visible. Your email is never shown.</span>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-app-primary w-full text-sm">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        Save profile
                    </button>
                </form>
            </article>

            <article class="app-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Portrait</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-white">Profile picture</h2>
                    </div>
                    <span class="grid h-10 w-10 place-items-center rounded-xl border border-gold-400/20 bg-gold-400/10 text-gold-300">
                        <i data-lucide="image-plus" class="h-4 w-4"></i>
                    </span>
                </div>

                <form method="POST" action="<?= e(url('/profile/avatar')) ?>" enctype="multipart/form-data" class="mt-4 space-y-3">
                    <?= csrf_field() ?>
                    <label for="avatar" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-white/15 bg-white/[0.02] px-4 py-5 text-center transition hover:border-gold-400/30 hover:bg-white/[0.03]">
                        <span class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/[0.03] text-slate-400">
                            <i data-lucide="upload" class="h-4 w-4"></i>
                        </span>
                        <span class="mt-2 text-xs font-semibold text-slate-200" data-avatar-name>Choose an image</span>
                        <span class="mt-1 text-[10px] text-slate-600">JPEG, PNG, or WebP · max 2 MB · 64×64 to 4096×4096</span>
                    </label>
                    <input
                        id="avatar"
                        name="avatar"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        data-avatar-input
                        required
                    >
                    <button type="submit" class="btn btn-app-ghost w-full text-sm">
                        <i data-lucide="image-plus" class="h-4 w-4"></i>
                        Upload picture
                    </button>
                </form>

                <?php if ($avatarUrl !== null): ?>
                    <form method="POST" action="<?= e(url('/profile/avatar/remove')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn w-full border border-rose-400/20 bg-rose-500/[0.06] text-xs text-rose-200 transition hover:bg-rose-500/[0.12]">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            Remove picture
                        </button>
                    </form>
                <?php endif; ?>

                <p class="mt-3 flex items-start gap-2 text-[10px] leading-relaxed text-slate-600">
                    <i data-lucide="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                    Uploads are checked by real image content, renamed server-side, and served from a directory that cannot execute scripts.
                </p>
            </article>
        </div>
    </div>
</section>
