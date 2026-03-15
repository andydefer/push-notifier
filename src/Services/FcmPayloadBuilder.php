<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FcmMessageData;

/**
 * Builds Firebase Cloud Messaging (FCM) payloads conforming to the FCM HTTP v1 API.
 *
 * This builder constructs platform-specific payloads for Android and iOS devices,
 * handling priority mapping, TTL calculations, and notification formatting according
 * to Firebase specifications.
 */
class FcmPayloadBuilder implements PayloadBuilderInterface
{
    private const  ANDROID_PRIORITY_HIGH = 'high';
    private const  ANDROID_PRIORITY_NORMAL = 'normal';
    private const  APNS_PRIORITY_HIGH = '10';
    private const  APNS_PRIORITY_NORMAL = '5';
    private const  APNS_EXPIRY_HIGH_PRIORITY = 86400; // 24 hours
    private const  APNS_EXPIRY_NORMAL_PRIORITY = 14400; // 4 hours

    /**
     * {@inheritdoc}
     *
     * The generated payload follows the FCM v1 message structure:
     * - Common fields: token, data, notification
     * - Android specific: priority, TTL, channel ID
     * - iOS specific: APNs headers and payload with badge, sound, content-available
     */
    public function build(string $deviceToken, FcmMessageData $message): array
    {
        return [
            'message' => [
                'token' => $deviceToken,
                'data' => $message->toFcmData(),
                ...$this->buildAndroidConfiguration($message),
                ...$this->buildApnsConfiguration($message),
                ...$this->buildNotificationConfiguration($message),
            ],
        ];
    }

    /**
     * Builds Android-specific message configuration.
     *
     * @param FcmMessageData $message The message data
     * @return array<string, mixed> Android configuration array
     */
    private function buildAndroidConfiguration(FcmMessageData $message): array
    {
        $config = [
            'priority' => $this->mapAndroidPriority($message->getPriority()),
        ];

        $optionalConfig = array_filter([
            'ttl' => $message->ttl > 0 ? $message->ttl . 's' : null,
            'notification' => $message->channelId !== null
                ? ['channel_id' => $message->channelId]
                : null,
        ]);

        return ['android' => array_merge($config, $optionalConfig)];
    }

    /**
     * Builds iOS (APNs) specific configuration including headers and payload.
     *
     * @param FcmMessageData $message The message data
     * @return array<string, mixed> APNs configuration array
     */
    private function buildApnsConfiguration(FcmMessageData $message): array
    {
        return [
            'apns' => [
                'headers' => [
                    'apns-priority' => $this->mapApnsPriority($message->getPriority()),
                    'apns-expiration' => (string) $this->calculateApnsExpiration($message),
                ],
                'payload' => [
                    'aps' => $this->buildApsPayload($message),
                ],
            ],
        ];
    }

    /**
     * Builds the APS payload for iOS notifications.
     *
     * @param FcmMessageData $message The message data
     * @return array<string, mixed> APS payload configuration
     */
    private function buildApsPayload(FcmMessageData $message): array
    {
        $basePayload = [
            'content-available' => $message->contentAvailable ? 1 : 0,
        ];

        $optionalFields = array_filter([
            'badge' => $message->badge,
            'sound' => $message->sound,
        ]);

        return array_merge($basePayload, $optionalFields);
    }

    /**
     * Builds the user-visible notification configuration.
     *
     * @param FcmMessageData $message The message data
     * @return array<string, mixed> Empty array if notification is invisible
     */
    private function buildNotificationConfiguration(FcmMessageData $message): array
    {
        if (!$message->isVisible()) {
            return [];
        }

        $baseNotification = [
            'title' => $message->title,
            'body' => $message->body,
        ];

        $optionalFields = array_filter([
            'image' => $message->imageUrl,
            'click_action' => $message->clickAction,
        ]);

        return ['notification' => array_merge($baseNotification, $optionalFields)];
    }

    /**
     * Converts internal priority to Android platform priority.
     *
     * @param string $priority Internal priority ('high' or 'normal')
     * @return string Android priority value
     */
    private function mapAndroidPriority(string $priority): string
    {
        return $priority === 'high'
            ? self::ANDROID_PRIORITY_HIGH
            : self::ANDROID_PRIORITY_NORMAL;
    }

    /**
     * Converts internal priority to APNs header priority.
     *
     * APNs uses '10' for high priority (immediate delivery) and
     * '5' for normal priority (power saving mode).
     *
     * @param string $priority Internal priority
     * @return string APNs priority header value
     */
    private function mapApnsPriority(string $priority): string
    {
        return $priority === 'high'
            ? self::APNS_PRIORITY_HIGH
            : self::APNS_PRIORITY_NORMAL;
    }

    /**
     * Calculates the APNs expiration timestamp.
     *
     * The expiration determines how long APNs should store and attempt
     * to deliver the notification if the device is offline.
     *
     * @param FcmMessageData $message The message data
     * @return int Unix timestamp when the notification expires
     */
    private function calculateApnsExpiration(FcmMessageData $message): int
    {
        if ($message->ttl > 0) {
            return time() + $message->ttl;
        }

        $expirySeconds = $message->getPriority() === 'high'
            ? self::APNS_EXPIRY_HIGH_PRIORITY
            : self::APNS_EXPIRY_NORMAL_PRIORITY;

        return time() + $expirySeconds;
    }
}
