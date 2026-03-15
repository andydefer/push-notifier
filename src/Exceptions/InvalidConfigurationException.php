<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown when Firebase push notification configuration is invalid.
 *
 * This exception indicates that the provided configuration for the push notifier
 * is malformed, missing required fields, or contains invalid values.
 */
class InvalidConfigurationException extends InvalidArgumentException
{
    /**
     * Creates a new invalid configuration exception.
     *
     * @param string $message Detailed description of the configuration error
     * @param Throwable|null $previous Optional previous exception for chain debugging
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
