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
 * Handles single and batch message delivery, token validation, and various
 * notification types (info, alert, warning, success, error, ping).
 * Automatically manages authentication and retry mechanisms.
 */
class FirebaseService
{
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const REQUEST_TIMEOUT = 30;
    private const BATCH_DELAY_MICROSECONDS = 100000; // 100ms pause every 50 messages
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

    /**
     * Delivers a push notification to a specific device.
     *
     * Handles authentication, payload construction, and FCM API communication.
     * Automatically refreshes expired tokens when authentication fails.
     *
     * @param string $deviceToken FCM registration token of target device
     * @param FcmMessageData $message Notification content and configuration
     * @return FcmResponseData Parsed FCM API response
     *
     * @throws FcmSendException When:
     *                         - FCM API returns an error
     *                         - Network communication fails
     *                         - Response parsing fails
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

    /**
     * Delivers a notification to multiple devices simultaneously.
     *
     * Processes each device individually with automatic error handling.
     * Includes rate limiting protection through strategic pauses.
     *
     * @param array<string> $deviceTokens List of FCM registration tokens
     * @param FcmMessageData $message Notification to send to all devices
     * @return array<string, FcmResponseData> Results indexed by device token
     */
    public function sendMulticast(array $deviceTokens, FcmMessageData $message): array
    {
        $results = [];

        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendWithErrorCapture($token, $message);
            $this->applyRateLimitingDelay($results);
        }

        return $results;
    }

    /**
     * Sends an informational notification.
     *
     * @param string $deviceToken Target device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Optional custom data payload
     */
    public function sendInfo(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::info(title: $title, body: $body, data: $data);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Sends an alert notification (high importance).
     *
     * @param string $deviceToken Target device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Optional custom data payload
     */
    public function sendAlert(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::alert(title: $title, body: $body, data: $data);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Sends a warning notification.
     *
     * @param string $deviceToken Target device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Optional custom data payload
     */
    public function sendWarning(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::warning(title: $title, body: $body, data: $data);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Sends a success confirmation notification.
     *
     * @param string $deviceToken Target device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Optional custom data payload
     */
    public function sendSuccess(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::success(title: $title, body: $body, data: $data);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Sends an error notification.
     *
     * @param string $deviceToken Target device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Optional custom data payload
     */
    public function sendError(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData
    {
        $message = FcmMessageData::error(title: $title, body: $body, data: $data);
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Sends a silent background notification to wake the app.
     *
     * Useful for data sync triggers or token validation without user visibility.
     *
     * @param string $deviceToken Target device token
     */
    public function ping(string $deviceToken): FcmResponseData
    {
        $message = FcmMessageData::ping();
        return $this->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Verifies if a device token is still valid for receiving notifications.
     *
     * Attempts to send a silent ping and interprets the result.
     * Invalid tokens typically indicate app uninstall or token expiration.
     *
     * @param string $deviceToken Token to validate
     * @return bool True if token can receive notifications
     */
    public function validateToken(string $deviceToken): bool
    {
        try {
            $response = $this->ping(deviceToken: $deviceToken);
            return $response->success;
        } catch (FcmSendException) {
            return false;
        }
    }

    /**
     * Processes FCM HTTP response and converts to standardized format.
     *
     * Handles authentication errors by clearing token cache for retry.
     *
     * @param object $response Raw HTTP client response
     * @return FcmResponseData Structured response data
     *
     * @throws FcmSendException When response indicates failure or malformed data
     */
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

    /**
     * Attempts to send a notification and captures any errors as response.
     *
     * Ensures batch operations continue even if individual sends fail.
     *
     * @param string $deviceToken Target device
     * @param FcmMessageData $message Notification to send
     * @return FcmResponseData Success response or formatted error
     */
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

    /**
     * Introduces strategic delays to prevent FCM rate limiting.
     *
     * Pauses execution after every batch of messages to avoid
     * overwhelming the FCM API.
     *
     * @param array $results Current batch results
     */
    private function applyRateLimitingDelay(array $results): void
    {
        if (count($results) % self::BATCH_SIZE_FOR_DELAY === 0) {
            usleep(self::BATCH_DELAY_MICROSECONDS);
        }
    }

    /**
     * Clears cached authentication when FCM reports authorization failures.
     *
     * @param string $errorCode FCM error code from response
     */
    private function clearAuthCacheIfUnauthorized(string $errorCode): void
    {
        $unauthorizedCodes = ['UNAUTHENTICATED', 'PERMISSION_DENIED'];
        if (in_array($errorCode, $unauthorizedCodes, true)) {
            $this->authProvider->clearCache();
        }
    }
}
