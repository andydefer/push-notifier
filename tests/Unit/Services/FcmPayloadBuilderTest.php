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

        // Vérifier que le type a été ajouté aux données
        $this->assertArrayHasKey('type', $payload['message']['data']);
        $this->assertEquals('INFO', $payload['message']['data']['type']);

        // Vérifier les autres données
        $this->assertEquals('Test Title', $payload['message']['data']['title']);
        $this->assertEquals('Test Body', $payload['message']['data']['body']);
    }

    public function test_all_values_are_converted_to_strings(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::make(
            type: 'TEST',
            data: [
                'intValue' => '123',
                'boolValue' => 'true',
                'floatValue' => '45.67',
                'arrayValue' => '["a","b","c"]',
                'nullValue' => '',
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        // Vérifier que le type du DTO est présent
        $this->assertArrayHasKey('type', $payload['message']['data']);
        $this->assertEquals('TEST', $payload['message']['data']['type']);

        // Vérifier les conversions
        $this->assertEquals('123', $payload['message']['data']['intValue']);
        $this->assertEquals('true', $payload['message']['data']['boolValue']);
        $this->assertEquals('45.67', $payload['message']['data']['floatValue']);
        $this->assertEquals('["a","b","c"]', $payload['message']['data']['arrayValue']);
        $this->assertEquals('', $payload['message']['data']['nullValue']);
    }

    public function test_custom_data_is_preserved(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::make(
            type: 'CUSTOM_EVENT',
            data: [
                'userId' => '123',
                'action' => 'open_profile',
                'metadata' => '{"source":"web"}'
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        // Vérifier que le type du DTO est ajouté
        $this->assertArrayHasKey('type', $payload['message']['data']);
        $this->assertEquals('CUSTOM_EVENT', $payload['message']['data']['type']);

        // Vérifier que les données personnalisées sont préservées
        $this->assertEquals('123', $payload['message']['data']['userId']);
        $this->assertEquals('open_profile', $payload['message']['data']['action']);
        $this->assertEquals('{"source":"web"}', $payload['message']['data']['metadata']);

        // Vérifier le nombre total de clés (type + 3 données)
        $this->assertCount(4, $payload['message']['data']);
    }

    public function test_ping_notification_works(): void
    {
        $deviceToken = 'test-token';

        $message = FcmMessageData::ping();

        $payload = $this->builder->build($deviceToken, $message);

        // Vérifier que le type PING est présent
        $this->assertArrayHasKey('type', $payload['message']['data']);
        $this->assertEquals('PING', $payload['message']['data']['type']);

        // Vérifier les données spécifiques au ping
        $this->assertEquals('true', $payload['message']['data']['connected']);
        $this->assertCount(2, $payload['message']['data']); // type + connected
    }

    public function test_data_does_not_contain_duplicate_type(): void
    {
        $deviceToken = 'test-token';

        // Le type est défini UNIQUEMENT dans le constructeur du DTO
        // PAS dans le tableau data
        $message = FcmMessageData::make(
            type: 'INFO',
            data: [
                'title' => 'Test',
                'body' => 'Body'
                // Pas de clé 'type' ici - c'est la bonne pratique
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        // Une seule clé 'type' doit être présente
        $this->assertEquals('INFO', $payload['message']['data']['type']);
        $this->assertEquals('Test', $payload['message']['data']['title']);
        $this->assertEquals('Body', $payload['message']['data']['body']);

        // Vérifier qu'il n'y a pas de duplication
        $typeCount = 0;
        foreach (array_keys($payload['message']['data']) as $key) {
            if ($key === 'type') {
                $typeCount++;
            }
        }
        $this->assertEquals(1, $typeCount, 'La clé "type" ne doit apparaître qu\'une seule fois');
    }
}
