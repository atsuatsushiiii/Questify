<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Append-only XP ledger. The unique key on quest_id is the last line of
 * defence against awarding the same quest twice; rows survive quest deletion
 * (quest_id becomes NULL) so the audit trail is never lost.
 */
final class XpTransaction
{
    /**
     * @param array{user_id: int, quest_id: int, quest_title: string, amount: int, level_before: int, level_after: int} $data
     */
    public static function create(PDO $db, array $data): int
    {
        $statement = $db->prepare(
            'INSERT INTO xp_transactions
                (user_id, quest_id, quest_title, source, amount, level_before, level_after, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['user_id'],
            $data['quest_id'],
            $data['quest_title'],
            'quest_completion',
            $data['amount'],
            $data['level_before'],
            $data['level_after'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function existsForQuest(int $questId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM xp_transactions WHERE quest_id = ? LIMIT 1',
        );
        $statement->execute([$questId]);

        return (bool) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function forQuest(int $questId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM xp_transactions WHERE quest_id = ? LIMIT 1',
        );
        $statement->execute([$questId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));

        $statement = Database::connection()->prepare(
            'SELECT * FROM xp_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function totalForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM xp_transactions WHERE user_id = ?',
        );
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }
}
