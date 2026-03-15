<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;
use Andydefer\PushNotifier\Enums\NotificationType;

class FcmMessageData extends Data
{
    /**
     * @param NotificationType $type Type of notification
     * @param string $title Notification title
     * @param string $body Notification body content
     * @param array<string, mixed> $data Additional custom data payload
     * @param string|null $imageUrl Optional image URL for rich notifications
     * @param string|null $clickAction Optional action when notification is clicked
     * @param string|null $channelId Android notification channel ID
     * @param int|null $badge iOS badge number
     * @param string|null $sound Custom notification sound
     * @param bool $contentAvailable iOS content-available flag for background notifications
     * @param int $ttl Time to live in seconds (0 = default)
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
     * Create a simple info notification.
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
     * Create an alert notification.
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
     * Create a success notification.
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
     * Create a warning notification.
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
     * Create an error notification.
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
     * Create a ping notification for connectivity check.
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
     * Get notification priority based on type.
     */
    public function getPriority(): string
    {
        return $this->type->priority();
    }

    /**
     * Check if this notification should be visible to the user.
     */
    public function isVisible(): bool
    {
        return $this->type->isVisible();
    }

    /**
     * Convert to FCM data payload (all values must be strings).
     *
     * @return array<string, string>
     */
    public function toFcmData(): array
    {
        $data = array_merge($this->data, [
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->getPriority(),
            'timestamp' => (string) time(),
        ]);

        if ($this->imageUrl !== null) {
            $data['image_url'] = $this->imageUrl;
        }

        if ($this->clickAction !== null) {
            $data['click_action'] = $this->clickAction;
        }

        if ($this->ttl > 0) {
            $data['ttl'] = (string) $this->ttl;
        }

        // Convert all values to strings for FCM
        return array_map(fn($value): string => (string) $value, $data);
    }
}
