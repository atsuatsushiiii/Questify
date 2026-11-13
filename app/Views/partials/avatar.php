<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.
/**
 * Avatar with an initial-letter fallback.
 *
 * @var string|null $avatarPath
 * @var string $name
 * @var string $size xs|sm|md|lg|xl
 */
$avatarClasses = match ($size) {
    'xs' => 'h-8 w-8 rounded-sm text-[10px]',
    'sm' => 'h-9 w-9 rounded-sm text-[11px]',
    'lg' => 'h-20 w-20 rounded-sm text-2xl',
    'xl' => 'h-24 w-24 rounded-sm text-3xl',
    default => 'h-10 w-10 rounded-sm text-sm',
};
$avatarUrl = avatar_url($avatarPath ?? null);
$avatarInitial = mb_strtoupper(mb_substr($name !== '' ? $name : 'Adventurer', 0, 1));
?>
<?php if ($avatarUrl !== null): ?>
    <img
        src="<?= e($avatarUrl) ?>"
        alt=""
        class="<?= e($avatarClasses) ?> shrink-0 border border-line object-cover"
        loading="lazy"
        decoding="async"
    >
<?php else: ?>
    <span
        class="<?= e($avatarClasses) ?> grid shrink-0 place-items-center border border-brand-500 bg-brand-400 font-bold text-[#14100B]"
        aria-hidden="true"
    ><?= e($avatarInitial) ?></span>
<?php endif; ?>
