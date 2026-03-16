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
     * Verifies that data values must be strings.
     */
    public function test_validates_data_values_are_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('doit être une string');

        new FcmMessageData(type: 'INFO', data: ['orderId' => 123]);
    }

    /**
     * Accepts valid camelCase keys with string values.
     */
    public function test_accepts_valid_camel_case_keys_with_string_values(): void
    {
        $this->expectNotToPerformAssertions();

        new FcmMessageData(type: 'INFO', data: [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'orderId' => '123',
            'isActive' => 'true',
            'createdAt' => (string) time(),
        ]);
    }

    /**
     * Confirms that the info notification helper works correctly.
     */
    public function test_info_helper_creates_correct_message(): void
    {
        // Act: Create an info notification
        $message = FcmMessageData::info(
            title: 'Info Title',
            body: 'Info Body'
        );

        // Assert: Verify info-specific configuration
        $this->assertEquals('INFO', $message->type);
        $this->assertEquals('Info Title', $message->data['title']);
        $this->assertEquals('Info Body', $message->data['body']);
        $this->assertCount(2, $message->data);
    }

    /**
     * Verifies that ping notifications are configured as silent background messages.
     */
    public function test_ping_helper_creates_correct_message(): void
    {
        // Act: Create a ping (silent) notification
        $message = FcmMessageData::ping();

        // Assert: Ping should have correct type and default data
        $this->assertEquals('PING', $message->type);
        $this->assertEquals('true', $message->data['connected']);
        $this->assertCount(1, $message->data);
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
                'userId' => '123',
                'timestamp' => (string) time()
            ]
        );

        // Assert: Custom type and data are preserved
        $this->assertEquals('CUSTOM_EVENT', $message->type);
        $this->assertEquals('user_login', $message->data['eventName']);
        $this->assertEquals('123', $message->data['userId']);
    }

    /**
     * Verifies that toArray returns the correct structure.
     */
    public function test_to_array_returns_correct_structure(): void
    {
        // Arrange: Create a message
        $message = FcmMessageData::info(
            title: 'Test',
            body: 'Body'
        );

        // Act: Convert to array
        $array = $message->toArray();

        // Assert: Array has correct structure
        $this->assertEquals([
            'type' => 'INFO',
            'data' => [
                'title' => 'Test',
                'body' => 'Body'
            ]
        ], $array);
    }
}
