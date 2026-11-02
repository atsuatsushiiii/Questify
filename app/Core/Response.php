<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable HTTP response.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $content,
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $content);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = '{"success":false,"error":{"code":"encoding_error","message":"Response encoding failed."}}';
            $status = 500;
        }

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], $encoded);
    }

    public static function noContent(int $status = 204): self
    {
        return new self($status, [], '');
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->statusCode, $headers, $this->content);
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->headers, $this->content);
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->content;
    }
}
