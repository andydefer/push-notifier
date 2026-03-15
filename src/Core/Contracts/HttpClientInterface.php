<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Http\HttpResponseData;
use RuntimeException;

/**
 * Contract for HTTP communication with external services.
 *
 * Defines a standardized interface for making HTTP requests, enabling
 * dependency injection and testability of HTTP-dependent components.
 * Implementations can use different underlying HTTP clients (Guzzle, cURL, etc.)
 * while maintaining consistent behavior.
 */
interface HttpClientInterface
{
    /**
     * Sends an HTTP POST request to the specified endpoint.
     *
     * Used primarily for creating resources or submitting data to APIs,
     * such as sending push notifications to Firebase or other services.
     *
     * @param string $url Target endpoint URL
     * @param array<string, mixed> $options Request configuration including:
     *                                      - headers: HTTP headers
     *                                      - json: Request body as array (auto-encoded)
     *                                      - timeout: Request timeout in seconds
     *                                      - verify: SSL verification enabled/disabled
     * @return HttpResponseData Normalized response with status, headers, and body
     *
     * @throws RuntimeException When the request fails due to:
     *                          - Network connectivity issues
     *                          - DNS resolution failures
     *                          - SSL/TLS errors
     *                          - Malformed URL
     */
    public function post(string $url, array $options = []): HttpResponseData;

    /**
     * Sends an HTTP GET request to the specified endpoint.
     *
     * Used for retrieving data from APIs, such as fetching device
     * information or checking notification status.
     *
     * @param string $url Target endpoint URL
     * @param array<string, mixed> $options Request configuration including:
     *                                      - headers: HTTP headers
     *                                      - query: URL query parameters
     *                                      - timeout: Request timeout in seconds
     *                                      - verify: SSL verification enabled/disabled
     * @return HttpResponseData Normalized response with status, headers, and body
     *
     * @throws RuntimeException When the request fails due to:
     *                          - Network connectivity issues
     *                          - DNS resolution failures
     *                          - SSL/TLS errors
     *                          - Malformed URL
     */
    public function get(string $url, array $options = []): HttpResponseData;
}
