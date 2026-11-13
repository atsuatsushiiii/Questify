<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Append-only administrative audit trail. There is deliberately no update or
 * delete method: an audit entry is a permanent record of a privileged action.
 */
final class AuditLog
{
    /**
     * @param array{actor_user_id: int|null, action: string, target_type: string, target_id: int|null, summary: string, metadata?: array<string, mixed>|null, ip_address?: string|null} $entry
     */
    public static function record(array $entry): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs
                (actor_user_id, action, target_type, target_id, summary, metadata, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([
            $entry['actor_user_id'],
            $entry['action'],
            $entry['target_type'],
            $entry['target_id'],
            $entry['summary'],
            isset($entry['metadata'])
                ? json_encode($entry['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            $entry['ip_address'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 8): array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.id, a.actor_user_id, a.action, a.target_type, a.target_id, a.summary,
                    a.metadata, a.ip_address, a.created_at,
                    u.username AS actor_username, u.display_name AS actor_display_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?',
        );
        $statement->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{action?: string, target_type?: string, actor?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, int $page, int $perPage = 25): array
    {
        [$where, $parameters] = self::criteria($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $statement = Database::connection()->prepare(
            'SELECT a.id, a.actor_user_id, a.action, a.target_type, a.target_id, a.summary,
                    a.metadata, a.ip_address, a.created_at,
                    u.username AS actor_username, u.display_name AS actor_display_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id'
            . $where
            . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{action?: string, target_type?: string, actor?: string} $filters
     */
    public static function count(array $filters): int
    {
        [$where, $parameters] = self::criteria($filters);

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id' . $where,
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forTarget(string $targetType, int $targetId, int $limit = 20): array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.id, a.actor_user_id, a.action, a.target_type, a.target_id, a.summary,
                    a.metadata, a.ip_address, a.created_at,
                    u.username AS actor_username, u.display_name AS actor_display_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.target_type = ? AND a.target_id = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?',
        );
        $statement->bindValue(1, $targetType);
        $statement->bindValue(2, $targetId, PDO::PARAM_INT);
        $statement->bindValue(3, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Audit entries for a report plus account actions that reference it.
     *
     * @return list<array<string, mixed>>
     */
    public static function forReport(int $reportId, int $limit = 20): array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.id, a.actor_user_id, a.action, a.target_type, a.target_id, a.summary,
                    a.metadata, a.ip_address, a.created_at,
                    u.username AS actor_username, u.display_name AS actor_display_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE (a.target_type = 'report' AND a.target_id = ?)
                OR (a.target_type = 'user'
                    AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.report_id')) = ?)
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?",
        );
        $statement->bindValue(1, $reportId, PDO::PARAM_INT);
        $statement->bindValue(2, (string) $reportId);
        $statement->bindValue(3, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return Database::connection()
            ->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param array{action?: string, target_type?: string, actor?: string} $filters
     * @return array{0: string, 1: list<string>}
     */
    private static function criteria(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if (($filters['action'] ?? '') !== '') {
            $clauses[] = 'a.action = ?';
            $parameters[] = $filters['action'];
        }

        if (($filters['target_type'] ?? '') !== '') {
            $clauses[] = 'a.target_type = ?';
            $parameters[] = $filters['target_type'];
        }

        if (($filters['actor'] ?? '') !== '') {
            $clauses[] = '(u.username LIKE ? OR u.display_name LIKE ?)';
            $like = '%' . $filters['actor'] . '%';
            $parameters[] = $like;
            $parameters[] = $like;
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
