<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Canonical quest categories.
 *
 * The migration seeds these rows so the quest board has a useful default
 * taxonomy. Admins can rename, recolor, deactivate, or add categories later.
 *
 * @return list<array{
 *     name: string,
 *     slug: string,
 *     color: string,
 *     icon: string,
 *     sort_order: int
 * }>
 */
return [
    [
        'name' => 'Work',
        'slug' => 'work',
        'color' => '#FACC15',
        'icon' => 'briefcase',
        'sort_order' => 10,
    ],
    [
        'name' => 'Study',
        'slug' => 'study',
        'color' => '#F87171',
        'icon' => 'book-open',
        'sort_order' => 20,
    ],
    [
        'name' => 'Health',
        'slug' => 'health',
        'color' => '#34D399',
        'icon' => 'heart-pulse',
        'sort_order' => 30,
    ],
    [
        'name' => 'Fitness',
        'slug' => 'fitness',
        'color' => '#FB923C',
        'icon' => 'dumbbell',
        'sort_order' => 40,
    ],
    [
        'name' => 'Personal',
        'slug' => 'personal',
        'color' => '#FBBF24',
        'icon' => 'user-round',
        'sort_order' => 50,
    ],
    [
        'name' => 'Chores',
        'slug' => 'chores',
        'color' => '#94A3B8',
        'icon' => 'home',
        'sort_order' => 60,
    ],
    [
        'name' => 'Creative',
        'slug' => 'creative',
        'color' => '#FCA5A5',
        'icon' => 'palette',
        'sort_order' => 70,
    ],
    [
        'name' => 'Social',
        'slug' => 'social',
        'color' => '#FDE68A',
        'icon' => 'users',
        'sort_order' => 80,
    ],
];
