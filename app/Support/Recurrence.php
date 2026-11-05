<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/**
 * Recurring quest cadence and period keys.
 *
 * A period key is derived from the account's timezone so a "daily" quest
 * resets at the user's local midnight rather than at UTC midnight. Keys are
 * also the uniqueness boundary in the XP ledger, so they must stay stable and
 * sortable.
 */
final class Recurrence
{
    public const NONE = 'none';
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::NONE => 'One-off',
            self::DAILY => 'Every day',
            self::WEEKLY => 'Every week',
            self::MONTHLY => 'Every month',
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function isRecurring(string $recurrence): bool
    {
        return $recurrence !== self::NONE;
    }

    public static function label(string $recurrence): string
    {
        return match ($recurrence) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            default => 'One-off',
        };
    }

    public static function periodNoun(string $recurrence): string
    {
        return match ($recurrence) {
            self::DAILY => 'day',
            self::WEEKLY => 'week',
            self::MONTHLY => 'month',
            default => 'once',
        };
    }

    public static function doneLabel(string $recurrence): string
    {
        return match ($recurrence) {
            self::DAILY => 'Done today',
            self::WEEKLY => 'Done this week',
            self::MONTHLY => 'Done this month',
            default => 'Completed',
        };
    }

    public static function resetLabel(string $recurrence): string
    {
        return match ($recurrence) {
            self::DAILY => 'Resets tomorrow at midnight.',
            self::WEEKLY => 'Resets next week.',
            self::MONTHLY => 'Resets next month.',
            default => '',
        };
    }

    /**
     * Stable key for the period containing $now in the user's timezone.
     *
     * One-off quests use "once", which keeps the original ledger uniqueness.
     */
    public static function periodKey(
        string $recurrence,
        ?DateTimeImmutable $now = null,
        string $timezone = 'UTC',
    ): string {
        if ($recurrence === self::NONE) {
            return 'once';
        }

        if (!in_array($recurrence, [self::DAILY, self::WEEKLY, self::MONTHLY], true)) {
            throw new InvalidArgumentException(sprintf('Unknown recurrence [%s].', $recurrence));
        }

        $zone = self::zone($timezone);
        $instant = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $local = $instant->setTimezone($zone);

        return match ($recurrence) {
            self::DAILY => $local->format('Y-m-d'),
            self::WEEKLY => $local->format('o-\WW'),
            self::MONTHLY => $local->format('Y-m'),
        };
    }

    /**
     * True when the quest has already been paid for the period containing
     * $now in the account timezone. One-off quests always return false; their
     * status carries the completed flag instead.
     */
    public static function isDoneForCurrentPeriod(
        string $recurrence,
        ?string $lastPeriod,
        string $timezone = 'UTC',
        ?DateTimeImmutable $now = null,
    ): bool {
        if (!self::isRecurring($recurrence)) {
            return false;
        }

        if ($lastPeriod === null || $lastPeriod === '') {
            return false;
        }

        return $lastPeriod === self::periodKey($recurrence, $now, $timezone);
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
