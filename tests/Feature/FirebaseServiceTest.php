<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Feature;

use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use Andydefer\PushNotifier\Http\HttpResponseData;
use Andydefer\PushNotifier\Services\FirebaseService;
use Andydefer\PushNotifier\Tests\TestCase;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;
use Mockery;
use Mockery\MockInterface;

/**
 * Feature tests validating FirebaseService behavior in real-world scenarios.
 *
 * Verifies notification delivery, error handling, token validation,
 * and helper methods for various notification types.
 */
final class FirebaseServiceTest extends TestCase
{
    private HttpClientInterface|MockInterface $httpClient;
    private AuthProviderInterface|MockInterface $authProvider;
    private PayloadBuilderInterface|MockInterface $payloadBuilder;
    private FirebaseService $firebaseService;
    private FirebaseConfigData $config;

    /**
     * Sets up the test environment with mocked dependencies.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->authProvider = Mockery::mock(AuthProviderInterface::class);
        $this->payloadBuilder = Mockery::mock(PayloadBuilderInterface::class);

        $this->config = FirebaseConfigData::fromServiceAccount(
            serviceAccountData: FirebaseConfigFixture::getValidConfig()
        );

        $this->firebaseService = new FirebaseService(
            httpClient: $this->httpClient,
            authProvider: $this->authProvider,
            payloadBuilder: $this->payloadBuilder,
            config: $this->config
        );
    }

    /**
     * Verifies successful notification delivery with all required parameters.
     */
    public function test_can_send_notification_successfully(): void
    {
        // Arrange: Configure mocks for successful FCM API interaction
        $deviceToken = 'test-device-token-123';
        $message = FcmMessageData::info(title: 'Test Title', body: 'Test Body');

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

        // Act: Send the notification
        $response = $this->firebaseService->send(deviceToken: $deviceToken, message: $message);

        // Assert: Verify successful response structure
        $this->assertTrue($response->success);
        $this->assertEquals('msg-123456789', $response->messageId);
        $this->assertEquals('projects/test-project-123/messages/msg-123456789', $response->name);
        $this->assertEquals(200, $response->statusCode);
    }

    /**
     * Confirms that the info notification helper properly constructs messages.
     */
    public function test_send_info_helper_works_correctly(): void
    {
        // Arrange: Set up expectations for info notification
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

        // Act: Use the info helper
        $response = $this->firebaseService->sendInfo(
            deviceToken: $deviceToken,
            title: 'Info Title',
            body: 'Info Body'
        );

        // Assert: Verify successful delivery
        $this->assertTrue($response->success);
    }

    /**
     * Validates that ping notifications are properly configured for silent delivery.
     */
    public function test_ping_helper_works_correctly(): void
    {
        // Arrange: Configure expectations for silent ping
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

        // Act: Send silent ping
        $response = $this->firebaseService->ping(deviceToken: $deviceToken);

        // Assert: Confirm ping was accepted
        $this->assertTrue($response->success);
    }

    /**
     * Ensures token validation correctly identifies valid device tokens.
     */
    public function test_validate_token_returns_true_for_valid_token(): void
    {
        // Arrange: Set up successful ping response
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

        // Act: Validate the token
        $result = $this->firebaseService->validateToken(deviceToken: $deviceToken);

        // Assert: Token should be considered valid
        $this->assertTrue($result);
    }

