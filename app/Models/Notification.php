<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * In-app notifications, always scoped to the owning user.
 */
final class Notification
{
    /**
     * @param array{user_id: int, type: string, title: string, message: string, icon: string, url?: string|null, related_type?: string|null, related_id?: int|null, event_key: string} $data
     * @return bool true when this call created the notification
     */
    public static function create(PDO $db, array $data): bool
    {
        $statement = $db->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, type, title, message, icon, url, related_type, related_id, event_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'],
            $data['icon'],
            $data['url'] ?? null,
            $data['related_type'] ?? null,
            $data['related_id'] ?? null,
            $data['event_key'],
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        $statement = Database::connection()->prepare(
            'SELECT * FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function paginateForUser(int $userId, int $page, int $perPage, bool $unreadOnly): array
    {
        $perPage = max(1, min(50, $perPage));
        $where = 'user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '');

        $count = Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE {$where}");
        $count->execute([$userId]);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare(
            "SELECT * FROM notifications
             WHERE {$where}
             ORDER BY created_at DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $statement->execute([$userId]);

        return [
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public static function unreadCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function findForUser(int $id, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1',
        );
        $statement->execute([$id, $userId]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);

        return $notification === false ? null : $notification;
    }

    public static function markRead(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications
             SET read_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND read_at IS NULL',
        );
        $statement->execute([$id, $userId]);

        return $statement->rowCount() === 1;
    }

    public static function markAllRead(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL',
        );
        $statement->execute([$userId]);

        return $statement->rowCount();
    }
}
