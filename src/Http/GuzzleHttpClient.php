<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Http;

use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * HTTP client implementation using Guzzle for Firebase Cloud Messaging requests.
 *
 * This adapter wraps Guzzle HTTP client to provide a standardized interface
 * for making API calls to Firebase services. It handles both successful
 * responses and failed requests, converting them into consistent HttpResponseData
 * objects with proper error information.
 */
class GuzzleHttpClient implements HttpClientInterface
{
    private GuzzleClient $client;

    /**
     * Initializes the Guzzle HTTP client with custom configuration.
     *
     * @param array<string, mixed> $config Guzzle client options (timeout, headers, etc.)
     */
    public function __construct(array $config = [])
    {
        $this->client = new GuzzleClient($config);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $url, array $options = []): HttpResponseData
    {
        return $this->sendRequest('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $url, array $options = []): HttpResponseData
    {
        return $this->sendRequest('GET', $url, $options);
    }

    /**
     * Executes an HTTP request and normalizes the response.
     *
     * Handles both successful requests and Guzzle exceptions, transforming
     * them into a standardized HttpResponseData format. When a request fails
     * with a response (like 4xx/5xx errors), the error response is still
     * returned as HttpResponseData. Only connection-level failures throw
     * RuntimeException.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request destination URL
     * @param array<string, mixed> $options Request options (headers, body, etc.)
     * @return HttpResponseData Normalized response with status, headers, and body
     *
     * @throws RuntimeException When the request fails without an HTTP response
     *                         (network error, DNS failure, SSL issues, etc.)
     */
    private function sendRequest(string $method, string $url, array $options = []): HttpResponseData
    {
        try {
            $response = $this->client->request($method, $url, $options);

            return $this->createHttpResponseData($response);
        } catch (RequestException $exception) {
            if ($exception->hasResponse()) {
                return $this->createHttpResponseData($exception->getResponse());
            }

            throw new RuntimeException(
                message: "HTTP request failed: {$exception->getMessage()}",
                previous: $exception
            );
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                message: "HTTP request failed: {$exception->getMessage()}",
                previous: $exception
            );
        }
    }

    /**
     * Transforms a Guzzle response into a normalized HttpResponseData object.
     *
     * Extracts status code, headers, and attempts to parse JSON body content.
     * If the response body isn't valid JSON, the raw body is preserved while
     * the parsed data remains null.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Raw Guzzle response
     * @return HttpResponseData Standardized response data structure
     */
    private function createHttpResponseData($response): HttpResponseData
    {
        $body = $response->getBody()->getContents();
        $decodedBody = json_decode($body, true);

        return new HttpResponseData(
            statusCode: $response->getStatusCode(),
            headers: $response->getHeaders(),
            data: is_array($decodedBody) ? $decodedBody : null,
            rawBody: $body,
        );
    }
}
