<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Fixtures;

/**
 * Fixtures for Firebase configuration testing.
 */
final class FirebaseConfigFixture
{
    /**
     * Get the path to the test Firebase credentials JSON file.
     */
    public static function getJsonFilePath(): string
    {
        return __DIR__ . '/firebase-credentials.json';
    }

    /**
     * Get the content of the test Firebase credentials JSON file.
     */
    public static function getJsonContent(): string
    {
        return file_get_contents(self::getJsonFilePath());
    }

    /**
     * Get a valid Firebase configuration array from the JSON file.
     *
     * @return array<string, mixed>
     */
    public static function getValidConfig(): array
    {
        return json_decode(self::getJsonContent(), true);
    }

    /**
     * Get the content of the test Firebase credentials JSON file as a string.
     */
    public static function getValidJsonString(): string
    {
        return file_get_contents(self::getJsonFilePath());
    }

    /**
     * Get a valid private key string from the JSON file.
     */
    public static function getValidPrivateKey(): string
    {
        $config = self::getValidConfig();
        return $config['private_key'];
    }

    /**
     * Get an invalid private key (missing BEGIN/END markers).
     */
    public static function getInvalidPrivateKey(): string
    {
        return "invalid-key-without-markers";
    }

    /**
     * Get a config with missing required fields.
     *
     * @return array<string, mixed>
     */
    public static function getConfigMissingFields(): array
    {
        return [
            'project_id' => 'test-project-123',
            // missing client_email and private_key
        ];
    }

    /**
     * Get a config with invalid token URI.
     *
     * @return array<string, mixed>
     */
    public static function getConfigWithInvalidTokenUri(): array
    {
        $config = self::getValidConfig();
        $config['token_uri'] = 'not-a-valid-url';

        return $config;
    }

    /**
     * Get a mock JWT token for testing.
     */
    public static function getMockJwtToken(): string
    {
        return 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ0ZXN0QGV4YW1wbGUuY29tIiwic2NvcGUiOiJodHRwczovL3d3dy5nb29nbGVhcGlzLmNvbS9hdXRoL2ZpcmViYXNlLm1lc3NhZ2luZyIsImF1ZCI6Imh0dHBzOi8vb2F1dGgyLmdvb2dsZWFwaXMuY29tL3Rva2VuIiwiaWF0IjoxNzAwMDAwMDAwLCJleHAiOjE3MDAwMDM2MDB9.signature';
    }

    /**
     * Get a mock OAuth2 response.
     *
     * @return array<string, mixed>
     */
    public static function getMockOAuthResponse(): array
    {
        return [
            'access_token' => 'ya29.mock.access.token.123456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Get a mock FCM send response.
     *
     * @return array<string, mixed>
     */
    public static function getMockFcmResponse(): array
    {
        return [
            'name' => 'projects/test-project-123/messages/msg-123456789',
        ];
    }

    /**
     * Get a mock FCM error response.
     *
     * @param string $errorCode
     * @param string $errorMessage
     * @return array<string, mixed>
     */
    public static function getMockFcmErrorResponse(string $errorCode = 'UNKNOWN', string $errorMessage = 'Unknown error'): array
    {
        return [
            'error' => [
                'code' => 400,
                'message' => $errorMessage,
                'status' => $errorCode,
            ],
        ];
    }

    /**
     * Get environment variables for testing.
     *
     * @return array<string, string>
     */
    public static function getMockEnvVars(): array
    {
        return [
            'FIREBASE_PROJECT_ID' => 'test-project-123',
            'FIREBASE_CLIENT_EMAIL' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            'FIREBASE_PRIVATE_KEY' => str_replace("\n", '\n', self::getValidPrivateKey()),
            'FIREBASE_TOKEN_URI' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
