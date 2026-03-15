<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests verifying FcmMessageData DTO behavior and factory methods.
 *
 * Ensures message creation, type-specific helpers, priority calculation,
 * visibility rules, and FCM data formatting work as expected.
 */
final class FcmMessageDataTest extends TestCase
{
    /**
     * Verifies that a basic message can be instantiated with minimal parameters.
     */
    public function test_can_create_basic_message(): void
    {
        // Arrange: Define minimal required parameters
        $type = NotificationType::INFO;
        $title = 'Test Title';
        $body = 'Test Body';

        // Act: Create message with only required fields
        $message = new FcmMessageData(
            type: $type,
            title: $title,
            body: $body
        );

        // Assert: All properties should be set with defaults for omitted fields
        $this->assertEquals($type, $message->type);
        $this->assertEquals($title, $message->title);
        $this->assertEquals($body, $message->body);
        $this->assertEquals([], $message->data);
        $this->assertTrue($message->contentAvailable);
        $this->assertEquals(0, $message->ttl);
    }

    /**
     * Confirms that the info notification helper sets correct type and preserves custom data.
     */
    public function test_info_helper_creates_correct_message(): void
    {
        // Act: Create an info notification with custom data
        $message = FcmMessageData::info(
            title: 'Info Title',
            body: 'Info Body',
            data: ['key' => 'value']
        );

        // Assert: Verify info-specific configuration
        $this->assertEquals(NotificationType::INFO, $message->type);
        $this->assertEquals('Info Title', $message->title);
        $this->assertEquals('Info Body', $message->body);
        $this->assertEquals(['key' => 'value'], $message->data);
    }

    /**
     * Verifies that alert notifications are properly configured with high visibility.
     */
    public function test_alert_helper_creates_correct_message(): void
    {
        // Act: Create an alert notification
        $message = FcmMessageData::alert(
            title: 'Alert Title',
            body: 'Alert Body'
        );

        // Assert: Alert should have appropriate type and visibility settings
        $this->assertEquals(NotificationType::ALERT, $message->type);
        $this->assertEquals('Alert Title', $message->title);
        $this->assertEquals('Alert Body', $message->body);
        $this->assertFalse($message->contentAvailable);
    }

    /**
     * Ensures warning notifications have correct type and content.
     */
    public function test_warning_helper_creates_correct_message(): void
    {
        // Act: Create a warning notification
        $message = FcmMessageData::warning(
            title: 'Warning Title',
            body: 'Warning Body'
        );

        // Assert: Warning should have appropriate type
        $this->assertEquals(NotificationType::WARNING, $message->type);
        $this->assertEquals('Warning Title', $message->title);
        $this->assertEquals('Warning Body', $message->body);
        $this->assertFalse($message->contentAvailable);
    }

    /**
     * Validates that success notifications are properly configured.
     */
    public function test_success_helper_creates_correct_message(): void
    {
        // Act: Create a success notification
        $message = FcmMessageData::success(
            title: 'Success Title',
            body: 'Success Body'
        );

        // Assert: Success should have appropriate type
        $this->assertEquals(NotificationType::SUCCESS, $message->type);
        $this->assertEquals('Success Title', $message->title);
        $this->assertEquals('Success Body', $message->body);
    }

    /**
     * Confirms that error notifications have correct type and content.
     */
    public function test_error_helper_creates_correct_message(): void
    {
        // Act: Create an error notification
        $message = FcmMessageData::error(
            title: 'Error Title',
            body: 'Error Body'
        );

        // Assert: Error should have appropriate type
        $this->assertEquals(NotificationType::ERROR, $message->type);
        $this->assertEquals('Error Title', $message->title);
        $this->assertEquals('Error Body', $message->body);
        $this->assertFalse($message->contentAvailable);
    }

    /**
     * Verifies that ping notifications are configured as silent background messages.
     */
    public function test_ping_helper_creates_correct_message(): void
    {
        // Act: Create a ping (silent) notification
        $message = FcmMessageData::ping();

        // Assert: Ping should have silent configuration with timestamp
        $this->assertEquals(NotificationType::PING, $message->type);
        $this->assertEquals('Connectivity Check', $message->title);
        $this->assertEquals('', $message->body);
        $this->assertTrue($message->contentAvailable);
        $this->assertArrayHasKey('timestamp', $message->data);
    }

    /**
     * Ensures each notification type returns appropriate FCM priority.
     */
    public function test_get_priority_returns_correct_values(): void
    {
        // Arrange: Create test messages for each type
        $infoMessage = FcmMessageData::info(title: '', body: '');
        $alertMessage = FcmMessageData::alert(title: '', body: '');
        $warningMessage = FcmMessageData::warning(title: '', body: '');
        $successMessage = FcmMessageData::success(title: '', body: '');
        $errorMessage = FcmMessageData::error(title: '', body: '');
        $pingMessage = FcmMessageData::ping();

        // Assert: Verify priority mapping by notification type
        $this->assertEquals('normal', $infoMessage->getPriority());
        $this->assertEquals('high', $alertMessage->getPriority());
        $this->assertEquals('high', $warningMessage->getPriority());
        $this->assertEquals('normal', $successMessage->getPriority());
        $this->assertEquals('high', $errorMessage->getPriority());
        $this->assertEquals('normal', $pingMessage->getPriority());
    }

