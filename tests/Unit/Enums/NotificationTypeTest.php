<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Enums;

use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests for NotificationType enum.
 */
final class NotificationTypeTest extends TestCase
{
    /**
     * Test that all cases have correct values.
     */
    public function test_cases_have_correct_values(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals('info', NotificationType::INFO->value);
        $this->assertEquals('alert', NotificationType::ALERT->value);
        $this->assertEquals('warning', NotificationType::WARNING->value);
        $this->assertEquals('success', NotificationType::SUCCESS->value);
        $this->assertEquals('error', NotificationType::ERROR->value);
        $this->assertEquals('ping', NotificationType::PING->value);
    }

    /**
     * Test that label returns correct human-readable strings.
     */
    public function test_label_returns_correct_strings(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals('Information', NotificationType::INFO->label());
        $this->assertEquals('Alert', NotificationType::ALERT->label());
        $this->assertEquals('Warning', NotificationType::WARNING->label());
        $this->assertEquals('Success', NotificationType::SUCCESS->label());
        $this->assertEquals('Error', NotificationType::ERROR->label());
        $this->assertEquals('Connectivity Ping', NotificationType::PING->label());
    }

    /**
     * Test that priority returns correct values.
     */
    public function test_priority_returns_correct_values(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals('normal', NotificationType::INFO->priority());
        $this->assertEquals('high', NotificationType::ALERT->priority());
        $this->assertEquals('high', NotificationType::WARNING->priority());
        $this->assertEquals('normal', NotificationType::SUCCESS->priority());
        $this->assertEquals('high', NotificationType::ERROR->priority());
        $this->assertEquals('normal', NotificationType::PING->priority());
    }

    /**
     * Test that isVisible returns correct visibility.
     */
    public function test_is_visible_returns_correct_visibility(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(NotificationType::INFO->isVisible());
        $this->assertTrue(NotificationType::ALERT->isVisible());
        $this->assertTrue(NotificationType::WARNING->isVisible());
        $this->assertTrue(NotificationType::SUCCESS->isVisible());
        $this->assertTrue(NotificationType::ERROR->isVisible());
        $this->assertFalse(NotificationType::PING->isVisible());
    }

    /**
     * Test that values returns all enum values.
     */
    public function test_values_returns_all_values(): void
    {
        // Act
        $values = NotificationType::values();

        // Assert
        $expected = ['info', 'alert', 'warning', 'success', 'error', 'ping'];
        $this->assertEquals($expected, $values);
        $this->assertCount(6, $values);
    }

    /**
     * Test that enum can be created from string value.
     */
    public function test_can_create_from_value(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals(NotificationType::INFO, NotificationType::from('info'));
        $this->assertEquals(NotificationType::ALERT, NotificationType::from('alert'));
        $this->assertEquals(NotificationType::PING, NotificationType::from('ping'));
    }

    /**
     * Test that tryFrom returns null for invalid value.
     */
    public function test_try_from_returns_null_for_invalid_value(): void
    {
        // Act
        $result = NotificationType::tryFrom('invalid_type');

        // Assert
        $this->assertNull($result);
    }
}
