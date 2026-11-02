<?php

declare(strict_types=1);

namespace App\Core;

/**
 * One-shot session messages rendered by the layouts.
 */
final class Flash
{
    private const SESSION_KEY = '_flash';

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('error', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    public static function set(string $type, string $message): void
    {
        Session::put(self::SESSION_KEY, [
            'type' => $type,
            'message' => $message,
        ]);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public static function consume(): ?array
    {
        $flash = Session::pull(self::SESSION_KEY);

        if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
            return null;
        }

        return [
            'type' => (string) $flash['type'],
            'message' => (string) $flash['message'],
        ];
    }
}
