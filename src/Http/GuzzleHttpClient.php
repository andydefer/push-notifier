<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Http;

use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class GuzzleHttpClient implements HttpClientInterface
{
    private GuzzleClient $client;

    /**
     * @param array<string, mixed> $config Guzzle client configuration
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
        return $this->request('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $url, array $options = []): HttpResponseData
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * Send an HTTP request.
     *
     * @throws RuntimeException
     */
    private function request(string $method, string $url, array $options = []): HttpResponseData
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            return new HttpResponseData(
                statusCode: $response->getStatusCode(),
                headers: $response->getHeaders(),
                data: is_array($data) ? $data : null,
                rawBody: $body,
            );
        } catch (RequestException $exception) {
            if ($exception->hasResponse()) {
                $response = $exception->getResponse();
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);

                return new HttpResponseData(
                    statusCode: $response->getStatusCode(),
                    headers: $response->getHeaders(),
                    data: is_array($data) ? $data : null,
                    rawBody: $body,
                );
            }

            throw new RuntimeException(
                "HTTP request failed: {$exception->getMessage()}",
                previous: $exception
            );
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                "HTTP request failed: {$exception->getMessage()}",
                previous: $exception
            );
        }
    }
}
