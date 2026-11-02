<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * An exception that maps directly to an HTTP status code.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = 'Page not found.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to do that.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return new self(401, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please slow down.'): self
    {
        return new self(429, $message);
    }

    public static function methodNotAllowed(string $message = 'Method not allowed.'): self
    {
        return new self(405, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'You do not have permission to do that.',
            404 => 'Page not found.',
            405 => 'Method not allowed.',
            419 => 'Your session has expired. Please try again.',
            429 => 'Too many requests. Please slow down.',
            default => 'Something went wrong.',
        };
    }
}
