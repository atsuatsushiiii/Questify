<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StreakService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Streak day boundaries are timezone-dependent and directly control rewards,
 * so they are pinned to fixed instants here.
 */
final class StreakServiceTest extends TestCase
{
    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    public function testLocalDateUsesTheUsersTimezone(): void
    {
        $instant = self::utc('2026-06-15 23:30:00');

        $this->assertSame('2026-06-15', StreakService::localDate($instant, 'UTC'));
        $this->assertSame('2026-06-16', StreakService::localDate($instant, 'Asia/Tokyo'));
        $this->assertSame('2026-06-16', StreakService::localDate($instant, 'Europe/London'));
        $this->assertSame('2026-06-15', StreakService::localDate($instant, 'America/New_York'));
    }

    public function testInvalidTimezoneFallsBackToUtc(): void
    {
        $this->assertSame(
            '2026-06-15',
            StreakService::localDate(self::utc('2026-06-15 12:00:00'), 'Mars/Olympus'),
        );
    }

    public function testCurrentStreakSurvivesACompletionToday(): void
    {
        $user = [
            'current_streak' => 4,
            'last_streak_date' => '2026-06-15',
            'timezone' => 'UTC',
        ];

        $this->assertSame(4, StreakService::effectiveCurrent($user, self::utc('2026-06-15 18:00:00')));
    }

    public function testCurrentStreakSurvivesUntilTheEndOfYesterday(): void
    {
        $user = [
            'current_streak' => 4,
            'last_streak_date' => '2026-06-14',
            'timezone' => 'UTC',
        ];

        $this->assertSame(4, StreakService::effectiveCurrent($user, self::utc('2026-06-15 08:00:00')));
    }

    public function testCurrentStreakExpiresAfterAMissedDay(): void
    {
        $user = [
            'current_streak' => 9,
            'last_streak_date' => '2026-06-13',
            'timezone' => 'UTC',
        ];

        $this->assertSame(0, StreakService::effectiveCurrent($user, self::utc('2026-06-15 08:00:00')));
    }

    public function testCurrentStreakIsZeroWithoutAHistoryRow(): void
    {
        $user = ['current_streak' => 12, 'last_streak_date' => null, 'timezone' => 'UTC'];

        $this->assertSame(0, StreakService::effectiveCurrent($user, self::utc('2026-06-15 08:00:00')));
    }

    public function testYesterdayIsResolvedInTheUsersTimezone(): void
    {
        // 23:30 UTC is already the next day in Tokyo, so a completion on the
        // Tokyo-local 15th is "yesterday" at that instant.
        $user = [
            'current_streak' => 3,
            'last_streak_date' => '2026-06-15',
            'timezone' => 'Asia/Tokyo',
        ];

        $this->assertSame(3, StreakService::effectiveCurrent($user, self::utc('2026-06-15 23:30:00')));
    }

    public function testMilestonesAreSortedUniqueAndPositive(): void
    {
        $milestones = StreakService::milestones();

        $this->assertSame([3, 7, 14, 30, 60, 100], $milestones);
        $this->assertSame($milestones, array_values(array_unique($milestones)));
    }
}
