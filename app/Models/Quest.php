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
               q.xp_reward, q.recurrence, q.status, q.completed_at, q.last_completed_period,
               q.created_at, q.updated_at,
               c.name AS category_name, c.slug AS category_slug,
               c.color AS category_color, c.icon AS category_icon
        FROM quests q
        LEFT JOIN categories c ON c.id = q.category_id
        SQL;

    /**
     * Admin moderation reads every quest, so the owner is joined in as well.
     */
    private const ADMIN_SELECT = <<<'SQL'
        SELECT q.id, q.user_id, q.category_id, q.title, q.description, q.difficulty,
               q.xp_reward, q.recurrence, q.status, q.completed_at, q.last_completed_period,
               q.created_at, q.updated_at,
               c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
               u.username AS owner_username, u.display_name AS owner_display_name,
               u.email AS owner_email, u.avatar_path AS owner_avatar_path
        FROM quests q
        INNER JOIN users u ON u.id = q.user_id
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
     * @param array{user_id: int, category_id: int|null, title: string, description: string, difficulty: string, xp_reward: int, recurrence?: string} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO quests (user_id, category_id, title, description, difficulty, xp_reward, recurrence, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['user_id'],
            $data['category_id'],
            $data['title'],
            $data['description'],
            $data['difficulty'],
            $data['xp_reward'],
            $data['recurrence'] ?? 'none',
            'active',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Only active quests can be edited: completed rewards must stay immutable
     * and archived quests have to be restored first.
     *
     * @param array{category_id: int|null, title: string, description: string, difficulty: string, xp_reward: int, recurrence: string, reset_period?: bool} $data
     */
    public static function updateActive(int $id, int $userId, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE quests
             SET category_id = ?, title = ?, description = ?, difficulty = ?, xp_reward = ?,
                 recurrence = ?, last_completed_period = IF(?, NULL, last_completed_period),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND status = ?',
        );
        $statement->execute([
            $data['category_id'],
            $data['title'],
            $data['description'],
            $data['difficulty'],
            $data['xp_reward'],
            $data['recurrence'],
            ($data['reset_period'] ?? false) ? 1 : 0,
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

    /**
     * Recurring quests stay active; the period key records which window was
     * paid so the next window can award again.
     */
    public static function completeRecurring(PDO $db, int $id, int $userId, string $periodKey): bool
    {
        $statement = $db->prepare(
            'UPDATE quests
             SET last_completed_period = ?, completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND status = ?',
        );
        $statement->execute([$periodKey, $id, $userId, 'active']);

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

    /**
     * Admin quest directory across every account.
     *
     * @param array{search?: string, status?: string, difficulty?: string, category?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function adminPaginate(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = self::adminCriteria($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $statement = Database::connection()->prepare(
            self::ADMIN_SELECT
            . $where
            . ' ORDER BY q.created_at DESC, q.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{search?: string, status?: string, difficulty?: string, category?: string} $filters
     */
    public static function adminCount(array $filters): int
    {
        [$where, $parameters] = self::adminCriteria($filters);

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM quests q
             INNER JOIN users u ON u.id = q.user_id
             LEFT JOIN categories c ON c.id = q.category_id' . $where,
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForAdmin(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            self::ADMIN_SELECT . ' WHERE q.id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $quest = $statement->fetch(PDO::FETCH_ASSOC);

        return $quest === false ? null : $quest;
    }

    /**
     * @return array{active: int, completed: int, archived: int}
     */
    public static function adminStatusCounts(): array
    {
        $counts = ['active' => 0, 'completed' => 0, 'archived' => 0];

        foreach (Database::connection()
            ->query('SELECT status, COUNT(*) AS total FROM quests GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) ($row['status'] ?? '');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Hide a quest from its owner without deleting the record or its XP history.
     */
    public static function adminArchive(int $id): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE quests
             SET status = 'archived', updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status <> 'archived'",
        );
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /**
     * Restore an archived quest to the status it had before moderation. A
     * completed one-off quest returns to `completed`, while a recurring quest
     * returns to `active`, so it can still be completed in the next period.
     */
    public static function adminRestore(int $id): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE quests
             SET status = IF(recurrence <> 'none' OR completed_at IS NULL, 'active', 'completed'),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'archived'",
        );
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /**
     * Delete a quest permanently. `xp_transactions.quest_id` is set to NULL by
     * the foreign key, so the ledger and its `quest_title` snapshot survive.
     */
    public static function adminDelete(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM quests WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{search?: string, status?: string, difficulty?: string, category?: string} $filters
     * @return array{0: string, 1: list<string>}
     */
    private static function adminCriteria(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $like = '%' . $filters['search'] . '%';
            $clauses[] = '(q.title LIKE ? OR q.description LIKE ? OR u.username LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?)';
            array_push($parameters, $like, $like, $like, $like, $like);
        }

        if (in_array($filters['status'] ?? '', ['active', 'completed', 'archived'], true)) {
            $clauses[] = 'q.status = ?';
            $parameters[] = $filters['status'];
        }

        if (in_array($filters['difficulty'] ?? '', ['easy', 'medium', 'hard', 'epic'], true)) {
            $clauses[] = 'q.difficulty = ?';
            $parameters[] = $filters['difficulty'];
        }

        if (($filters['category'] ?? '') !== '') {
            $clauses[] = 'c.slug = ?';
            $parameters[] = $filters['category'];
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
