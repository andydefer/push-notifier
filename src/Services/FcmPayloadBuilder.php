<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FcmMessageData;

class FcmPayloadBuilder implements PayloadBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(string $deviceToken, FcmMessageData $message): array
    {
        $payload = [
            'message' => [
                'token' => $deviceToken,
                'data' => $message->toFcmData(),
            ],
        ];

        // Add TTL if specified
        if ($message->ttl > 0) {
            $payload['message']['android']['ttl'] = $message->ttl . 's';
        }

        // Android specific configuration
        $payload['message']['android'] = array_merge(
            $payload['message']['android'] ?? [],
            [
                'priority' => $this->mapAndroidPriority($message->getPriority()),
            ]
        );

        if ($message->channelId !== null) {
            $payload['message']['android']['notification'] = [
                'channel_id' => $message->channelId,
            ];
        }

        // iOS (APNs) specific configuration
        $apnsPayload = [
            'aps' => [
                'content-available' => $message->contentAvailable ? 1 : 0,
            ],
        ];

        if ($message->badge !== null) {
            $apnsPayload['aps']['badge'] = $message->badge;
        }

        if ($message->sound !== null) {
            $apnsPayload['aps']['sound'] = $message->sound;
        }

        $payload['message']['apns'] = [
            'payload' => $apnsPayload,
            'headers' => [
                'apns-priority' => $message->getPriority() === 'high' ? '10' : '5',
                'apns-expiration' => $this->calculateApnsExpiration($message),
            ],
        ];

        // Add notification display properties if it's a user-visible notification
        if ($message->isVisible()) {
            $notification = [
                'title' => $message->title,
                'body' => $message->body,
            ];

            if ($message->imageUrl !== null) {
                $notification['image'] = $message->imageUrl;
            }

            if ($message->clickAction !== null) {
                $notification['click_action'] = $message->clickAction;
            }

            $payload['message']['notification'] = $notification;
        }

        return $payload;
    }

    /**
     * Map internal priority to Android priority.
     */
    private function mapAndroidPriority(string $priority): string
    {
        return $priority === 'high' ? 'high' : 'normal';
    }

    /**
     * Calculate APNs expiration timestamp.
     */
    private function calculateApnsExpiration(FcmMessageData $message): int
    {
        if ($message->ttl > 0) {
            return time() + $message->ttl;
        }

        // Default: 24 hours for high priority, 4 hours for normal
        return $message->getPriority() === 'high'
            ? time() + 86400  // 24 hours
            : time() + 14400; // 4 hours
    }
}
