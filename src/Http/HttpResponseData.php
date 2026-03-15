<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Http;

use Spatie\LaravelData\Data;

class HttpResponseData extends Data
{
    /**
     * @param int $statusCode HTTP status code
     * @param array<string, array<string>> $headers Response headers
     * @param array<string, mixed>|null $data Parsed response data
     * @param string|null $rawBody Raw response body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers = [],
        public readonly ?array $data = null,
        public readonly ?string $rawBody = null,
    ) {}

    /**
     * Check if the request was successful (2xx status code).
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the request was a client error (4xx status code).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if the request was a server error (5xx status code).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Get a specific header value.
     */
    public function getHeader(string $name): ?string
    {
        $lowerName = strtolower($name);
        $values = $this->headers[$lowerName] ?? null;

        if ($values === null || $values === []) {
            return null;
        }

        return $values[0];
    }

    /**
     * Get all headers with the given name.
     *
     * @return array<string>
     */
    public function getHeaderLines(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }
}
