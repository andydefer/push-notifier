<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;
use Andydefer\PushNotifier\Enums\NotificationType;

/**
 * Data transfer object for Firebase Cloud Messaging notifications.
 *
 * This DTO encapsulates all parameters required to send a push notification
 * through FCM, including platform-specific configurations for iOS and Android,
 * as well as notification visibility and priority settings.
 */
class FcmMessageData extends Data
{
    /**
     * Creates a new FCM message instance.
     *
     * @param NotificationType $type Categorizes the notification (info, alert, error, etc.)
     * @param string $title Brief notification headline displayed to users
     * @param string $body Detailed message content shown in notification
     * @param array<string, mixed> $data Custom key-value payload for app processing
     * @param string|null $imageUrl Optional URL for rich media notifications
     * @param string|null $clickAction Optional deep link or action when notification tapped
     * @param string|null $channelId Android-specific notification channel configuration
     * @param int|null $badge iOS badge number to display on app icon
     * @param string|null $sound Custom notification sound name (platform-specific)
     * @param bool $contentAvailable iOS flag enabling background processing
     * @param int $ttl Message lifespan in seconds (0 uses FCM default)
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?string $imageUrl = null,
        public readonly ?string $clickAction = null,
        public readonly ?string $channelId = null,
        public readonly ?int $badge = null,
        public readonly ?string $sound = null,
        public readonly bool $contentAvailable = true,
        public readonly int $ttl = 0,
    ) {}

    /**
     * Creates an informational notification with default visibility.
     */
    public static function info(string $title, string $body, array $data = []): self
    {
        return new self(
            type: NotificationType::INFO,
            title: $title,
            body: $body,
            data: $data,
        );
    }

    /**
     * Creates a high-priority alert notification.
     */
    public static function alert(string $title, string $body, array $data = []): self
    {
        return new self(
            type: NotificationType::ALERT,
            title: $title,
            body: $body,
            data: $data,
            contentAvailable: false,
        );
    }

    /**
     * Creates a positive outcome notification.
     */
    public static function success(string $title, string $body, array $data = []): self
    {
        return new self(
            type: NotificationType::SUCCESS,
            title: $title,
            body: $body,
            data: $data,
        );
    }

    /**
     * Creates a cautionary notification about potential issues.
     */
    public static function warning(string $title, string $body, array $data = []): self
    {
        return new self(
            type: NotificationType::WARNING,
            title: $title,
            body: $body,
            data: $data,
            contentAvailable: false,
        );
    }

    /**
     * Creates a critical failure notification.
     */
    public static function error(string $title, string $body, array $data = []): self
    {
        return new self(
            type: NotificationType::ERROR,
            title: $title,
            body: $body,
            data: $data,
            contentAvailable: false,
        );
    }

    /**
     * Creates a connectivity test notification.
     */
    public static function ping(string $title = 'Connectivity Check', string $body = ''): self
    {
        return new self(
            type: NotificationType::PING,
            title: $title,
            body: $body,
            data: ['timestamp' => (string) time()],
            contentAvailable: true,
        );
    }

    /**
     * Resolves the delivery priority based on notification type.
     *
     * @return string 'high' for time-sensitive notifications, 'normal' otherwise
     */
    public function getPriority(): string
    {
        return $this->type->fcmPriority();
    }

    /**
     * Determines if the notification should be displayed to the user.
     *
     * @return bool True for user-visible notifications, false for silent/background
     */
    public function isVisible(): bool
    {
        return $this->type->shouldDisplayToUser();
    }

    /**
     * Transforms the DTO into an FCM-compatible payload.
     *
     * All values are converted to strings as required by FCM's API.
     * Null values are automatically filtered out to minimize payload size.
     *
     * @return array<string, string> Key-value pairs with string values only
     */
    public function toFcmData(): array
    {
        $payload = array_merge($this->data, [
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->getPriority(),
            'timestamp' => (string) time(),
            'image_url' => $this->imageUrl,
            'click_action' => $this->clickAction,
            'ttl' => $this->ttl > 0 ? (string) $this->ttl : null,
        ]);

        // Remove null values to keep payload minimal
        $filteredPayload = array_filter(
            $payload,
            fn($value): bool => $value !== null
        );

        // Convert all remaining values to strings as required by FCM
        return array_map(
            fn($value): string => (string) $value,
            $filteredPayload
        );
    }
}
