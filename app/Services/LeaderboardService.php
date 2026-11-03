<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Support\Progression;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Leaderboard queries backed by users.xp_total (all-time) and the append-only
 * XP ledger (weekly/monthly). UTC calendar boundaries keep a global board
 * deterministic.
 */
final class LeaderboardService
{
    /** @return array<string, string> */
    public static function periods(): array
    {
        return [
            'all' => 'All time',
            'weekly' => 'This week',
            'monthly' => 'This month',
        ];
    }

    public static function normalizePeriod(string $period): string
    {
        return array_key_exists($period, self::periods()) ? $period : 'all';
    }

    /**
     * @param array<string, mixed> $viewer
     * @return array<string, mixed>
     */
    public static function board(string $period, int $page, int $perPage, array $viewer): array
    {
        $period = self::normalizePeriod($period);
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);
        [$start, $end] = self::boundaries($period);
        $total = self::count($period, $start, $end);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $rows = self::rows($period, $start, $end, $perPage, $offset);
        $podium = self::rows($period, $start, $end, 3, 0);
        $eligible = self::eligible($viewer);
        $viewerRank = $eligible ? self::viewerRank($period, $start, $end, $viewer) : null;
        $viewerXp = $eligible ? self::viewerPeriodXp($period, $start, $end, $viewer) : 0;

