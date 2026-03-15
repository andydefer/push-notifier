<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Http;

use Spatie\LaravelData\Data;

/**
 * Immutable data transfer object representing an HTTP response.
 *
 * Encapsulates all response components including status code, headers,
 * parsed data, and raw body. Provides convenience methods for inspecting
 * response characteristics and accessing header information.
 */
class HttpResponseData extends Data
{
    /**
     * @param int $statusCode HTTP status code indicating request outcome
     * @param array<string, array<string>> $headers Response headers with lowercase keys
     * @param array<string, mixed>|null $data Decoded response payload
     * @param string|null $rawBody Original unprocessed response body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $headers = [],
        public readonly ?array $data = null,
        public readonly ?string $rawBody = null,
    ) {}

    /**
     * Determines if the response indicates a successful operation (2xx status).
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Determines if the response indicates a client-side error (4xx status).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Determines if the response indicates a server-side error (5xx status).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Retrieves the first value of a specific response header.
     *
     * @param string $name Case-insensitive header name
     * @return string|null Header value or null if header doesn't exist
     */
    public function getHeader(string $name): ?string
    {
        $headers = $this->getHeaderLines($name);

        return $headers[0] ?? null;
    }

    /**
     * Retrieves all values for a specific response header.
     *
     * @param string $name Case-insensitive header name
     * @return array<string> List of header values, empty array if header doesn't exist
     */
    public function getHeaderLines(string $name): array
    {
        $normalizedName = strtolower($name);

        return $this->headers[$normalizedName] ?? [];
    }
}
