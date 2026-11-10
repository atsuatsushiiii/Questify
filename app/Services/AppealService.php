<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\User;
use Throwable;

/**
 * Read-only account appeals.
 *
 * A restricted account can ask for a review once at a time, and a moderator
 * either accepts the appeal (lifting the limit) or denies it. Every transition
 * is audited and the account is notified. The decision note is shared with the
 * account so the outcome is never unexplained.
 */
final class AppealService
{
    public const MIN_MESSAGE = 20;

    public const MAX_MESSAGE = 2000;

    /** A new appeal can be filed one day after the previous decision. */
    public const COOLDOWN_SECONDS = 86400;

    /**
     * @param array{search?: string, status?: string} $filters
     * @return array{appeals: list<array<string, mixed>>, total: int, page: int, perPage: int, pages: int, statusCounts: array<string, int>}
     */
    public function queue(array $filters, int $page, int $perPage = 20): array
    {
        $total = Appeal::adminCount($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'appeals' => Appeal::adminPaginate($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'statusCounts' => Appeal::statusCounts(),
        ];
    }

    /**
     * @return array{appeal: array<string, mixed>, history: list<array<string, mixed>>, audit: list<array<string, mixed>>}|null
     */
    public function detail(int $appealId): ?array
    {
        $appeal = Appeal::find($appealId);

        if ($appeal === null) {
            return null;
        }

        return [
            'appeal' => $appeal,
            'history' => Appeal::latestForUser((int) $appeal['user_id'], 8),
            'audit' => AuditLog::forTarget('appeal', $appealId, 15),
        ];
    }

    /**
     * File an appeal for the signed-in read-only account.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function submit(array $actor, string $message, string $ipAddress): array
    {
        $userId = (int) $actor['id'];
        $user = User::find($userId);

        if ($user === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        if (!User::isRestricted($user)) {
            return ['ok' => false, 'status' => 409, 'message' => 'Only read-only accounts can request a review.'];
        }

        $message = trim($message);
        $length = mb_strlen($message);

        if ($length < self::MIN_MESSAGE) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Explain your situation in at least ' . self::MIN_MESSAGE . ' characters.',
            ];
        }

        if ($length > self::MAX_MESSAGE) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Keep your appeal to ' . self::MAX_MESSAGE . ' characters or fewer.',
            ];
        }

        if (Appeal::findOpenForUser($userId) !== null) {
            return ['ok' => false, 'status' => 409, 'message' => 'You already have an appeal waiting for review.'];
        }

        if (Appeal::hasRecentDecision($userId, self::COOLDOWN_SECONDS)) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'A decision was made in the last 24 hours. You can submit another appeal after that.',
            ];
        }

        $appealId = Appeal::create([
            'user_id' => $userId,
            'message' => $message,
        ]);

        if ($appealId < 1) {
            return ['ok' => false, 'status' => 409, 'message' => 'Your appeal could not be submitted.'];
        }

        AuditLog::record([
            'actor_user_id' => $userId,
            'action' => 'appeal.submitted',
            'target_type' => 'appeal',
            'target_id' => $appealId,
            'summary' => sprintf('Submitted appeal #%d for read-only access', $appealId),
            'metadata' => [
                'user_id' => $userId,
                'status' => 'open',
            ],
            'ip_address' => $ipAddress,
        ]);

        NotificationService::appealReceived(Database::connection(), $userId, $appealId);

        return [
            'ok' => true,
            'message' => 'Your appeal was submitted. A moderator will review it and reply in your notifications.',
        ];
    }

    /**
     * Accept or deny an open appeal.
     *
     * @param array<string, mixed> $actor
     * @return array{ok: bool, status?: int, message: string}
     */
    public function resolve(
        array $actor,
        int $appealId,
        bool $accept,
        ?string $note,
        string $ipAddress,
    ): array {
        $appeal = Appeal::find($appealId);

        if ($appeal === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That appeal could not be found.'];
        }

        if ((string) $appeal['status'] !== 'open') {
            return ['ok' => false, 'status' => 409, 'message' => 'Only open appeals can be reviewed.'];
        }

        $userId = (int) $appeal['user_id'];
        $actorId = (int) $actor['id'];

        if ($userId === $actorId) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot review your own appeal.'];
        }

        $note = $note !== null ? trim($note) : '';

        if (mb_strlen($note) > 1000) {
            return ['ok' => false, 'status' => 422, 'message' => 'The decision note must be 1000 characters or fewer.'];
        }

        $target = User::find($userId);

        if ($target === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'The appealing account no longer exists.'];
        }

        $wasRestricted = User::isRestricted($target);
        $status = $accept ? 'accepted' : 'denied';

        $db = Database::connection();
        $db->beginTransaction();

        try {
            if (!Appeal::close($appealId, $actorId, $status, $note !== '' ? $note : null)) {
                $db->rollBack();

                return ['ok' => false, 'status' => 409, 'message' => 'That appeal was already reviewed.'];
            }

            if ($accept && $wasRestricted && !User::setRestricted($userId, false)) {
                $db->rollBack();

                return ['ok' => false, 'status' => 409, 'message' => 'The account could not be updated.'];
            }

            $db->commit();
        } catch (Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return ['ok' => false, 'status' => 409, 'message' => 'That appeal could not be reviewed.'];
        }

        $userLabel = sprintf('%s @%s', (string) $target['display_name'], (string) $target['username']);

        if ($accept) {
            NotificationService::appealAccepted(
                $db,
                $userId,
                $appealId,
                $note !== '' ? $note : null,
            );

            AuditLog::record([
                'actor_user_id' => $actorId,
                'action' => 'appeal.accepted',
                'target_type' => 'appeal',
                'target_id' => $appealId,
                'summary' => sprintf('Accepted appeal #%d from %s', $appealId, $userLabel),
                'metadata' => [
                    'user_id' => $userId,
                    'status' => 'accepted',
                    'note' => $note !== '' ? $note : null,
                ],
                'ip_address' => $ipAddress,
            ]);

            if ($wasRestricted) {
                AuditLog::record([
                    'actor_user_id' => $actorId,
                    'action' => 'user.unrestricted',
                    'target_type' => 'user',
                    'target_id' => $userId,
                    'summary' => sprintf('Restored full access for %s after appeal #%d', $userLabel, $appealId),
                    'metadata' => [
                        'appeal_id' => $appealId,
                        'reason' => 'appeal',
                        'read_only' => false,
                    ],
                    'ip_address' => $ipAddress,
                ]);
            }

            return [
                'ok' => true,
                'message' => $wasRestricted
                    ? 'Appeal accepted. Full access was restored.'
                    : 'Appeal accepted. The account already had full access.',
            ];
        }

        NotificationService::appealDenied(
            $db,
            $userId,
            $appealId,
            $note !== '' ? $note : null,
        );

        AuditLog::record([
            'actor_user_id' => $actorId,
            'action' => 'appeal.denied',
            'target_type' => 'appeal',
            'target_id' => $appealId,
            'summary' => sprintf('Denied appeal #%d from %s', $appealId, $userLabel),
            'metadata' => [
                'user_id' => $userId,
                'status' => 'denied',
                'note' => $note !== '' ? $note : null,
            ],
            'ip_address' => $ipAddress,
        ]);

        return [
            'ok' => true,
            'message' => 'Appeal denied. The read-only limit stays in place.',
        ];
    }
}
