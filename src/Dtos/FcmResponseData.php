<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;

/**
 * Data transfer object representing a Firebase Cloud Messaging API response.
 *
 * This DTO encapsulates both successful and failed FCM message delivery attempts,
 * providing structured access to response data and error information.
 */
class FcmResponseData extends Data
{
    /**
     * @param string $messageId Unique Firebase identifier for the sent message
     * @param string $name Complete resource name in format: projects/{project}/messages/{message_id}
     * @param array<string, mixed> $rawResponse Original API response data for debugging
     * @param bool $success Indicates whether the message was accepted by FCM
     * @param string|null $errorCode FCM-specific error code when delivery fails
     * @param string|null $errorMessage Human-readable error description
     * @param int|null $statusCode HTTP status code from the FCM API response
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $name,
        public readonly array $rawResponse = [],
        public readonly bool $success = true,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $statusCode = null,
    ) {}

    /**
     * Creates a DTO instance from a successful FCM API response.
     *
     * Parses the FCM response structure and extracts the relevant information
     * into a standardized format.
     *
     * @param array{name: string} $response Raw FCM API response containing the message resource name
     * @param int $statusCode HTTP status code from the FCM API
     * @return self DTO representing the successful message delivery
     */
    public static function fromFcmResponse(array $response, int $statusCode = 200): self
    {
        return new self(
            messageId: self::extractMessageId($response['name']),
            name: $response['name'],
            rawResponse: $response,
            statusCode: $statusCode,
        );
    }

    /**
     * Creates a DTO instance representing a failed message delivery attempt.
     *
     * @param string $errorCode FCM error code identifying the failure type
     * @param string $errorMessage Detailed error description
     * @param int|null $statusCode HTTP status code if available
     * @return self DTO representing the failed delivery
     */
    public static function fromError(string $errorCode, string $errorMessage, ?int $statusCode = null): self
    {
        return new self(
            messageId: '',
            name: '',
            rawResponse: [],
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            statusCode: $statusCode,
        );
    }

    /**
     * Determines if the failure is related to an invalid or unregistered device token.
     *
     * These errors indicate that the target device token is no longer valid
     * and should be removed from the system.
     *
     * @return bool True if the token should be considered invalid
     */
    public function isInvalidToken(): bool
    {
        return in_array($this->errorCode, [
            'UNREGISTERED',
            'INVALID_ARGUMENT',
            'NOT_FOUND',
        ], true);
    }

    /**
     * Determines if the failure is due to exceeding Firebase quotas or rate limits.
     *
     * These errors suggest temporary conditions that may resolve with retry
     * after implementing appropriate backoff strategies.
     *
     * @return bool True if quota or rate limits were exceeded
     */
    public function isQuotaExceeded(): bool
    {
        return in_array($this->errorCode, [
            'QUOTA_EXCEEDED',
            'RESOURCE_EXHAUSTED',
            'RATE_EXCEEDED',
        ], true);
    }

    /**
     * Determines if the failure is related to authentication or authorization issues.
     *
     * These errors indicate problems with the Firebase credentials or permissions,
     * requiring configuration updates.
     *
     * @return bool True for authentication or permission failures
     */
    public function isAuthError(): bool
    {
        return in_array($this->errorCode, [
            'UNAUTHENTICATED',
            'PERMISSION_DENIED',
        ], true) || $this->statusCode === 401 || $this->statusCode === 403;
    }

    /**
     * Extracts the message identifier from the full Firebase resource name.
     *
     * FCM returns message names in the format: projects/{project}/messages/{message_id}
     * This method isolates the actual message ID from the complete path.
     *
     * @param string $resourceName Full resource path from FCM response
     * @return string The extracted message identifier
     */
    private static function extractMessageId(string $resourceName): string
    {
        $pathSegments = explode('/', $resourceName);
        return end($pathSegments);
    }
}
