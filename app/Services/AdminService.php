<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Achievement;
use App\Models\Category;
use App\Models\Quest;
use App\Models\RememberToken;
use App\Models\User;
use App\Models\UserSession;

/**
 * Administrative queries and moderation rules for accounts, quests, and the
 * catalogue.
 *
 * Every mutation records an audit entry and is guarded server-side: the actor
 * cannot suspend or demote themselves, and the last administrator cannot be
 * demoted.
 */
final class AdminService
{
    /** @return array<string, mixed> */
    public function dashboard(int $seriesDays = 14): array
    {
        return [
            'kpis' => [
                'users' => User::count(),
                'active_users' => User::countByStatus('active'),
                'suspended_users' => User::countByStatus('suspended'),
                'admins' => User::countByRole('admin'),
                'quests' => $this->scalar('SELECT COUNT(*) FROM quests'),
                'completed_quests' => $this->scalar("SELECT COUNT(*) FROM quests WHERE status = 'completed'"),
                'xp_awarded' => $this->scalar('SELECT COALESCE(SUM(xp_total), 0) FROM users'),
                'achievements_unlocked' => $this->scalar('SELECT COUNT(*) FROM user_achievements'),
                'categories' => Category::countActive(),
                'achievements' => Achievement::countActive(),
            ],
            'signupSeries' => User::signupSeries($seriesDays),
            'completionSeries' => $this->completionSeries($seriesDays),
            'recentAudit' => AuditLog::recent(8),
        ];
    }

    /**
     * @param array{search?: string, role?: string, status?: string} $filters
     * @return array{users: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int}
     */
    public function users(array $filters, int $page, int $perPage = 20): array
    {
        $total = User::adminCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'users' => User::adminPaginate($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function userDetail(int $userId): ?array
    {
        $user = User::find($userId);

        if ($user === null) {
            return null;
        }

        unset($user['password_hash']);

        $statement = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS total FROM quests WHERE user_id = ? GROUP BY status',
        );
        $statement->execute([$userId]);
        $questCounts = ['active' => 0, 'completed' => 0, 'archived' => 0];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $questCounts[(string) $row['status']] = (int) $row['total'];
        }

        $xpStatement = Database::connection()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM xp_transactions WHERE user_id = ?',
        );
        $xpStatement->execute([$userId]);

