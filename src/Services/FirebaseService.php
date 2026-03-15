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

class FirebaseService
{
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const REQUEST_TIMEOUT = 30;

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

    /**
     * Send a push notification to a device.
     *
     * @param string $deviceToken Target device FCM token
     * @param FcmMessageData $message Notification message
     * @throws FcmSendException
     */
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

            if (!$response->isSuccessful()) {
                // Try to parse FCM error
                $errorCode = $response->data['error']['status'] ?? 'UNKNOWN';
                $errorMessage = $response->data['error']['message'] ?? 'Unknown FCM error';

                // If token is invalid, clear auth cache to force refresh
                if ($errorCode === 'UNAUTHENTICATED' || $errorCode === 'PERMISSION_DENIED') {
                    $this->authProvider->clearCache();
                }

                throw new FcmSendException(
                    "FCM request failed: {$errorMessage}",
                    $response->statusCode,
                    $errorCode
                );
            }

            if ($response->data === null) {
                throw new FcmSendException('FCM response contained no data');
            }

            return FcmResponseData::fromFcmResponse($response->data, $response->statusCode);
        } catch (FcmSendException $e) {
            throw $e;
        } catch (RuntimeException $exception) {
            throw new FcmSendException(
                "Failed to send FCM message: {$exception->getMessage()}",
                null,
                null,
                $exception
            );
        }
    }

    /**
     * Send a notification to multiple devices (batch).
     *
     * @param array<string> $deviceTokens Array of device tokens
     * @param FcmMessageData $message Notification message
     * @return array<string, FcmResponseData> Results keyed by device token
     */
    public function sendMulticast(array $deviceTokens, FcmMessageData $message): array
    {
        $results = [];

        foreach ($deviceTokens as $token) {
            try {
                $results[$token] = $this->send($token, $message);
            } catch (FcmSendException $exception) {
                $results[$token] = FcmResponseData::fromError(
                    $exception->getErrorCode() ?? 'send_failed',
                    $exception->getMessage(),
                    $exception->getStatusCode()
                );
            }

            // Small delay to avoid rate limiting
            if (count($results) % 50 === 0) {
                usleep(100000); // 100ms
            }
        }

        return $results;
    }

    /**
     * Send a simple info notification.
     */
    public function sendInfo(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::info($title, $body, $data);
        return $this->send($deviceToken, $message);
    }

    /**
     * Send an alert notification.
     */
    public function sendAlert(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::alert($title, $body, $data);
        return $this->send($deviceToken, $message);
    }

    /**
     * Send a warning notification.
     */
    public function sendWarning(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::warning($title, $body, $data);
        return $this->send($deviceToken, $message);
    }

    /**
     * Send a success notification.
     */
    public function sendSuccess(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::success($title, $body, $data);
        return $this->send($deviceToken, $message);
    }

    /**
     * Send an error notification.
     */
    public function sendError(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::error($title, $body, $data);
        return $this->send($deviceToken, $message);
    }

    /**
     * Send a ping notification (silent background notification).
     */
    public function ping(string $deviceToken): FcmResponseData
    {
        $message = FcmMessageData::ping();
        return $this->send($deviceToken, $message);
    }

    /**
     * Validate a device token by sending a ping.
     * Returns true if token is valid, false otherwise.
     */
    public function validateToken(string $deviceToken): bool
    {
        try {
            $response = $this->ping($deviceToken);
            return $response->success;
        } catch (FcmSendException $e) {
            return false;
        }
    }
}
