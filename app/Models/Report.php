<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Explicit SQL for the reports queue.
 *
 * Reports are user-submitted flags against a public profile or a quest. The
 * target label is snapshotted at submission time so the queue stays readable
 * after a profile is renamed or a quest is deleted.
 */
final class Report
{
    /** @var array<string, string> */
    public const REASONS = [
        'harassment' => 'Harassment or bullying',
        'hate' => 'Hate speech',
        'impersonation' => 'Impersonation',
        'spam' => 'Spam or misleading',
        'inappropriate' => 'Inappropriate content',
        'privacy' => 'Shares private information',
        'other' => 'Something else',
    ];

    /** @var list<string> */
    public const TARGET_TYPES = ['profile', 'quest'];

    /** @var list<string> */
    public const STATUSES = ['open', 'resolved', 'dismissed'];

    private const SELECT_WITH_ACTORS = <<<'SQL'
        SELECT r.id, r.reporter_user_id, r.target_type, r.target_id, r.target_label,
               r.reason, r.details, r.status, r.resolved_by_user_id, r.resolution_note,
               r.resolved_at, r.created_at, r.updated_at,
               reporter.username AS reporter_username,
               reporter.display_name AS reporter_display_name,
               reporter.email AS reporter_email,
               reporter.status AS reporter_status,
               reporter.avatar_path AS reporter_avatar_path,
               resolver.username AS resolver_username,
               resolver.display_name AS resolver_display_name
        FROM reports r
        LEFT JOIN users reporter ON reporter.id = r.reporter_user_id
        LEFT JOIN users resolver ON resolver.id = r.resolved_by_user_id
        SQL;

    /**
     * @param array{reporter_user_id: int|null, target_type: string, target_id: int, target_label: string, reason: string, details: string|null} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO reports
                (reporter_user_id, target_type, target_id, target_label, reason, details, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['reporter_user_id'],
            $data['target_type'],
            $data['target_id'],
            $data['target_label'],
            $data['reason'],
            $data['details'],
            'open',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS . ' WHERE r.id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $report = $statement->fetch(PDO::FETCH_ASSOC);

        return $report === false ? null : $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForReporter(int $reporterId, string $targetType, int $targetId): ?array
    {
        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS
            . ' WHERE r.reporter_user_id = ? AND r.target_type = ? AND r.target_id = ? LIMIT 1',
        );
        $statement->execute([$reporterId, $targetType, $targetId]);
        $report = $statement->fetch(PDO::FETCH_ASSOC);

        return $report === false ? null : $report;
    }

    /**
     * @param array{search?: string, status?: string, target_type?: string, reason?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function adminPaginate(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = self::adminCriteria($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $statement = Database::connection()->prepare(
            self::SELECT_WITH_ACTORS
            . $where
            . " ORDER BY CASE WHEN r.status = 'open' THEN 0 ELSE 1 END ASC,
                       r.created_at DESC, r.id DESC
                LIMIT " . $perPage . ' OFFSET ' . $offset,
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{search?: string, status?: string, target_type?: string, reason?: string} $filters
     */
    public static function adminCount(array $filters): int
    {
        [$where, $parameters] = self::adminCriteria($filters);

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM reports r
             LEFT JOIN users reporter ON reporter.id = r.reporter_user_id' . $where,
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{open: int, resolved: int, dismissed: int}
     */
    public static function statusCounts(): array
    {
        $counts = ['open' => 0, 'resolved' => 0, 'dismissed' => 0];

        foreach (Database::connection()
            ->query('SELECT status, COUNT(*) AS total FROM reports GROUP BY status')
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
            ->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")
            ->fetchColumn();
    }

    /**
     * Close an open report. Closed reports are immutable so the audit trail
     * and the decision note cannot drift after the fact.
     */
    public static function close(int $id, int $resolverId, string $status, ?string $note): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE reports
             SET status = ?, resolved_by_user_id = ?, resolution_note = ?,
                 resolved_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = ?',
        );
        $statement->execute([$status, $resolverId, $note, $id, 'open']);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{search?: string, status?: string, target_type?: string, reason?: string} $filters
     * @return array{0: string, 1: list<string>}
     */
    private static function adminCriteria(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $like = '%' . $filters['search'] . '%';
            $clauses[] = '(r.target_label LIKE ? OR r.details LIKE ? OR r.resolution_note LIKE ?
                OR reporter.username LIKE ? OR reporter.display_name LIKE ?)';
            array_push($parameters, $like, $like, $like, $like, $like);
        }

        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $clauses[] = 'r.status = ?';
            $parameters[] = $filters['status'];
        }

        if (in_array($filters['target_type'] ?? '', self::TARGET_TYPES, true)) {
            $clauses[] = 'r.target_type = ?';
            $parameters[] = $filters['target_type'];
        }

        if (array_key_exists($filters['reason'] ?? '', self::REASONS)) {
            $clauses[] = 'r.reason = ?';
            $parameters[] = $filters['reason'];
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
