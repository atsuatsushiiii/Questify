<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Explicit SQL for the quests table. Every statement is prepared and every
 * read/write is scoped to the owning user id.
 */
final class Quest
{
    private const SELECT_WITH_CATEGORY = <<<'SQL'
        SELECT q.id, q.user_id, q.category_id, q.title, q.description, q.difficulty,
               q.xp_reward, q.status, q.completed_at, q.created_at, q.updated_at,
               c.name AS category_name, c.slug AS category_slug,
               c.color AS category_color, c.icon AS category_icon
        FROM quests q
        LEFT JOIN categories c ON c.id = q.category_id
        SQL;

    /** @return array<string, mixed>|null */
    public static function findForUser(int $id, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_CATEGORY . ' WHERE q.id = ? AND q.user_id = ? LIMIT 1',
        );
        $statement->execute([$id, $userId]);
        $quest = $statement->fetch(PDO::FETCH_ASSOC);

        return $quest === false ? null : $quest;
    }

    /**
     * Lock a quest row for the duration of the caller's transaction. The
     * ownership predicate means another user can never lock or read it.
     *
     * @return array<string, mixed>|null
     */
    public static function findForUserForUpdate(PDO $db, int $id, int $userId): ?array
    {
        $statement = $db->prepare(
            'SELECT * FROM quests WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE',
        );
        $statement->execute([$id, $userId]);
        $quest = $statement->fetch(PDO::FETCH_ASSOC);

        return $quest === false ? null : $quest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForUser(
        int $userId,
        string $status,
        ?string $difficulty = null,
        ?string $categorySlug = null,
    ): array {
        $sql = self::SELECT_WITH_CATEGORY . ' WHERE q.user_id = ? AND q.status = ?';
        $parameters = [$userId, $status];

        if ($difficulty !== null && $difficulty !== '') {
            $sql .= ' AND q.difficulty = ?';
            $parameters[] = $difficulty;
        }

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = ?';
            $parameters[] = $categorySlug;
        }

        $sql .= match ($status) {
            'completed' => ' ORDER BY q.completed_at DESC, q.id DESC',
            'archived' => ' ORDER BY q.updated_at DESC, q.id DESC',
            default => ' ORDER BY q.created_at DESC, q.id DESC',
        };

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recent quests for the admin user detail screen.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 20): array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_CATEGORY
            . ' WHERE q.user_id = ? ORDER BY q.created_at DESC, q.id DESC LIMIT ' . max(1, $limit),
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{active: int, completed: int, archived: int}
     */
    public static function countsForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS total FROM quests WHERE user_id = ? GROUP BY status',
        );
        $statement->execute([$userId]);

        $counts = ['active' => 0, 'completed' => 0, 'archived' => 0];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) ($row['status'] ?? '');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param array{user_id: int, category_id: int|null, title: string, description: string, difficulty: string, xp_reward: int} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO quests (user_id, category_id, title, description, difficulty, xp_reward, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['user_id'],
            $data['category_id'],
            $data['title'],
            $data['description'],
            $data['difficulty'],
            $data['xp_reward'],
            'active',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Only active quests can be edited: completed rewards must stay immutable
     * and archived quests have to be restored first.
     *
     * @param array{category_id: int|null, title: string, description: string, difficulty: string, xp_reward: int} $data
     */
    public static function updateActive(int $id, int $userId, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE quests
             SET category_id = ?, title = ?, description = ?, difficulty = ?, xp_reward = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND status = ?',
        );
        $statement->execute([
            $data['category_id'],
            $data['title'],
            $data['description'],
            $data['difficulty'],
            $data['xp_reward'],
            $id,
            $userId,
            'active',
        ]);

        return $statement->rowCount() === 1;
    }

    public static function complete(PDO $db, int $id, int $userId): bool
    {
        $statement = $db->prepare(
            'UPDATE quests
             SET status = ?, completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND status = ?',
        );
        $statement->execute(['completed', $id, $userId, 'active']);

        return $statement->rowCount() === 1;
    }

    public static function setStatus(int $id, int $userId, string $status): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE quests SET status = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?',
        );
        $statement->execute([$status, $id, $userId]);

        return $statement->rowCount() === 1;
    }

    public static function delete(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM quests WHERE id = ? AND user_id = ?',
        );
        $statement->execute([$id, $userId]);

        return $statement->rowCount() === 1;
    }
}
