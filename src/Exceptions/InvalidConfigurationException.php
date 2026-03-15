<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use InvalidArgumentException;

class InvalidConfigurationException extends InvalidArgumentException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
