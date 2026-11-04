<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * One row per user per local calendar day on which a quest was completed.
 */
final class StreakActivity
{
    /**
     * @return bool true when this call created the day record
     */
    public static function record(PDO $db, int $userId, string $activityDate, ?int $questId): bool
    {
        $statement = $db->prepare(
            'INSERT IGNORE INTO streak_activity (user_id, activity_date, quest_id, created_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([$userId, $activityDate, $questId]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return list<string> Y-m-d values
     */
    public static function datesForUser(int $userId, string $start, string $end): array
    {
        $statement = Database::connection()->prepare(
            'SELECT activity_date FROM streak_activity
             WHERE user_id = ? AND activity_date BETWEEN ? AND ?
             ORDER BY activity_date ASC',
        );
        $statement->execute([$userId, $start, $end]);

        return array_map(
            static fn (mixed $value): string => (string) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public static function countForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?',
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function latestForUser(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM streak_activity WHERE user_id = ? ORDER BY activity_date DESC LIMIT 1',
        );
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
