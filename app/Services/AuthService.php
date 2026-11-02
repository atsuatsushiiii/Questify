<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Validator;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\RememberToken;
use App\Models\User;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Registration, sign-in, sign-out and password recovery rules.
 */
final class AuthService
{
    private const PASSWORD_RESET_TOKEN_BYTES = 32;

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, redirect?: string, message?: string}
     */
    public function register(array $input, Request $request): array
    {
        $minPassword = (int) config('auth.min_password_length', 10);
        $maxPassword = (int) config('auth.max_password_length', 72);

        $validator = Validator::make(
            $input,
            [
                'display_name' => 'required|string|min:2|max:50',
                'username' => 'required|string|username',
                'email' => 'required|string|email|max:190',
                'password' => "required|string|min:{$minPassword}|max:{$maxPassword}|password",
                'password_confirmation' => 'required|string|same:password',
                'timezone' => 'required|timezone',
                'terms' => 'required|accepted',
            ],
            [
                'display_name' => 'Display name',
                'username' => 'Username',
                'email' => 'Email address',
                'password' => 'Password',
                'password_confirmation' => 'Password confirmation',
                'timezone' => 'Timezone',
                'terms' => 'The terms',
            ],
        );

        $old = [
            'display_name' => $this->text($input['display_name'] ?? ''),
            'username' => $this->text($input['username'] ?? ''),
            'email' => $this->text($input['email'] ?? ''),
            'timezone' => $this->text($input['timezone'] ?? ''),
        ];

        if (!$validator->validate()) {
            return ['ok' => false, 'status' => 422, 'errors' => $validator->errors(), 'old' => $old];
        }

        $data = $validator->validated();

        $displayName = preg_replace('/\s+/u', ' ', trim((string) $data['display_name'])) ?? '';
        $username = trim((string) $data['username']);
        $email = mb_strtolower(trim((string) $data['email']));
        $timezone = (string) $data['timezone'];

        $errors = [];

        if (User::emailExists($email)) {
            $errors['email'] = 'That email address is already registered.';
        }

        if (User::usernameExists($username)) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $errors, 'old' => $old];
        }

        $hash = password_hash((string) $data['password'], PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Unable to hash the password.');
        }

        try {
            $userId = User::create([
                'username' => $username,
                'email' => $email,
                'password_hash' => $hash,
                'display_name' => $displayName,
                'timezone' => $timezone,
            ]);
        } catch (PDOException $exception) {
            // The unique indexes are the final authority against races.
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'errors' => ['email' => 'That username or email address is already registered.'],
                    'old' => $old,
                ];
            }

            throw $exception;
        }

        User::touchLogin($userId);
        $user = User::find($userId);

        if ($user === null) {
            throw new RuntimeException('Registration completed but the account could not be loaded.');
        }

        Auth::login($user);

        return [
            'ok' => true,
            'redirect' => '/dashboard',
            'message' => 'Welcome to Questify, ' . $displayName . '. Your adventure starts now.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, redirect?: string, message?: string, retry_after?: int}
     */
    public function attempt(array $input, Request $request): array
    {
        $validator = Validator::make(
            $input,
            [
                'identifier' => 'required|string|max:190',
                'password' => 'required|string|max:200',
            ],
            [
                'identifier' => 'Email or username',
                'password' => 'Password',
            ],
        );

        $identifier = trim((string) ($input['identifier'] ?? ''));

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'old' => ['identifier' => $identifier],
            ];
        }

        $password = (string) ($input['password'] ?? '');
        $ip = $request->ip();
        $decay = (int) config('auth.login_decay', 900);
        $maxPerIdentifier = (int) config('auth.max_login_attempts', 5);
        $maxPerIp = (int) config('auth.max_login_attempts_per_ip', 20);

        if (random_int(1, 50) === 1) {
            LoginAttempt::purgeOlderThan(60 * 60 * 24 * 30);
        }

        if (LoginAttempt::failures($identifier, $ip, $decay) >= $maxPerIdentifier) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'Too many failed attempts for this account. Please wait 15 minutes and try again.',
                'old' => ['identifier' => $identifier],
                'retry_after' => $decay,
            ];
        }

        if (LoginAttempt::ipFailures($ip, $decay) >= $maxPerIp) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'Too many failed attempts from this network. Please wait 15 minutes and try again.',
                'old' => ['identifier' => $identifier],
                'retry_after' => $decay,
            ];
        }

        $user = User::findByLogin($identifier);
        $valid = password_verify($password, (string) ($user['password_hash'] ?? Auth::dummyPasswordHash()));

        if ($user === null || !$valid) {
            LoginAttempt::record($identifier, $ip, false);

            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Those credentials do not match our records.',
                'old' => ['identifier' => $identifier],
            ];
        }

        if (($user['status'] ?? '') !== 'active') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'This account is suspended. Contact an administrator if you believe this is a mistake.',
                'old' => ['identifier' => $identifier],
            ];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = password_hash($password, PASSWORD_DEFAULT);

            if ($rehash !== false) {
                User::updatePassword((int) $user['id'], $rehash);
            }
        }

        LoginAttempt::clearFailures($identifier, $ip);
        LoginAttempt::record($identifier, $ip, true);
        User::touchLogin((int) $user['id']);

        if ($this->truthy($input['remember'] ?? false)) {
            Auth::remember((int) $user['id'], $request);
        }

        $refreshed = User::find((int) $user['id']) ?? $user;
        Auth::login($refreshed);

        return [
            'ok' => true,
            'redirect' => '/dashboard',
            'message' => 'Welcome back, ' . (string) ($refreshed['display_name'] ?? $refreshed['username']) . '.',
        ];
    }

    public function logout(Request $request): void
    {
        Auth::forgetRemember();
        Auth::logout();
    }

    /**
     * Always returns the same message to avoid account enumeration.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message: string}
     */
    public function requestPasswordReset(array $input, Request $request): array
    {
        $validator = Validator::make(
            $input,
            ['email' => 'required|string|email|max:190'],
            ['email' => 'Email address'],
        );

        $generic = 'If an account matches that email address, a password reset link is on its way.';

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'old' => ['email' => $this->text($input['email'] ?? '')],
                'message' => 'Enter the email address linked to your account.',
            ];
        }

        $email = mb_strtolower(trim((string) $validator->validated()['email']));
        $user = User::findByLogin($email);

        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return ['ok' => true, 'message' => $generic];
        }

        $ttl = (int) config('auth.password_reset_ttl', 3600);
        $perHour = (int) config('auth.password_reset_per_hour', 3);

        if (PasswordReset::countRecentForUser((int) $user['id'], 3600) >= $perHour
            || PasswordReset::countRecentForIp($request->ip(), 3600) >= $perHour * 3) {
            return ['ok' => true, 'message' => $generic];
        }

        $token = bin2hex(random_bytes(self::PASSWORD_RESET_TOKEN_BYTES));

        if (random_int(1, 20) === 1) {
            PasswordReset::purgeExpired();
        }

        PasswordReset::deleteForUser((int) $user['id']);
        PasswordReset::create(
            (int) $user['id'],
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + $ttl),
            $request->ip(),
        );

        try {
            (new MailService())->sendPasswordReset($user, $token);
        } catch (Throwable $exception) {
            // Do not reveal whether the account exists or that mail failed.
            ErrorHandler::report($exception);
        }

        return ['ok' => true, 'message' => $generic];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, message: string}
     */
    public function resetPassword(array $input): array
    {
        $minPassword = (int) config('auth.min_password_length', 10);
        $maxPassword = (int) config('auth.max_password_length', 72);

        $validator = Validator::make(
            $input,
            [
                'token' => 'required|string|hex:64',
                'password' => "required|string|min:{$minPassword}|max:{$maxPassword}|password",
                'password_confirmation' => 'required|string|same:password',
            ],
            [
                'token' => 'Reset link',
                'password' => 'Password',
                'password_confirmation' => 'Password confirmation',
            ],
        );

        if (!$validator->validate()) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $validator->errors(),
                'message' => 'Please fix the highlighted fields.',
            ];
        }

        $data = $validator->validated();
        $hash = hash('sha256', (string) $data['token']);
        $reset = PasswordReset::findValidByHash($hash);

        if ($reset === null) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['token' => 'This password reset link is invalid or has expired.'],
                'message' => 'Request a fresh reset link to continue.',
            ];
        }

        $newHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);

        if ($newHash === false) {
            throw new RuntimeException('Unable to hash the password.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            User::updatePassword((int) $reset['user_id'], $newHash);
            PasswordReset::markUsed((int) $reset['id']);
            PasswordReset::deleteForUser((int) $reset['user_id']);

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        // A password change invalidates every other credential.
        RememberToken::deleteForUser((int) $reset['user_id']);
        User::revokeSessions((int) $reset['user_id']);

        return [
            'ok' => true,
            'message' => 'Your password has been updated. Sign in with your new password.',
        ];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'on', 'yes', 'true'], true);
    }
}
