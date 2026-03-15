<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FcmResponseData;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests for FcmResponseData DTO.
 */
final class FcmResponseDataTest extends TestCase
{
    /**
     * Test that successful response can be created.
     */
    public function test_can_create_successful_response(): void
    {
        // Arrange
        $responseData = [
            'name' => 'projects/test-project/messages/msg-123456',
        ];

        // Act
        $response = FcmResponseData::fromFcmResponse($responseData, 200);

        // Assert
        $this->assertTrue($response->success);
        $this->assertEquals('msg-123456', $response->messageId);
        $this->assertEquals('projects/test-project/messages/msg-123456', $response->name);
        $this->assertEquals($responseData, $response->rawResponse);
        $this->assertEquals(200, $response->statusCode);
        $this->assertNull($response->errorCode);
        $this->assertNull($response->errorMessage);
    }

    /**
     * Test that error response can be created.
     */
    public function test_can_create_error_response(): void
    {
        // Act
        $response = FcmResponseData::fromError('UNREGISTERED', 'Device token invalid', 404);

        // Assert
        $this->assertFalse($response->success);
        $this->assertEquals('', $response->messageId);
        $this->assertEquals('', $response->name);
        $this->assertEquals('UNREGISTERED', $response->errorCode);
        $this->assertEquals('Device token invalid', $response->errorMessage);
        $this->assertEquals(404, $response->statusCode);
    }

    /**
     * Test that message ID is correctly extracted from full name.
     */
    public function test_message_id_extracted_correctly(): void
    {
        // Arrange
        $variations = [
            'projects/test/messages/msg-123' => 'msg-123',
            'projects/test/messages/msg-456/extra' => 'extra',
            'msg-789' => 'msg-789',
        ];

        foreach ($variations as $name => $expectedId) {
            // Act
            $response = FcmResponseData::fromFcmResponse(['name' => $name]);

            // Assert
            $this->assertEquals($expectedId, $response->messageId);
        }
    }

    /**
     * Test that isInvalidToken detects invalid token errors.
     */
    public function test_is_invalid_token_detects_token_errors(): void
    {
        // Arrange
        $invalidTokenErrors = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'];
        $otherErrors = ['QUOTA_EXCEEDED', 'INTERNAL', 'UNAVAILABLE'];

        foreach ($invalidTokenErrors as $errorCode) {
            // Act
            $response = FcmResponseData::fromError($errorCode, 'Error');

            // Assert
            $this->assertTrue($response->isInvalidToken(), "Failed for error code: {$errorCode}");
        }

        foreach ($otherErrors as $errorCode) {
            // Act
            $response = FcmResponseData::fromError($errorCode, 'Error');

            // Assert
            $this->assertFalse($response->isInvalidToken(), "Failed for error code: {$errorCode}");
        }
    }

    /**
     * Test that isQuotaExceeded detects quota errors.
     */
    public function test_is_quota_exceeded_detects_quota_errors(): void
    {
        // Arrange
        $quotaErrors = ['QUOTA_EXCEEDED', 'RESOURCE_EXHAUSTED', 'RATE_EXCEEDED'];
        $otherErrors = ['UNREGISTERED', 'INTERNAL', 'UNAVAILABLE'];

        foreach ($quotaErrors as $errorCode) {
            // Act
            $response = FcmResponseData::fromError($errorCode, 'Error');

            // Assert
            $this->assertTrue($response->isQuotaExceeded(), "Failed for error code: {$errorCode}");
        }

        foreach ($otherErrors as $errorCode) {
            // Act
            $response = FcmResponseData::fromError($errorCode, 'Error');

            // Assert
            $this->assertFalse($response->isQuotaExceeded(), "Failed for error code: {$errorCode}");
        }
    }

    /**
     * Test that isAuthError detects authentication errors.
     */
    public function test_is_auth_error_detects_auth_errors(): void
    {
        // Arrange
        $authErrorCodes = ['UNAUTHENTICATED', 'PERMISSION_DENIED'];
        $authStatusCodes = [401, 403];

        foreach ($authErrorCodes as $errorCode) {
            // Act
            $response = FcmResponseData::fromError($errorCode, 'Error');

            // Assert
            $this->assertTrue($response->isAuthError(), "Failed for error code: {$errorCode}");
        }

        foreach ($authStatusCodes as $statusCode) {
            // Act
            $response = FcmResponseData::fromError('OTHER', 'Error', $statusCode);

            // Assert
            $this->assertTrue($response->isAuthError(), "Failed for status code: {$statusCode}");
        }

        // Test non-auth errors
        $response = FcmResponseData::fromError('UNREGISTERED', 'Error', 404);
        $this->assertFalse($response->isAuthError());
    }
}
