<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;

class FcmResponseData extends Data
{
    /**
     * @param string $messageId FCM message ID
     * @param string $name Full resource name of the message
     * @param array<string, mixed> $rawResponse Original response data
     * @param bool $success Whether the send was successful
     * @param string|null $errorCode Error code if failed
     * @param string|null $errorMessage Error message if failed
     * @param int|null $statusCode HTTP status code
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
     * Create from FCM API response.
     *
     * @param array{name: string} $response
     * @param int $statusCode HTTP status code
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
     * Create error response.
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
     * Extract message ID from full resource name.
     */
    private static function extractMessageId(string $name): string
    {
        // Format: projects/{project}/messages/{message_id}
        $parts = explode('/', $name);
        return end($parts);
    }

    /**
     * Check if the response indicates a registration token is invalid.
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
     * Check if the response indicates a quota exceeded error.
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
     * Check if the response indicates an authentication error.
     */
    public function isAuthError(): bool
    {
        return in_array($this->errorCode, [
            'UNAUTHENTICATED',
            'PERMISSION_DENIED',
        ], true) || $this->statusCode === 401 || $this->statusCode === 403;
    }
}
