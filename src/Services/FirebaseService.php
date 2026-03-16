<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Dtos\FcmResponseData;
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use RuntimeException;

/**
 * Primary service for sending push notifications through Firebase Cloud Messaging.
 *
 * Handles single and batch message delivery, token validation.
 */
class FirebaseService
{
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const REQUEST_TIMEOUT = 30;
    private const BATCH_DELAY_MICROSECONDS = 100000;
    private const BATCH_SIZE_FOR_DELAY = 50;

    private HttpClientInterface $httpClient;
    private AuthProviderInterface $authProvider;
    private PayloadBuilderInterface $payloadBuilder;
    private FirebaseConfigData $config;

    public function __construct(
        HttpClientInterface $httpClient,
        AuthProviderInterface $authProvider,
        PayloadBuilderInterface $payloadBuilder,
        FirebaseConfigData $config
    ) {
        $this->httpClient = $httpClient;
        $this->authProvider = $authProvider;
        $this->payloadBuilder = $payloadBuilder;
        $this->config = $config;
    }

    public function send(string $deviceToken, FcmMessageData $message): FcmResponseData
    {
        $url = sprintf(self::FCM_URL, $this->config->projectId);

        try {
            $accessToken = $this->authProvider->getAccessToken($this->config);
            $payload = $this->payloadBuilder->build($deviceToken, $message);

            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::REQUEST_TIMEOUT,
            ]);

            return $this->handleFcmResponse($response);
        } catch (FcmSendException $e) {
            throw $e;
        } catch (RuntimeException $exception) {
            throw new FcmSendException(
                message: "Failed to send FCM message: {$exception->getMessage()}",
                previous: $exception
            );
        }
    }

    public function sendMulticast(array $deviceTokens, FcmMessageData $message): array
    {
        $results = [];

        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendWithErrorCapture($token, $message);
            $this->applyRateLimitingDelay($results);
        }

        return $results;
    }

    public function sendInfo(string $deviceToken, string $title, string $body): FcmResponseData
    {
        $message = FcmMessageData::info(title: $title, body: $body);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    public function ping(string $deviceToken): FcmResponseData
    {
        $message = FcmMessageData::ping();
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    public function validateToken(string $deviceToken): bool
    {
        try {
            $response = $this->ping(deviceToken: $deviceToken);
            return $response->success;
        } catch (FcmSendException) {
            return false;
        }
    }

    private function handleFcmResponse(object $response): FcmResponseData
    {
        if (!$response->isSuccessful()) {
            $errorCode = $response->data['error']['status'] ?? 'UNKNOWN';
            $errorMessage = $response->data['error']['message'] ?? 'Unknown FCM error';

            $this->clearAuthCacheIfUnauthorized($errorCode);

            throw new FcmSendException(
                message: "FCM request failed: {$errorMessage}",
                statusCode: $response->statusCode,
                errorCode: $errorCode
            );
        }

        if ($response->data === null) {
            throw new FcmSendException(message: 'FCM response contained no data');
        }

        return FcmResponseData::fromFcmResponse(
            response: $response->data,
            statusCode: $response->statusCode
        );
    }

    private function sendWithErrorCapture(string $deviceToken, FcmMessageData $message): FcmResponseData
    {
        try {
            return $this->send(deviceToken: $deviceToken, message: $message);
        } catch (FcmSendException $exception) {
            return FcmResponseData::fromError(
                errorCode: $exception->getErrorCode() ?? 'send_failed',
                errorMessage: $exception->getMessage(),
                statusCode: $exception->getStatusCode()
            );
        }
    }

    private function applyRateLimitingDelay(array $results): void
    {
        if (count($results) % self::BATCH_SIZE_FOR_DELAY === 0) {
            usleep(self::BATCH_DELAY_MICROSECONDS);
        }
    }

    private function clearAuthCacheIfUnauthorized(string $errorCode): void
    {
        $unauthorizedCodes = ['UNAUTHENTICATED', 'PERMISSION_DENIED'];
        if (in_array($errorCode, $unauthorizedCodes, true)) {
            $this->authProvider->clearCache();
        }
    }
}
