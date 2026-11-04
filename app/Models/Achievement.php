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

    /**
     * Admin catalogue: inactive definitions included, with unlock totals.
     *
     * @return list<array<string, mixed>>
     */
    public static function allForAdmin(): array
    {
        return Database::connection()->query(
            'SELECT a.id, a.achievement_key, a.name, a.description, a.icon, a.category,
                    a.criteria_type, a.threshold, a.sort_order, a.is_active, a.created_at, a.updated_at,
                    COUNT(ua.id) AS unlock_count
             FROM achievements a
             LEFT JOIN user_achievements ua ON ua.achievement_id = a.id
             GROUP BY a.id, a.achievement_key, a.name, a.description, a.icon, a.category,
                      a.criteria_type, a.threshold, a.sort_order, a.is_active, a.created_at, a.updated_at
             ORDER BY a.sort_order ASC, a.id ASC',
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM achievements WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $achievement = $statement->fetch(PDO::FETCH_ASSOC);

        return $achievement === false ? null : $achievement;
    }

    public static function keyExists(string $key, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM achievements WHERE achievement_key = ?';
        $parameters = [$key];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{achievement_key: string, name: string, description: string, icon: string, category: string, criteria_type: string, threshold: int, sort_order: int, is_active: bool} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO achievements
                (achievement_key, name, description, icon, category, criteria_type, threshold, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['achievement_key'],
            $data['name'],
            $data['description'],
            $data['icon'],
            $data['category'],
            $data['criteria_type'],
            $data['threshold'],
            $data['sort_order'],
            $data['is_active'] ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array{achievement_key: string, name: string, description: string, icon: string, category: string, criteria_type: string, threshold: int, sort_order: int, is_active: bool} $data
     */
    public static function update(int $id, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE achievements
             SET achievement_key = ?, name = ?, description = ?, icon = ?, category = ?,
                 criteria_type = ?, threshold = ?, sort_order = ?, is_active = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([
            $data['achievement_key'],
            $data['name'],
            $data['description'],
            $data['icon'],
            $data['category'],
            $data['criteria_type'],
            $data['threshold'],
            $data['sort_order'],
            $data['is_active'] ? 1 : 0,
            $id,
        ]);

        // A no-op save still succeeds; the row was verified by the service.
        return true;
    }

    public static function unlockedCount(int $id): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM user_achievements WHERE achievement_id = ?',
        );
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    public static function delete(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM achievements WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }
}
