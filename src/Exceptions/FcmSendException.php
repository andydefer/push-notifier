<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when sending a Firebase Cloud Message fails.
 *
 * Provides detailed error information from the FCM API response,
 * including HTTP status codes and Google-specific error codes
 * for precise error handling and debugging.
 */
class FcmSendException extends RuntimeException
{
    private ?int $statusCode;
    private ?string $errorCode;

    /**
     * Creates a new FCM send exception instance.
     *
     * @param string $message Human-readable error description
     * @param int|null $statusCode HTTP status code from FCM API response
     * @param string|null $errorCode Google-specific error code (e.g., 'UNREGISTERED', 'INVALID_ARGUMENT')
     * @param Throwable|null $previous Previous exception for exception chaining
     */
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            code: 0,
            previous: $previous
        );

        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }

    /**
     * Returns the HTTP status code from the failed FCM request.
     *
     * Common status codes:
     * - 400: Bad request (invalid arguments)
     * - 401: Unauthorized (invalid token)
     * - 404: Not found (invalid project ID)
     * - 429: Too many requests (rate limit exceeded)
     * - 500: Internal server error (FCM service issue)
     *
     * @return int|null HTTP status code or null if not available
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Returns the Google-specific error code from the FCM API.
     *
     * Common error codes:
     * - 'UNREGISTERED': Device token is no longer valid
     * - 'INVALID_ARGUMENT': Malformed message payload
     * - 'QUOTA_EXCEEDED': Daily message quota reached
     * - 'SENDER_ID_MISMATCH': Wrong project credentials
     * - 'UNAVAILABLE': Service temporarily unavailable
     *
     * @return string|null FCM error code or null if not available
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
