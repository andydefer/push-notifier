<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Http\HttpResponseData;

interface HttpClientInterface
{
    /**
     * Send a POST request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $options Request options
     * @throws \RuntimeException On request failure
     */
    public function post(string $url, array $options = []): HttpResponseData;

    /**
     * Send a GET request.
     *
     * @param string $url Request URL
     * @param array<string, mixed> $options Request options
     * @throws \RuntimeException On request failure
     */
    public function get(string $url, array $options = []): HttpResponseData;
}
