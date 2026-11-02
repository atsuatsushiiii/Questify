<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renders PHP views inside an optional layout.
 */
final class View
{
    private static string $path = '';

    public static function init(string $path): void
    {
        self::$path = rtrim($path, '/\\');
    }

    public static function exists(string $view): bool
    {
        return is_file(self::file($view));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): Response
    {
        $content = self::capture($view, $data);

        if ($layout !== null) {
            $content = self::capture($layout, array_merge($data, ['content' => $content]));
        }

        return Response::html($content);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function capture(string $view, array $data = []): string
    {
        $file = self::file($view);

        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    private static function file(string $view): string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);

        return self::$path . DIRECTORY_SEPARATOR . $relative . '.php';
    }
}
