<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Models\StreakActivity;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Streak rules. A streak is maintained by at least one qualifying quest
 * completion per local calendar day; multiple completions on the same day
 * count once.
 */
final class StreakService
{
    /**
     * The user's local calendar date for a given instant.
     */
    public static function localDate(?DateTimeImmutable $now, string $timezone): string
    {
        $zone = self::zone($timezone);
        $instant = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $instant->setTimezone($zone)->format('Y-m-d');
    }

    /**
     * Called inside the quest completion transaction with the locked user row.
     *
     * @param array<string, mixed> $user
     * @return array{current: int, longest: int, date: string, milestone: int|null, incremented: bool}
     */
    public static function recordCompletion(
        PDO $db,
        array $user,
        ?int $questId,
        ?DateTimeImmutable $now = null,
    ): array {
        $userId = (int) $user['id'];
        $localDate = self::localDate($now, (string) ($user['timezone'] ?? 'UTC'));
        $current = max(0, (int) ($user['current_streak'] ?? 0));
        $longest = max(0, (int) ($user['longest_streak'] ?? 0));
        $last = isset($user['last_streak_date'])
            ? (string) $user['last_streak_date']
            : null;
        $incremented = false;

        if ($last !== $localDate) {
            $today = new DateTimeImmutable($localDate, new DateTimeZone('UTC'));
            $yesterday = $today->modify('-1 day')->format('Y-m-d');

            $current = $last === $yesterday ? $current + 1 : 1;
            $longest = max($longest, $current);
            $incremented = true;
        }

        User::updateStreak($db, $userId, $current, $longest, $localDate);
        StreakActivity::record($db, $userId, $localDate, $questId);

        $milestone = $incremented && in_array($current, self::milestones(), true) ? $current : null;

        return [
            'current' => $current,
            'longest' => $longest,
            'date' => $localDate,
            'milestone' => $milestone,
            'incremented' => $incremented,
        ];
    }

    /**
     * A stored current streak decays to zero when the last qualifying day is
     * older than yesterday. Reads never mutate the row.
     *
     * @param array<string, mixed> $user
     */
    public static function effectiveCurrent(array $user, ?DateTimeImmutable $now = null): int
    {
        $current = max(0, (int) ($user['current_streak'] ?? 0));
        $last = isset($user['last_streak_date'])
            ? (string) $user['last_streak_date']
            : null;

        if ($last === null) {
            return 0;
        }

        $today = self::localDate($now, (string) ($user['timezone'] ?? 'UTC'));

        if ($last === $today) {
            return $current;
        }

        $yesterday = (new DateTimeImmutable($today, new DateTimeZone('UTC')))
            ->modify('-1 day')
            ->format('Y-m-d');

        return $last === $yesterday ? $current : 0;
    }

    /**
     * 12-week GitHub-style history. Each week is a column of seven local days.
     *
     * @return list<list<array{date: string, label: string, active: bool, today: bool, future: bool}>>
     */
    public static function heatmap(int $userId, string $timezone, int $weeks = 12): array
    {
        $weeks = max(4, min(26, $weeks));
        $zone = self::zone($timezone);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($zone);
        $todayKey = $today->format('Y-m-d');
        $start = $today->modify('monday this week')->modify('-' . ($weeks - 1) . ' weeks')->setTime(0, 0);
        $end = $start->modify('+' . ($weeks * 7 - 1) . ' days');
        $active = array_flip(StreakActivity::datesForUser(
            $userId,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ));

        $columns = [];

        for ($week = 0; $week < $weeks; $week++) {
            $days = [];

            for ($day = 0; $day < 7; $day++) {
                $date = $start->modify('+' . ($week * 7 + $day) . ' days');
                $key = $date->format('Y-m-d');

                $days[] = [
                    'date' => $key,
                    'label' => $date->format('D j M'),
                    'active' => isset($active[$key]),
                    'today' => $key === $todayKey,
                    'future' => $key > $todayKey,
                ];
            }

            $columns[] = $days;
        }

        return $columns;
    }

    /**
     * @return list<array{date: string, label: string, active: bool, today: bool}>
     */
    public static function lastSevenDays(int $userId, string $timezone): array
    {
        $zone = self::zone($timezone);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($zone);
        $start = $today->modify('-6 days');
        $active = array_flip(StreakActivity::datesForUser(
            $userId,
            $start->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));

        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $start->modify('+' . $offset . ' days');
            $key = $date->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'label' => $date->format('D'),
                'active' => isset($active[$key]),
                'today' => $key === $today->format('Y-m-d'),
            ];
        }

        return $days;
    }

    /** @return list<int> */
    public static function milestones(): array
    {
        $configured = (array) config('game.streak_milestones', []);
        $milestones = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $configured),
            static fn (int $value): bool => $value > 0,
        )));
        sort($milestones);

        return $milestones;
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
