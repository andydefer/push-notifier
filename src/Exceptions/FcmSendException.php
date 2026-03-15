<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use RuntimeException;

class FcmSendException extends RuntimeException
{
    private ?int $statusCode;
    private ?string $errorCode;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
