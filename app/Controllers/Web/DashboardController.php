<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Quest;
use App\Models\XpTransaction;
use App\Services\AchievementService;
use App\Services\LeaderboardService;
use App\Services\StreakService;
use App\Support\Progression;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        $progression = Progression::snapshot($user);
        $timezone = (string) ($user['timezone'] ?? 'UTC');
        $userId = (int) $user['id'];
        $counts = Quest::countsForUser($userId);
        $achievementOverview = AchievementService::overview($userId, $user);
        $leaderboard = LeaderboardService::board('all', 1, 5, $user);
        $currentStreak = StreakService::effectiveCurrent($user);
        $recentActivity = [];

        foreach (XpTransaction::recentForUser($userId, 6) as $entry) {
            $timestamp = (string) ($entry['created_at'] ?? '');

            $recentActivity[] = [
                'title' => (string) ($entry['quest_title'] ?? 'Quest'),
                'quest_id' => isset($entry['quest_id']) ? (int) $entry['quest_id'] : null,
                'xp' => (int) ($entry['amount'] ?? 0),
                'level_before' => (int) ($entry['level_before'] ?? 1),
                'level_after' => (int) ($entry['level_after'] ?? 1),
                'completed_at' => $this->formatDateTime($timestamp, $timezone),
                'iso' => iso_datetime($timestamp),
            ];
        }

        return view('pages/dashboard', [
            'title' => 'Command center — Questify',
            'description' => 'Your Questify level, XP, quest log, and recent activity.',
            'user' => $user,
            'progression' => $progression,
            'level' => $progression['level'],
            'progress' => $progression['progress'],
            'xpToNext' => $progression['xp_to_next'],
            'rank' => $progression['rank'],
            'streak' => $currentStreak,
            'longestStreak' => max($currentStreak, (int) ($user['longest_streak'] ?? 0)),
            'today' => $this->today($timezone),
            'memberSince' => $this->formatDate((string) $user['created_at'], $timezone),
            'lastLogin' => $this->formatDate(
                isset($user['last_login_at']) ? (string) $user['last_login_at'] : null,
                $timezone,
            ),
            'completedQuests' => $counts['completed'],
            'activeQuests' => $counts['active'],
            'recentActivity' => $recentActivity,
            'achievementPreview' => AchievementService::preview($achievementOverview, 4),
            'achievementSummary' => $achievementOverview['summary'],
            'leaderboardEntries' => $leaderboard['rows'],
            'leaderboardTotal' => $leaderboard['total'],
            'viewerRank' => $leaderboard['viewer_rank'],
            'streakDays' => StreakService::lastSevenDays($userId, $timezone),
        ], 'layouts/app');
    }

    private function today(string $timezone): string
    {
        try {
            return (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('l, j F');
        } catch (Throwable) {
            return (new DateTimeImmutable('now'))->format('l, j F');
        }
    }

    private function formatDate(?string $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $date = $date->setTimezone(new DateTimeZone($timezone));

            return $date->format('j M Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function formatDateTime(?string $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $date = $date->setTimezone(new DateTimeZone($timezone));

            return $date->format('j M Y, H:i');
        } catch (Throwable) {
            return null;
        }
    }
}