        $achievementStatement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM user_achievements WHERE user_id = ?',
        );
        $achievementStatement->execute([$userId]);

        return [
            'user' => $user,
            'questCounts' => $questCounts,
            'ledgerXp' => (int) $xpStatement->fetchColumn(),
            'achievementsUnlocked' => (int) $achievementStatement->fetchColumn(),
            'recentQuests' => Quest::recentForUser($userId, 12),
            'sessions' => UserSession::forUser($userId),
            'audit' => AuditLog::forTarget('user', $userId, 15),
        ];
    }

    /**
     * @param array{search?: string, status?: string, difficulty?: string, category?: string} $filters
     * @return array{quests: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>, categories: list<array<string, mixed>>}
     */
    public function quests(array $filters, int $page, int $perPage = 20): array
    {
        $total = Quest::adminCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'quests' => Quest::adminPaginate($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'statusCounts' => Quest::adminStatusCounts(),
            'categories' => Category::allWithUsage(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function questDetail(int $questId): ?array
    {
        $quest = Quest::findForAdmin($questId);

        if ($quest === null) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, amount, level_before, level_after, period_key, created_at
             FROM xp_transactions
             WHERE quest_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 20',
        );
        $statement->execute([$questId]);

        return [
            'quest' => $quest,
            'xpTransactions' => $statement->fetchAll(\PDO::FETCH_ASSOC),
            'audit' => AuditLog::forTarget('quest', $questId, 15),
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function archiveQuest(array $actor, int $questId, string $ipAddress): array
    {
        return $this->moderateQuest($actor, $questId, 'archived', $ipAddress);
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function restoreQuest(array $actor, int $questId, string $ipAddress): array
    {
        return $this->moderateQuest($actor, $questId, 'restored', $ipAddress);
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function deleteQuest(array $actor, int $questId, string $ipAddress): array
    {
        $actorId = (int) $actor['id'];
        $quest = Quest::findForAdmin($questId);

        if ($quest === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
        }

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) AS entries, COALESCE(SUM(amount), 0) AS xp
             FROM xp_transactions WHERE quest_id = ?',
        );
        $statement->execute([$questId]);
        $ledger = $statement->fetch(\PDO::FETCH_ASSOC) ?: ['entries' => 0, 'xp' => 0];

        if (!Quest::adminDelete($questId)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That quest could not be deleted.'];
        }

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => 'quest.deleted',
            'target_type' => 'quest',
            'target_id' => $questId,
            'summary' => sprintf(
                'Deleted quest “%s” owned by @%s',
                (string) $quest['title'],
                (string) $quest['owner_username'],
            ),
            'metadata' => [
                'status' => (string) $quest['status'],
                'owner_id' => (int) $quest['user_id'],
                'owner_username' => (string) $quest['owner_username'],
                'difficulty' => (string) $quest['difficulty'],
                'xp_reward' => (int) $quest['xp_reward'],
                'ledger_entries' => (int) $ledger['entries'],
                'ledger_xp' => (int) $ledger['xp'],
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => 'Quest deleted. Its XP ledger history was preserved.',
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    private function moderateQuest(array $actor, int $questId, string $action, string $ipAddress): array
    {
        $quest = Quest::findForAdmin($questId);

        if ($quest === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
        }

        $current = (string) $quest['status'];
        $archiving = $action === 'archived';

        if ($archiving && $current === 'archived') {
            return ['ok' => true, 'message' => 'This quest is already archived.'];
        }

        if (!$archiving && $current !== 'archived') {
            return ['ok' => true, 'message' => 'This quest is already visible.'];
        }

        $updated = $archiving
            ? Quest::adminArchive($questId)
            : Quest::adminRestore($questId);

        if (!$updated) {
            return ['ok' => false, 'status' => 409, 'message' => 'That quest could not be updated.'];
        }

        $target = $archiving
            ? 'archived'
            : (
                (string) ($quest['recurrence'] ?? 'none') !== 'none' || $quest['completed_at'] === null
                    ? 'active'
                    : 'completed'
            );

        AuditLog::record([
            'actor_user_id' => (int) $actor['id'],
            'action' => 'quest.' . $action,
            'target_type' => 'quest',
            'target_id' => $questId,
            'summary' => sprintf(
                '%s quest “%s” owned by @%s',
                $archiving ? 'Archived' : 'Restored',
                (string) $quest['title'],
                (string) $quest['owner_username'],
            ),
            'metadata' => [
                'from' => $current,
                'to' => $target,
                'owner_id' => (int) $quest['user_id'],
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $archiving
                ? 'Quest archived. It is hidden from the owner and excluded from their active board.'
                : 'Quest restored. The owner can see it again.',
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function setStatus(array $actor, int $targetId, string $status, string $ipAddress): array
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid account status.'];
        }

        $actorId = (int) $actor['id'];

        if ($targetId === $actorId) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot change your own account status.'];
        }

        $target = User::find($targetId);

        if ($target === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        $current = (string) $target['status'];

        if ($current === $status) {
            return ['ok' => true, 'message' => $status === 'active' ? 'This account is already active.' : 'This account is already suspended.'];
        }

        if (!User::setStatus($targetId, $status)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That account could not be updated.'];
        }

        if ($status === 'suspended') {
            User::revokeSessions($targetId);
            RememberToken::deleteForUser($targetId);
        }

        $action = $status === 'active' ? 'user.activated' : 'user.suspended';
        $summary = sprintf(
            '%s %s @%s',
            $status === 'active' ? 'Activated' : 'Suspended',
            (string) $target['display_name'],
            (string) $target['username'],
        );

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $targetId,
            'summary' => $summary,
            'metadata' => ['from' => $current, 'to' => $status],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $status === 'active'
                ? 'Account activated. The user can sign in again.'
                : 'Account suspended. Every active session was revoked.',
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function setRole(array $actor, int $targetId, string $role, string $ipAddress): array
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid account role.'];
        }

        $actorId = (int) $actor['id'];

        if ($targetId === $actorId) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot change your own administrator role.'];
        }

        $target = User::find($targetId);

        if ($target === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        $current = (string) $target['role'];

        if ($current === $role) {
            return ['ok' => true, 'message' => 'This account already has that role.'];
        }

        if ($current === 'admin' && $role === 'user' && User::countByRole('admin') <= 1) {
            return ['ok' => false, 'status' => 409, 'message' => 'The last administrator cannot be demoted.'];
        }

        if (!User::setRole($targetId, $role)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That account could not be updated.'];
        }

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => $role === 'admin' ? 'user.promoted' : 'user.demoted',
            'target_type' => 'user',
            'target_id' => $targetId,
            'summary' => sprintf(
                '%s %s @%s',
                $role === 'admin' ? 'Promoted' : 'Demoted',
                (string) $target['display_name'],
                (string) $target['username'],
            ),
            'metadata' => ['from' => $current, 'to' => $role],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $role === 'admin'
                ? 'Account promoted to administrator.'
                : 'Administrator access removed.',
        ];
    }

    /** @return list<array{date: string, total: int}> */
    private function completionSeries(int $days): array
    {
        $days = max(1, $days);
        $statement = Database::connection()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM xp_transactions
             WHERE created_at >= UTC_DATE() - INTERVAL ' . ($days - 1) . ' DAY
             GROUP BY DATE(created_at)
             ORDER BY day ASC',
        );
        $statement->execute();

        $counts = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['day']] = (int) $row['total'];
        }

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = gmdate('Y-m-d', strtotime('-' . $offset . ' days'));
            $series[] = ['date' => $date, 'total' => $counts[$date] ?? 0];
        }

        return $series;
    }

    private function scalar(string $sql): int
    {
        return (int) Database::connection()->query($sql)->fetchColumn();
    }
}
