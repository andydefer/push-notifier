<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when Firebase authentication operations fail.
 *
 * This exception indicates issues with obtaining or managing OAuth2 tokens
 * for Firebase Cloud Messaging, including invalid credentials, network problems,
 * or malformed configuration.
 */
class FirebaseAuthException extends RuntimeException
{
    /**
     * Creates a new Firebase authentication exception.
     *
     * @param string $message Human-readable description of the authentication failure
     * @param Throwable|null $previous Optional previous exception for exception chaining
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(
            message: $message,
            code: 0,
            previous: $previous
        );
    }
}
