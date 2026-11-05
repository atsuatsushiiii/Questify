<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Recurrence;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Period keys are the uniqueness boundary for recurring XP awards, so their
 * timezone handling and rollover boundaries must stay deterministic.
 */
final class RecurrenceTest extends TestCase
{
    public function testOptionsAndLabelsCoverEveryCadence(): void
    {
        self::assertSame(
            ['none', 'daily', 'weekly', 'monthly'],
            Recurrence::values(),
        );
        self::assertSame('One-off', Recurrence::label('none'));
        self::assertSame('Daily', Recurrence::label('daily'));
        self::assertSame('Weekly', Recurrence::label('weekly'));
        self::assertSame('Monthly', Recurrence::label('monthly'));
        self::assertSame('Done today', Recurrence::doneLabel('daily'));
        self::assertSame('Done this week', Recurrence::doneLabel('weekly'));
        self::assertSame('Done this month', Recurrence::doneLabel('monthly'));
    }

    public function testOneOffQuestsKeepTheOnceKey(): void
    {
        self::assertSame('once', Recurrence::periodKey('none'));
        self::assertFalse(Recurrence::isRecurring('none'));
        self::assertTrue(Recurrence::isRecurring('daily'));
    }

    public function testDailyKeyUsesTheAccountTimezone(): void
    {
        $instant = new DateTimeImmutable('2026-11-06 03:30:00', new DateTimeZone('UTC'));

        self::assertSame('2026-11-06', Recurrence::periodKey('daily', $instant, 'UTC'));
        // 03:30 UTC is still the previous evening in New York.
        self::assertSame('2026-11-05', Recurrence::periodKey('daily', $instant, 'America/New_York'));
        // Tokyo has already rolled over to the next day.
        self::assertSame('2026-11-06', Recurrence::periodKey('daily', $instant, 'Asia/Tokyo'));
    }

    public function testDailyBoundaryResetsAtLocalMidnight(): void
    {
        $before = new DateTimeImmutable('2026-11-05 23:59:59', new DateTimeZone('America/New_York'));
        $after = new DateTimeImmutable('2026-11-06 00:00:01', new DateTimeZone('America/New_York'));

        self::assertSame('2026-11-05', Recurrence::periodKey('daily', $before, 'America/New_York'));
        self::assertSame('2026-11-06', Recurrence::periodKey('daily', $after, 'America/New_York'));
    }

    public function testWeeklyKeyUsesIsoWeekNumbers(): void
    {
        $newYear = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));

        self::assertSame('2026-W01', Recurrence::periodKey('weekly', $newYear, 'UTC'));
    }

    public function testMonthlyKeyUsesTheLocalMonth(): void
    {
        $instant = new DateTimeImmutable('2026-11-01 02:00:00', new DateTimeZone('UTC'));

        self::assertSame('2026-11', Recurrence::periodKey('monthly', $instant, 'UTC'));
        // 02:00 UTC on the first is still October in New York.
        self::assertSame('2026-10', Recurrence::periodKey('monthly', $instant, 'America/New_York'));
    }

    public function testInvalidTimezoneFallsBackToUtc(): void
    {
        $instant = new DateTimeImmutable('2026-11-06 12:00:00', new DateTimeZone('UTC'));

        self::assertSame(
            Recurrence::periodKey('daily', $instant, 'UTC'),
            Recurrence::periodKey('daily', $instant, 'Not/AZone'),
        );
    }

    public function testUnknownCadenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Recurrence::periodKey('hourly');
    }

    public function testDoneForCurrentPeriod(): void
    {
        $now = new DateTimeImmutable('2026-11-06 12:00:00', new DateTimeZone('UTC'));

        self::assertTrue(Recurrence::isDoneForCurrentPeriod('daily', '2026-11-06', 'UTC', $now));
        self::assertFalse(Recurrence::isDoneForCurrentPeriod('daily', '2026-11-05', 'UTC', $now));
        self::assertFalse(Recurrence::isDoneForCurrentPeriod('daily', null, 'UTC', $now));
        self::assertFalse(Recurrence::isDoneForCurrentPeriod('none', 'once', 'UTC', $now));
    }
}
