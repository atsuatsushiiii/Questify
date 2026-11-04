<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser token CSRF protection.
 *
 * A single unpredictable token lives in the server-side session and is
 * compared with hash_equals(), so partial matches take constant time.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function verify(?string $candidate): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        return is_string($expected)
            && $expected !== ''
            && is_string($candidate)
            && hash_equals($expected, $candidate);
    }

    /**
     * Rotate after authentication changes so a token captured before login
     * cannot be replayed afterwards.
     */
    public static function rotate(): void
    {
        Session::put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(self::token()) . '">';
    }
}
