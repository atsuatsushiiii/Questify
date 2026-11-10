<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Explicit SQL for the users table. Every statement is prepared.
 */
final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /** @return array<string, mixed>|null */
    public static function findByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1',
        );
        $statement->execute([mb_strtolower($login), $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
        );
        $statement->execute([mb_strtolower($email)]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /** @return array<string, mixed>|null */
    public static function findByGoogleId(string $googleId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE google_id = ? LIMIT 1',
        );
        $statement->execute([$googleId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public static function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $statement->execute([mb_strtolower($email)]);

        return (bool) $statement->fetchColumn();
    }

    public static function usernameExists(string $username): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Public profile lookup. Hidden profiles and suspended accounts return null
     * so they are indistinguishable from a missing account.
     *
     * @return array<string, mixed>|null
     */
    public static function findPublicByUsername(string $username): ?array
    {
        self::liftExpiredSuspensions();

        $statement = Database::connection()->prepare(
            "SELECT id, username, display_name, avatar_path, xp_total,
                    current_streak, longest_streak, last_streak_date, timezone, created_at
             FROM users
             WHERE username = ? AND status = 'active' AND show_profile = 1
             LIMIT 1",
        );
        $statement->execute([$username]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /**
     * Reportable profile lookup by id. Hidden profiles and suspended accounts
     * are deliberately indistinguishable from a missing account.
     *
     * @return array<string, mixed>|null
     */
    public static function findPublicById(int $id): ?array
    {
        self::liftExpiredSuspensions();

        $statement = Database::connection()->prepare(
            "SELECT id, username, display_name, avatar_path, status, show_profile, created_at
             FROM users
             WHERE id = ? AND status = 'active' AND show_profile = 1
             LIMIT 1",
        );
        $statement->execute([$id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /**
     * @param array{username: string, email: string, password_hash: string, display_name: string, timezone: string} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, timezone, role, status, password_set_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );

        $statement->execute([
            $data['username'],
            mb_strtolower($data['email']),
            $data['password_hash'],
            $data['display_name'],
            $data['timezone'],
            'user',
            'active',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Lock the account row so concurrent XP awards serialize on the same
     * balance instead of overwriting each other.
     *
     * @return array<string, mixed>|null
     */
    public static function findForUpdate(PDO $db, int $id): ?array
    {
        $statement = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public static function updateProgression(PDO $db, int $id, int $xpTotal, int $level): void
    {
        $statement = $db->prepare(
            'UPDATE users SET xp_total = ?, level = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([max(0, $xpTotal), max(1, $level), $id]);
    }

    public static function updateStreak(PDO $db, int $id, int $current, int $longest, string $lastDate): void
    {
        $statement = $db->prepare(
            'UPDATE users
             SET current_streak = ?, longest_streak = ?, last_streak_date = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([max(0, $current), max(0, $longest), $lastDate, $id]);
    }

    public static function updateProfile(
        int $id,
        string $displayName,
        string $timezone,
        bool $leaderboardVisible,
        bool $profileVisible,
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET display_name = ?, timezone = ?, show_on_leaderboard = ?, show_profile = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([
            $displayName,
            $timezone,
            $leaderboardVisible ? 1 : 0,
            $profileVisible ? 1 : 0,
            $id,
        ]);
    }

    public static function updateAvatar(int $id, ?string $path): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET avatar_path = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$path, $id]);
    }

    public static function updateEmail(int $id, string $email): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET email = ?, email_verified_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([mb_strtolower($email), $id]);
    }

    public static function updateNotificationPreferences(
        int $id,
        bool $achievements,
        bool $levelUps,
        bool $streaks,
        bool $leaderboard,
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET notify_achievements = ?,
                 notify_level_ups = ?,
                 notify_streaks = ?,
                 notify_leaderboard = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([
            $achievements ? 1 : 0,
            $levelUps ? 1 : 0,
            $streaks ? 1 : 0,
            $leaderboard ? 1 : 0,
            $id,
        ]);
    }

    public static function updateLeaderboardRank(PDO $db, int $id, int $rank): void
    {
        $statement = $db->prepare(
            'UPDATE users
             SET last_leaderboard_rank = ?, last_leaderboard_checked_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([max(1, $rank), $id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$hash, $id]);
    }

    /**
     * Mark that the account owner now knows a usable password. Google-created
     * accounts start with a placeholder hash and no password set.
     */
    public static function markPasswordSet(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET password_set_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$id]);
    }

    /**
     * @param array{username: string, email: string, password_hash: string, display_name: string, timezone: string, google_id: string, google_email: string} $data
     */
    public static function createFromGoogle(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users
                (username, email, password_hash, display_name, timezone, role, status,
                 google_id, google_email, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );

        $statement->execute([
            $data['username'],
            mb_strtolower($data['email']),
            $data['password_hash'],
            $data['display_name'],
            $data['timezone'],
            'user',
            'active',
            $data['google_id'],
            mb_strtolower($data['google_email']),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function linkGoogle(int $id, string $googleId, string $googleEmail): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET google_id = ?, google_email = ?, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([$googleId, mb_strtolower($googleEmail), $id]);
    }

    public static function updateGoogleEmail(int $id, string $googleEmail): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET google_email = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([mb_strtolower($googleEmail), $id]);
    }

    public static function disconnectGoogle(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET google_id = NULL, google_email = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$id]);
    }

    public static function touchLogin(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$id]);
    }

    /**
     * Revoke every session for a user, e.g. after a password reset.
     */
    public static function revokeSessions(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE user_id = ?');
        $statement->execute([$id]);
    }

    /**
     * Permanently delete an account. All owned rows cascade from the foreign
     * keys defined by the schema.
     */
    public static function delete(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM users WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function countByStatus(string $status): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
        $statement->execute([$status]);

        return (int) $statement->fetchColumn();
    }

    public static function countByRole(string $role): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $statement->execute([$role]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Admin user directory with search, role/status filters, and pagination.
     *
     * @param array{search?: string, role?: string, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function adminPaginate(array $filters, int $page, int $perPage = 20): array
    {
        [$where, $parameters] = self::adminCriteria($filters);
        $offset = (max(1, $page) - 1) * $perPage;

        $statement = Database::connection()->prepare(
            'SELECT id, username, email, display_name, avatar_path, role, status,
                    suspended_until, restricted_at,
                    xp_total, level, current_streak, longest_streak, created_at, updated_at, last_login_at
             FROM users'
            . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{search?: string, role?: string, status?: string} $filters
     */
    public static function adminCount(array $filters): int
    {
        [$where, $parameters] = self::adminCriteria($filters);

        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM users' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array{date: string, total: int}>
     */
    public static function signupSeries(int $days): array
    {
        $days = max(1, $days);
        $statement = Database::connection()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM users
             WHERE created_at >= UTC_DATE() - INTERVAL ' . ($days - 1) . ' DAY
             GROUP BY DATE(created_at)
             ORDER BY day ASC',
        );
        $statement->execute();

        $counts = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['day']] = (int) $row['total'];
        }

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = gmdate('Y-m-d', strtotime('-' . $offset . ' days'));
            $series[] = ['date' => $date, 'total' => $counts[$date] ?? 0];
        }

        return $series;
    }

    /**
     * Set the account status. A suspended account can carry an expiry; active
     * accounts always clear it.
     */
    public static function setStatus(int $id, string $status, ?string $suspendedUntil = null): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET status = ?, suspended_until = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );

        return $statement->execute([
            $status,
            $status === 'suspended' ? $suspendedUntil : null,
            $id,
        ]);
    }

    /**
     * Lift every suspension whose expiry has passed. Called on login and on
     * public-profile reads so a timed suspension ends even without an admin.
     */
    public static function liftExpiredSuspensions(): void
    {
        $statement = Database::connection()->prepare(
            "UPDATE users
             SET status = 'active', suspended_until = NULL, updated_at = UTC_TIMESTAMP()
             WHERE status = 'suspended'
               AND suspended_until IS NOT NULL
               AND suspended_until <= UTC_TIMESTAMP()",
        );
        $statement->execute();
    }

    /**
     * Lift a single suspension if it has expired, returning whether it changed.
     */
    public static function liftSuspensionIfExpired(int $id): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE users
             SET status = 'active', suspended_until = NULL, updated_at = UTC_TIMESTAMP()
             WHERE id = ?
               AND status = 'suspended'
               AND suspended_until IS NOT NULL
               AND suspended_until <= UTC_TIMESTAMP()",
        );
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function suspensionExpired(array $user): bool
    {
        if ((string) ($user['status'] ?? '') !== 'suspended') {
            return false;
        }

        $until = $user['suspended_until'] ?? null;

        return is_string($until)
            && $until !== ''
            && strtotime($until . ' UTC') !== false
            && strtotime($until . ' UTC') <= time();
    }

    /**
     * Limit an account to read-only access without suspending it. The account
     * keeps its sessions and can still sign in, browse, and manage settings.
     */
    public static function setRestricted(int $id, bool $restricted): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET restricted_at = ' . ($restricted ? 'UTC_TIMESTAMP()' : 'NULL') . ',
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );

        return $statement->execute([$id]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isRestricted(array $user): bool
    {
        return isset($user['restricted_at'])
            && $user['restricted_at'] !== null
            && $user['restricted_at'] !== '';
    }

    public static function setRole(int $id, string $role): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET role = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$role, $id]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{search?: string, role?: string, status?: string} $filters
     * @return array{0: string, 1: list<string>}
     */
    private static function adminCriteria(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $clauses[] = '(username LIKE ? OR email LIKE ? OR display_name LIKE ?)';
            $like = '%' . $search . '%';
            $parameters[] = $like;
            $parameters[] = $like;
            $parameters[] = $like;
        }

        if (in_array($filters['role'] ?? '', ['user', 'admin'], true)) {
            $clauses[] = 'role = ?';
            $parameters[] = $filters['role'];
        }

        if (($filters['status'] ?? '') === 'restricted') {
            $clauses[] = 'restricted_at IS NOT NULL';
        } elseif (in_array($filters['status'] ?? '', ['active', 'suspended'], true)) {
            $clauses[] = 'status = ?';
            $parameters[] = $filters['status'];
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