    /**
     * Verifies that token validation correctly identifies expired/invalid tokens.
     */
    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        // Arrange: Simulate FCM error for unregistered token
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
                data: FirebaseConfigFixture::getMockFcmErrorResponse(
                    errorCode: 'UNREGISTERED',
                    errorMessage: 'Not Found'
                )
            ));

        // Act: Validate the token
        $result = $this->firebaseService->validateToken(deviceToken: $deviceToken);

        // Assert: Token should be marked invalid
        $this->assertFalse($result);
    }

    /**
     * Confirms that multicast sends to all tokens and returns proper results.
     */
    public function test_send_multicast_handles_multiple_tokens(): void
    {
        // Arrange: Prepare multiple device tokens
        $tokens = ['token1', 'token2', 'token3'];
        $message = FcmMessageData::info(title: 'Batch', body: 'Test');

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

        // Act: Send to multiple devices
        $results = $this->firebaseService->sendMulticast(
            deviceTokens: $tokens,
            message: $message
        );

        // Assert: All sends should succeed
        $this->assertCount(3, $results);
        foreach ($results as $token => $response) {
            $this->assertTrue($response->success);
            $this->assertContains($token, $tokens);
        }
    }

    /**
     * Verifies that multicast continues processing even when individual sends fail.
     */
    public function test_send_multicast_handles_failures_gracefully(): void
    {
        // Arrange: Mix of valid and invalid tokens
        $tokens = ['good-token', 'bad-token', 'good-token-2'];
        $message = FcmMessageData::info(title: 'Batch', body: 'Test');

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

        // Act: Send batch notification
        $results = $this->firebaseService->sendMulticast(
            deviceTokens: $tokens,
            message: $message
        );

        // Assert: Results should reflect mixed success/failure
        $this->assertCount(3, $results);
        $this->assertTrue($results['good-token']->success);
        $this->assertFalse($results['bad-token']->success);
        $this->assertEquals('UNREGISTERED', $results['bad-token']->errorCode);
        $this->assertTrue($results['good-token-2']->success);
    }

    /**
     * Ensures that HTTP errors properly trigger FcmSendException.
     */
    public function test_send_throws_exception_on_http_error(): void
    {
        // Arrange: Simulate server error response
        $deviceToken = 'test-token';
        $message = FcmMessageData::info(title: 'Test', body: 'Error');

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
                data: FirebaseConfigFixture::getMockFcmErrorResponse(
                    errorCode: 'INTERNAL',
                    errorMessage: 'Server error'
                )
            ));

        // Assert: Exception should be thrown
        $this->expectException(FcmSendException::class);
        $this->expectExceptionMessage('FCM request failed: Server error');

        // Act: Attempt to send (should throw)
        $this->firebaseService->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Verifies that authentication cache is cleared on auth-related errors.
     */
    public function test_auth_cache_is_cleared_on_auth_errors(): void
    {
        // Arrange: Simulate authentication failure
        $deviceToken = 'test-token';
        $message = FcmMessageData::info(title: 'Test', body: 'Auth Error');

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
                data: FirebaseConfigFixture::getMockFcmErrorResponse(
                    errorCode: 'UNAUTHENTICATED',
                    errorMessage: 'Invalid token'
                )
            ));

        // Assert: Exception should be thrown
        $this->expectException(FcmSendException::class);

        // Act: Attempt to send (should clear cache)
        $this->firebaseService->send(deviceToken: $deviceToken, message: $message);
    }

    /**
     * Validates all notification type helpers (alert, warning, success, error).
     */
    public function test_all_send_helpers_work_correctly(): void
    {
        // Arrange: Set up test data for each notification type
        $deviceToken = 'test-token';
        $notificationTypes = [
            'alert' => ['Alert', 'Alert Body'],
            'warning' => ['Warning', 'Warning Body'],
            'success' => ['Success', 'Success Body'],
            'error' => ['Error', 'Error Body'],
        ];

        foreach ($notificationTypes as $type => $params) {
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

        // Act & Assert: Test each helper method
        foreach ($notificationTypes as $type => $params) {
            $method = 'send' . ucfirst($type);
            $response = $this->firebaseService->$method(
                deviceToken: $deviceToken,
                title: $params[0],
                body: $params[1]
            );
            $this->assertTrue($response->success);
        }
    }

    /**
     * Ensures all custom notification fields are properly preserved.
     */
    public function test_send_with_custom_data_preserves_fields(): void
    {
        // Arrange: Create message with all possible customizations
        $deviceToken = 'test-token';
        $customData = ['order_id' => '12345', 'user_id' => '67890'];

        $message = new FcmMessageData(
            type: NotificationType::INFO,
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

        // Act: Send fully customized notification
        $response = $this->firebaseService->send(deviceToken: $deviceToken, message: $message);

        // Assert: Verify delivery success
        $this->assertTrue($response->success);
    }
}
