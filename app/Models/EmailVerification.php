<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Pending signup email confirmations. Only a SHA-256 hash of the token is
 * stored, so a database leak cannot be used to verify an address.
 */
final class EmailVerification
{
    public static function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([$userId, $tokenHash, $expiresAt]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function deleteForUser(int $userId): void
    {
        $statement = Database::connection()->prepare('DELETE FROM email_verifications WHERE user_id = ?');
        $statement->execute([$userId]);
    }

    public static function deleteById(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM email_verifications WHERE id = ?');
        $statement->execute([$id]);
    }

    /**
     * Atomically consume a single request. The first confirmation wins; later
     * submissions find no row and are rejected.
     */
    public static function consume(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM email_verifications WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public static function findValidByHash(string $tokenHash): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM email_verifications WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1',
        );
        $statement->execute([$tokenHash]);
        $request = $statement->fetch(PDO::FETCH_ASSOC);

        return $request === false ? null : $request;
    }

    public static function countRecentForUser(int $userId, int $seconds): int
    {
        $threshold = gmdate('Y-m-d H:i:s', time() - max(1, $seconds));
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM email_verifications WHERE user_id = ? AND created_at >= ?',
        );
        $statement->execute([$userId, $threshold]);

        return (int) $statement->fetchColumn();
    }

    public static function purgeExpired(): void
    {
        Database::connection()->exec('DELETE FROM email_verifications WHERE expires_at <= UTC_TIMESTAMP()');
    }
}
