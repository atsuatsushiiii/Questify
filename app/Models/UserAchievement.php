<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Unlock records. The unique key on (user_id, achievement_id) is the final
 * defence against duplicate unlocks.
 */
final class UserAchievement
{
    /**
     * Insert an unlock only when it does not already exist.
     *
     * @return bool true when this call created the unlock
     */
    public static function insertIgnore(PDO $db, int $userId, int $achievementId): bool
    {
        $statement = $db->prepare(
            'INSERT IGNORE INTO user_achievements (user_id, achievement_id, unlocked_at)
             VALUES (?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([$userId, $achievementId]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 4): array
    {
        $limit = max(1, min(50, $limit));

        $statement = Database::connection()->prepare(
            'SELECT a.*, ua.unlocked_at
             FROM user_achievements ua
             INNER JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = ?
             ORDER BY ua.unlocked_at DESC, ua.id DESC
             LIMIT ' . $limit,
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM user_achievements WHERE user_id = ?',
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    public static function exists(int $userId, int $achievementId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM user_achievements WHERE user_id = ? AND achievement_id = ? LIMIT 1',
        );
        $statement->execute([$userId, $achievementId]);

        return (bool) $statement->fetchColumn();
    }
}
