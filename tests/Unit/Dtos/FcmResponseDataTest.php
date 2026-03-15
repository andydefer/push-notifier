<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FcmResponseData;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests validating FcmResponseData DTO behavior for both successful
 * and failed Firebase Cloud Messaging API responses.
 */
final class FcmResponseDataTest extends TestCase
{
    /**
     * Verifies that a successful FCM response is properly parsed into a DTO.
     */
    public function test_can_create_successful_response(): void
    {
        // Arrange: Prepare a mock FCM success response
        $responseData = [
            'name' => 'projects/test-project/messages/msg-123456',
        ];

        // Act: Convert raw response to DTO
        $response = FcmResponseData::fromFcmResponse(
            response: $responseData,
            statusCode: 200
        );

        // Assert: Verify all success properties are correctly set
        $this->assertTrue($response->success);
        $this->assertEquals('msg-123456', $response->messageId);
        $this->assertEquals('projects/test-project/messages/msg-123456', $response->name);
        $this->assertEquals($responseData, $response->rawResponse);
        $this->assertEquals(200, $response->statusCode);
        $this->assertNull($response->errorCode);
        $this->assertNull($response->errorMessage);
    }

    /**
     * Verifies that error responses are properly structured with failure details.
     */
    public function test_can_create_error_response(): void
    {
        // Act: Create an error response DTO
        $response = FcmResponseData::fromError(
            errorCode: 'UNREGISTERED',
            errorMessage: 'Device token invalid',
            statusCode: 404
        );

        // Assert: Verify error properties are correctly populated
        $this->assertFalse($response->success);
        $this->assertEquals('', $response->messageId);
        $this->assertEquals('', $response->name);
        $this->assertEquals('UNREGISTERED', $response->errorCode);
        $this->assertEquals('Device token invalid', $response->errorMessage);
        $this->assertEquals(404, $response->statusCode);
    }

    /**
     * Confirms that message IDs are correctly extracted from various resource name formats.
     */
    public function test_message_id_extracted_correctly(): void
    {
        // Arrange: Test various resource name formats
        $resourceNameScenarios = [
            'projects/test/messages/msg-123' => 'msg-123',
            'projects/test/messages/msg-456/extra' => 'extra',
            'msg-789' => 'msg-789',
        ];

        foreach ($resourceNameScenarios as $resourceName => $expectedMessageId) {
            // Act: Parse each resource name variation
            $response = FcmResponseData::fromFcmResponse(
                response: ['name' => $resourceName]
            );

            // Assert: Verify message ID extraction
            $this->assertEquals($expectedMessageId, $response->messageId);
        }
    }

    /**
     * Verifies that token invalidation errors are correctly identified.
     */
    public function test_is_invalid_token_detects_token_errors(): void
    {
        // Arrange: Categorize error codes
        $invalidTokenErrorCodes = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'];
        $otherErrorCodes = ['QUOTA_EXCEEDED', 'INTERNAL', 'UNAVAILABLE'];

        // Act & Assert: Verify invalid token detection
        foreach ($invalidTokenErrorCodes as $errorCode) {
            $response = FcmResponseData::fromError(
                errorCode: $errorCode,
                errorMessage: 'Error'
            );

            $this->assertTrue(
                condition: $response->isInvalidToken(),
                message: "Failed to detect invalid token for error code: {$errorCode}"
            );
        }

        // Act & Assert: Verify other errors are not flagged
        foreach ($otherErrorCodes as $errorCode) {
            $response = FcmResponseData::fromError(
                errorCode: $errorCode,
                errorMessage: 'Error'
            );

            $this->assertFalse(
                condition: $response->isInvalidToken(),
                message: "Incorrectly flagged as invalid token: {$errorCode}"
            );
        }
    }

    /**
     * Verifies that quota exceeded errors are correctly identified.
     */
    public function test_is_quota_exceeded_detects_quota_errors(): void
    {
        // Arrange: Categorize error codes
        $quotaExceededErrorCodes = ['QUOTA_EXCEEDED', 'RESOURCE_EXHAUSTED', 'RATE_EXCEEDED'];
        $otherErrorCodes = ['UNREGISTERED', 'INTERNAL', 'UNAVAILABLE'];

        // Act & Assert: Verify quota detection
        foreach ($quotaExceededErrorCodes as $errorCode) {
            $response = FcmResponseData::fromError(
                errorCode: $errorCode,
                errorMessage: 'Error'
            );

            $this->assertTrue(
                condition: $response->isQuotaExceeded(),
                message: "Failed to detect quota exceeded for error code: {$errorCode}"
            );
        }

        // Act & Assert: Verify other errors are not flagged
        foreach ($otherErrorCodes as $errorCode) {
            $response = FcmResponseData::fromError(
                errorCode: $errorCode,
                errorMessage: 'Error'
            );

            $this->assertFalse(
                condition: $response->isQuotaExceeded(),
                message: "Incorrectly flagged as quota exceeded: {$errorCode}"
            );
        }
    }

    /**
     * Verifies that authentication errors are correctly identified from both
     * error codes and HTTP status codes.
     */
    public function test_is_auth_error_detects_auth_errors(): void
    {
        // Arrange: Test cases for auth errors
        $authErrorScenarios = [
            'error_codes' => ['UNAUTHENTICATED', 'PERMISSION_DENIED'],
            'auth_status_codes' => [401, 403],
        ];

        // Act & Assert: Verify error code detection
        foreach ($authErrorScenarios['error_codes'] as $errorCode) {
            $response = FcmResponseData::fromError(
                errorCode: $errorCode,
                errorMessage: 'Error'
            );

            $this->assertTrue(
                condition: $response->isAuthError(),
                message: "Failed to detect auth error for error code: {$errorCode}"
            );
        }

        // Act & Assert: Verify HTTP status code detection
        foreach ($authErrorScenarios['auth_status_codes'] as $statusCode) {
            $response = FcmResponseData::fromError(
                errorCode: 'OTHER',
                errorMessage: 'Error',
                statusCode: $statusCode
            );

            $this->assertTrue(
                condition: $response->isAuthError(),
                message: "Failed to detect auth error for status code: {$statusCode}"
            );
        }

        // Act: Test non-auth error
        $nonAuthResponse = FcmResponseData::fromError(
            errorCode: 'UNREGISTERED',
            errorMessage: 'Error',
            statusCode: 404
        );

        // Assert: Verify non-auth errors are correctly excluded
        $this->assertFalse(
            condition: $nonAuthResponse->isAuthError(),
            message: "Incorrectly flagged non-auth error as auth error"
        );
    }
}
