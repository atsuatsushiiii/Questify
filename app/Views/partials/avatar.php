<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Avatar with an initial-letter fallback.
 *
 * @var string|null $avatarPath
 * @var string $name
 * @var string $size xs|sm|md|lg|xl
 */
$avatarSize = $size ?? 'md';
$avatarClasses = match ($avatarSize) {
    'xs' => 'h-8 w-8 rounded-lg text-[10px]',
    'sm' => 'h-9 w-9 rounded-lg text-[11px]',
    'lg' => 'h-20 w-20 rounded-2xl text-2xl',
    'xl' => 'h-24 w-24 rounded-2xl text-3xl',
    default => 'h-10 w-10 rounded-xl text-sm',
};
$avatarUrl = avatar_url($avatarPath ?? null);
$avatarInitial = mb_strtoupper(mb_substr($name !== '' ? $name : 'Adventurer', 0, 1));
?>
<?php if ($avatarUrl !== null): ?>
    <img
        src="<?= e($avatarUrl) ?>"
        alt=""
        class="<?= e($avatarClasses) ?> shrink-0 border border-white/10 object-cover"
        loading="lazy"
        decoding="async"
    >
<?php else: ?>
    <span
        class="<?= e($avatarClasses) ?> grid shrink-0 place-items-center border border-gold-400/20 bg-gradient-to-br from-ember-500 to-gold-400 font-bold text-white"
        aria-hidden="true"
    ><?= e($avatarInitial) ?></span>
<?php endif; ?>
