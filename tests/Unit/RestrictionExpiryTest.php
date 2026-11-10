<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Timed read-only limits must behave like timed suspensions: the stored
 * timestamp decides whether the account is still limited, and an expired
 * limit is treated as absent even before the lazy database cleanup runs.
 */
final class RestrictionExpiryTest extends TestCase
{
    public function testActiveAccountIsNotRestricted(): void
    {
        $user = ['status' => 'active', 'restricted_at' => null, 'restricted_until' => null];

        $this->assertFalse(User::isRestricted($user));
        $this->assertFalse(User::restrictionExpired($user));
    }

    public function testIndefiniteRestrictionStaysUntilAnAdminLiftsIt(): void
    {
        $user = ['restricted_at' => '2026-11-10 12:00:00', 'restricted_until' => null];

        $this->assertTrue(User::isRestricted($user));
        $this->assertFalse(User::restrictionExpired($user));
    }

    public function testFutureRestrictionIsActive(): void
    {
        $user = [
            'restricted_at' => '2026-11-10 12:00:00',
            'restricted_until' => gmdate('Y-m-d H:i:s', time() + 3600),
        ];

        $this->assertTrue(User::isRestricted($user));
        $this->assertFalse(User::restrictionExpired($user));
    }

    public function testPastRestrictionIsExpiredAndTreatedAsLifted(): void
    {
        $user = [
            'restricted_at' => '2026-11-01 12:00:00',
            'restricted_until' => gmdate('Y-m-d H:i:s', time() - 60),
        ];

        $this->assertTrue(User::restrictionExpired($user));
        $this->assertFalse(User::isRestricted($user));
    }

    public function testEmptyExpiryMeansIndefinite(): void
    {
        $user = ['restricted_at' => '2026-11-10 12:00:00', 'restricted_until' => ''];

        $this->assertTrue(User::isRestricted($user));
        $this->assertFalse(User::restrictionExpired($user));
    }

    public function testUnparseableExpiryIsTreatedAsIndefinite(): void
    {
        $user = ['restricted_at' => '2026-11-10 12:00:00', 'restricted_until' => 'not-a-date'];

        $this->assertTrue(User::isRestricted($user));
        $this->assertFalse(User::restrictionExpired($user));
    }
}
