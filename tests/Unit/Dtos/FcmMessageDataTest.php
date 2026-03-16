<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Tests\TestCase;
use InvalidArgumentException;

/**
 * Unit tests verifying FcmMessageData DTO behavior and factory methods.
 *
 * Ensures message creation, type-specific helpers, and validation work as expected.
 */
final class FcmMessageDataTest extends TestCase
{
    /**
     * Verifies that a basic message can be created with the constructor.
     */
    public function test_can_create_basic_message(): void
    {
        // Act: Create message with minimal parameters
        $message = new FcmMessageData(
            type: 'INFO',
            data: ['title' => 'Test Title', 'body' => 'Test Body']
        );

        // Assert: Properties should be set correctly
        $this->assertEquals('INFO', $message->type);
        $this->assertEquals(['title' => 'Test Title', 'body' => 'Test Body'], $message->data);
    }

    /**
     * Verifies that type must be in SCREAMING_SNAKE_CASE.
     */
    public function test_validates_type_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être en SCREAMING_SNAKE_CASE');

        new FcmMessageData(type: 'info', data: []);
    }

    /**
     * Verifies that data keys must be in camelCase.
     */
    public function test_validates_data_keys_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être en camelCase');

        new FcmMessageData(type: 'INFO', data: ['snake_case' => 'value']);
    }

    /**
     * Accepts valid camelCase keys.
     */
    public function test_accepts_valid_camel_case_keys(): void
    {
        $this->expectNotToPerformAssertions();

        new FcmMessageData(type: 'INFO', data: [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'orderId' => 123,
            'isActive' => true,
            'createdAt' => time(),
        ]);
    }

    /**
     * Confirms that the info notification helper works correctly.
     */
    public function test_info_helper_creates_correct_message(): void
    {
        // Act: Create an info notification with custom data
        $message = FcmMessageData::info(
            title: 'Info Title',
            body: 'Info Body',
            data: ['customKey' => 'customValue']
        );

        // Assert: Verify info-specific configuration
        $this->assertEquals('INFO', $message->type);
        $this->assertEquals('Info Title', $message->data['title']);
        $this->assertEquals('Info Body', $message->data['body']);
        $this->assertEquals('customValue', $message->data['customKey']);
    }

    /**
     * Verifies that alert notifications are properly configured.
     */
    public function test_alert_helper_creates_correct_message(): void
    {
        // Act: Create an alert notification
        $message = FcmMessageData::alert(
            title: 'Alert Title',
            body: 'Alert Body'
        );

        // Assert: Alert should have appropriate type
        $this->assertEquals('ALERT', $message->type);
        $this->assertEquals('Alert Title', $message->data['title']);
        $this->assertEquals('Alert Body', $message->data['body']);
    }

    /**
     * Ensures warning notifications have correct type.
     */
    public function test_warning_helper_creates_correct_message(): void
    {
        // Act: Create a warning notification
        $message = FcmMessageData::warning(
            title: 'Warning Title',
            body: 'Warning Body'
        );

        // Assert: Warning should have appropriate type
        $this->assertEquals('WARNING', $message->type);
        $this->assertEquals('Warning Title', $message->data['title']);
        $this->assertEquals('Warning Body', $message->data['body']);
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
        $this->assertEquals('SUCCESS', $message->type);
        $this->assertEquals('Success Title', $message->data['title']);
        $this->assertEquals('Success Body', $message->data['body']);
    }

    /**
     * Confirms that error notifications have correct type.
     */
    public function test_error_helper_creates_correct_message(): void
    {
        // Act: Create an error notification
        $message = FcmMessageData::error(
            title: 'Error Title',
            body: 'Error Body'
        );

        // Assert: Error should have appropriate type
        $this->assertEquals('ERROR', $message->type);
        $this->assertEquals('Error Title', $message->data['title']);
        $this->assertEquals('Error Body', $message->data['body']);
    }

    /**
     * Verifies that ping notifications are configured as silent background messages.
     */
    public function test_ping_helper_creates_correct_message(): void
    {
        // Act: Create a ping (silent) notification
        $message = FcmMessageData::ping(['customKey' => 'value']);

        // Assert: Ping should have correct type and data
        $this->assertEquals('PING', $message->type);
        $this->assertEquals('value', $message->data['customKey']);
    }

    /**
     * Test the make helper for custom types.
     */
    public function test_make_helper_creates_custom_message(): void
    {
        // Act: Create a custom notification
        $message = FcmMessageData::make(
            type: 'CUSTOM_EVENT',
            data: [
                'eventName' => 'user_login',
                'userId' => 123,
                'timestamp' => time()
            ]
        );

        // Assert: Custom type and data are preserved
        $this->assertEquals('CUSTOM_EVENT', $message->type);
        $this->assertEquals('user_login', $message->data['eventName']);
        $this->assertEquals(123, $message->data['userId']);
    }

    /**
     * Verifies that toArray returns the correct structure.
     */
    public function test_to_array_returns_correct_structure(): void
    {
        // Arrange: Create a message
        $message = FcmMessageData::info(
            title: 'Test',
            body: 'Body',
            data: ['extra' => 'data']
        );

        // Act: Convert to array
        $array = $message->toArray();

        // Assert: Array has correct structure
        $this->assertEquals([
            'type' => 'INFO',
            'data' => [
                'title' => 'Test',
                'body' => 'Body',
                'extra' => 'data'
            ]
        ], $array);
    }
}
