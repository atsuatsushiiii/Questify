<?php

declare(strict_types=1);

/**
 * Canonical achievement definitions.
 *
 * The migration seeds these rows so a fresh database is playable, and
 * scripts/seed_achievements.php can re-apply them after a definition change.
 * Keep criteria_type values inside the whitelist understood by
 * App\Services\AchievementService.
 *
 * @return list<array{
 *     achievement_key: string,
 *     name: string,
 *     description: string,
 *     icon: string,
 *     category: string,
 *     criteria_type: string,
 *     threshold: int,
 *     sort_order: int
 * }>
 */
return [
    [
        'achievement_key' => 'first_steps',
        'name' => 'First Steps',
        'description' => 'Complete your first quest.',
        'icon' => 'sparkles',
        'category' => 'milestone',
        'criteria_type' => 'quests_completed',
        'threshold' => 1,
        'sort_order' => 10,
    ],
    [
        'achievement_key' => 'getting_started',
        'name' => 'Getting Started',
        'description' => 'Complete 5 quests.',
        'icon' => 'target',
        'category' => 'milestone',
        'criteria_type' => 'quests_completed',
        'threshold' => 5,
        'sort_order' => 20,
    ],
    [
        'achievement_key' => 'quest_master',
        'name' => 'Quest Master',
        'description' => 'Complete 25 quests.',
        'icon' => 'swords',
        'category' => 'milestone',
        'criteria_type' => 'quests_completed',
        'threshold' => 25,
        'sort_order' => 30,
    ],
    [
        'achievement_key' => 'xp_collector',
        'name' => 'XP Collector',
        'description' => 'Earn 500 XP.',
        'icon' => 'zap',
        'category' => 'xp',
        'criteria_type' => 'xp_total',
        'threshold' => 500,
        'sort_order' => 40,
    ],
    [
        'achievement_key' => 'xp_legend',
        'name' => 'XP Legend',
        'description' => 'Earn 1,000 XP.',
        'icon' => 'trending-up',
        'category' => 'xp',
        'criteria_type' => 'xp_total',
        'threshold' => 1000,
        'sort_order' => 50,
    ],
    [
        'achievement_key' => 'rising_star',
        'name' => 'Rising Star',
        'description' => 'Reach level 5.',
        'icon' => 'star',
        'category' => 'level',
        'criteria_type' => 'level',
        'threshold' => 5,
        'sort_order' => 60,
    ],
    [
        'achievement_key' => 'legendary',
        'name' => 'Legendary',
        'description' => 'Reach level 10.',
        'icon' => 'crown',
        'category' => 'level',
        'criteria_type' => 'level',
        'threshold' => 10,
        'sort_order' => 70,
    ],
    [
        'achievement_key' => 'epic_victory',
        'name' => 'Epic Victory',
        'description' => 'Complete an Epic difficulty quest.',
        'icon' => 'medal',
        'category' => 'difficulty',
        'criteria_type' => 'epic_quests',
        'threshold' => 1,
        'sort_order' => 80,
    ],
    [
        'achievement_key' => 'consistency',
        'name' => 'Consistency',
        'description' => 'Maintain a 7-day streak.',
        'icon' => 'flame',
        'category' => 'streak',
        'criteria_type' => 'longest_streak',
        'threshold' => 7,
        'sort_order' => 90,
    ],
    [
        'achievement_key' => 'unstoppable',
        'name' => 'Unstoppable',
        'description' => 'Maintain a 30-day streak.',
        'icon' => 'calendar-check',
        'category' => 'streak',
        'criteria_type' => 'longest_streak',
        'threshold' => 30,
        'sort_order' => 100,
    ],
];
