<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Read-only account appeals.
 *
 * A restricted account can ask a moderator to review the limit. An appeal is
 * either open, accepted (the limit was lifted), or denied. Closed appeals are
 * immutable so the decision note cannot drift after the fact.
 */
final class Appeal
{
    /** @var list<string> */
    public const STATUSES = ['open', 'accepted', 'denied'];

    private const SELECT_WITH_ACTORS = <<<'SQL'
        SELECT a.id, a.user_id, a.message, a.status, a.reviewed_by_user_id,
               a.resolution_note, a.resolved_at, a.created_at, a.updated_at,
               u.username AS user_username,
               u.display_name AS user_display_name,
               u.email AS user_email,
               u.avatar_path AS user_avatar_path,
               u.status AS user_status,
               u.restricted_at AS user_restricted_at,
               u.restricted_until AS user_restricted_until,
               reviewer.username AS reviewer_username,
               reviewer.display_name AS reviewer_display_name
        FROM account_appeals a
        INNER JOIN users u ON u.id = a.user_id
        LEFT JOIN users reviewer ON reviewer.id = a.reviewed_by_user_id
        SQL;

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS . ' WHERE a.id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $appeal = $statement->fetch(PDO::FETCH_ASSOC);

        return $appeal === false ? null : $appeal;
    }

    /** @return array<string, mixed>|null */
    public static function findOpenForUser(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS . " WHERE a.user_id = ? AND a.status = 'open' ORDER BY a.id DESC LIMIT 1",
        );
        $statement->execute([$userId]);
        $appeal = $statement->fetch(PDO::FETCH_ASSOC);

        return $appeal === false ? null : $appeal;
    }

    /**
     * @param array{user_id: int, message: string} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO account_appeals (user_id, message, status, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            (int) $data['user_id'],
            (string) $data['message'],
            'open',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function latestForUser(int $userId, int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS . ' WHERE a.user_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT ' . max(1, $limit),
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * True when the account already received a decision inside the cooldown.
     */
    public static function hasRecentDecision(int $userId, int $seconds): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM account_appeals
             WHERE user_id = ? AND status IN ('accepted', 'denied')
               AND resolved_at IS NOT NULL
               AND resolved_at >= ?
             LIMIT 1",
        );
        $statement->execute([$userId, gmdate('Y-m-d H:i:s', time() - max(1, $seconds))]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{search?: string, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function adminPaginate(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = self::adminCriteria($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS
            . $where
            . " ORDER BY CASE WHEN a.status = 'open' THEN 0 ELSE 1 END ASC,
                       a.created_at DESC, a.id DESC
                LIMIT " . $perPage . ' OFFSET ' . $offset,
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{search?: string, status?: string} $filters
     */
    public static function adminCount(array $filters): int
    {
        [$where, $parameters] = self::adminCriteria($filters);

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM account_appeals a
             INNER JOIN users u ON u.id = a.user_id' . $where,
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @return array{open: int, accepted: int, denied: int} */
    public static function statusCounts(): array
    {
        $counts = ['open' => 0, 'accepted' => 0, 'denied' => 0];

        foreach (Database::connection()
            ->query('SELECT status, COUNT(*) AS total FROM account_appeals GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) ($row['status'] ?? '');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    public static function openCount(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM account_appeals WHERE status = 'open'")
            ->fetchColumn();
    }

    /**
     * Close an open appeal. Closed appeals are immutable.
     */
    public static function close(int $id, int $reviewerId, string $status, ?string $note): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE account_appeals
             SET status = ?, reviewed_by_user_id = ?, resolution_note = ?,
                 resolved_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = ?',
        );
        $statement->execute([$status, $reviewerId, $note, $id, 'open']);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{search?: string, status?: string} $filters
     * @return array{0: string, 1: list<string>}
     */
    private static function adminCriteria(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $like = '%' . $filters['search'] . '%';
            $clauses[] = '(u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?
                OR a.message LIKE ? OR a.resolution_note LIKE ?)';
            array_push($parameters, $like, $like, $like, $like, $like);
        }

        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $clauses[] = 'a.status = ?';
            $parameters[] = $filters['status'];
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
