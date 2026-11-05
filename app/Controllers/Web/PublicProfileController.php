<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Quest;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\StreakService;
use App\Support\Progression;

/**
 * Guest-facing public profile.
 *
 * Only active accounts that opted in through `users.show_profile` resolve.
 * Hidden and suspended accounts deliberately return the same 404 as an unknown
 * username so the endpoint cannot be used to enumerate accounts.
 */
final class PublicProfileController
{
    public function show(Request $request, string $username): Response
    {
        $username = trim($username);
        $user = $username === '' ? null : User::findPublicByUsername($username);

        if ($user === null) {
            throw HttpException::notFound();
        }

        $userId = (int) $user['id'];
        $progression = Progression::snapshot($user);
        $counts = Quest::countsForUser($userId);
        $overview = AchievementService::overview($userId, $user);
        $currentStreak = StreakService::effectiveCurrent($user);
        $longestStreak = max($currentStreak, (int) ($user['longest_streak'] ?? 0));
        $unlocked = array_values(array_filter(
            $overview['achievements'],
            static fn (array $achievement): bool => (bool) ($achievement['unlocked'] ?? false),
        ));

        usort(
            $unlocked,
            static fn (array $a, array $b): int => strcmp(
                (string) ($b['unlocked_at'] ?? ''),
                (string) ($a['unlocked_at'] ?? ''),
            ),
        );

        $displayName = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
        $memberSince = format_datetime(
            isset($user['created_at']) ? (string) $user['created_at'] : null,
            'UTC',
            'F Y',
        );

        return view('pages/profile/public', [
            'title' => $displayName . ' (@' . (string) $user['username'] . ') — Questify',
            'description' => $displayName . ' is a Level ' . (int) $progression['level']
                . ' ' . $progression['rank'] . ' adventurer on Questify with '
                . number_format((int) $progression['xp']) . ' XP, '
                . (int) $counts['completed'] . ' completed quests, and '
                . (int) $overview['summary']['unlocked'] . ' achievements.',
            'account' => $user,
            'progression' => $progression,
            'completedQuests' => (int) $counts['completed'],
            'achievements' => $unlocked,
            'achievementSummary' => $overview['summary'],
            'streak' => [
                'current' => $currentStreak,
                'longest' => $longestStreak,
            ],
            'memberSince' => $memberSince,
        ]);
    }
}
