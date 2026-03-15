<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Services;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Services\FcmPayloadBuilder;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests for FcmPayloadBuilder service.
 */
final class FcmPayloadBuilderTest extends TestCase
{
    private FcmPayloadBuilder $builder;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new FcmPayloadBuilder();
    }

    /**
     * Test that basic payload is built correctly.
     */
    public function test_builds_basic_payload(): void
    {
        // Arrange
        $deviceToken = 'test-device-token-123';
        $message = FcmMessageData::info('Test Title', 'Test Body');

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayHasKey('message', $payload);
        $this->assertEquals($deviceToken, $payload['message']['token']);
        $this->assertArrayHasKey('data', $payload['message']);
        $this->assertEquals('info', $payload['message']['data']['type']);
        $this->assertEquals('Test Title', $payload['message']['data']['title']);
    }

    /**
     * Test that visible notifications include notification block.
     */
    public function test_visible_notifications_include_notification_block(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::info('Visible Title', 'Visible Body');

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayHasKey('notification', $payload['message']);
        $this->assertEquals('Visible Title', $payload['message']['notification']['title']);
        $this->assertEquals('Visible Body', $payload['message']['notification']['body']);
    }

    /**
     * Test that ping notifications don't include notification block.
     */
    public function test_ping_notifications_dont_include_notification_block(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::ping();

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayNotHasKey('notification', $payload['message']);
    }

    /**
     * Test that Android configuration is added correctly.
     */
    public function test_android_configuration_is_added(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::alert('Alert', 'Body');

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayHasKey('android', $payload['message']);
        $this->assertEquals('high', $payload['message']['android']['priority']);
    }

    /**
     * Test that Android channel ID is added when provided.
     */
    public function test_android_channel_id_is_added_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            channelId: 'test-channel'
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayHasKey('notification', $payload['message']['android']);
        $this->assertEquals('test-channel', $payload['message']['android']['notification']['channel_id']);
    }

    /**
     * Test that APNs configuration is added correctly.
     */
    public function test_apns_configuration_is_added(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::alert('Alert', 'Body');

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertArrayHasKey('apns', $payload['message']);
        $this->assertArrayHasKey('payload', $payload['message']['apns']);
        $this->assertArrayHasKey('aps', $payload['message']['apns']['payload']);
        $this->assertArrayHasKey('headers', $payload['message']['apns']);
    }

    /**
     * Test that APNs priority is set correctly based on message priority.
     */
    public function test_apns_priority_is_set_correctly(): void
    {
        // Arrange
        $deviceToken = 'test-token';

        // Act
        $highPriorityPayload = $this->builder->build(
            $deviceToken,
            FcmMessageData::alert('High', 'Priority')
        );

        $normalPriorityPayload = $this->builder->build(
            $deviceToken,
            FcmMessageData::info('Normal', 'Priority')
        );

        // Assert
        $this->assertEquals('10', $highPriorityPayload['message']['apns']['headers']['apns-priority']);
        $this->assertEquals('5', $normalPriorityPayload['message']['apns']['headers']['apns-priority']);
    }

    /**
     * Test that iOS badge is added when provided.
     */
    public function test_ios_badge_is_added_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            badge: 5
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertEquals(5, $payload['message']['apns']['payload']['aps']['badge']);
    }

    /**
     * Test that iOS sound is added when provided.
     */
    public function test_ios_sound_is_added_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            sound: 'custom.wav'
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertEquals('custom.wav', $payload['message']['apns']['payload']['aps']['sound']);
    }

    /**
     * Test that content-available flag is set correctly.
     */
    public function test_content_available_flag_is_set_correctly(): void
    {
        // Arrange
        $deviceToken = 'test-token';

        // Act
        $withContentAvailable = $this->builder->build(
            $deviceToken,
            new FcmMessageData(
                type: NotificationType::INFO,
                title: 'Title',
                body: 'Body',
                contentAvailable: true
            )
        );

        $withoutContentAvailable = $this->builder->build(
            $deviceToken,
            new FcmMessageData(
                type: NotificationType::INFO,
                title: 'Title',
                body: 'Body',
                contentAvailable: false
            )
        );

        // Assert
        $this->assertEquals(1, $withContentAvailable['message']['apns']['payload']['aps']['content-available']);
        $this->assertEquals(0, $withoutContentAvailable['message']['apns']['payload']['aps']['content-available']);
    }

    /**
     * Test that TTL is added when provided.
     */
    public function test_ttl_is_added_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            ttl: 3600
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertEquals('3600s', $payload['message']['android']['ttl']);
    }

    /**
     * Test that image URL is added to notification when provided.
     */
    public function test_image_url_is_added_to_notification_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            imageUrl: 'https://example.com/image.jpg'
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertEquals('https://example.com/image.jpg', $payload['message']['notification']['image']);
        $this->assertEquals('https://example.com/image.jpg', $payload['message']['data']['image_url']);
    }

    /**
     * Test that click action is added when provided.
     */
    public function test_click_action_is_added_when_provided(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            clickAction: 'OPEN_ACTIVITY'
        );

        // Act
        $payload = $this->builder->build($deviceToken, $message);

        // Assert
        $this->assertEquals('OPEN_ACTIVITY', $payload['message']['notification']['click_action']);
        $this->assertEquals('OPEN_ACTIVITY', $payload['message']['data']['click_action']);
    }
}
