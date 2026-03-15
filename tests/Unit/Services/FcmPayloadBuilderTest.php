<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Services;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Services\FcmPayloadBuilder;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests validating FCM payload structure generation.
 *
 * Verifies that the payload builder correctly formats messages for
 * different platforms (Android/iOS) and notification types.
 */
final class FcmPayloadBuilderTest extends TestCase
{
    private FcmPayloadBuilder $builder;

    /**
     * Initializes a fresh payload builder for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new FcmPayloadBuilder();
    }

    /**
     * Verifies that minimal required payload structure is always present.
     */
    public function test_builds_basic_payload(): void
    {
        // Arrange: Create minimal notification
        $deviceToken = 'test-device-token-123';
        $message = FcmMessageData::info(title: 'Test Title', body: 'Test Body');

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Core structure exists with correct values
        $this->assertArrayHasKey('message', $payload);
        $this->assertEquals($deviceToken, $payload['message']['token']);
        $this->assertArrayHasKey('data', $payload['message']);
        $this->assertEquals('info', $payload['message']['data']['type']);
        $this->assertEquals('Test Title', $payload['message']['data']['title']);
    }

    /**
     * Ensures visible notifications include platform notification blocks.
     */
    public function test_visible_notifications_include_notification_block(): void
    {
        // Arrange: Create visible notification
        $deviceToken = 'test-token';
        $message = FcmMessageData::info(title: 'Visible Title', body: 'Visible Body');

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Notification block present with correct content
        $this->assertArrayHasKey('notification', $payload['message']);
        $this->assertEquals('Visible Title', $payload['message']['notification']['title']);
        $this->assertEquals('Visible Body', $payload['message']['notification']['body']);
    }

    /**
     * Confirms silent notifications (ping) omit visual notification blocks.
     */
    public function test_ping_notifications_dont_include_notification_block(): void
    {
        // Arrange: Create silent ping notification
        $deviceToken = 'test-token';
        $message = FcmMessageData::ping();

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: No notification block for silent messages
        $this->assertArrayNotHasKey('notification', $payload['message']);
    }

    /**
     * Verifies Android-specific configuration is properly structured.
     */
    public function test_android_configuration_is_added(): void
    {
        // Arrange: Create notification requiring Android config
        $deviceToken = 'test-token';
        $message = FcmMessageData::alert(title: 'Alert', body: 'Body');

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Android block exists with correct priority
        $this->assertArrayHasKey('android', $payload['message']);
        $this->assertEquals('high', $payload['message']['android']['priority']);
    }

    /**
     * Ensures Android channel ID is properly propagated.
     */
    public function test_android_channel_id_is_added_when_provided(): void
    {
        // Arrange: Create message with specific Android channel
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            channelId: 'test-channel'
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Channel ID appears in Android notification config
        $this->assertArrayHasKey('notification', $payload['message']['android']);
        $this->assertEquals('test-channel', $payload['message']['android']['notification']['channel_id']);
    }

    /**
     * Validates iOS (APNs) configuration structure.
     */
    public function test_apns_configuration_is_added(): void
    {
        // Arrange: Create notification requiring iOS delivery
        $deviceToken = 'test-token';
        $message = FcmMessageData::alert(title: 'Alert', body: 'Body');

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: APNs block structure is complete
        $this->assertArrayHasKey('apns', $payload['message']);
        $this->assertArrayHasKey('payload', $payload['message']['apns']);
        $this->assertArrayHasKey('aps', $payload['message']['apns']['payload']);
        $this->assertArrayHasKey('headers', $payload['message']['apns']);
    }

    /**
     * Confirms iOS priority mapping based on notification type.
     */
    public function test_apns_priority_is_set_correctly(): void
    {
        // Arrange: Prepare device token
        $deviceToken = 'test-token';

        // Act: Generate payloads for different priority levels
        $highPriorityPayload = $this->builder->build(
            deviceToken: $deviceToken,
            message: FcmMessageData::alert(title: 'High', body: 'Priority')
        );

        $normalPriorityPayload = $this->builder->build(
            deviceToken: $deviceToken,
            message: FcmMessageData::info(title: 'Normal', body: 'Priority')
        );

        // Assert: APNs priority reflects message importance
        $this->assertEquals('10', $highPriorityPayload['message']['apns']['headers']['apns-priority']);
        $this->assertEquals('5', $normalPriorityPayload['message']['apns']['headers']['apns-priority']);
    }

    /**
     * Verifies iOS badge number is properly set.
     */
    public function test_ios_badge_is_added_when_provided(): void
    {
        // Arrange: Create message with badge count
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            badge: 5
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Badge appears in APNs payload
        $this->assertEquals(5, $payload['message']['apns']['payload']['aps']['badge']);
    }

    /**
     * Ensures custom iOS sound is properly configured.
     */
    public function test_ios_sound_is_added_when_provided(): void
    {
        // Arrange: Create message with custom sound
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            sound: 'custom.wav'
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Custom sound appears in APNs configuration
        $this->assertEquals('custom.wav', $payload['message']['apns']['payload']['aps']['sound']);
    }

    /**
     * Validates content-available flag for background updates.
     */
    public function test_content_available_flag_is_set_correctly(): void
    {
        // Arrange: Prepare device token
        $deviceToken = 'test-token';

        // Act: Generate payloads with and without content-available
        $withContentAvailable = $this->builder->build(
            deviceToken: $deviceToken,
            message: new FcmMessageData(
                type: NotificationType::INFO,
                title: 'Title',
                body: 'Body',
                contentAvailable: true
            )
        );

        $withoutContentAvailable = $this->builder->build(
            deviceToken: $deviceToken,
            message: new FcmMessageData(
                type: NotificationType::INFO,
                title: 'Title',
                body: 'Body',
                contentAvailable: false
            )
        );

        // Assert: Flag correctly toggles between 1 and 0
        $this->assertEquals(1, $withContentAvailable['message']['apns']['payload']['aps']['content-available']);
        $this->assertEquals(0, $withoutContentAvailable['message']['apns']['payload']['aps']['content-available']);
    }

    /**
     * Confirms TTL (Time To Live) is properly formatted.
     */
    public function test_ttl_is_added_when_provided(): void
    {
        // Arrange: Create message with TTL
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            ttl: 3600
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: TTL appears with proper unit suffix
        $this->assertEquals('3600s', $payload['message']['android']['ttl']);
    }

    /**
     * Verifies image URL is included in both notification and data payloads.
     */
    public function test_image_url_is_added_to_notification_when_provided(): void
    {
        // Arrange: Create message with image
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            imageUrl: 'https://example.com/image.jpg'
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Image appears in both visible and data sections
        $this->assertEquals('https://example.com/image.jpg', $payload['message']['notification']['image']);
        $this->assertEquals('https://example.com/image.jpg', $payload['message']['data']['image_url']);
    }

    /**
     * Ensures click action is available in both notification and data.
     */
    public function test_click_action_is_added_when_provided(): void
    {
        // Arrange: Create message with click action
        $deviceToken = 'test-token';
        $message = new FcmMessageData(
            type: NotificationType::INFO,
            title: 'Title',
            body: 'Body',
            clickAction: 'OPEN_ACTIVITY'
        );

        // Act: Generate payload
        $payload = $this->builder->build(deviceToken: $deviceToken, message: $message);

        // Assert: Click action duplicated for maximum compatibility
        $this->assertEquals('OPEN_ACTIVITY', $payload['message']['notification']['click_action']);
        $this->assertEquals('OPEN_ACTIVITY', $payload['message']['data']['click_action']);
    }
}
