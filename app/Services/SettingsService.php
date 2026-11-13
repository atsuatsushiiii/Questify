<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Session;
use App\Core\Validator;
use App\Models\EmailChangeRequest;
use App\Models\RememberToken;
use App\Models\User;
use App\Models\UserSession;
use PDOException;
use Throwable;

/**
 * Account settings: password changes, verified email changes, notification
 * preferences, active sessions, data export, and account deletion.
 *
 * Every mutation is owner-scoped by the authenticated user id. Email changes
 * require a single-use token delivered to the new address.
 */
final class SettingsService
{
    private const EMAIL_CHANGE_TOKEN_BYTES = 32;

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, message?: string}
     */
    public function changePassword(array $user, array $input): array
    {
        $userId = (int) $user['id'];
        $minPassword = (int) config('auth.min_password_length', 10);
        $maxPassword = (int) config('auth.max_password_length', 72);

        $validator = Validator::make(
            $input,
            [
                'current_password' => 'required|string|max:200',
                'password' => "required|string|min:{$minPassword}|max:{$maxPassword}|password",
                'password_confirmation' => 'required|string|same:password',
            ],
            [
                'current_password' => 'Current password',
                'password' => 'New password',
                'password_confirmation' => 'Password confirmation',
            ],
        );

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'message' => 'Please fix the highlighted security fields.',
            ];
        }

        $data = $validator->validated();
        $stored = User::find($userId);

        if ($stored === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        if (!password_verify((string) $data['current_password'], (string) $stored['password_hash'])) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['current_password' => 'That is not your current password.'],
                'message' => 'Please fix the highlighted security fields.',
            ];
        }

        $newPassword = (string) $data['password'];

        if (password_verify($newPassword, (string) $stored['password_hash'])) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['password' => 'Choose a password different from your current one.'],
                'message' => 'Please fix the highlighted security fields.',
            ];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::updatePassword($userId, $hash);
            NotificationService::passwordChanged($db, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        // A password change invalidates every other credential. The current
        // session is rotated instead of being destroyed; its previous row is
        // removed after rotation so it cannot linger in the session list.
        RememberToken::deleteForUser($userId);

        $refreshed = User::find($userId);

        if ($refreshed !== null) {
            Auth::login($refreshed);
        }

        UserSession::deleteForUserExcept($userId, Session::storageKey());

        return [
            'ok' => true,
            'message' => 'Password updated. Other devices have been signed out.',
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, message?: string}
     */
    public function requestEmailChange(array $user, array $input): array
    {
        $userId = (int) $user['id'];

        $validator = Validator::make(
            $input,
            [
                'new_email' => 'required|string|email|max:190',
                'current_password' => 'required|string|max:200',
            ],
            [
                'new_email' => 'New email address',
                'current_password' => 'Current password',
            ],
        );

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'message' => 'Please fix the highlighted email fields.',
            ];
        }

        $stored = User::find($userId);

        if ($stored === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        $data = $validator->validated();

        if (!password_verify((string) $data['current_password'], (string) $stored['password_hash'])) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['current_password' => 'That is not your current password.'],
                'message' => 'Please fix the highlighted email fields.',
            ];
        }

        $newEmail = mb_strtolower(trim((string) $data['new_email']));

        if ($newEmail === mb_strtolower((string) $stored['email'])) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['new_email' => 'That is already your email address.'],
                'message' => 'Please fix the highlighted email fields.',
            ];
        }

        if (User::emailExists($newEmail)) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['new_email' => 'That email address is already registered.'],
                'message' => 'Please fix the highlighted email fields.',
            ];
        }

        $perHour = max(1, (int) config('auth.email_change_per_hour', 3));

        if (EmailChangeRequest::countRecentForUser($userId, 3600) >= $perHour) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['new_email' => 'Too many email change requests. Try again in an hour.'],
                'message' => 'Please wait before requesting another confirmation link.',
            ];
        }

        $ttl = max(300, (int) config('auth.email_change_ttl', 3600));
        $token = bin2hex(random_bytes(self::EMAIL_CHANGE_TOKEN_BYTES));

        $requestId = EmailChangeRequest::create(
            $userId,
            $newEmail,
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + $ttl),
        );

        try {
            (new MailService())->sendEmailChange($stored, $newEmail, $token);
        } catch (Throwable $exception) {
            EmailChangeRequest::deleteById($requestId);
            ErrorHandler::report($exception);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'The confirmation email could not be sent. Try again later.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Confirmation link sent to ' . $newEmail . '. Open it to finish the change.',
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, status?: int, errors?: array<string, string>, message?: string}
     */
    public function confirmEmailChange(array $user, string $token): array
    {
        $userId = (int) $user['id'];
        $token = trim($token);

        if (strlen($token) !== self::EMAIL_CHANGE_TOKEN_BYTES * 2 || !ctype_xdigit($token)) {
            return ['ok' => false, 'status' => 422, 'message' => 'This confirmation link is invalid.'];
        }

        $request = EmailChangeRequest::findValidByHash(hash('sha256', $token));

        if ($request === null || (int) $request['user_id'] !== $userId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This confirmation link is invalid or has expired. Request a new one from Settings.',
            ];
        }

        $latest = EmailChangeRequest::latestForUser($userId);

        if ($latest === null || (int) $latest['id'] !== (int) $request['id']) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This confirmation link has been superseded by a newer request. Open the latest email or request another link.',
            ];
        }

        $newEmail = mb_strtolower((string) $request['new_email']);

        if (User::emailExists($newEmail)) {
            EmailChangeRequest::deleteForUser($userId);

            return [
                'ok' => false,
                'status' => 422,
                'message' => 'That email address was registered by another account. Request a different address.',
            ];
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            if (!EmailChangeRequest::consume((int) $request['id'])) {
                $db->rollBack();

                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'This confirmation link has already been used or has expired.',
                ];
            }

            User::updateEmail($userId, $newEmail);
            EmailChangeRequest::deleteForUser($userId);
            NotificationService::emailChanged($db, $userId, $newEmail);
            $db->commit();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if (($exception->errorInfo[1] ?? null) === 1062) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'That email address is already registered.',
                ];
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'message' => 'Email address updated to ' . $newEmail . '. Use it the next time you sign in.',
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string}
     */
    public function updateNotifications(array $user, array $input): array
    {
        $truthy = static fn (mixed $value): bool => in_array(
            strtolower(is_string($value) ? $value : ''),
            ['1', 'on', 'yes', 'true'],
            true,
        );

        User::updateNotificationPreferences(
            (int) $user['id'],
            $truthy($input['notify_achievements'] ?? false),
            $truthy($input['notify_level_ups'] ?? false),
            $truthy($input['notify_streaks'] ?? false),
            $truthy($input['notify_leaderboard'] ?? false),
        );

        return ['ok' => true, 'message' => 'Notification preferences saved.'];
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function sessions(array $user): array
    {
        $current = Session::storageKey();
        $rows = UserSession::forUser((int) $user['id']);

        foreach ($rows as $index => $row) {
            $agent = (string) ($row['user_agent'] ?? '');
            $rows[$index]['is_current'] = hash_equals($current, (string) $row['id']);
            $rows[$index]['is_mobile'] = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $agent) === 1;
            $rows[$index]['last_activity_at'] = gmdate('Y-m-d H:i:s', (int) $row['last_activity']);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, status?: int, message: string}
     */
    public function revokeSession(array $user, string $sessionId): array
    {
        if (strlen($sessionId) !== 64 || !ctype_xdigit($sessionId)) {
            return ['ok' => false, 'status' => 404, 'message' => 'That session could not be found.'];
        }

        if (hash_equals(Session::storageKey(), $sessionId)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'You cannot revoke the session you are currently using.',
            ];
        }

        if (!UserSession::deleteOwned((int) $user['id'], $sessionId)) {
            return ['ok' => false, 'status' => 404, 'message' => 'That session is no longer active.'];
        }

        return ['ok' => true, 'message' => 'Session signed out.'];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, message: string, revoked: int}
     */
    public function revokeOtherSessions(array $user): array
    {
        $revoked = UserSession::deleteForUserExcept((int) $user['id'], Session::storageKey());

        return [
            'ok' => true,
            'revoked' => $revoked,
            'message' => $revoked === 0
                ? 'No other active sessions were found.'
                : $revoked . ' other ' . ($revoked === 1 ? 'session was' : 'sessions were') . ' signed out.',
        ];
    }

    /**
     * Build a portable export without password hashes, session payloads, reset
     * tokens, or remember-me secrets.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function exportData(array $user): array
    {
        $userId = (int) $user['id'];
        $db = Database::connection();

        $statement = $db->prepare(
            'SELECT id, username, email, email_verified_at, display_name, bio, timezone, role, status,
                    xp_total, level, current_streak, longest_streak, last_streak_date,
                    show_on_leaderboard, show_profile, notify_achievements, notify_level_ups, notify_streaks,
                    notify_leaderboard, avatar_path, created_at, updated_at, last_login_at
             FROM users WHERE id = ? LIMIT 1',
        );
        $statement->execute([$userId]);
        $account = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        $fetch = static function (string $sql, int $id) use ($db): array {
            $query = $db->prepare($sql);
            $query->execute([$id]);

            return $query->fetchAll(\PDO::FETCH_ASSOC);
        };

        $achievements = $fetch(
            'SELECT a.achievement_key, a.name, a.description, a.category, a.criteria_type, a.threshold,
                    ua.unlocked_at
             FROM user_achievements ua
             INNER JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = ?
             ORDER BY ua.unlocked_at ASC, a.id ASC',
            $userId,
        );

        $sessions = [];

        foreach ($this->sessions($user) as $session) {
            $sessions[] = [
                'ip_address' => $session['ip_address'] ?? null,
                'user_agent' => $session['user_agent'] ?? null,
                'last_activity' => $session['last_activity_at'] ?? null,
                'created_at' => $session['created_at'] ?? null,
                'current' => (bool) ($session['is_current'] ?? false),
            ];
        }

        return [
            'exported_at' => gmdate('c'),
            'account' => $account,
            'quests' => $fetch('SELECT * FROM quests WHERE user_id = ? ORDER BY created_at ASC, id ASC', $userId),
            'xp_transactions' => $fetch(
                'SELECT * FROM xp_transactions WHERE user_id = ? ORDER BY created_at ASC, id ASC',
                $userId,
            ),
            'achievements' => $achievements,
            'streak_activity' => $fetch(
                'SELECT * FROM streak_activity WHERE user_id = ? ORDER BY activity_date ASC',
                $userId,
            ),
            'notifications' => $fetch(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at ASC, id ASC',
                $userId,
            ),
            'sessions' => $sessions,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, message?: string}
     */
    public function deleteAccount(array $user, array $input): array
    {
        $userId = (int) $user['id'];

        $validator = Validator::make(
            $input,
            [
                'current_password' => 'required|string|max:200',
                'confirmation' => 'required|string|max:40',
            ],
            [
                'current_password' => 'Current password',
                'confirmation' => 'Confirmation phrase',
            ],
        );

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'message' => 'Confirm the deletion before continuing.',
            ];
        }

        $stored = User::find($userId);

        if ($stored === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That account could not be found.'];
        }

        $data = $validator->validated();

        if (!password_verify((string) $data['current_password'], (string) $stored['password_hash'])) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['current_password' => 'That is not your current password.'],
                'message' => 'Confirm the deletion before continuing.',
            ];
        }

        if (trim((string) $data['confirmation']) !== 'DELETE MY ACCOUNT') {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['confirmation' => 'Type DELETE MY ACCOUNT exactly to confirm.'],
                'message' => 'Confirm the deletion before continuing.',
            ];
        }

        $avatarUser = $stored;
        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::delete($userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        (new ProfileService())->deleteAvatarFile($avatarUser);

        return ['ok' => true, 'message' => 'Your account and all of its data have been deleted.'];
    }
}
