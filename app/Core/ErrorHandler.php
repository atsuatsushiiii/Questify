<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Converts PHP errors and uncaught exceptions into safe HTTP responses and
 * appends details to storage/logs/app.log.
 */
final class ErrorHandler
{
    private static bool $debug = false;
    private static bool $handled = false;

    public static function register(bool $debug = false): void
    {
        self::$debug = $debug;

        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handle(Throwable $exception): void
    {
        if (self::$handled) {
            return;
        }

        self::$handled = true;

        $status = $exception instanceof HttpException ? $exception->statusCode() : 500;

        self::report($exception);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . $status . '] ' . $exception->getMessage() . PHP_EOL);

            if (self::$debug) {
                fwrite(STDERR, $exception->getTraceAsString() . PHP_EOL);
            }

            exit(1);
        }

        if (headers_sent()) {
            echo self::fallbackPage($status, $exception);

            return;
        }

        http_response_code($status);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');

            $payload = [
                'success' => false,
                'error' => [
                    'code' => self::codeFor($status),
                    'message' => ($status === 500 && !self::$debug)
                        ? 'An unexpected error occurred.'
                        : $exception->getMessage(),
                ],
            ];

            if (self::$debug && $status >= 500) {
                $payload['error']['detail'] = [
                    'type' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }

            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo self::page($status, $exception);
    }

    public static function handleShutdown(): void
    {
        if (self::$handled) {
            return;
        }

        $error = error_get_last();

        if ($error === null || !in_array(
            $error['type'],
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
            true,
        )) {
            return;
        }

        self::handle(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        ));
    }

    public static function report(Throwable $exception): void
    {
        $logDirectory = Config::get('paths.storage');
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            gmdate('c'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        );

        if (is_string($logDirectory) && $logDirectory !== '') {
            $directory = $logDirectory . DIRECTORY_SEPARATOR . 'logs';

            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            if (@file_put_contents($directory . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }

        error_log($line);
    }

    private static function page(int $status, Throwable $exception): string
    {
        $view = View::exists('errors/' . $status) ? 'errors/' . $status : 'errors/error';

        try {
            return View::render($view, [
                'title' => $status . ' — ' . self::titleFor($status),
                'status' => $status,
                'message' => ($status === 500 && !self::$debug)
                    ? 'Something went wrong on our side. The error has been logged.'
                    : $exception->getMessage(),
                'exception' => self::$debug ? $exception : null,
            ], 'layouts/error')->body();
        } catch (Throwable $renderFailure) {
            self::report($renderFailure);

            return self::fallbackPage($status, $exception);
        }
    }

    private static function fallbackPage(int $status, Throwable $exception): string
    {
        $title = self::titleFor($status);
        $message = self::$debug ? $exception->getMessage() : 'Please try again in a moment.';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $status . ' — ' . htmlspecialchars($title, ENT_QUOTES) . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;'
            . 'background:#0b0907;color:#f3e6c8;font-family:"IBM Plex Sans",system-ui,sans-serif;text-align:center}'
            . 'h1{font-size:72px;margin:0;color:#c9993f;font-family:Georgia,serif}p{color:#b6ab98}'
            . 'a{color:#d9b266;text-decoration:none}</style></head><body><main>'
            . '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
            . '<a href="/">Return home</a></main></body></html>';
    }

    private static function wantsJson(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_starts_with($uri, '/api/') || str_contains($accept, 'application/json');
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'session_expired',
            429 => 'too_many_requests',
            default => 'server_error',
        };
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Access denied',
            404 => 'Page not found',
            405 => 'Method not allowed',
            419 => 'Session expired',
            429 => 'Slow down',
            default => 'Something went wrong',
        };
    }
}
