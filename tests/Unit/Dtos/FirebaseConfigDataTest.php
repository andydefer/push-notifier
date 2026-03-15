<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;
use Andydefer\PushNotifier\Tests\TestCase;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;

/**
 * Unit tests for FirebaseConfigData DTO.
 */
final class FirebaseConfigDataTest extends TestCase
{
    /**
     * Test that a valid configuration can be created.
     */
    public function test_can_create_valid_config(): void
    {
        // Arrange
        $config = FirebaseConfigFixture::getValidConfig();

        // Act
        $firebaseConfig = new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key'],
            tokenUri: $config['token_uri']
        );

        // Assert
        $this->assertEquals($config['project_id'], $firebaseConfig->projectId);
        $this->assertEquals($config['client_email'], $firebaseConfig->clientEmail);
        $this->assertEquals($config['private_key'], $firebaseConfig->privateKey);
        $this->assertEquals($config['token_uri'], $firebaseConfig->tokenUri);
    }

    /**
     * Test that fromServiceAccount creates valid config.
     */
    public function test_can_create_from_service_account(): void
    {
        // Arrange
        $config = FirebaseConfigFixture::getValidConfig();

        // Act
        $firebaseConfig = FirebaseConfigData::fromServiceAccount($config);

        // Assert
        $this->assertEquals($config['project_id'], $firebaseConfig->projectId);
        $this->assertEquals($config['client_email'], $firebaseConfig->clientEmail);
        $this->assertEquals($config['private_key'], $firebaseConfig->privateKey);
    }

    /**
     * Test that fromServiceAccount uses default token URI when not provided.
     */
    public function test_uses_default_token_uri_when_not_provided(): void
    {
        // Arrange
        $config = FirebaseConfigFixture::getValidConfig();
        unset($config['token_uri']);

        // Act
        $firebaseConfig = FirebaseConfigData::fromServiceAccount($config);

        // Assert
        $this->assertEquals('https://oauth2.googleapis.com/token', $firebaseConfig->tokenUri);
    }

    /**
     * Test that fromJsonString creates valid config.
     */
    public function test_can_create_from_json_string(): void
    {
        // Arrange
        $jsonString = file_get_contents(FirebaseConfigFixture::getJsonFilePath());

        // Act
        $firebaseConfig = FirebaseConfigData::fromJsonString($jsonString);

        // Assert
        $this->assertEquals('autotext-d50ea', $firebaseConfig->projectId);
        $this->assertEquals('firebase-adminsdk-fbsvc@autotext-d50ea.iam.gserviceaccount.com', $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
        $this->assertStringContainsString('-----END PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Test that fromJsonFile creates valid config.
     */
    public function test_can_create_from_json_file(): void
    {
        // Arrange
        $jsonPath = FirebaseConfigFixture::getJsonFilePath();

        // Act
        $firebaseConfig = FirebaseConfigData::fromJsonFile($jsonPath);

        // Assert
        $this->assertEquals('autotext-d50ea', $firebaseConfig->projectId);
        $this->assertEquals('firebase-adminsdk-fbsvc@autotext-d50ea.iam.gserviceaccount.com', $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Test that fromJsonFile throws exception when file not found.
     */
    public function test_throws_exception_when_json_file_not_found(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Firebase config file not found');

        // Act
        FirebaseConfigData::fromJsonFile('/path/to/nonexistent/file.json');
    }

    /**
     * Test that fromJsonString throws exception on invalid JSON.
     */
    public function test_throws_exception_on_invalid_json_string(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid Firebase service account JSON');

        // Act
        FirebaseConfigData::fromJsonString('{invalid json}');
    }

    /**
     * Test that validation fails when private key missing BEGIN marker.
     */
    public function test_validation_fails_when_private_key_missing_begin_marker(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid private key format: Must contain BEGIN PRIVATE KEY marker');

        // Arrange
        $config = FirebaseConfigFixture::getValidConfig();
        $config['private_key'] = 'invalid-key';

        // Act
        new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key']
        );
    }

    /**
     * Test that validation fails when private key missing END marker.
     */
    public function test_validation_fails_when_private_key_missing_end_marker(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid private key format: Must contain END PRIVATE KEY marker');

        // Arrange
        $config = FirebaseConfigFixture::getValidConfig();
        $config['private_key'] = "-----BEGIN PRIVATE KEY-----\nkey-content\n";

        // Act
        new FirebaseConfigData(
            projectId: $config['project_id'],
            clientEmail: $config['client_email'],
            privateKey: $config['private_key']
        );
    }

    /**
     * Test that fromServiceAccount throws exception when required fields missing.
     */
    public function test_throws_exception_when_required_fields_missing(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required field: client_email');

        // Arrange
        $config = FirebaseConfigFixture::getConfigMissingFields();

        // Act
        FirebaseConfigData::fromServiceAccount($config);
    }

    /**
     * Test that fromEnv creates valid config.
     */
    public function test_can_create_from_env(): void
    {
        // Arrange
        $env = FirebaseConfigFixture::getMockEnvVars();

        // Act
        $firebaseConfig = FirebaseConfigData::fromEnv($env);

        // Assert
        $this->assertEquals($env['FIREBASE_PROJECT_ID'], $firebaseConfig->projectId);
        $this->assertEquals($env['FIREBASE_CLIENT_EMAIL'], $firebaseConfig->clientEmail);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $firebaseConfig->privateKey);
    }

    /**
     * Test that fromEnv throws exception when env vars missing.
     */
    public function test_throws_exception_when_env_vars_missing(): void
    {
        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing FIREBASE_CLIENT_EMAIL');

        // Act
        FirebaseConfigData::fromEnv([
            'FIREBASE_PROJECT_ID' => 'test',
        ]);
    }
}
