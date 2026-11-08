<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Shared application navigation definition.
 *
 * `phase` is null for shipped pages; a number marks a destination that is
 * intentionally a preview until that phase lands. The admin section is only
 * rendered for accounts with the admin role.
 *
 * @return array<int, array{label: string, items: array<int, array{label: string, href: string, icon: string, phase: int|null}>}>
 */
$user = auth_user();
$nav = [
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

if (($user['role'] ?? '') === 'admin') {
    $nav[] = [
        'label' => 'Administration',
        'items' => [
            ['label' => 'Overview', 'href' => '/admin', 'icon' => 'gauge', 'phase' => null],
            ['label' => 'Users', 'href' => '/admin/users', 'icon' => 'users', 'phase' => null],
            ['label' => 'Quests', 'href' => '/admin/quests', 'icon' => 'scroll-text', 'phase' => null],
            ['label' => 'Categories', 'href' => '/admin/categories', 'icon' => 'folder-tree', 'phase' => null],
            ['label' => 'Achievements', 'href' => '/admin/achievements', 'icon' => 'award', 'phase' => null],
            ['label' => 'Audit log', 'href' => '/admin/audit', 'icon' => 'history', 'phase' => null],
        ],
    ];
}

return $nav;