        return [
            'period' => $period,
            'period_label' => self::periods()[$period],
            'period_note' => match ($period) {
                'weekly' => 'XP earned since Monday 00:00 UTC.',
                'monthly' => 'XP earned since the first of the month at 00:00 UTC.',
                default => 'Lifetime XP maintained from the quest ledger.',
            },
            'start' => $start,
            'end' => $end,
            'rows' => $rows,
            'podium' => $podium,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'viewer_rank' => $viewerRank,
            'viewer_xp' => $viewerXp,
            'viewer_visible' => $eligible,
            'viewer_row' => $eligible && $viewerRank !== null
                ? self::viewerRow($viewer, $viewerRank, $viewerXp)
                : null,
        ];
    }

    /**
     * Compare the viewer's all-time rank with the stored baseline and notify
     * once per local day when it changes. Called when the leaderboard is
     * viewed because Phase 4 has no background scheduler.
     *
     * @param array<string, mixed> $viewer
     */
    public static function detectRankChange(array $viewer, ?int $rank, string $period): void
    {
        if ($period !== 'all' || $rank === null || $rank < 1 || !self::eligible($viewer)) {
            return;
        }

        $userId = (int) $viewer['id'];
        $oldRank = isset($viewer['last_leaderboard_rank']) && $viewer['last_leaderboard_rank'] !== null
            ? (int) $viewer['last_leaderboard_rank']
            : null;

        if ($oldRank === $rank) {
            User::updateLeaderboardRank(Database::connection(), $userId, $rank);

            return;
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::updateLeaderboardRank($db, $userId, $rank);

            if ($oldRank !== null) {
                NotificationService::leaderboardRankChanged(
                    $db,
                    $userId,
                    $oldRank,
                    $rank,
                    StreakService::localDate(null, (string) ($viewer['timezone'] ?? 'UTC')),
                );
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function boundaries(string $period): array
    {
        if ($period === 'all') {
            return [null, null];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($period === 'weekly') {
            $start = $now
                ->modify('-' . ((int) $now->format('N') - 1) . ' days')
                ->setTime(0, 0, 0);
            $end = $start->modify('+7 days');

            return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
        }

        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $start->modify('+1 month');

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    private static function count(string $period, ?string $start, ?string $end): int
    {
        $db = Database::connection();

        if ($period === 'all') {
            return (int) $db->query(
                "SELECT COUNT(*) FROM users
                 WHERE status = 'active' AND show_on_leaderboard = 1 AND xp_total > 0",
            )->fetchColumn();
        }

        $statement = $db->prepare(
            "SELECT COUNT(*) FROM (
                SELECT xt.user_id
                FROM xp_transactions xt
                INNER JOIN users u ON u.id = xt.user_id
                WHERE xt.created_at >= ? AND xt.created_at < ? AND xt.amount > 0
                  AND u.status = 'active' AND u.show_on_leaderboard = 1
                GROUP BY xt.user_id
            ) ranked",
        );
        $statement->execute([$start, $end]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        string $period,
        ?string $start,
        ?string $end,
        int $limit,
        int $offset,
    ): array {
        $db = Database::connection();
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);

        if ($period === 'all') {
            $statement = $db->prepare(
                "SELECT u.id, u.username, u.display_name, u.avatar_path, u.level, u.xp_total,
                        u.xp_total AS period_xp,
                        ROW_NUMBER() OVER (ORDER BY u.xp_total DESC, u.id ASC) AS rank_position
                 FROM users u
                 WHERE u.status = 'active' AND u.show_on_leaderboard = 1 AND u.xp_total > 0
                 ORDER BY u.xp_total DESC, u.id ASC
                 LIMIT {$limit} OFFSET {$offset}",
            );
            $statement->execute();
        } else {
            $statement = $db->prepare(
                "SELECT u.id, u.username, u.display_name, u.avatar_path, u.level, u.xp_total,
                        p.period_xp,
                        ROW_NUMBER() OVER (
                            ORDER BY p.period_xp DESC, u.xp_total DESC, u.id ASC
                        ) AS rank_position
                 FROM users u
                 INNER JOIN (
                    SELECT user_id, SUM(amount) AS period_xp
                    FROM xp_transactions
                    WHERE created_at >= ? AND created_at < ? AND amount > 0
                    GROUP BY user_id
                 ) p ON p.user_id = u.id
                 WHERE u.status = 'active' AND u.show_on_leaderboard = 1
                 ORDER BY p.period_xp DESC, u.xp_total DESC, u.id ASC
                 LIMIT {$limit} OFFSET {$offset}",
            );
            $statement->execute([$start, $end]);
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $row['rank_position'] = (int) $row['rank_position'];
            $row['period_xp'] = (int) $row['period_xp'];
            $row['xp_total'] = (int) $row['xp_total'];
            $row['level'] = (int) $row['level'];
            $row['rank_title'] = Progression::rank($row['level']);

            return $row;
        }, $rows);
    }

    /** @param array<string, mixed> $viewer */
    private static function eligible(array $viewer): bool
    {
        return (int) ($viewer['id'] ?? 0) > 0
            && (string) ($viewer['status'] ?? 'active') === 'active'
            && (int) ($viewer['show_on_leaderboard'] ?? 1) === 1;
    }

    /**
     * @param array<string, mixed> $viewer
     */
    private static function viewerRank(
        string $period,
        ?string $start,
        ?string $end,
        array $viewer,
    ): ?int {
        $viewerXp = self::viewerPeriodXp($period, $start, $end, $viewer);

        if ($viewerXp < 1) {
            return null;
        }

        $db = Database::connection();
        $userId = (int) $viewer['id'];

        if ($period === 'all') {
            $statement = $db->prepare(
                "SELECT COUNT(*) + 1 FROM users
                 WHERE status = 'active' AND show_on_leaderboard = 1 AND xp_total > 0
                   AND (xp_total > ? OR (xp_total = ? AND id < ?))",
            );
            $statement->execute([$viewerXp, $viewerXp, $userId]);

            return (int) $statement->fetchColumn();
        }

        $statement = $db->prepare(
            "SELECT COUNT(*) + 1 FROM (
                SELECT u.id, u.xp_total, SUM(xt.amount) AS period_xp
                FROM users u
                INNER JOIN xp_transactions xt ON xt.user_id = u.id
                WHERE xt.created_at >= ? AND xt.created_at < ? AND xt.amount > 0
                  AND u.status = 'active' AND u.show_on_leaderboard = 1
                GROUP BY u.id, u.xp_total
            ) p
            WHERE p.period_xp > ?
               OR (p.period_xp = ? AND (p.xp_total > ? OR (p.xp_total = ? AND p.id < ?)))",
        );
        $statement->execute([
            $start,
            $end,
            $viewerXp,
            $viewerXp,
            (int) ($viewer['xp_total'] ?? 0),
            (int) ($viewer['xp_total'] ?? 0),
            $userId,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $viewer
     */
    private static function viewerPeriodXp(
        string $period,
        ?string $start,
        ?string $end,
        array $viewer,
    ): int {
        if ($period === 'all') {
            return max(0, (int) ($viewer['xp_total'] ?? 0));
        }

        $statement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM xp_transactions
             WHERE user_id = ? AND created_at >= ? AND created_at < ? AND amount > 0',
        );
        $statement->execute([(int) $viewer['id'], $start, $end]);

        return max(0, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, mixed> $viewer
     * @return array<string, mixed>
     */
    private static function viewerRow(array $viewer, int $rank, int $periodXp): array
    {
        $progression = Progression::snapshot($viewer);

        return [
            'id' => (int) $viewer['id'],
            'username' => (string) ($viewer['username'] ?? ''),
            'display_name' => (string) ($viewer['display_name'] ?? $viewer['username'] ?? 'Adventurer'),
            'avatar_path' => isset($viewer['avatar_path']) ? (string) $viewer['avatar_path'] : null,
            'level' => (int) $progression['level'],
            'xp_total' => (int) $progression['xp'],
            'period_xp' => $periodXp,
            'rank_position' => $rank,
            'rank_title' => (string) $progression['rank'],
        ];
    }
}
