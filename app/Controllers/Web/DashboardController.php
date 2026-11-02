<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
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

        return view('pages/dashboard', [
            'title' => 'Command center — Questify',
            'description' => 'Your Questify level, XP, streak, and quest overview.',
            'user' => $user,
            'progression' => $progression,
            'level' => $progression['level'],
            'progress' => $progression['progress'],
            'xpToNext' => $progression['xp_to_next'],
            'rank' => $progression['rank'],
            'streak' => max(0, (int) ($user['current_streak'] ?? 0)),
            'longestStreak' => max(0, (int) ($user['longest_streak'] ?? 0)),
            'today' => $this->today($timezone),
            'memberSince' => $this->formatDate((string) $user['created_at'], $timezone),
            'lastLogin' => $this->formatDate(
                isset($user['last_login_at']) ? (string) $user['last_login_at'] : null,
                $timezone,
            ),
            // Quest tables arrive in Phase 3. Null (not zero) tells the view to
            // render an honest empty state instead of a fabricated statistic.
            'completedQuests' => null,
            'activeQuests' => null,
            'recentActivity' => [],
            'achievementPreview' => [],
            'leaderboardEntries' => [],
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
}
