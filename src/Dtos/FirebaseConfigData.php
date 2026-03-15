<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;

class FirebaseConfigData extends Data
{
    /**
     * @param string $projectId Firebase project ID
     * @param string $clientEmail Service account client email
     * @param string $privateKey Private key for JWT signing (preserved with newlines)
     * @param string $tokenUri OAuth2 token URI
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $clientEmail,
        public readonly string $privateKey,
        public readonly string $tokenUri = 'https://oauth2.googleapis.com/token',
    ) {
        $this->validatePrivateKey();
    }

    /**
     * Validate that the private key has the correct format.
     *
     * @throws InvalidConfigurationException
     */
    private function validatePrivateKey(): void
    {
        if (!str_contains($this->privateKey, '-----BEGIN PRIVATE KEY-----')) {
            throw new InvalidConfigurationException(
                'Invalid private key format: Must contain BEGIN PRIVATE KEY marker'
            );
        }

        if (!str_contains($this->privateKey, '-----END PRIVATE KEY-----')) {
            throw new InvalidConfigurationException(
                'Invalid private key format: Must contain END PRIVATE KEY marker'
            );
        }
    }

    /**
     * Create from Firebase service account JSON string.
     * This is the recommended way to initialize the config.
     *
     * @param string $jsonContent Raw JSON content from service account file
     * @throws InvalidConfigurationException
     */
    public static function fromJsonString(string $jsonContent): self
    {
        $data = json_decode($jsonContent, true);

        if (!is_array($data)) {
            throw new InvalidConfigurationException('Invalid Firebase service account JSON');
        }

        return self::fromServiceAccount($data);
    }

    /**
     * Create from Firebase service account JSON file path.
     * This preserves the private key exactly as in the file.
     *
     * @param string $jsonPath Path to service account JSON file
     * @throws InvalidConfigurationException
     */
    public static function fromJsonFile(string $jsonPath): self
    {
        if (!file_exists($jsonPath)) {
            throw new InvalidConfigurationException("Firebase config file not found: {$jsonPath}");
        }

        $content = file_get_contents($jsonPath);
        if ($content === false) {
            throw new InvalidConfigurationException("Failed to read Firebase config file: {$jsonPath}");
        }

        return self::fromJsonString($content);
    }

    /**
     * Create from Firebase service account JSON array.
     *
     * @param array{
     *     project_id: string,
     *     client_email: string,
     *     private_key: string,
     *     token_uri?: string
     * } $data
     * @throws InvalidConfigurationException
     */
    public static function fromServiceAccount(array $data): self
    {
        $required = ['project_id', 'client_email', 'private_key'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidConfigurationException("Missing required field: {$field}");
            }
        }

        return new self(
            projectId: $data['project_id'],
            clientEmail: $data['client_email'],
            privateKey: $data['private_key'],
            tokenUri: $data['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        );
    }

    /**
     * Create from environment variables.
     * WARNING: Be careful with private key newlines in .env files!
     *
     * @param array<string, string> $env
     * @throws InvalidConfigurationException
     */
    public static function fromEnv(array $env): self
    {
        return new self(
            projectId: $env['FIREBASE_PROJECT_ID'] ?? throw new InvalidConfigurationException('Missing FIREBASE_PROJECT_ID'),
            clientEmail: $env['FIREBASE_CLIENT_EMAIL'] ?? throw new InvalidConfigurationException('Missing FIREBASE_CLIENT_EMAIL'),
            privateKey: str_replace('\\n', "\n", $env['FIREBASE_PRIVATE_KEY'] ?? throw new InvalidConfigurationException('Missing FIREBASE_PRIVATE_KEY')),
            tokenUri: $env['FIREBASE_TOKEN_URI'] ?? 'https://oauth2.googleapis.com/token',
        );
    }
}
