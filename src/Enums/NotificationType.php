<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Enums;

/**
 * Defines the available notification categories for push messages.
 *
 * Each type carries specific semantic meaning that influences:
 * - How the notification is displayed to recipients
 * - Delivery priority through FCM
 * - Visibility behavior on client devices
 */
enum NotificationType: string
{
    /**
     * General informational message
     */
    case INFO = 'info';

    /**
     * Important update requiring user attention
     */
    case ALERT = 'alert';

    /**
     * Potential issue that may need attention
     */
    case WARNING = 'warning';

    /**
     * Operation completed successfully
     */
    case SUCCESS = 'success';

    /**
     * Operation failed or encountered an error
     */
    case ERROR = 'error';

    /**
     * Silent connectivity check for device reachability
     */
    case PING = 'ping';

    /**
     * Returns a human-readable display name for the notification type.
     *
     * Used for UI components, logging, and user-facing messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Information',
            self::ALERT => 'Alert',
            self::WARNING => 'Warning',
            self::SUCCESS => 'Success',
            self::ERROR => 'Error',
            self::PING => 'Connectivity Ping',
        };
    }

    /**
     * Determines the Firebase Cloud Messaging priority for this type.
     *
     * - 'high' priority: Time-sensitive notifications that should wake devices
     * - 'normal' priority: Standard notifications that can be batched
     */
    public function fcmPriority(): string
    {
        return match ($this) {
            self::INFO, self::SUCCESS, self::PING => 'normal',
            self::ALERT, self::WARNING, self::ERROR => 'high',
        };
    }

    /**
     * Indicates whether this notification should be displayed to the user.
     *
     * Silent types (like PING) are used for background operations and
     * should not trigger visible notifications on the device.
     */
    public function shouldDisplayToUser(): bool
    {
        return match ($this) {
            self::PING => false,
            default => true,
        };
    }

    /**
     * Returns all available notification type values as strings.
     *
     * Useful for validation, form inputs, and API documentation.
     *
     * @return array<string> List of all possible notification type values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
