<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Fixtures;

/**
 * Provides test data fixtures for Firebase configuration testing.
 *
 * This class centralizes all mock data, configuration samples, and test assets
 * used across the test suite to ensure consistency and maintainability.
 */
final class FirebaseConfigFixture
{
    private const FCM_MESSAGE_NAME = 'projects/test-project-123/messages/msg-123456789';
    private const MOCK_ACCESS_TOKEN = 'ya29.mock.access.token.123456';
    private const MOCK_JWT_TOKEN = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ0ZXN0QGV4YW1wbGUuY29tIiwic2NvcGUiOiJodHRwczovL3d3dy5nb29nbGVhcGlzLmNvbS9hdXRoL2ZpcmViYXNlLm1lc3NhZ2luZyIsImF1ZCI6Imh0dHBzOi8vb2F1dGgyLmdvb2dsZWFwaXMuY29tL3Rva2VuIiwiaWF0IjoxNzAwMDAwMDAwLCJleHAiOjE3MDAwMDM2MDB9.signature';

    /**
     * Returns the absolute path to the test Firebase credentials JSON file.
     */
    public static function getJsonFilePath(): string
    {
        return __DIR__ . '/firebase-credentials.json';
    }

    /**
     * Retrieves the raw JSON content from the test credentials file.
     */
    public static function getJsonContent(): string
    {
        return file_get_contents(self::getJsonFilePath());
    }

    /**
     * Provides a complete, valid Firebase configuration array.
     *
     * @return array<string, mixed> Parsed service account JSON
     */
    public static function getValidConfig(): array
    {
        return json_decode(self::getJsonContent(), true);
    }

    /**
     * Returns a valid Firebase private key extracted from the configuration.
     */
    public static function getValidPrivateKey(): string
    {
        $config = self::getValidConfig();
        return $config['private_key'];
    }

    /**
     * Generates a malformed private key missing required PEM markers.
     *
     * Used for testing error handling with invalid key formats.
     */
    public static function getInvalidPrivateKey(): string
    {
        return 'invalid-key-without-markers';
    }

    /**
     * Creates a configuration array missing required authentication fields.
     *
     * Tests validation logic when client_email or private_key are absent.
     *
     * @return array<string, mixed> Incomplete configuration
     */
    public static function getConfigMissingFields(): array
    {
        return [
            'project_id' => 'test-project-123',
        ];
    }

    /**
     * Produces a configuration with an invalid token URI format.
     *
     * Verifies URL validation during configuration processing.
     *
     * @return array<string, mixed> Configuration with malformed token_uri
     */
    public static function getConfigWithInvalidTokenUri(): array
    {
        $config = self::getValidConfig();
        $config['token_uri'] = 'not-a-valid-url';

        return $config;
    }

    /**
     * Returns a pre-generated mock JWT token for authentication testing.
     */
    public static function getMockJwtToken(): string
    {
        return self::MOCK_JWT_TOKEN;
    }

    /**
     * Provides a simulated successful OAuth2 token response.
     *
     * @return array<string, mixed> Mock OAuth2 credentials
     */
    public static function getMockOAuthResponse(): array
    {
        return [
            'access_token' => self::MOCK_ACCESS_TOKEN,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Generates a mock successful FCM API response.
     *
     * @return array<string, mixed> FCM message confirmation
     */
    public static function getMockFcmResponse(): array
    {
        return [
            'name' => self::FCM_MESSAGE_NAME,
        ];
    }

    /**
     * Creates a mock FCM error response for testing failure scenarios.
     *
     * @param string $errorCode FCM error status (e.g., 'UNREGISTERED', 'UNAUTHENTICATED')
     * @param string $errorMessage Human-readable error description
     * @return array<string, mixed> Structured error response
     */
    public static function getMockFcmErrorResponse(
        string $errorCode = 'UNKNOWN',
        string $errorMessage = 'Unknown error'
    ): array {
        return [
            'error' => [
                'code' => 400,
                'message' => $errorMessage,
                'status' => $errorCode,
            ],
        ];
    }

    /**
     * Provides environment variables for testing configuration loading.
     *
     * @return array<string, string> Key-value pairs of environment settings
     */
    public static function getMockEnvVars(): array
    {
        return [
            'FIREBASE_PROJECT_ID' => 'test-project-123',
            'FIREBASE_CLIENT_EMAIL' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            'FIREBASE_PRIVATE_KEY' => str_replace(search: "\n", replace: '\n', subject: self::getValidPrivateKey()),
            'FIREBASE_TOKEN_URI' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
