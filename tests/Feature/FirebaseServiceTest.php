<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Feature;

use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use Andydefer\PushNotifier\Http\HttpResponseData;
use Andydefer\PushNotifier\Services\FirebaseService;
use Andydefer\PushNotifier\Tests\TestCase;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;
use Mockery;

/**
 * Feature tests for FirebaseService.
 */
final class FirebaseServiceTest extends TestCase
{
    private HttpClientInterface|Mockery\MockInterface $httpClient;
    private AuthProviderInterface|Mockery\MockInterface $authProvider;
    private PayloadBuilderInterface|Mockery\MockInterface $payloadBuilder;
    private FirebaseService $firebaseService;
    private FirebaseConfigData $config;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->authProvider = Mockery::mock(AuthProviderInterface::class);
        $this->payloadBuilder = Mockery::mock(PayloadBuilderInterface::class);

        $this->config = FirebaseConfigData::fromServiceAccount(
            FirebaseConfigFixture::getValidConfig()
        );

        $this->firebaseService = new FirebaseService(
            httpClient: $this->httpClient,
            authProvider: $this->authProvider,
            payloadBuilder: $this->payloadBuilder,
            config: $this->config
        );
    }
    /**
     * Test successful notification send.
     */
    public function test_can_send_notification_successfully(): void
    {
        // Arrange
        $deviceToken = 'test-device-token-123';
        $message = FcmMessageData::info('Test Title', 'Test Body');

        $mockPayload = ['message' => ['token' => $deviceToken, 'data' => []]];
        $mockFcmResponse = FirebaseConfigFixture::getMockFcmResponse();

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->with($this->config)
            ->andReturn('mock-access-token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->with($deviceToken, $message)
            ->andReturn($mockPayload);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $options) use ($mockPayload) {
                return str_contains($url, 'fcm.googleapis.com')
                    && $options['headers']['Authorization'] === 'Bearer mock-access-token'
                    && $options['json'] === $mockPayload;
            })
            ->andReturn(new HttpResponseData(
                statusCode: 200,
                data: $mockFcmResponse
            ));

        // Act
        $response = $this->firebaseService->send($deviceToken, $message);

        // Assert
        $this->assertTrue($response->success);
        $this->assertEquals('msg-123456789', $response->messageId);
        $this->assertEquals('projects/test-project-123/messages/msg-123456789', $response->name);
        $this->assertEquals(200, $response->statusCode);
    }

    /**
     * Test that sendInfo helper works correctly.
     */
    public function test_send_info_helper_works_correctly(): void
    {
        // Arrange
        $deviceToken = 'test-token';

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->withArgs(function ($token, $message) {
                return $token === 'test-token'
                    && $message->type->value === 'info'
                    && $message->title === 'Info Title'
                    && $message->body === 'Info Body';
            })
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 200,
                data: FirebaseConfigFixture::getMockFcmResponse()
            ));

        // Act
        $response = $this->firebaseService->sendInfo(
            $deviceToken,
            'Info Title',
            'Info Body'
        );

        // Assert
        $this->assertTrue($response->success);
    }

    /**
     * Test that ping helper works correctly.
     */
    public function test_ping_helper_works_correctly(): void
    {
        // Arrange
        $deviceToken = 'test-token';

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->withArgs(function ($token, $message) {
                return $token === 'test-token'
                    && $message->type->value === 'ping'
                    && $message->contentAvailable === true;
            })
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 200,
                data: FirebaseConfigFixture::getMockFcmResponse()
            ));

        // Act
        $response = $this->firebaseService->ping($deviceToken);

        // Assert
        $this->assertTrue($response->success);
    }

    /**
     * Test that validateToken returns true for valid token.
     */
    public function test_validate_token_returns_true_for_valid_token(): void
    {
        // Arrange
        $deviceToken = 'valid-token';

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 200,
                data: FirebaseConfigFixture::getMockFcmResponse()
            ));

        // Act
        $result = $this->firebaseService->validateToken($deviceToken);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test that validateToken returns false for invalid token.
     */
    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        // Arrange
        $deviceToken = 'invalid-token';

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 404,
                data: FirebaseConfigFixture::getMockFcmErrorResponse('UNREGISTERED', 'Not Found')
            ));

        // Act
        $result = $this->firebaseService->validateToken($deviceToken);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that sendMulticast handles multiple tokens correctly.
     */
    public function test_send_multicast_handles_multiple_tokens(): void
    {
        // Arrange
        $tokens = ['token1', 'token2', 'token3'];
        $message = FcmMessageData::info('Batch', 'Test');

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->times(3)
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->times(3)
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->times(3)
            ->andReturn(
                new HttpResponseData(200, data: FirebaseConfigFixture::getMockFcmResponse()),
                new HttpResponseData(200, data: FirebaseConfigFixture::getMockFcmResponse()),
                new HttpResponseData(200, data: FirebaseConfigFixture::getMockFcmResponse())
            );

        // Act
        $results = $this->firebaseService->sendMulticast($tokens, $message);

        // Assert
        $this->assertCount(3, $results);
        foreach ($results as $token => $response) {
            $this->assertTrue($response->success);
            $this->assertContains($token, $tokens);
        }
    }

    /**
     * Test that sendMulticast handles failures gracefully.
     */
    public function test_send_multicast_handles_failures_gracefully(): void
    {
        // Arrange
        $tokens = ['good-token', 'bad-token', 'good-token-2'];
        $message = FcmMessageData::info('Batch', 'Test');

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->times(3)
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->times(3)
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->times(3)
            ->andReturn(
                new HttpResponseData(200, data: FirebaseConfigFixture::getMockFcmResponse()),
                new HttpResponseData(404, data: FirebaseConfigFixture::getMockFcmErrorResponse('UNREGISTERED')),
                new HttpResponseData(200, data: FirebaseConfigFixture::getMockFcmResponse())
            );

        // Act
        $results = $this->firebaseService->sendMulticast($tokens, $message);

        // Assert
        $this->assertCount(3, $results);
        $this->assertTrue($results['good-token']->success);
        $this->assertFalse($results['bad-token']->success);
        $this->assertEquals('UNREGISTERED', $results['bad-token']->errorCode);
        $this->assertTrue($results['good-token-2']->success);
    }

    /**
     * Test that send throws exception on HTTP error.
     */
    public function test_send_throws_exception_on_http_error(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::info('Test', 'Error');

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 500,
                data: FirebaseConfigFixture::getMockFcmErrorResponse('INTERNAL', 'Server error')
            ));

        // Assert
        $this->expectException(FcmSendException::class);
        $this->expectExceptionMessage('FCM request failed: Server error');

        // Act
        $this->firebaseService->send($deviceToken, $message);
    }

    /**
     * Test that auth cache is cleared on auth errors.
     */
    public function test_auth_cache_is_cleared_on_auth_errors(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $message = FcmMessageData::info('Test', 'Auth Error');

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->authProvider
            ->shouldReceive('clearCache')
            ->once();

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 401,
                data: FirebaseConfigFixture::getMockFcmErrorResponse('UNAUTHENTICATED', 'Invalid token')
            ));

        // Assert
        $this->expectException(FcmSendException::class);

        // Act
        $this->firebaseService->send($deviceToken, $message);
    }

    /**
     * Test that sendAll helpers work correctly.
     */
    public function test_all_send_helpers_work_correctly(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $types = [
            'alert' => ['Alert', 'Alert Body'],
            'warning' => ['Warning', 'Warning Body'],
            'success' => ['Success', 'Success Body'],
            'error' => ['Error', 'Error Body'],
        ];

        foreach ($types as $type => $params) {
            $this->authProvider
                ->shouldReceive('getAccessToken')
                ->once()
                ->andReturn('token');

            $this->payloadBuilder
                ->shouldReceive('build')
                ->once()
                ->withArgs(function ($token, $message) use ($type) {
                    return $token === 'test-token'
                        && $message->type->value === $type;
                })
                ->andReturn(['message' => []]);

            $this->httpClient
                ->shouldReceive('post')
                ->once()
                ->andReturn(new HttpResponseData(
                    statusCode: 200,
                    data: FirebaseConfigFixture::getMockFcmResponse()
                ));
        }

        // Act & Assert
        $method = 'send' . ucfirst($type);
        foreach ($types as $type => $params) {
            $method = 'send' . ucfirst($type);
            $response = $this->firebaseService->$method($deviceToken, $params[0], $params[1]);
            $this->assertTrue($response->success);
        }
    }

    /**
     * Test that send with custom data preserves all fields.
     */
    public function test_send_with_custom_data_preserves_fields(): void
    {
        // Arrange
        $deviceToken = 'test-token';
        $customData = ['order_id' => '12345', 'user_id' => '67890'];

        $message = new FcmMessageData(
            type: \Andydefer\PushNotifier\Enums\NotificationType::INFO,
            title: 'Custom Title',
            body: 'Custom Body',
            data: $customData,
            imageUrl: 'https://example.com/image.jpg',
            clickAction: 'OPEN_ORDER',
            channelId: 'orders',
            badge: 5,
            sound: 'order.wav',
            ttl: 3600
        );

        $this->authProvider
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('token');

        $this->payloadBuilder
            ->shouldReceive('build')
            ->once()
            ->withArgs(function ($token, $msg) use ($customData) {
                return $token === 'test-token'
                    && $msg->data === $customData
                    && $msg->imageUrl === 'https://example.com/image.jpg'
                    && $msg->clickAction === 'OPEN_ORDER'
                    && $msg->channelId === 'orders'
                    && $msg->badge === 5
                    && $msg->sound === 'order.wav'
                    && $msg->ttl === 3600;
            })
            ->andReturn(['message' => []]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new HttpResponseData(
                statusCode: 200,
                data: FirebaseConfigFixture::getMockFcmResponse()
            ));

        // Act
        $response = $this->firebaseService->send($deviceToken, $message);

        // Assert
        $this->assertTrue($response->success);
    }
}