    /**
     * Confirms that visibility rules correctly identify visible vs silent notifications.
     */
    public function test_is_visible_returns_correct_values(): void
    {
        // Arrange: Create test messages for each type
        $infoMessage = FcmMessageData::info(title: '', body: '');
        $alertMessage = FcmMessageData::alert(title: '', body: '');
        $warningMessage = FcmMessageData::warning(title: '', body: '');
        $successMessage = FcmMessageData::success(title: '', body: '');
        $errorMessage = FcmMessageData::error(title: '', body: '');
        $pingMessage = FcmMessageData::ping();

        // Assert: Only ping should be invisible (silent)
        $this->assertTrue($infoMessage->isVisible());
        $this->assertTrue($alertMessage->isVisible());
        $this->assertTrue($warningMessage->isVisible());
        $this->assertTrue($successMessage->isVisible());
        $this->assertTrue($errorMessage->isVisible());
        $this->assertFalse($pingMessage->isVisible());
    }

    /**
     * Verifies that FCM data conversion properly stringifies all values.
     *
     * FCM requires all values to be strings, including metadata and custom data.
     */
    public function test_to_fcm_data_converts_all_values_to_strings(): void
    {
        // Arrange: Create message with mixed data types
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Test',
            body: 'Body',
            data: [
                'int' => 123,
                'bool' => true,
                'float' => 45.67
            ],
            imageUrl: 'https://example.com/image.jpg',
            clickAction: 'OPEN_APP',
            ttl: 3600
        );

        // Act: Convert to FCM-compatible format
        $fcmData = $message->toFcmData();

        // Assert: All values must be strings
        foreach ($fcmData as $value) {
            $this->assertIsString($value);
        }

        // Verify specific conversions for custom data
        $this->assertEquals('123', $fcmData['int']);
        $this->assertEquals('1', $fcmData['bool']);
        $this->assertEquals('45.67', $fcmData['float']);
    }

    /**
     * Ensures that basic messages include all required metadata fields.
     */
    public function test_to_fcm_data_includes_metadata_for_basic_message(): void
    {
        // Arrange: Create minimal message without custom data
        $message = FcmMessageData::info(title: 'Test', body: 'Body');

        // Act: Convert to FCM format
        $fcmData = $message->toFcmData();

        // Assert: Should include metadata fields
        $this->assertEquals('info', $fcmData['type']);
        $this->assertEquals('Test', $fcmData['title']);
        $this->assertEquals('Body', $fcmData['body']);
        $this->assertEquals('normal', $fcmData['priority']);
        $this->assertArrayHasKey('timestamp', $fcmData);
        $this->assertIsString($fcmData['timestamp']);

        // Optional fields should be null and filtered out
        $this->assertArrayNotHasKey('image_url', $fcmData);
        $this->assertArrayNotHasKey('click_action', $fcmData);
        $this->assertArrayNotHasKey('ttl', $fcmData);
    }

    /**
     * Verifies that messages include custom data when provided.
     */
    public function test_to_fcm_data_includes_custom_data_when_provided(): void
    {
        // Arrange: Create message with custom data
        $message = FcmMessageData::info(
            title: 'Test',
            body: 'Body',
            data: ['custom_key' => 'custom_value']
        );

        // Act: Convert to FCM format
        $fcmData = $message->toFcmData();

        // Assert: Should include metadata plus custom data
        $this->assertEquals('info', $fcmData['type']);
        $this->assertEquals('Test', $fcmData['title']);
        $this->assertEquals('Body', $fcmData['body']);
        $this->assertEquals('normal', $fcmData['priority']);
        $this->assertArrayHasKey('timestamp', $fcmData);
        $this->assertEquals('custom_value', $fcmData['custom_key']);
    }

    /**
     * Verifies that TTL is included only when greater than zero.
     */
    public function test_to_fcm_data_includes_ttl_only_when_positive(): void
    {
        // Arrange: Create message with TTL
        $messageWithTtl = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Test',
            body: 'Body',
            ttl: 3600
        );

        $messageWithoutTtl = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Test',
            body: 'Body',
            ttl: 0
        );

        // Act
        $fcmDataWithTtl = $messageWithTtl->toFcmData();
        $fcmDataWithoutTtl = $messageWithoutTtl->toFcmData();

        // Assert
        $this->assertEquals('3600', $fcmDataWithTtl['ttl']);
        $this->assertArrayNotHasKey('ttl', $fcmDataWithoutTtl);
    }
}
