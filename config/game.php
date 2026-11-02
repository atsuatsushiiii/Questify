<?php

declare(strict_types=1);

/**
 * Game economy rules. Levels, XP and streak thresholds live here so the
 * frontend and backend can never disagree about the numbers.
 */
return [
    'xp_per_difficulty' => [
        'easy' => 10,
        'medium' => 25,
        'hard' => 50,
        'epic' => 100,
    ],

    // Total XP required to reach level L = base * (L - 1)^2
    'level_curve_base' => 100,
    'max_level' => 100,

    'ranks' => [
        1 => 'Novice',
        5 => 'Adventurer',
        10 => 'Champion',
        20 => 'Legend',
        35 => 'Mythic',
    ],

    'streak_milestones' => [3, 7, 14, 30, 60, 100],

    'difficulties' => ['easy', 'medium', 'hard', 'epic'],
    'recurrences' => ['once', 'daily', 'weekly'],
];
