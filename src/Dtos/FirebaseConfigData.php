<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Dtos;

use Spatie\LaravelData\Data;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;

/**
 * Firebase project authentication configuration.
 *
 * This data transfer object encapsulates all credentials and settings required
 * for authenticating with Firebase Cloud Messaging using a service account.
 * It ensures the private key format is valid and provides multiple factory methods
 * for flexible configuration loading.
 */
class FirebaseConfigData extends Data
{
    /**
     * Creates a new Firebase configuration instance.
     *
     * @param string $projectId Firebase project unique identifier
     * @param string $clientEmail Service account email for authentication
     * @param string $privateKey PEM-formatted private key (preserves newlines)
     * @param string $tokenUri OAuth2 token endpoint URL
     *
     * @throws InvalidConfigurationException When private key format is invalid
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $clientEmail,
        public readonly string $privateKey,
        public readonly string $tokenUri = 'https://oauth2.googleapis.com/token',
    ) {
        $this->ensurePrivateKeyHasValidFormat();
    }

    /**
     * Validates the private key contains proper PEM markers.
     *
     * @throws InvalidConfigurationException When key lacks required BEGIN/END markers
     */
    private function ensurePrivateKeyHasValidFormat(): void
    {
        if (!str_contains($this->privateKey, '-----BEGIN PRIVATE KEY-----')) {
            throw new InvalidConfigurationException(
                'Invalid private key format: Missing BEGIN PRIVATE KEY marker'
            );
        }

        if (!str_contains($this->privateKey, '-----END PRIVATE KEY-----')) {
            throw new InvalidConfigurationException(
                'Invalid private key format: Missing END PRIVATE KEY marker'
            );
        }
    }

    /**
     * Creates configuration from a Firebase service account JSON string.
     *
     * This is the primary method for loading credentials from Google Cloud
     * Console downloads. Preserves the exact key format from the source.
     *
     * @param string $jsonContent Raw JSON content from service account file
     * @return self Configured Firebase credentials
     *
     * @throws InvalidConfigurationException When JSON is malformed or invalid
     */
    public static function fromJsonString(string $jsonContent): self
    {
        $serviceAccountData = json_decode($jsonContent, true);

        if (!is_array($serviceAccountData)) {
            throw new InvalidConfigurationException('Invalid Firebase service account JSON: Malformed content');
        }

        return self::fromServiceAccount($serviceAccountData);
    }

    /**
     * Creates configuration from a Firebase service account JSON file.
     *
     * Convenience method for loading credentials directly from a file path.
     * The private key formatting is preserved exactly as stored in the file.
     *
     * @param string $jsonPath Absolute or relative path to service account JSON file
     * @return self Configured Firebase credentials
     *
     * @throws InvalidConfigurationException When file is missing or unreadable
     */
    public static function fromJsonFile(string $jsonPath): self
    {
        if (!file_exists($jsonPath)) {
            throw new InvalidConfigurationException("Firebase service account file not found: {$jsonPath}");
        }

        $fileContents = file_get_contents($jsonPath);
        if ($fileContents === false) {
            throw new InvalidConfigurationException("Unable to read Firebase service account file: {$jsonPath}");
        }

        return self::fromJsonString($fileContents);
    }

    /**
     * Creates configuration from a parsed Firebase service account array.
     *
     * Processes the raw service account data structure and validates
     * that all required fields are present.
     *
     * @param array{
     *     project_id: string,
     *     client_email: string,
     *     private_key: string,
     *     token_uri?: string
     * } $serviceAccountData Parsed service account credentials
     * @return self Configured Firebase credentials
     *
     * @throws InvalidConfigurationException When required fields are missing
     */
    public static function fromServiceAccount(array $serviceAccountData): self
    {
        $requiredFields = ['project_id', 'client_email', 'private_key'];
        foreach ($requiredFields as $field) {
            if (!isset($serviceAccountData[$field])) {
                throw new InvalidConfigurationException("Missing required service account field: {$field}");
            }
        }

        return new self(
            projectId: $serviceAccountData['project_id'],
            clientEmail: $serviceAccountData['client_email'],
            privateKey: $serviceAccountData['private_key'],
            tokenUri: $serviceAccountData['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        );
    }

    /**
     * Creates configuration from environment variables.
     *
     * Note: When storing private keys in .env files, ensure newlines are
     * properly escaped as \n to maintain the correct PEM format.
     *
     * @param array<string, string> $environmentVariables Environment variables array (e.g., $_ENV)
     * @return self Configured Firebase credentials
     *
     * @throws InvalidConfigurationException When required environment variables are missing
     */
    public static function fromEnv(array $environmentVariables): self
    {
        return new self(
            projectId: $environmentVariables['FIREBASE_PROJECT_ID'] ?? throw new InvalidConfigurationException('Missing required env: FIREBASE_PROJECT_ID'),
            clientEmail: $environmentVariables['FIREBASE_CLIENT_EMAIL'] ?? throw new InvalidConfigurationException('Missing required env: FIREBASE_CLIENT_EMAIL'),
            privateKey: self::normalizePrivateKeyFromEnv($environmentVariables['FIREBASE_PRIVATE_KEY'] ?? throw new InvalidConfigurationException('Missing required env: FIREBASE_PRIVATE_KEY')),
            tokenUri: $environmentVariables['FIREBASE_TOKEN_URI'] ?? 'https://oauth2.googleapis.com/token',
        );
    }

    /**
     * Converts escaped newlines in environment variable back to actual newlines.
     *
     * Environment variables often require escaping newlines as \n. This method
     * restores them to the proper PEM format expected by cryptographic functions.
     *
     * @param string $rawPrivateKey Private key from environment with escaped newlines
     * @return string Private key with actual newline characters
     */
    private static function normalizePrivateKeyFromEnv(string $rawPrivateKey): string
    {
        return str_replace('\\n', "\n", $rawPrivateKey);
    }
}
