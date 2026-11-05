<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Seeds a demo account and a realistic quest history for local demos.
 *
 * Usage: php scripts/seed_demo.php
 *
 * Safe to re-run: the account is created once and quests are only added when
 * the account has no quests yet.
 */

use App\Core\App;
use App\Core\Database;
use App\Models\Category;
use App\Models\Quest;
use App\Models\User;
use App\Services\QuestService;
use App\Support\Progression;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

App::boot($root, false);

$email = 'demo@questify.local';
$username = 'demo';
$password = 'Questify123';

$pdo = Database::connection();

$find = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$find->execute([$email]);
$userId = (int) ($find->fetchColumn() ?: 0);

if ($userId === 0) {
    $userId = User::create([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => 'Demo Adventurer',
        'timezone' => 'Europe/London',
    ]);

    fwrite(STDOUT, "Created demo account #{$userId}.\n");
} else {
    fwrite(STDOUT, "Demo account already exists (#{$userId}).\n");
}

$categories = [];

foreach (Category::allActive() as $category) {
    $categories[(string) $category['slug']] = (int) $category['id'];
}

$quests = [
    [
        'title' => 'Ship the portfolio refresh',
        'description' => 'Rewrite the case studies, add the new screenshots, and publish the updated site.',
        'difficulty' => 'hard',
        'category' => 'work',
        'complete' => true,
    ],
    [
        'title' => 'Run 5K without stopping',
        'description' => 'Easy pace, no walking breaks. Log the route and how it felt.',
        'difficulty' => 'medium',
        'category' => 'fitness',
        'complete' => true,
    ],
    [
        'title' => 'Read 30 pages',
        'description' => 'Continue the current book and note one idea worth keeping.',
        'difficulty' => 'easy',
        'category' => 'study',
        'complete' => true,
    ],
    [
        'title' => 'Deep work block',
        'description' => 'Two hours, no notifications, one meaningful deliverable.',
        'difficulty' => 'epic',
        'category' => 'work',
        'complete' => true,
    ],
    [
        'title' => 'Plan next week',
        'description' => 'Review the week, choose three priorities, and block time for them.',
        'difficulty' => 'medium',
        'category' => 'personal',
        'complete' => false,
    ],
    [
        'title' => 'Meditate for 10 minutes',
        'description' => 'Sit somewhere quiet and focus on the breath. No phone.',
        'difficulty' => 'easy',
        'category' => 'health',
        'complete' => false,
    ],
    [
        'title' => 'Call someone you miss',
        'description' => 'A real conversation, not a text. Ask how they are doing.',
        'difficulty' => 'easy',
        'category' => 'social',
        'complete' => false,
    ],
];

$count = $pdo->prepare('SELECT COUNT(*) FROM quests WHERE user_id = ?');
$count->execute([$userId]);
$existingQuests = (int) $count->fetchColumn();

if ($existingQuests > 0) {
    fwrite(STDOUT, "Quests already exist ({$existingQuests}); leaving them untouched.\n");
} else {
    $service = new QuestService();
    $created = 0;
    $completed = 0;

    foreach ($quests as $quest) {
        $categoryId = $categories[$quest['category']] ?? null;

        if ($categoryId === null) {
            fwrite(STDERR, "Missing category '{$quest['category']}'. Run migrations first.\n");
            exit(1);
        }

        $questId = Quest::create([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $quest['title'],
            'description' => $quest['description'],
            'difficulty' => $quest['difficulty'],
            'xp_reward' => QuestService::rewardFor($quest['difficulty']) ?? 10,
        ]);

        $created++;

        if ($quest['complete']) {
            $result = $service->complete($questId, $userId);

            if ($result['ok'] ?? false) {
                $completed++;
            } else {
                fwrite(STDERR, "Could not complete '{$quest['title']}': " . ($result['message'] ?? 'unknown error') . "\n");
            }
        }
    }

    fwrite(STDOUT, "Created {$created} quests ({$completed} completed).\n");
}

$fresh = User::find($userId);

if ($fresh !== null) {
    $snapshot = Progression::snapshot($fresh);
    $questTotal = $pdo->prepare('SELECT COUNT(*) FROM quests WHERE user_id = ?');
    $questTotal->execute([$userId]);

    fwrite(STDOUT, sprintf(
        "Demo ready: %s / %s\nQuests: %d | XP: %d | Level %d (%s)\n",
        $email,
        $password,
        (int) $questTotal->fetchColumn(),
        $snapshot['xp'],
        $snapshot['level'],
        $snapshot['rank'],
    ));
}
