<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests\Unit\Services;

use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Andydefer\PushNotifier\Http\HttpResponseData;
use Andydefer\PushNotifier\Services\FirebaseAuthProvider;
use Andydefer\PushNotifier\Tests\TestCase;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * Unit tests validating FirebaseAuthProvider token management behavior.
 *
 * Verifies OAuth2 token acquisition, caching strategies, expiration handling,
 * and error scenarios for Firebase Cloud Messaging authentication.
 */
final class FirebaseAuthProviderTest extends TestCase
{
    private HttpClientInterface&MockInterface $httpClient;
    private FirebaseAuthProvider $authProvider;
    private FirebaseConfigData $validConfig;

    /**
     * Initializes test environment with mocked HTTP client and valid configuration.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var HttpClientInterface&MockInterface $httpClient */
        $this->httpClient = Mockery::mock(HttpClientInterface::class);

        $this->authProvider = new FirebaseAuthProvider(httpClient: $this->httpClient);

        $this->validConfig = FirebaseConfigData::fromServiceAccount(
            serviceAccountData: FirebaseConfigFixture::getValidConfig()
        );
    }

    /**
     * Confirms successful token retrieval from OAuth2 endpoint.
     */
    public function test_get_access_token_returns_valid_token(): void
    {
        // Arrange: Simulate successful OAuth2 response
        $mockResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(function ($url, $options) {
                return $url === 'https://oauth2.googleapis.com/token'
                    && isset($options['form_params']['grant_type'])
                    && $options['form_params']['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer';
            })
            ->andReturn($mockResponse);

        // Act: Request access token
        $token = $this->authProvider->getAccessToken(config: $this->validConfig);

        // Assert: Verify token format and value
        $this->assertEquals('ya29.mock.access.token.123456', $token);
    }

    /**
     * Verifies that tokens are cached to minimize OAuth2 requests.
     */
    public function test_get_access_token_caches_the_token(): void
    {
        // Arrange: Configure single HTTP call expectation
        $mockResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        // Act: Request token twice in succession
        $firstToken = $this->authProvider->getAccessToken(config: $this->validConfig);
        $secondToken = $this->authProvider->getAccessToken(config: $this->validConfig);

        // Assert: Same token returned without second HTTP call
        $this->assertEquals($firstToken, $secondToken);
    }

    /**
     * Ensures clearCache forces a fresh token request.
     */
    public function test_clear_cache_forces_token_refresh(): void
    {
        // Arrange: Prepare two different token responses
        $firstResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $secondResponse = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'new.token.789']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        // Act: Get token, clear cache, get another token
        $firstToken = $this->authProvider->getAccessToken(config: $this->validConfig);
        $this->authProvider->clearCache();
        $secondToken = $this->authProvider->getAccessToken(config: $this->validConfig);

        // Assert: Tokens are different and second matches expected value
        $this->assertNotEquals($firstToken, $secondToken);
        $this->assertEquals('new.token.789', $secondToken);
    }

    /**
     * Validates proper exception when OAuth2 returns error response.
     */
    public function test_throws_exception_on_oauth_error(): void
    {
        // Arrange: Simulate OAuth2 error response
        $errorResponse = new HttpResponseData(
            statusCode: 400,
            data: [
                'error' => 'invalid_grant',
                'error_description' => 'Invalid JWT',
            ]
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($errorResponse);

        // Assert: Expect authentication exception with error details
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('Failed to obtain access token: HTTP 400 - invalid_grant Invalid JWT');

        // Act: Attempt to get token (should throw)
        $this->authProvider->getAccessToken(config: $this->validConfig);
    }

    /**
     * Verifies exception when OAuth2 returns empty response body.
     */
    public function test_throws_exception_when_response_has_no_data(): void
    {
        // Arrange: Simulate empty response
        $emptyResponse = new HttpResponseData(
            statusCode: 200,
            data: null
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($emptyResponse);

        // Assert: Expect generic authentication exception
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('Failed to obtain access token: HTTP 200 - Unknown error');

        // Act: Attempt to get token (should throw)
        $this->authProvider->getAccessToken(config: $this->validConfig);
    }

    /**
     * Confirms exception when OAuth2 response lacks access_token field.
     */
    public function test_throws_exception_when_response_missing_access_token(): void
    {
        // Arrange: Return valid status but incomplete data
        $incompleteResponse = new HttpResponseData(
            statusCode: 200,
            data: ['expires_in' => 3600]
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($incompleteResponse);

        // Assert: Expect specific missing token error
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('OAuth response missing access_token');

        // Act: Attempt to get token (should throw)
        $this->authProvider->getAccessToken(config: $this->validConfig);
    }

    /**
     * Ensures HTTP client failures are properly wrapped.
     */
    public function test_throws_exception_when_http_request_fails(): void
    {
        // Arrange: Simulate network failure
        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andThrow(new RuntimeException('Connection timeout'));

        // Assert: Verify exception wrapping
        try {
            // Act: Attempt to get token (should throw wrapped exception)
            $this->authProvider->getAccessToken(config: $this->validConfig);
            $this->fail('Expected exception was not thrown');
        } catch (FirebaseAuthException $exception) {
            $this->assertStringContainsString('Failed to obtain access token: HTTP 0 -', $exception->getMessage());
            $this->assertStringContainsString('Connection timeout', $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    /**
     * Verifies project ID extraction from configuration.
     */
    public function test_get_project_id_returns_correct_id(): void
    {
        // Act: Extract project ID
        $projectId = $this->authProvider->getProjectId(config: $this->validConfig);

        // Assert: Verify expected project identifier
        $this->assertEquals('autotext-d50ea', $projectId);
    }

    /**
     * Validates automatic token refresh after expiration.
     */
    public function test_token_is_refreshed_when_expired(): void
    {
        // Arrange: Configure two token responses
        $firstResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $secondResponse = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'refreshed.token.456']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        // Act - Phase 1: Get initial token
        $initialToken = $this->authProvider->getAccessToken(config: $this->validConfig);

        // Force token expiration using reflection
        $reflection = new \ReflectionClass($this->authProvider);
        $expiryProperty = $reflection->getProperty('cachedTokenExpiry');
        $expiryProperty->setValue($this->authProvider, time() - 100);

        // Act - Phase 2: Request token after expiration
        $refreshedToken = $this->authProvider->getAccessToken(config: $this->validConfig);

        // Assert: Verify token was refreshed
        $this->assertNotEquals($initialToken, $refreshedToken);
        $this->assertEquals('refreshed.token.456', $refreshedToken);
    }

    /**
     * Ensures cache isolation between different Firebase projects.
     */
    public function test_cache_is_cleared_when_project_changes(): void
    {
        // Arrange: Prepare responses for different projects
        $firstResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $secondResponse = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'different.project.token']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        $firstProjectConfig = $this->validConfig;

        $secondProjectConfig = FirebaseConfigData::fromServiceAccount(serviceAccountData: [
            'project_id' => 'different-project-456',
            'client_email' => 'admin@different-project.iam.gserviceaccount.com',
            'private_key' => $this->getValidPrivateKey(),
        ]);

        // Act: Get tokens for different projects
        $firstProjectToken = $this->authProvider->getAccessToken(config: $firstProjectConfig);
        $secondProjectToken = $this->authProvider->getAccessToken(config: $secondProjectConfig);

        // Assert: Different projects get different tokens
        $this->assertNotEquals($firstProjectToken, $secondProjectToken);
        $this->assertEquals('different.project.token', $secondProjectToken);
    }
}
