<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable wrapper around the current HTTP request.
 */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $body = $_POST;

        if ($method === 'POST' && isset($body['_method'])) {
            $override = strtoupper((string) $body['_method']);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $rawPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim((string) ($rawPath ?: '/'), '/');

        return new self($method, $path, $_GET, $body, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Request input, checking the body first and then the query string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();

        if (array_key_exists($key, $body)) {
            return $body[$key];
        }

        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body());
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $contentType = strtolower($this->header('Content-Type') ?? '');

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        } else {
            $this->jsonBody = $this->body;
        }

        return $this->jsonBody;
    }

    public function header(string $name): ?string
    {
        $key = strtoupper(str_replace('-', '_', $name));

        if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
            return isset($this->server[$key]) ? (string) $this->server[$key] : null;
        }

        $value = $this->server['HTTP_' . $key] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi() || str_contains(strtolower($this->header('Accept') ?? ''), 'application/json');
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }
}
