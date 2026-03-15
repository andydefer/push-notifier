<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Dtos;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests for FcmMessageData DTO.
 */
final class FcmMessageDataTest extends TestCase
{
    /**
     * Test that a basic message can be created.
     */
    public function test_can_create_basic_message(): void
    {
        // Arrange
        $type = NotificationType::INFO;
        $title = 'Test Title';
        $body = 'Test Body';

        // Act
        $message = new FcmMessageData(
            type: $type,
            title: $title,
            body: $body
        );

        // Assert
        $this->assertEquals($type, $message->type);
        $this->assertEquals($title, $message->title);
        $this->assertEquals($body, $message->body);
        $this->assertEquals([], $message->data);
        $this->assertTrue($message->contentAvailable);
        $this->assertEquals(0, $message->ttl);
    }

    /**
     * Test that info notification helper creates correct message.
     */
    public function test_info_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::info('Info Title', 'Info Body', ['key' => 'value']);

        // Assert
        $this->assertEquals(NotificationType::INFO, $message->type);
        $this->assertEquals('Info Title', $message->title);
        $this->assertEquals('Info Body', $message->body);
        $this->assertEquals(['key' => 'value'], $message->data);
    }

    /**
     * Test that alert notification helper creates correct message.
     */
    public function test_alert_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::alert('Alert Title', 'Alert Body');

        // Assert
        $this->assertEquals(NotificationType::ALERT, $message->type);
        $this->assertEquals('Alert Title', $message->title);
        $this->assertEquals('Alert Body', $message->body);
        $this->assertFalse($message->contentAvailable);
    }

    /**
     * Test that warning notification helper creates correct message.
     */
    public function test_warning_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::warning('Warning Title', 'Warning Body');

        // Assert
        $this->assertEquals(NotificationType::WARNING, $message->type);
        $this->assertEquals('Warning Title', $message->title);
        $this->assertEquals('Warning Body', $message->body);
    }

    /**
     * Test that success notification helper creates correct message.
     */
    public function test_success_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::success('Success Title', 'Success Body');

        // Assert
        $this->assertEquals(NotificationType::SUCCESS, $message->type);
        $this->assertEquals('Success Title', $message->title);
        $this->assertEquals('Success Body', $message->body);
    }

    /**
     * Test that error notification helper creates correct message.
     */
    public function test_error_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::error('Error Title', 'Error Body');

        // Assert
        $this->assertEquals(NotificationType::ERROR, $message->type);
        $this->assertEquals('Error Title', $message->title);
        $this->assertEquals('Error Body', $message->body);
    }

    /**
     * Test that ping notification helper creates correct message.
     */
    public function test_ping_helper_creates_correct_message(): void
    {
        // Act
        $message = FcmMessageData::ping();

        // Assert
        $this->assertEquals(NotificationType::PING, $message->type);
        $this->assertEquals('Connectivity Check', $message->title);
        $this->assertEquals('', $message->body);
        $this->assertTrue($message->contentAvailable);
    }

    /**
     * Test that getPriority returns correct priority for different types.
     */
    public function test_get_priority_returns_correct_values(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals('normal', FcmMessageData::info('', '')->getPriority());
        $this->assertEquals('high', FcmMessageData::alert('', '')->getPriority());
        $this->assertEquals('high', FcmMessageData::warning('', '')->getPriority());
        $this->assertEquals('normal', FcmMessageData::success('', '')->getPriority());
        $this->assertEquals('high', FcmMessageData::error('', '')->getPriority());
        $this->assertEquals('normal', FcmMessageData::ping()->getPriority());
    }

    /**
     * Test that isVisible returns correct values for different types.
     */
    public function test_is_visible_returns_correct_values(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(FcmMessageData::info('', '')->isVisible());
        $this->assertTrue(FcmMessageData::alert('', '')->isVisible());
        $this->assertTrue(FcmMessageData::warning('', '')->isVisible());
        $this->assertTrue(FcmMessageData::success('', '')->isVisible());
        $this->assertTrue(FcmMessageData::error('', '')->isVisible());
        $this->assertFalse(FcmMessageData::ping()->isVisible());
    }

    /**
     * Test that toFcmData converts all values to strings.
     */
    public function test_to_fcm_data_converts_all_values_to_strings(): void
    {
        // Arrange
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Test',
            body: 'Body',
            data: ['int' => 123, 'bool' => true, 'float' => 45.67],
            imageUrl: 'https://example.com/image.jpg',
            clickAction: 'OPEN_APP',
            ttl: 3600
        );

        // Act
        $fcmData = $message->toFcmData();

        // Assert
        foreach ($fcmData as $value) {
            $this->assertIsString($value);
        }

        $this->assertEquals('123', $fcmData['int']);
        $this->assertEquals('1', $fcmData['bool']);
        $this->assertEquals('45.67', $fcmData['float']);
        $this->assertEquals('3600', $fcmData['ttl']);
        $this->assertEquals('https://example.com/image.jpg', $fcmData['image_url']);
        $this->assertEquals('OPEN_APP', $fcmData['click_action']);
    }
}
