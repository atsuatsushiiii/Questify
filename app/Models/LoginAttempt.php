<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Failed login bookkeeping used for per-identifier and per-IP throttling.
 */
final class LoginAttempt
{
    public static function record(string $identifier, string $ipAddress, bool $successful): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, successful, attempted_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([
            mb_substr(mb_strtolower($identifier), 0, 190),
            $ipAddress,
            $successful ? 1 : 0,
        ]);
    }

    public static function failures(string $identifier, string $ipAddress, int $seconds): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = ? AND ip_address = ? AND successful = 0 AND attempted_at >= ?',
        );
        $statement->execute([
            mb_substr(mb_strtolower($identifier), 0, 190),
            $ipAddress,
            gmdate('Y-m-d H:i:s', time() - $seconds),
        ]);

        return (int) $statement->fetchColumn();
    }

    public static function ipFailures(string $ipAddress, int $seconds): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND successful = 0 AND attempted_at >= ?',
        );
        $statement->execute([$ipAddress, gmdate('Y-m-d H:i:s', time() - $seconds)]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Clear the active window after a successful sign-in so a legitimate user
     * who mistyped twice is not locked out later.
     */
    public static function clearFailures(string $identifier, string $ipAddress): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM login_attempts
             WHERE identifier = ? AND ip_address = ? AND successful = 0',
        );
        $statement->execute([
            mb_substr(mb_strtolower($identifier), 0, 190),
            $ipAddress,
        ]);
    }

    public static function purgeOlderThan(int $seconds): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < ?',
        );
        $statement->execute([gmdate('Y-m-d H:i:s', time() - $seconds)]);
    }
}
