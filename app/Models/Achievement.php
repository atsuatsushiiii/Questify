<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Achievement definitions. Definitions are seeded data, never user input.
 */
final class Achievement
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        return self::allActiveWith(Database::connection());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function allActiveWith(PDO $db): array
    {
        $statement = $db->query(
            'SELECT * FROM achievements WHERE is_active = 1 ORDER BY sort_order ASC, id ASC',
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active definitions joined to the current user's unlock state.
     *
     * @return list<array<string, mixed>>
     */
    public static function allWithUnlocks(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.*, ua.unlocked_at
             FROM achievements a
             LEFT JOIN user_achievements ua
                ON ua.achievement_id = a.id AND ua.user_id = ?
             WHERE a.is_active = 1
             ORDER BY a.sort_order ASC, a.id ASC',
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public static function findByKey(string $key): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM achievements WHERE achievement_key = ? LIMIT 1',
        );
        $statement->execute([$key]);
        $achievement = $statement->fetch(PDO::FETCH_ASSOC);

        return $achievement === false ? null : $achievement;
    }

    public static function countActive(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM achievements WHERE is_active = 1')
            ->fetchColumn();
    }
}
