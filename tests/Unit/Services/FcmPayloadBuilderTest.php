<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Services;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Services\FcmPayloadBuilder;
use Andydefer\PushNotifier\Tests\TestCase;

/**
 * Unit tests validating FCM payload structure generation.
 *
 * Verifies that the payload builder correctly formats messages.
 */
final class FcmPayloadBuilderTest extends TestCase
{
    private FcmPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FcmPayloadBuilder();
    }

    public function test_builds_basic_payload(): void
    {
        $deviceToken = 'test-device-token-123';

        $message = FcmMessageData::info(
            title: 'Test Title',
            body: 'Test Body'
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertArrayHasKey('message', $payload);
        $this->assertEquals($deviceToken, $payload['message']['token']);
        $this->assertArrayHasKey('data', $payload['message']);

        $this->assertEquals('Test Title', $payload['message']['data']['title']);
        $this->assertEquals('Test Body', $payload['message']['data']['body']);

        // Vérifier que 'type' n'est PAS dans data (c'est une propriété du DTO)
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_all_values_are_converted_to_strings(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::make(
            type: 'TEST',
            data: [
                'int' => '123',
                'bool' => 'true',
                'float' => '45.67',
                'array' => '["a","b","c"]',
                'null' => '',
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertEquals('123', $payload['message']['data']['int']);
        $this->assertEquals('true', $payload['message']['data']['bool']);
        $this->assertEquals('45.67', $payload['message']['data']['float']);
        $this->assertEquals('["a","b","c"]', $payload['message']['data']['array']);
        $this->assertEquals('', $payload['message']['data']['null']);

        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_custom_data_is_preserved(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::make(
            type: 'CUSTOM_EVENT',
            data: [
                'eventType' => 'CUSTOM_EVENT',
                'userId' => '123',
                'action' => 'open_profile',
                'metadata' => '{"source":"web"}'
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertEquals('CUSTOM_EVENT', $payload['message']['data']['eventType']);
        $this->assertEquals('123', $payload['message']['data']['userId']);
        $this->assertEquals('open_profile', $payload['message']['data']['action']);
        $this->assertEquals('{"source":"web"}', $payload['message']['data']['metadata']);

        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_ping_notification_works(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::ping();

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertEquals('true', $payload['message']['data']['connected']);
        $this->assertCount(1, $payload['message']['data']);
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_user_can_add_type_to_data_if_needed(): void
    {
        $deviceToken = 'test-token';

        // L'utilisateur ajoute explicitement 'type' dans data
        $message = FcmMessageData::make(
            type: 'INFO',
            data: [
                'type' => 'INFO',  // Ajout explicite
                'title' => 'Test',
                'body' => 'Body'
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertEquals('INFO', $payload['message']['data']['type']);
        $this->assertEquals('Test', $payload['message']['data']['title']);
        $this->assertEquals('Body', $payload['message']['data']['body']);
    }
}
