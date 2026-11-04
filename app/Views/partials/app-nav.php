<?php

declare(strict_types=1);

/**
 * Shared application navigation definition.
 *
 * `phase` is null for shipped pages; a number marks a destination that is
 * intentionally a preview until that phase lands.
 *
 * @return array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string, phase: int|null}>}>
 */
return [
    [
        'label' => 'Adventure',
        'items' => [
            ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'layout-dashboard', 'phase' => null],
            ['label' => 'My Quests', 'href' => '/quests', 'icon' => 'scroll-text', 'phase' => null],
            ['label' => 'Achievements', 'href' => '/achievements', 'icon' => 'trophy', 'phase' => null],
            ['label' => 'Leaderboard', 'href' => '/leaderboard', 'icon' => 'crown', 'phase' => null],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['label' => 'My Profile', 'href' => '/profile', 'icon' => 'user-round', 'phase' => null],
            ['label' => 'Notifications', 'href' => '/notifications', 'icon' => 'bell', 'phase' => null],
            ['label' => 'Settings', 'href' => '/settings', 'icon' => 'settings', 'phase' => null],
        ],
    ],
];
