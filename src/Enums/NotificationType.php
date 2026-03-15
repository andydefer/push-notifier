<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Enums;

/**
 * Defines the types of push notifications that can be sent.
 */
enum NotificationType: string
{
    /**
     * Simple informational message
     */
    case INFO = 'info';

    /**
     * Alert notification for important updates
     */
    case ALERT = 'alert';

    /**
     * Warning notification for potential issues
     */
    case WARNING = 'warning';

    /**
     * Success notification for completed operations
     */
    case SUCCESS = 'success';

    /**
     * Error notification for failures
     */
    case ERROR = 'error';

    /**
     * Connectivity ping to check device reachability
     */
    case PING = 'ping';

    /**
     * Get a human-readable label for the notification type.
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
     * Get the default priority for this notification type.
     */
    public function priority(): string
    {
        return match ($this) {
            self::INFO, self::SUCCESS, self::PING => 'normal',
            self::ALERT, self::WARNING, self::ERROR => 'high',
        };
    }

    /**
     * Check if this notification type should be visible to the user.
     */
    public function isVisible(): bool
    {
        return match ($this) {
            self::PING => false,
            default => true,
        };
    }

    /**
     * Get all available values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
