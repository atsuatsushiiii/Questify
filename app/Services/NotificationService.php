<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use PDO;

/**
 * Builds and stores in-app notifications. Every event carries a stable key and
 * the table's unique (user_id, event_key) index makes creation idempotent.
 */
final class NotificationService
{
    public static function achievementUnlocked(PDO $db, int $userId, array $achievement): bool
    {
        $key = (string) ($achievement['achievement_key'] ?? '');

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'achievement',
            'title' => 'Achievement unlocked — ' . (string) ($achievement['name'] ?? 'Badge'),
            'message' => (string) ($achievement['description'] ?? 'A new achievement is yours.'),
            'icon' => (string) ($achievement['icon'] ?? 'trophy'),
            'url' => '/achievements',
            'related_type' => 'achievement',
            'related_id' => isset($achievement['id']) ? (int) $achievement['id'] : null,
            'event_key' => 'achievement:' . $key,
        ]);
    }

    public static function levelUp(PDO $db, int $userId, int $level, string $rank): bool
    {
        $level = max(1, $level);

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'level',
            'title' => 'Level ' . $level . ' reached',
            'message' => 'You are now a ' . $rank . '. Keep climbing.',
            'icon' => 'trending-up',
            'url' => '/dashboard',
            'event_key' => 'level:' . $level,
        ]);
    }

    public static function streakMilestone(PDO $db, int $userId, int $milestone, string $localDate): bool
    {
        $milestone = max(1, $milestone);

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'streak',
            'title' => $milestone . '-day streak',
            'message' => 'The flame is still alive. Complete a quest tomorrow to keep it burning.',
            'icon' => 'flame',
            'url' => '/profile',
            'event_key' => 'streak:' . $localDate . ':' . $milestone,
        ]);
    }

    public static function profileUpdated(PDO $db, int $userId): bool
    {
        return self::create($db, [
            'user_id' => $userId,
            'type' => 'profile',
            'title' => 'Profile updated',
            'message' => 'Your profile changes are live.',
            'icon' => 'user-round',
            'url' => '/profile',
            'event_key' => 'profile:' . gmdate('YmdHis') . ':' . bin2hex(random_bytes(4)),
        ]);
    }

    public static function leaderboardRankChanged(
        PDO $db,
        int $userId,
        int $oldRank,
        int $newRank,
        string $localDate,
    ): bool {
        if ($oldRank === $newRank || $oldRank < 1 || $newRank < 1) {
            return false;
        }

        $direction = $newRank < $oldRank ? 'climbed' : 'moved';

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'leaderboard',
            'title' => 'Leaderboard rank changed',
            'message' => 'You ' . $direction . ' from #' . $oldRank . ' to #' . $newRank . '.',
            'icon' => 'crown',
            'url' => '/leaderboard',
            'event_key' => 'leaderboard:rank:' . $localDate . ':' . $oldRank . '->' . $newRank,
        ]);
    }

    public static function passwordChanged(PDO $db, int $userId): bool
    {
        return self::create($db, [
            'user_id' => $userId,
            'type' => 'security',
            'title' => 'Password changed',
            'message' => 'Your password was updated and other signed-in devices were signed out.',
            'icon' => 'shield-check',
            'url' => '/settings',
            'event_key' => 'security:password:' . gmdate('YmdHis') . ':' . bin2hex(random_bytes(4)),
        ]);
    }

    public static function emailChanged(PDO $db, int $userId, string $newEmail): bool
    {
        return self::create($db, [
            'user_id' => $userId,
            'type' => 'security',
            'title' => 'Email address updated',
            'message' => 'Your sign-in email is now ' . $newEmail . '.',
            'icon' => 'mail',
            'url' => '/settings',
            'event_key' => 'security:email:' . gmdate('YmdHis') . ':' . bin2hex(random_bytes(4)),
        ]);
    }

    /**
     * Close the loop for the reporter without revealing the administrator's
     * private resolution note or the reported account's response.
     */
    public static function reportClosed(PDO $db, int $userId, int $reportId, string $targetLabel, bool $resolved): bool
    {
        $label = trim($targetLabel) !== '' ? trim($targetLabel) : 'the reported content';

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'report',
            'title' => $resolved ? 'Your report was resolved' : 'Your report was reviewed',
            'message' => $resolved
                ? 'An administrator resolved your report against ' . $label . '.'
                : 'An administrator reviewed your report against ' . $label . ' and took no further action.',
            'icon' => 'flag',
            'url' => '/reports',
            'related_type' => 'report',
            'related_id' => $reportId,
            'event_key' => 'report.closed:' . $reportId,
        ]);
    }

    /**
     * Send a moderator warning to a reported account. The reporter is never
     * identified and the private resolution note is never included.
     */
    public static function moderationWarning(
        PDO $db,
        int $userId,
        int $reportId,
        string $reasonLabel,
        ?string $message = null,
    ): bool
    {
        $body = 'An administrator reviewed a report about your account for ' . $reasonLabel . '.';
        $message = $message !== null ? trim($message) : '';

        if ($message !== '') {
            $body .= ' ' . $message;
        }

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'warning',
            'title' => 'Moderation warning',
            'message' => $body,
            'icon' => 'triangle-alert',
            'url' => '/notifications',
            'related_type' => 'report',
            'related_id' => $reportId,
            'event_key' => 'moderation.warning:report:' . $reportId,
        ]);
    }

    /**
     * Tell an account it has been suspended, and for how long. The notification
     * is stored even though the account cannot sign in until it ends.
     */
    public static function moderationSuspended(
        PDO $db,
        int $userId,
        ?int $reportId,
        string $reasonLabel,
        ?string $until,
        ?string $message = null,
    ): bool
    {
        $message = $message !== null ? trim($message) : '';
        $duration = $until === null || $until === ''
            ? 'indefinitely'
            : 'until ' . (format_datetime($until, 'UTC', 'j M Y, H:i') ?? $until) . ' UTC';
        $body = 'Your account was suspended ' . $duration . ' after a review for ' . $reasonLabel . '.';

        if ($message !== '') {
            $body .= ' ' . $message;
        }

        $key = $reportId !== null
            ? 'moderation.suspended:report:' . $reportId . ':' . ($until ?? 'permanent')
            : 'moderation.suspended:user:' . $userId . ':' . gmdate('YmdHis');

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'moderation',
            'title' => 'Account suspended',
            'message' => $body,
            'icon' => 'ban',
            'url' => '/notifications',
            'related_type' => $reportId !== null ? 'report' : 'user',
            'related_id' => $reportId ?? $userId,
            'event_key' => $key,
        ]);
    }

    /**
     * Tell an account that it has been limited to read-only access. The
     * account can still sign in, so this notification is immediately visible.
     */
    public static function moderationRestricted(
        PDO $db,
        int $userId,
        ?int $reportId,
        string $reasonLabel,
        ?string $message = null,
    ): bool {
        $message = $message !== null ? trim($message) : '';
        $body = 'An administrator limited your account to read-only access after a review for ' . $reasonLabel
            . '. You can still sign in and browse, but creating, editing, completing, archiving, restoring, or deleting quests and submitting reports is disabled.';

        if ($message !== '') {
            $body .= ' ' . $message;
        }

        $key = 'moderation.restricted:' . ($reportId !== null ? 'report:' . $reportId : 'user:' . $userId)
            . ':' . gmdate('YmdHis') . ':' . bin2hex(random_bytes(4));

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'moderation',
            'title' => 'Account limited to read-only',
            'message' => $body,
            'icon' => 'eye-off',
            'url' => '/notifications',
            'related_type' => $reportId !== null ? 'report' : 'user',
            'related_id' => $reportId ?? $userId,
            'event_key' => $key,
        ]);
    }

    /**
     * Tell an account that its read-only limit has been lifted.
     */
    public static function moderationUnrestricted(
        PDO $db,
        int $userId,
        ?int $reportId,
        string $reasonLabel,
        ?string $message = null,
    ): bool {
        $message = $message !== null ? trim($message) : '';
        $body = 'An administrator restored full access to your account after a review for ' . $reasonLabel
            . '. You can create, edit, complete, archive, restore, and delete quests again.';

        if ($message !== '') {
            $body .= ' ' . $message;
        }

        $key = 'moderation.unrestricted:' . ($reportId !== null ? 'report:' . $reportId : 'user:' . $userId)
            . ':' . gmdate('YmdHis') . ':' . bin2hex(random_bytes(4));

        return self::create($db, [
            'user_id' => $userId,
            'type' => 'moderation',
            'title' => 'Read-only limit lifted',
            'message' => $body,
            'icon' => 'shield-check',
            'url' => '/notifications',
            'related_type' => $reportId !== null ? 'report' : 'user',
            'related_id' => $reportId ?? $userId,
            'event_key' => $key,
        ]);
    }

    /**
     * @param array{user_id: int, type: string, title: string, message: string, icon: string, url?: string|null, related_type?: string|null, related_id?: int|null, event_key: string} $data
     */
    public static function create(PDO $db, array $data): bool
    {
        $type = mb_substr((string) $data['type'], 0, 32);

        if (!self::enabled($db, (int) $data['user_id'], $type)) {
            return false;
        }

        return Notification::create($db, [
            'user_id' => (int) $data['user_id'],
            'type' => $type,
            'title' => mb_substr((string) $data['title'], 0, 120),
            'message' => mb_substr((string) $data['message'], 0, 1000),
            'icon' => mb_substr((string) $data['icon'], 0, 64),
            'url' => isset($data['url']) ? mb_substr((string) $data['url'], 0, 255) : null,
            'related_type' => isset($data['related_type']) ? mb_substr((string) $data['related_type'], 0, 32) : null,
            'related_id' => isset($data['related_id']) ? (int) $data['related_id'] : null,
            'event_key' => mb_substr((string) $data['event_key'], 0, 120),
        ]);
    }

    /**
     * Achievement, level, streak, and leaderboard events can be muted from
     * Settings. Profile and security messages are always delivered.
     */
    private static function enabled(PDO $db, int $userId, string $type): bool
    {
        $column = match ($type) {
            'achievement' => 'notify_achievements',
            'level' => 'notify_level_ups',
            'streak' => 'notify_streaks',
            'leaderboard' => 'notify_leaderboard',
            default => null,
        };

        if ($column === null) {
            return true;
        }

        $statement = $db->prepare('SELECT ' . $column . ' FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $value = $statement->fetchColumn();

        return $value !== false && (int) $value === 1;
    }
}
