<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Exceptions;

use Exception;

/**
 * Exception thrown when Firebase configuration is invalid.
 */
class InvalidConfigurationException extends Exception
{
    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('Firebase service account file not found: %s', $path));
    }

    public static function invalidJson(string $message = 'Malformed content'): self
    {
        return new self(sprintf('Invalid Firebase service account JSON: %s', $message));
    }

    public static function missingRequiredField(string $field): self
    {
        return new self(sprintf('Missing required service account field: %s', $field));
    }

    public static function invalidPrivateKeyFormat(string $reason): self
    {
        return new self(sprintf('Invalid private key format: %s', $reason));
    }

    public static function missingEnvVar(string $var): self
    {
        return new self(sprintf('Missing required env: %s', $var));
    }
}
