<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;
use Andydefer\PushNotifier\Tests\TestCase;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;

/**
 * Unit tests validating FirebaseConfigData DTO creation and validation.
 *
 * Ensures configuration objects are properly constructed from various sources
 * (arrays, JSON, files, environment) and that validation catches invalid formats.
 */
final class FirebaseConfigDataTest extends TestCase
{
    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /**
     * Verifies direct instantiation with valid configuration parameters.
     */
    public function test_can_create_valid_config(): void
    {
        // Arrange: Load valid Firebase configuration fixture
        $config = FirebaseConfigFixture::getValidConfig();

        // Act: Create configuration instance with direct constructor
        $firebaseConfig = new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key'],
            tokenUri: $config['token_uri']
        );

        // Assert: All properties should match input values
        $this->assertEquals($config['project_id'], $firebaseConfig->projectId);
        $this->assertEquals($config['client_email'], $firebaseConfig->clientEmail);
        $this->assertEquals($config['private_key'], $firebaseConfig->privateKey);
        $this->assertEquals($config['token_uri'], $firebaseConfig->tokenUri);
    }

    /**
     * Confirms service account array is properly mapped to configuration object.
     */
    public function test_can_create_from_service_account(): void
    {
        // Arrange: Prepare service account configuration array
        $config = FirebaseConfigFixture::getValidConfig();

        // Act: Create configuration using service account factory
        $firebaseConfig = FirebaseConfigData::fromServiceAccount($config);

        // Assert: Required fields should be correctly mapped
        $this->assertEquals($config['project_id'], $firebaseConfig->projectId);
        $this->assertEquals($config['client_email'], $firebaseConfig->clientEmail);
        $this->assertEquals($config['private_key'], $firebaseConfig->privateKey);
    }

    /**
     * Ensures default token URI is applied when not specified in configuration.
     */
    public function test_uses_default_token_uri_when_not_provided(): void
    {
        // Arrange: Remove token_uri from valid configuration
        $config = FirebaseConfigFixture::getValidConfig();
        unset($config['token_uri']);

        // Act: Create configuration without explicit token URI
        $firebaseConfig = FirebaseConfigData::fromServiceAccount($config);

        // Assert: Default token URI should be automatically assigned
        $this->assertEquals(self::DEFAULT_TOKEN_URI, $firebaseConfig->tokenUri);
    }

    /**
     * Validates JSON string parsing into configuration object.
     */
    public function test_can_create_from_json_string(): void
    {
        // Arrange: Load service account JSON content
        $jsonString = file_get_contents(FirebaseConfigFixture::getJsonFilePath());

        // Act: Parse JSON string into configuration
        $firebaseConfig = FirebaseConfigData::fromJsonString($jsonString);

        // Assert: Configuration should match expected values from JSON
        $this->assertEquals('autotext-d50ea', $firebaseConfig->projectId);
        $this->assertEquals('firebase-adminsdk-fbsvc@autotext-d50ea.iam.gserviceaccount.com', $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
        $this->assertStringContainsString('-----END PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Verifies configuration loading from physical JSON file.
     */
    public function test_can_create_from_json_file(): void
    {
        // Arrange: Get path to service account JSON file
        $jsonPath = FirebaseConfigFixture::getJsonFilePath();

        // Act: Load configuration from file
        $firebaseConfig = FirebaseConfigData::fromJsonFile($jsonPath);

        // Assert: Configuration should match file contents
        $this->assertEquals('autotext-d50ea', $firebaseConfig->projectId);
        $this->assertEquals('firebase-adminsdk-fbsvc@autotext-d50ea.iam.gserviceaccount.com', $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Ensures appropriate exception when loading non-existent JSON file.
     */
    public function test_throws_exception_when_json_file_not_found(): void
    {
        // Arrange: Define path to non-existent file
        $nonExistentPath = '/path/to/nonexistent/file.json';

        // Assert: Loading should trigger file not found exception
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Firebase service account file not found: {$nonExistentPath}");

        // Act: Attempt to load from missing file
        FirebaseConfigData::fromJsonFile($nonExistentPath);
    }

    /**
     * Validates error handling for malformed JSON strings.
     */
    public function test_throws_exception_on_invalid_json_string(): void
    {
        // Assert: Invalid JSON should trigger parsing exception
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid Firebase service account JSON: Malformed content');

        // Act: Attempt to parse broken JSON
        FirebaseConfigData::fromJsonString('{invalid json}');
    }

    /**
     * Ensures private key validation catches missing BEGIN marker.
     */
    public function test_validation_fails_when_private_key_missing_begin_marker(): void
    {
        // Arrange: Create config with malformed private key (missing BEGIN)
        $config = FirebaseConfigFixture::getValidConfig();
        $config['private_key'] = 'invalid-key';

        // Assert: Constructor should reject invalid key format
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid private key format: Missing BEGIN PRIVATE KEY marker');

        // Act: Attempt to create config with incomplete key
        new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key']
        );
    }

    /**
     * Confirms private key validation catches missing END marker.
     */
    public function test_validation_fails_when_private_key_missing_end_marker(): void
    {
        // Arrange: Create config with private key missing END marker
        $config = FirebaseConfigFixture::getValidConfig();
        $config['private_key'] = "-----BEGIN PRIVATE KEY-----\nkey-content\n";

        // Assert: Constructor should detect incomplete key
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid private key format: Missing END PRIVATE KEY marker');

        // Act: Attempt to create config with truncated key
        new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key']
        );
    }

    /**
     * Verifies validation of required fields in service account configuration.
     */
    public function test_throws_exception_when_required_fields_missing(): void
    {
        // Arrange: Load incomplete configuration missing required fields
        $config = FirebaseConfigFixture::getConfigMissingFields();

        // Assert: Missing field should trigger validation exception
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required service account field: client_email');

        // Act: Attempt to create from incomplete data
        FirebaseConfigData::fromServiceAccount($config);
    }

    /**
     * Validates configuration creation from environment variables.
     */
    public function test_can_create_from_env(): void
    {
        // Arrange: Prepare mock environment variables
        $env = FirebaseConfigFixture::getMockEnvVars();

        // Act: Create configuration from environment
        $firebaseConfig = FirebaseConfigData::fromEnv($env);

        // Assert: Properties should match environment values
        $this->assertEquals($env['FIREBASE_PROJECT_ID'], $firebaseConfig->projectId);
        $this->assertEquals($env['FIREBASE_CLIENT_EMAIL'], $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Ensures proper error when required environment variables are missing.
     */
    public function test_throws_exception_when_env_vars_missing(): void
    {
        // Arrange: Provide incomplete environment variables
        $incompleteEnv = [
            'FIREBASE_PROJECT_ID' => 'test',
        ];

        // Assert: Missing environment variable should trigger exception
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required env: FIREBASE_CLIENT_EMAIL');

        // Act: Attempt to create from incomplete environment
        FirebaseConfigData::fromEnv($incompleteEnv);
    }

    /**
     * Verifies that configuration can be created with minimal required fields.
     */
    public function test_can_create_with_minimal_required_fields(): void
    {
        // Arrange: Prepare minimal valid configuration (no token_uri)
        $config = FirebaseConfigFixture::getValidConfig();
        unset($config['token_uri']);

        // Act: Create configuration without optional token_uri
        $firebaseConfig = FirebaseConfigData::fromServiceAccount($config);

        // Assert: Configuration should be valid with default token URI
        $this->assertEquals($config['project_id'], $firebaseConfig->projectId);
        $this->assertEquals($config['client_email'], $firebaseConfig->clientEmail);
        $this->assertEquals($config['private_key'], $firebaseConfig->privateKey);
        $this->assertEquals(self::DEFAULT_TOKEN_URI, $firebaseConfig->tokenUri);
    }

    /**
     * Confirms that whitespace in private key is properly handled.
     */
    public function test_handles_whitespace_in_private_key(): void
    {
        // Arrange: Create config with extra whitespace in private key
        $config = FirebaseConfigFixture::getValidConfig();
        $config['private_key'] = "  \n  " . $config['private_key'] . "  \n  ";

        // Act: Create configuration with whitespace-padded key
        $firebaseConfig = new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key']
        );

        // Assert: Key should preserve its structure despite surrounding whitespace
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
        $this->assertStringContainsString('-----END PRIVATE KEY-----', $firebaseConfig->privateKey);

        // The actual key content (including markers) should remain intact
        $this->assertStringContainsString(
            $config['private_key'],
            $firebaseConfig->privateKey
        );
    }
}
