<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Enums;

use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Verifies the behavior and properties of the NotificationType enum.
 *
 * Ensures that each notification type has correct:
 * - Underlying string values
 * - Human-readable labels
 * - FCM priority mappings
 * - Visibility rules
 */
final class NotificationTypeTest extends TestCase
{
    /**
     * Confirms each enum case has the expected string value.
     */
    public function test_each_case_has_correct_underlying_value(): void
    {
        // Assert: Verify string values match FCM expectations
        $this->assertEquals('info', NotificationType::INFO->value);
        $this->assertEquals('alert', NotificationType::ALERT->value);
        $this->assertEquals('warning', NotificationType::WARNING->value);
        $this->assertEquals('success', NotificationType::SUCCESS->value);
        $this->assertEquals('error', NotificationType::ERROR->value);
        $this->assertEquals('ping', NotificationType::PING->value);
    }

    /**
     * Verifies that human-readable labels are properly formatted.
     */
    public function test_label_method_returns_display_ready_strings(): void
    {
        // Assert: Labels should be capitalized and user-friendly
        $this->assertEquals('Information', NotificationType::INFO->label());
        $this->assertEquals('Alert', NotificationType::ALERT->label());
        $this->assertEquals('Warning', NotificationType::WARNING->label());
        $this->assertEquals('Success', NotificationType::SUCCESS->label());
        $this->assertEquals('Error', NotificationType::ERROR->label());
        $this->assertEquals('Connectivity Ping', NotificationType::PING->label());
    }

    /**
     * Ensures each notification type maps to correct FCM delivery priority.
     *
     * Critical alerts use 'high' priority for immediate delivery,
     * while informational messages use 'normal' priority.
     */
    public function test_fcm_priority_mapping_is_appropriate_for_message_importance(): void
    {
        // Assert: High priority for time-sensitive notifications
        $this->assertEquals('normal', NotificationType::INFO->fcmPriority());
        $this->assertEquals('high', NotificationType::ALERT->fcmPriority());
        $this->assertEquals('high', NotificationType::WARNING->fcmPriority());
        $this->assertEquals('normal', NotificationType::SUCCESS->fcmPriority());
        $this->assertEquals('high', NotificationType::ERROR->fcmPriority());
        $this->assertEquals('normal', NotificationType::PING->fcmPriority());
    }

    /**
     * Confirms which notification types should appear in the system UI.
     *
     * Ping notifications are silent and should never be displayed to users,
     * while all other types should be visible.
     */
    public function test_visibility_rules_for_user_display(): void
    {
        // Assert: All except ping should be visible
        $this->assertTrue(NotificationType::INFO->shouldDisplayToUser());
        $this->assertTrue(NotificationType::ALERT->shouldDisplayToUser());
        $this->assertTrue(NotificationType::WARNING->shouldDisplayToUser());
        $this->assertTrue(NotificationType::SUCCESS->shouldDisplayToUser());
        $this->assertTrue(NotificationType::ERROR->shouldDisplayToUser());
        $this->assertFalse(NotificationType::PING->shouldDisplayToUser());
    }

    /**
     * Verifies that all possible enum values are accessible as an array.
     */
    public function test_values_method_returns_all_possible_string_values(): void
    {
        // Act: Retrieve all possible values
        $values = NotificationType::values();

        // Assert: Complete set of values should be returned
        $expectedValues = ['info', 'alert', 'warning', 'success', 'error', 'ping'];
        $this->assertEquals($expectedValues, $values);
        $this->assertCount(6, $values);
    }

    /**
     * Ensures enum instances can be instantiated from valid string values.
     */
    public function test_can_recreate_enum_instance_from_valid_string(): void
    {
        // Act & Assert: Each valid string should produce correct enum case
        $this->assertEquals(NotificationType::INFO, NotificationType::from('info'));
        $this->assertEquals(NotificationType::ALERT, NotificationType::from('alert'));
        $this->assertEquals(NotificationType::WARNING, NotificationType::from('warning'));
        $this->assertEquals(NotificationType::SUCCESS, NotificationType::from('success'));
        $this->assertEquals(NotificationType::ERROR, NotificationType::from('error'));
        $this->assertEquals(NotificationType::PING, NotificationType::from('ping'));
    }

    /**
     * Confirms that invalid strings gracefully return null instead of throwing.
     */
    public function test_try_from_returns_null_for_invalid_notification_type(): void
    {
        // Act: Attempt to create enum from invalid string
        $result = NotificationType::tryFrom('invalid_type');

        // Assert: Should fail gracefully with null
        $this->assertNull($result);
    }
}
