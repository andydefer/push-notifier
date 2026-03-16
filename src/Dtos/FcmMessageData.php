<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use InvalidArgumentException;

/**
 * Data Transfer Object for Firebase Cloud Messaging messages.
 *
 * Simple et flexible :
 * - type : string en SCREAMING_SNAKE_CASE
 * - data : array avec clés en camelCase (ce que l'utilisateur veut)
 */
class FcmMessageData
{
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
    ) {
        $this->validateTypeCase($type);
        $this->validateDataKeysCase($data);
    }

    private function validateTypeCase(string $type): void
    {
        if (!preg_match('/^[A-Z][A-Z0-9]*(_[A-Z][A-Z0-9]*)*$/', $type)) {
            throw new InvalidArgumentException(
                sprintf('Le type "%s" doit être en SCREAMING_SNAKE_CASE', $type)
            );
        }
    }

    private function validateDataKeysCase(array $data): void
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Les clés de data doivent être des strings');
            }

            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $key)) {
                throw new InvalidArgumentException(
                    sprintf('La clé "%s" doit être en camelCase', $key)
                );
            }
        }
    }

    public static function make(string $type, array $data = []): self
    {
        return new self($type, $data);
    }

    public static function info(string $title, string $body, array $data = []): self
    {
        return new self('INFO', array_merge(['title' => $title, 'body' => $body], $data));
    }

    public static function alert(string $title, string $body, array $data = []): self
    {
        return new self('ALERT', array_merge(['title' => $title, 'body' => $body], $data));
    }

    public static function warning(string $title, string $body, array $data = []): self
    {
        return new self('WARNING', array_merge(['title' => $title, 'body' => $body], $data));
    }

    public static function success(string $title, string $body, array $data = []): self
    {
        return new self('SUCCESS', array_merge(['title' => $title, 'body' => $body], $data));
    }

    public static function error(string $title, string $body, array $data = []): self
    {
        return new self('ERROR', array_merge(['title' => $title, 'body' => $body], $data));
    }

    public static function ping(array $data = []): self
    {
        return new self('PING', $data);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
