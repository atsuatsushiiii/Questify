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
use App\Models\Report;
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
    /** Suspension lengths offered in the moderation UI. */
    public const SUSPENSION_OPTIONS = [
        '1' => '1 day',
        '2' => '2 days',
        '3' => '3 days',
        '7' => '7 days',
        '14' => '14 days',
        '30' => '30 days',
        'permanent' => 'Permanent',
    ];

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
                'open_reports' => Report::openCount(),
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
     * @param array{search?: string, status?: string, target_type?: string, reason?: string} $filters
     * @return array{reports: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>}
     */
    public function reports(array $filters, int $page, int $perPage = 20): array
    {
        $total = Report::adminCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'reports' => Report::adminPaginate($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'statusCounts' => Report::statusCounts(),
        ];
    }

    /**
     * @return array{report: array<string, mixed>, target: array<string, mixed>|null, audit: list<array<string, mixed>>}|null
     */
    public function reportDetail(int $reportId): ?array
    {
        $report = Report::find($reportId);

        if ($report === null) {
            return null;
        }

        return [
            'report' => $report,
            'target' => $this->reportTarget($report),
            'audit' => AuditLog::forReport($reportId, 15),
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function resolveReport(array $actor, int $reportId, ?string $note, string $ipAddress): array
    {
        return $this->moderateReport($actor, $reportId, 'resolved', $note, $ipAddress);
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function dismissReport(array $actor, int $reportId, ?string $note, string $ipAddress): array
    {
        return $this->moderateReport($actor, $reportId, 'dismissed', $note, $ipAddress);
    }

    /**
     * @param array<string, mixed> $report
     * @return array{type: string, record: array<string, mixed>, profile_url: string|null, admin_url: string|null}|null
     */
    private function reportTarget(array $report): ?array
    {
        $targetId = (int) $report['target_id'];

        if ((string) $report['target_type'] === 'profile') {
            $user = User::find($targetId);

            if ($user === null) {
                return null;
            }

            unset($user['password_hash']);

            return [
                'type' => 'profile',
                'record' => $user,
                'profile_url' => (string) ($user['status'] ?? '') === 'active' && (int) ($user['show_profile'] ?? 0) === 1
                    ? '/u/' . rawurlencode((string) $user['username'])
                    : null,
                'admin_url' => '/admin/users/' . $targetId,
            ];
        }

        if ((string) $report['target_type'] === 'quest') {
            $quest = Quest::findForAdmin($targetId);

            if ($quest === null) {
                return null;
            }

            return [
                'type' => 'quest',
                'record' => $quest,
                'profile_url' => null,
                'admin_url' => '/admin/quests/' . $targetId,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    private function moderateReport(
        array $actor,
        int $reportId,
        string $status,
        ?string $note,
        string $ipAddress,
    ): array {
        if (!in_array($status, ['resolved', 'dismissed'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid report decision.'];
        }

        $note = $note !== null ? trim($note) : '';

        if (mb_strlen($note) > 1000) {
            return ['ok' => false, 'status' => 422, 'message' => 'The resolution note must be 1000 characters or fewer.'];
        }

        $report = Report::find($reportId);

        if ($report === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That report could not be found.'];
        }

        if ((string) $report['status'] !== 'open') {
            return ['ok' => true, 'message' => 'This report has already been closed.'];
        }

        if (!Report::close($reportId, (int) $actor['id'], $status, $note !== '' ? $note : null)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That report could not be updated.'];
        }

        $verb = $status === 'resolved' ? 'Resolved' : 'Dismissed';

        AuditLog::record([
            'actor_user_id' => (int) $actor['id'],
            'action' => 'report.' . $status,
            'target_type' => 'report',
            'target_id' => $reportId,
            'summary' => sprintf(
                '%s report #%d against %s “%s”',
                $verb,
                $reportId,
                (string) $report['target_type'],
                (string) $report['target_label'],
            ),
            'metadata' => [
                'from' => 'open',
                'to' => $status,
                'reason' => (string) $report['reason'],
                'target_type' => (string) $report['target_type'],
                'target_id' => (int) $report['target_id'],
                'target_label' => (string) $report['target_label'],
                'reporter_user_id' => $report['reporter_user_id'] !== null ? (int) $report['reporter_user_id'] : null,
                'resolution_note' => $note !== '' ? $note : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        if ($report['reporter_user_id'] !== null) {
            NotificationService::reportClosed(
                Database::connection(),
                (int) $report['reporter_user_id'],
                $reportId,
                (string) $report['target_label'],
                $status === 'resolved',
            );
        }

        return [
            'ok' => true,
            'message' => $status === 'resolved'
                ? 'Report resolved. The decision and note are recorded in the audit log.'
                : 'Report dismissed. The decision and note are recorded in the audit log.',
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
    public function setStatus(
        array $actor,
        int $targetId,
        string $status,
        string $ipAddress,
        ?string $duration = null,
        ?string $message = null,
    ): array
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid account status.'];
        }

        $duration = $duration === null || $duration === '' ? 'permanent' : $duration;

        if ($status === 'suspended' && !array_key_exists($duration, self::SUSPENSION_OPTIONS)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid suspension length.'];
        }

        $message = $message !== null ? trim($message) : '';

        if (mb_strlen($message) > 500) {
            return ['ok' => false, 'status' => 422, 'message' => 'The message to the account must be 500 characters or fewer.'];
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
        $currentUntil = isset($target['suspended_until']) && $target['suspended_until'] !== null
            ? (string) $target['suspended_until']
            : null;
        $suspendedUntil = $status === 'suspended' && $duration !== 'permanent'
            ? gmdate('Y-m-d H:i:s', time() + ((int) $duration) * 86400)
            : null;

        if ($current === $status && ($status === 'active' || $currentUntil === $suspendedUntil)) {
            return ['ok' => true, 'message' => $status === 'active' ? 'This account is already active.' : 'This account is already suspended for that length.'];
        }

        if (!User::setStatus($targetId, $status, $suspendedUntil)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That account could not be updated.'];
        }

        if ($status === 'suspended') {
            User::revokeSessions($targetId);
            RememberToken::deleteForUser($targetId);
            NotificationService::moderationSuspended(
                Database::connection(),
                $targetId,
                null,
                'community standards',
                $suspendedUntil,
                $message !== '' ? $message : null,
            );
        }

        $action = $status === 'active' ? 'user.activated' : 'user.suspended';
        $expiryLabel = $suspendedUntil !== null
            ? (format_datetime($suspendedUntil, 'UTC', 'j M Y, H:i') ?? $suspendedUntil) . ' UTC'
            : null;
        $summary = $status === 'active'
            ? sprintf('Activated %s @%s', (string) $target['display_name'], (string) $target['username'])
            : sprintf(
                'Suspended %s @%s %s',
                (string) $target['display_name'],
                (string) $target['username'],
                $expiryLabel !== null ? 'until ' . $expiryLabel : 'indefinitely',
            );

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $targetId,
            'summary' => $summary,
            'metadata' => [
                'from' => $current,
                'to' => $status,
                'suspended_until' => $suspendedUntil,
                'duration' => $status === 'suspended' ? $duration : null,
                'message' => $message !== '' ? $message : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $status === 'active'
                ? 'Account activated. The user can sign in again.'
                : ($expiryLabel !== null
                    ? 'Account suspended until ' . $expiryLabel . '. Every active session was revoked.'
                    : 'Account suspended indefinitely. Every active session was revoked.'),
        ];
    }

    /**
     * Limit an account to read-only access, or restore full access, without
     * suspending it. Sessions are intentionally preserved.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function setRestricted(
        array $actor,
        int $targetId,
        bool $restricted,
        string $ipAddress,
        ?string $message = null,
    ): array {
        $actorId = (int) $actor['id'];

        if ($targetId === $actorId) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot change your own read-only access.'];
        }

        $target = User::find($targetId);

        if ($target === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        unset($target['password_hash']);

        return $this->applyRestriction(
            $actor,
            $target,
            $restricted,
            null,
            'community standards',
            $message,
            $ipAddress,
        );
    }

    /**
     * Send a warning notification to the account named in an open profile report.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function warnReportedUser(array $actor, int $reportId, ?string $message, string $ipAddress): array
    {
        $context = $this->accountActionTarget($actor, $reportId);

        if (isset($context['error'])) {
            return $context['error'];
        }

        $report = $context['report'];
        $target = $context['target'];
        $message = $message !== null ? trim($message) : '';

        if (mb_strlen($message) > 500) {
            return ['ok' => false, 'status' => 422, 'message' => 'The message to the account must be 500 characters or fewer.'];
        }

        $reasonLabel = Report::REASONS[(string) $report['reason']] ?? ucfirst((string) $report['reason']);

        NotificationService::moderationWarning(
            Database::connection(),
            (int) $target['id'],
            $reportId,
            $reasonLabel,
            $message !== '' ? $message : null,
        );

        AuditLog::record([
            'actor_user_id' => (int) $actor['id'],
            'action' => 'user.warned',
            'target_type' => 'user',
            'target_id' => (int) $target['id'],
            'summary' => sprintf(
                'Warned %s @%s from report #%d',
                (string) $target['display_name'],
                (string) $target['username'],
                $reportId,
            ),
            'metadata' => [
                'report_id' => $reportId,
                'reason' => (string) $report['reason'],
                'message' => $message !== '' ? $message : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => 'Warning sent to @' . (string) $target['username'] . '. The reporter is not identified.',
        ];
    }

    /**
     * Suspend the account named in an open profile report for a chosen length.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function suspendReportedUser(
        array $actor,
        int $reportId,
        ?string $duration,
        ?string $message,
        string $ipAddress,
    ): array
    {
        $duration = $duration === null || $duration === '' ? 'permanent' : $duration;

        if (!array_key_exists($duration, self::SUSPENSION_OPTIONS)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Choose a valid suspension length.'];
        }

        $context = $this->accountActionTarget($actor, $reportId);

        if (isset($context['error'])) {
            return $context['error'];
        }

        $report = $context['report'];
        $target = $context['target'];
        $message = $message !== null ? trim($message) : '';

        if (mb_strlen($message) > 500) {
            return ['ok' => false, 'status' => 422, 'message' => 'The message to the account must be 500 characters or fewer.'];
        }

        $suspendedUntil = $duration === 'permanent'
            ? null
            : gmdate('Y-m-d H:i:s', time() + ((int) $duration) * 86400);

        if (!User::setStatus((int) $target['id'], 'suspended', $suspendedUntil)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That account could not be updated.'];
        }

        User::revokeSessions((int) $target['id']);
        RememberToken::deleteForUser((int) $target['id']);

        $reasonLabel = Report::REASONS[(string) $report['reason']] ?? ucfirst((string) $report['reason']);

        NotificationService::moderationSuspended(
            Database::connection(),
            (int) $target['id'],
            $reportId,
            $reasonLabel,
            $suspendedUntil,
            $message !== '' ? $message : null,
        );

        $expiryLabel = $suspendedUntil !== null
            ? (format_datetime($suspendedUntil, 'UTC', 'j M Y, H:i') ?? $suspendedUntil) . ' UTC'
            : null;

        AuditLog::record([
            'actor_user_id' => (int) $actor['id'],
            'action' => 'user.suspended',
            'target_type' => 'user',
            'target_id' => (int) $target['id'],
            'summary' => sprintf(
                'Suspended %s @%s %s from report #%d',
                (string) $target['display_name'],
                (string) $target['username'],
                $expiryLabel !== null ? 'until ' . $expiryLabel : 'indefinitely',
                $reportId,
            ),
            'metadata' => [
                'report_id' => $reportId,
                'reason' => (string) $report['reason'],
                'duration' => $duration,
                'suspended_until' => $suspendedUntil,
                'message' => $message !== '' ? $message : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $expiryLabel !== null
                ? 'Account suspended until ' . $expiryLabel . '. Every active session was revoked.'
                : 'Account suspended indefinitely. Every active session was revoked.',
        ];
    }

    /**
     * Limit the account named in an open profile report to read-only access.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function restrictReportedUser(
        array $actor,
        int $reportId,
        ?string $message,
        string $ipAddress,
    ): array {
        $context = $this->accountActionTarget($actor, $reportId);

        if (isset($context['error'])) {
            return $context['error'];
        }

        $report = $context['report'];
        $reasonLabel = Report::REASONS[(string) $report['reason']] ?? ucfirst((string) $report['reason']);

        return $this->applyRestriction(
            $actor,
            $context['target'],
            true,
            $reportId,
            $reasonLabel,
            $message,
            $ipAddress,
        );
    }

    /**
     * Restore full access for the account named in an open profile report.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function unrestrictReportedUser(
        array $actor,
        int $reportId,
        ?string $message,
        string $ipAddress,
    ): array {
        $context = $this->accountActionTarget($actor, $reportId);

        if (isset($context['error'])) {
            return $context['error'];
        }

        $report = $context['report'];
        $reasonLabel = Report::REASONS[(string) $report['reason']] ?? ucfirst((string) $report['reason']);

        return $this->applyRestriction(
            $actor,
            $context['target'],
            false,
            $reportId,
            $reasonLabel,
            $message,
            $ipAddress,
        );
    }

    /**
     * Shared restriction transition: guard, persist, notify, and audit.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $target
     * @return array{ok: bool, status?: int, message: string}
     */
    private function applyRestriction(
        array $actor,
        array $target,
        bool $restricted,
        ?int $reportId,
        string $reasonLabel,
        ?string $message,
        string $ipAddress,
    ): array {
        $actorId = (int) $actor['id'];
        $targetId = (int) $target['id'];

        if ($targetId === $actorId) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot change your own read-only access.'];
        }

        $message = $message !== null ? trim($message) : '';

        if (mb_strlen($message) > 500) {
            return ['ok' => false, 'status' => 422, 'message' => 'The message to the account must be 500 characters or fewer.'];
        }

        $current = User::isRestricted($target);

        if ($current === $restricted) {
            return [
                'ok' => true,
                'message' => $restricted
                    ? 'This account is already limited to read-only access.'
                    : 'This account already has full access.',
            ];
        }

        if (!User::setRestricted($targetId, $restricted)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That account could not be updated.'];
        }

        if ($restricted) {
            NotificationService::moderationRestricted(
                Database::connection(),
                $targetId,
                $reportId,
                $reasonLabel,
                $message !== '' ? $message : null,
            );
        } else {
            NotificationService::moderationUnrestricted(
                Database::connection(),
                $targetId,
                $reportId,
                $reasonLabel,
                $message !== '' ? $message : null,
            );
        }

        $reportSuffix = $reportId !== null ? ' from report #' . $reportId : '';
        $summary = $restricted
            ? sprintf(
                'Limited %s @%s to read-only access%s',
                (string) $target['display_name'],
                (string) $target['username'],
                $reportSuffix,
            )
            : sprintf(
                'Restored full access for %s @%s%s',
                (string) $target['display_name'],
                (string) $target['username'],
                $reportSuffix,
            );

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => $restricted ? 'user.restricted' : 'user.unrestricted',
            'target_type' => 'user',
            'target_id' => $targetId,
            'summary' => $summary,
            'metadata' => [
                'report_id' => $reportId,
                'reason' => $reasonLabel,
                'read_only' => $restricted,
                'message' => $message !== '' ? $message : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => $restricted
                ? 'Account limited to read-only access. It keeps its sessions and can still browse.'
                : 'Full access restored. The account can create and complete quests again.',
        ];
    }

    /**
     * Resolve an open profile report and its target for account actions.
     *
     * @param array<string, mixed> $actor
     * @return array{report?: array<string, mixed>, target?: array<string, mixed>, error?: array{ok: false, status: int, message: string}}
     */
    private function accountActionTarget(array $actor, int $reportId): array
    {
        $report = Report::find($reportId);

        if ($report === null) {
            return ['error' => ['ok' => false, 'status' => 404, 'message' => 'That report could not be found.']];
        }

        if ((string) $report['status'] !== 'open') {
            return ['error' => ['ok' => false, 'status' => 409, 'message' => 'Only open reports can trigger account actions.']];
        }

        if ((string) $report['target_type'] !== 'profile') {
            return ['error' => ['ok' => false, 'status' => 422, 'message' => 'Account actions are only available for profile reports.']];
        }

        if ((int) $report['target_id'] === (int) $actor['id']) {
            return ['error' => ['ok' => false, 'status' => 409, 'message' => 'You cannot moderate your own account from a report.']];
        }

        $target = User::find((int) $report['target_id']);

        if ($target === null) {
            return ['error' => ['ok' => false, 'status' => 404, 'message' => 'The reported account no longer exists.']];
        }

        unset($target['password_hash']);

        return ['report' => $report, 'target' => $target];
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
