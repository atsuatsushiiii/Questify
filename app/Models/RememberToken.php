<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Remember-me tokens use a selector + validator split. Only the SHA-256 hash
 * of the validator is stored, so a database leak cannot be replayed.
 */
final class RememberToken
{
    public static function create(
        int $userId,
        string $selector,
        string $tokenHash,
        string $expiresAt,
        string $ipAddress,
        string $userAgent,
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO remember_tokens
                (user_id, selector, token_hash, ip_address, user_agent, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        );

        $statement->execute([$userId, $selector, $tokenHash, $ipAddress, $userAgent, $expiresAt]);
    }

    /** @return array<string, mixed>|null */
    public static function findValidBySelector(string $selector): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1',
        );
        $statement->execute([$selector]);
        $token = $statement->fetch(PDO::FETCH_ASSOC);

        return $token === false ? null : $token;
    }

    public static function rotate(int $id, string $tokenHash, string $expiresAt): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE remember_tokens
             SET token_hash = ?, expires_at = ?, last_used_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([$tokenHash, $expiresAt, $id]);
    }

    public static function deleteBySelector(string $selector): void
    {
        $statement = Database::connection()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $statement->execute([$selector]);
    }

    public static function deleteForUser(int $userId): void
    {
        $statement = Database::connection()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $statement->execute([$userId]);
    }

    public static function purgeExpired(): void
    {
        Database::connection()->exec('DELETE FROM remember_tokens WHERE expires_at <= UTC_TIMESTAMP()');
    }
}
