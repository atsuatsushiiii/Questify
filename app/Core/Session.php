<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Session facade. Values live in $_SESSION; storage and cookie hardening are
 * handled by DatabaseSessionHandler.
 */
final class Session
{
    private static bool $active = false;
    private static ?DatabaseSessionHandler $handler = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$active = true;

            return;
        }

        $handler = new DatabaseSessionHandler(
            Database::connection(),
            (int) ($config['idle_timeout'] ?? 1800),
            (int) ($config['absolute_timeout'] ?? 43200),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        );

        self::$handler = $handler;
        session_set_save_handler($handler, true);

        session_name((string) ($config['cookie_name'] ?? 'questify_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($config['secure'] ?? false),
            'httponly' => true,
            'samesite' => (string) ($config['same_site'] ?? 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set(
            'session.gc_maxlifetime',
            (string) max((int) ($config['idle_timeout'] ?? 1800), (int) ($config['absolute_timeout'] ?? 43200)),
        );

        if (!session_start()) {
            throw new RuntimeException('Unable to start session.');
        }

        self::$active = true;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function id(): string
    {
        return session_id();
    }

    /**
     * The identifier stored in the sessions table. The handler hashes the raw
     * cookie id so a database dump cannot be replayed as a valid session.
     */
    public static function storageKey(): string
    {
        return hash('sha256', session_id());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function regenerate(): void
    {
        if (self::$active) {
            session_regenerate_id(true);
        }
    }

    /**
     * Clear all session values while keeping a valid session for flash
     * messages, then rotate the identifier.
     */
    public static function flush(): void
    {
        $_SESSION = [];
        self::regenerate();
    }

    public static function destroy(): void
    {
        if (!self::$active) {
            return;
        }

        $_SESSION = [];

        if ((bool) ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly'],
            );
        }

        session_destroy();
        self::$active = false;
    }

    public static function attachUser(int $userId): void
    {
        self::put('user_id', $userId);

        if (self::$handler !== null) {
            self::$handler->attachUser(session_id(), $userId);
        }
    }

    public static function detachUser(): void
    {
        self::forget('user_id');

        if (self::$handler !== null) {
            self::$handler->detachUser(session_id());
        }
    }
}
