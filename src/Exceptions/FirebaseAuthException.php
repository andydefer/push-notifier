<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use RuntimeException;

class FirebaseAuthException extends RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
