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

        // Note: le type n'est PAS dans data, c'est une propriété séparée
        $message = FcmMessageData::info(
            title: 'Test Title',
            body: 'Test Body',
            data: ['customKey' => 'customValue']
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertArrayHasKey('message', $payload);
        $this->assertEquals($deviceToken, $payload['message']['token']);
        $this->assertArrayHasKey('data', $payload['message']);

        // Le type n'est PAS dans data, donc on ne le teste pas
        $this->assertEquals('Test Title', $payload['message']['data']['title']);
        $this->assertEquals('Test Body', $payload['message']['data']['body']);
        $this->assertEquals('customValue', $payload['message']['data']['customKey']);

        // Vérifier que 'type' n'est PAS dans data (c'est une propriété du DTO, pas du payload)
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_all_values_are_converted_to_strings(): void
    {
        $deviceToken = 'test-token';

        // Ici on utilise make() pour avoir un contrôle total sur data
        $message = FcmMessageData::make(
            type: 'TEST', // ce type n'ira PAS dans data
            data: [
                'int' => 123,
                'bool' => true,
                'float' => 45.67,
                'array' => ['a', 'b', 'c'],
                'null' => null,
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        $this->assertEquals('123', $payload['message']['data']['int']);
        $this->assertEquals('true', $payload['message']['data']['bool']);
        $this->assertEquals('45.67', $payload['message']['data']['float']);
        $this->assertEquals('["a","b","c"]', $payload['message']['data']['array']);
        $this->assertEquals('', $payload['message']['data']['null']);

        // Vérifier que 'type' n'est PAS dans data
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_custom_data_is_preserved(): void
    {
        $deviceToken = 'test-token';

        // Si l'utilisateur veut un type dans data, il doit l'ajouter explicitement
        $message = FcmMessageData::make(
            type: 'CUSTOM_EVENT', // ceci est la propriété type du DTO
            data: [
                'eventType' => 'CUSTOM_EVENT', // l'utilisateur ajoute ce qu'il veut dans data
                'userId' => 123,
                'action' => 'open_profile',
                'metadata' => ['source' => 'web']
            ]
        );

        $payload = $this->builder->build($deviceToken, $message);

        // Le type dans data est celui que l'utilisateur a mis explicitement
        $this->assertEquals('CUSTOM_EVENT', $payload['message']['data']['eventType']);
        $this->assertEquals('123', $payload['message']['data']['userId']);
        $this->assertEquals('open_profile', $payload['message']['data']['action']);
        $this->assertEquals('{"source":"web"}', $payload['message']['data']['metadata']);

        // Vérifier qu'il n'y a PAS de clé 'type' automatique
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_ping_notification_works(): void
    {
        $deviceToken = 'test-token';

        // ping() ne met rien dans data par défaut
        $message = FcmMessageData::ping(['custom' => 'data']);

        $payload = $this->builder->build($deviceToken, $message);

        // Il n'y a pas de 'type' dans data
        $this->assertEquals('data', $payload['message']['data']['custom']);
        $this->assertArrayNotHasKey('type', $payload['message']['data']);
    }

    public function test_user_can_add_type_to_data_if_needed(): void
    {
        $deviceToken = 'test-token';

        // Si l'utilisateur veut un type dans data, il l'ajoute explicitement
        $message = FcmMessageData::info(
            title: 'Test',
            body: 'Body',
            data: ['type' => 'INFO'] // Il ajoute explicitement le type dans data
        );

        $payload = $this->builder->build($deviceToken, $message);

        // Maintenant 'type' est présent car l'utilisateur l'a mis
        $this->assertEquals('INFO', $payload['message']['data']['type']);
        $this->assertEquals('Test', $payload['message']['data']['title']);
        $this->assertEquals('Body', $payload['message']['data']['body']);
    }
}
