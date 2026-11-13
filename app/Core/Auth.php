<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

use App\Models\RememberToken;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Authentication state for the current request.
 *
 * Normal sessions are authoritative. When the session has expired, a valid
 * remember-me token transparently re-authenticates the user and rotates its
 * validator. Only the hash of the validator is stored in the database.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;

        $sessionId = Session::get('user_id');

        if (is_numeric($sessionId)) {
            $user = User::find((int) $sessionId);

            if ($user !== null && User::suspensionExpired($user)) {
                if (User::liftSuspensionIfExpired((int) $user['id'])) {
                    $user = User::find((int) $user['id']);
                }
            }

            if ($user !== null && User::restrictionExpired($user)) {
                $user = self::liftExpiredRestriction($user);
            }

            if ($user !== null && ($user['status'] ?? '') === 'active') {
                self::$user = self::sanitize($user);

                return self::$user;
            }

            // Deleted or suspended account: drop the stale session identity.
            Session::detachUser();
        }

        $remembered = self::restoreFromRememberCookie();

        if ($remembered !== null) {
            self::$user = self::sanitize($remembered);
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        if (Session::isActive()) {
            Session::regenerate();
            Session::attachUser((int) $user['id']);
        }

        Csrf::rotate();

        self::$user = self::sanitize($user);
        self::$resolved = true;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$resolved = true;

        if (Session::isActive()) {
            Session::flush();
        }
    }

    /**
     * Issue a new remember-me token and set its cookie.
     */
    public static function remember(int $userId, Request $request): void
    {
        self::purgeExpiredTokens();

        $lifetime = (int) config('auth.remember_lifetime', 60 * 60 * 24 * 30);
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        RememberToken::create(
            $userId,
            $selector,
            hash('sha256', $validator),
            gmdate('Y-m-d H:i:s', time() + $lifetime),
            $request->ip(),
            $request->userAgent(),
        );

        self::writeRememberCookie($selector . ':' . $validator, time() + $lifetime);
    }

    /**
     * Revoke the remember-me token referenced by the current cookie.
     */
    public static function forgetRemember(): void
    {
        $cookie = self::rememberCookie();

        if ($cookie !== null) {
            [$selector] = explode(':', $cookie, 2);

            if (strlen($selector) === 32 && ctype_xdigit($selector)) {
                RememberToken::deleteBySelector($selector);
            }
        }

        self::clearRememberCookie();
    }

    /**
     * Where to send the user after signing in.
     */
    public static function intended(string $fallback = '/dashboard'): string
    {
        $intended = Session::pull('url.intended');

        if (!is_string($intended) || $intended === '') {
            return $fallback;
        }

        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            return $fallback;
        }

        return $intended;
    }

    /**
     * A real bcrypt hash so a missing account costs the same as a wrong
     * password. This closes the obvious user-enumeration timing signal.
     */
    public static function dummyPasswordHash(): string
    {
        static $hash = null;

        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    /**
     * Clear an elapsed read-only limit and notify the account once. The
     * notification is a courtesy, so a storage failure must never block
     * authentication.
     *
     * @param array<string, mixed>|null $user
     * @return array<string, mixed>|null
     */
    private static function liftExpiredRestriction(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $userId = (int) $user['id'];
        $expiredUntil = isset($user['restricted_until'])
            ? (string) $user['restricted_until']
            : null;
        $restrictedAt = isset($user['restricted_at'])
            ? (string) $user['restricted_at']
            : null;

        if (!User::liftRestrictionIfExpired($userId)) {
            return $user;
        }

        try {
            NotificationService::moderationRestrictionExpired(
                Database::connection(),
                $userId,
                $expiredUntil,
                $restrictedAt,
            );
        } catch (\Throwable) {
            // The user still regains access even if the inbox write fails.
        }

        return User::find($userId) ?? $user;
    }

    /** @return array<string, mixed>|null */
    private static function restoreFromRememberCookie(): ?array
    {
        $cookie = self::rememberCookie();

        if ($cookie === null) {
            return null;
        }

        $parts = explode(':', $cookie);

        if (count($parts) !== 2) {
            self::clearRememberCookie();

            return null;
        }

        [$selector, $validator] = $parts;

        if (strlen($selector) !== 32 || strlen($validator) !== 64
            || !ctype_xdigit($selector) || !ctype_xdigit($validator)) {
            self::clearRememberCookie();

            return null;
        }

        $token = RememberToken::findValidBySelector($selector);

        if ($token === null) {
            self::clearRememberCookie();

            return null;
        }

        if (!hash_equals((string) $token['token_hash'], hash('sha256', $validator))) {
            // A valid selector with the wrong validator means the cookie was
            // tampered with. Revoke the whole token.
            RememberToken::deleteBySelector($selector);
            self::clearRememberCookie();

            return null;
        }

        $user = User::find((int) $token['user_id']);

        if ($user !== null && User::suspensionExpired($user)) {
            if (User::liftSuspensionIfExpired((int) $user['id'])) {
                $user = User::find((int) $user['id']);
            }
        }

        if ($user !== null && User::restrictionExpired($user)) {
            $user = self::liftExpiredRestriction($user);
        }

        if ($user === null || ($user['status'] ?? '') !== 'active') {
            RememberToken::deleteBySelector($selector);
            self::clearRememberCookie();

            return null;
        }

        $lifetime = (int) config('auth.remember_lifetime', 60 * 60 * 24 * 30);
        $rotatedValidator = bin2hex(random_bytes(32));

        RememberToken::rotate(
            (int) $token['id'],
            hash('sha256', $rotatedValidator),
            gmdate('Y-m-d H:i:s', time() + $lifetime),
        );

        self::writeRememberCookie($selector . ':' . $rotatedValidator, time() + $lifetime);

        if (Session::isActive()) {
            Session::regenerate();
            Session::attachUser((int) $user['id']);
        }

        return $user;
    }

    private static function rememberCookie(): ?string
    {
        $name = (string) config('auth.remember_cookie', 'questify_remember');
        $value = $_COOKIE[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function writeRememberCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            (string) config('auth.remember_cookie', 'questify_remember'),
            $value,
            [
                'expires' => $expires,
                'path' => '/',
                'domain' => '',
                'secure' => (bool) config('auth.remember_secure', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    private static function clearRememberCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            (string) config('auth.remember_cookie', 'questify_remember'),
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => (bool) config('auth.remember_secure', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    private static function purgeExpiredTokens(): void
    {
        // Cheap housekeeping; the indexed expiry check keeps this fast.
        if (random_int(1, 20) === 1) {
            RememberToken::purgeExpired();
        }
    }

    /**
     * Never let the password hash ride along in view data.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private static function sanitize(array $user): array
    {
        unset($user['password_hash']);

        return $user;
    }
}
