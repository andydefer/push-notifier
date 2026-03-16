<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use InvalidArgumentException;

/**
 * Data Transfer Object for Firebase Cloud Messaging messages.
 *
 * Simple et flexible :
 * - type : string en SCREAMING_SNAKE_CASE
 * - data : array avec clés en camelCase (valeurs must be strings)
 */
class FcmMessageData
{
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
    ) {
        $this->validateTypeCase($type);
        $this->validateDataKeysCase($data);
        $this->validateDataValues($data);
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

    private function validateDataValues(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    sprintf('La valeur de "%s" doit être une string, %s donné', $key, gettype($value))
                );
            }
        }
    }

    public static function make(string $type, array $data = []): self
    {
        return new self($type, $data);
    }

    public static function info(string $title, string $body): self
    {
        return new self('INFO', [
            'title' => $title,
            'body' => $body
        ]);
    }

    public static function ping(): self
    {
        return new self('PING', [
            'connected' => 'true'
        ]);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
