<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Read and revoke database-backed sessions for the settings screen.
 *
 * Session identifiers are stored as SHA-256 hashes; exposing a row id on the
 * settings page does not expose a replayable cookie value.
 */
final class UserSession
{
    /** @return list<array<string, mixed>> */
    public static function forUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, ip_address, user_agent, last_activity, created_at, expires_at
             FROM sessions
             WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()
             ORDER BY last_activity DESC',
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function deleteOwned(int $userId, string $sessionId): bool
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM sessions WHERE id = ? AND user_id = ?',
        );
        $statement->execute([$sessionId, $userId]);

        return $statement->rowCount() === 1;
    }

    public static function deleteForUserExcept(int $userId, string $keepSessionId): int
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM sessions WHERE user_id = ? AND id <> ?',
        );
        $statement->execute([$userId, $keepSessionId]);

        return $statement->rowCount();
    }

    public static function countForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()',
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }
}
