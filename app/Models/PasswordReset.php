<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Password reset tokens are stored as SHA-256 hashes and are single-use.
 */
final class PasswordReset
{
    public static function create(int $userId, string $tokenHash, string $expiresAt, string $ipAddress): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, ip_address, expires_at, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([$userId, $tokenHash, $ipAddress, $expiresAt]);
    }

    /** @return array<string, mixed>|null */
    public static function findValidByHash(string $tokenHash): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()
             LIMIT 1',
        );
        $statement->execute([$tokenHash]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);

        return $reset === false ? null : $reset;
    }

    public static function markUsed(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ? AND used_at IS NULL',
        );
        $statement->execute([$id]);
    }

    public static function deleteForUser(int $userId): void
    {
        $statement = Database::connection()->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $statement->execute([$userId]);
    }

    public static function countRecentForUser(int $userId, int $seconds): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= ?',
        );
        $statement->execute([$userId, gmdate('Y-m-d H:i:s', time() - $seconds)]);

        return (int) $statement->fetchColumn();
    }

    public static function countRecentForIp(string $ipAddress, int $seconds): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM password_resets WHERE ip_address = ? AND created_at >= ?',
        );
        $statement->execute([$ipAddress, gmdate('Y-m-d H:i:s', time() - $seconds)]);

        return (int) $statement->fetchColumn();
    }

    public static function purgeExpired(): void
    {
        Database::connection()->exec(
            'DELETE FROM password_resets WHERE expires_at <= UTC_TIMESTAMP() AND used_at IS NOT NULL',
        );
    }
}
