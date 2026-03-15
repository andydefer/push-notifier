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

/**
 * Unit tests for FirebaseAuthProvider service.
 */
final class FirebaseAuthProviderTest extends TestCase
{
    private HttpClientInterface&MockInterface $httpClient;
    private FirebaseAuthProvider $authProvider;
    private FirebaseConfigData $validConfig;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var HttpClientInterface&MockInterface $httpClient */
        $this->httpClient = Mockery::mock(HttpClientInterface::class);

        // Create real instance of FirebaseAuthProvider
        $this->authProvider = new FirebaseAuthProvider($this->httpClient);

        $this->validConfig = FirebaseConfigData::fromServiceAccount(
            FirebaseConfigFixture::getValidConfig()
        );
    }

    /**
     * Test that getAccessToken returns a valid token.
     */
    public function test_get_access_token_returns_valid_token(): void
    {
        // Arrange
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

        // Act
        $token = $this->authProvider->getAccessToken($this->validConfig);

        // Assert
        $this->assertEquals('ya29.mock.access.token.123456', $token);
    }

    /**
     * Test that getAccessToken caches the token.
     */
    public function test_get_access_token_caches_the_token(): void
    {
        // Arrange
        $mockResponse = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once() // Should be called only once
            ->andReturn($mockResponse);

        // Act
        $token1 = $this->authProvider->getAccessToken($this->validConfig);
        $token2 = $this->authProvider->getAccessToken($this->validConfig);

        // Assert
        $this->assertEquals($token1, $token2);
    }

    /**
     * Test that clearCache forces token refresh.
     */
    public function test_clear_cache_forces_token_refresh(): void
    {
        // Arrange
        $mockResponse1 = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $mockResponse2 = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'new.token.789']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($mockResponse1, $mockResponse2);

        // Act
        $token1 = $this->authProvider->getAccessToken($this->validConfig);
        $this->authProvider->clearCache();
        $token2 = $this->authProvider->getAccessToken($this->validConfig);

        // Assert
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals('new.token.789', $token2);
    }

    /**
     * Test that getAccessToken throws exception on OAuth error.
     */
    public function test_throws_exception_on_oauth_error(): void
    {
        // Arrange
        $mockResponse = new HttpResponseData(
            statusCode: 400,
            data: [
                'error' => 'invalid_grant',
                'error_description' => 'Invalid JWT',
            ]
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        // Assert
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('Failed to obtain access token: HTTP 400 - invalid_grant Invalid JWT');

        // Act
        $this->authProvider->getAccessToken($this->validConfig);
    }

    /**
     * Test that getAccessToken throws exception when response has no data.
     */
    public function test_throws_exception_when_response_has_no_data(): void
    {
        // Arrange
        $mockResponse = new HttpResponseData(
            statusCode: 200,
            data: null
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        // Assert
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('Failed to obtain access token: HTTP 200 - Unknown error');

        // Act
        $this->authProvider->getAccessToken($this->validConfig);
    }

    /**
     * Test that getAccessToken throws exception when response missing access_token.
     */
    public function test_throws_exception_when_response_missing_access_token(): void
    {
        // Arrange
        $mockResponse = new HttpResponseData(
            statusCode: 200,
            data: ['expires_in' => 3600] // missing access_token
        );

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn($mockResponse);

        // Assert
        $this->expectException(FirebaseAuthException::class);
        $this->expectExceptionMessage('OAuth response missing access_token');

        // Act
        $this->authProvider->getAccessToken($this->validConfig);
    }

    /**
     * Test that getAccessToken throws exception when HTTP request fails.
     */
    public function test_throws_exception_when_http_request_fails(): void
    {
        // Arrange
        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));

        try {
            // Act
            $this->authProvider->getAccessToken($this->validConfig);
            $this->fail('Expected exception was not thrown');
        } catch (FirebaseAuthException $e) {
            // Assert
            $this->assertStringContainsString('Failed to obtain access token: HTTP 0 -', $e->getMessage());
            $this->assertStringContainsString('Connection timeout', $e->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    /**
     * Test that getProjectId returns correct project ID.
     */
    public function test_get_project_id_returns_correct_id(): void
    {
        // Act
        $projectId = $this->authProvider->getProjectId($this->validConfig);

        // Assert
        $this->assertEquals('autotext-d50ea', $projectId);
    }

    /**
     * Test that token is refreshed when expired.
     */
    public function test_token_is_refreshed_when_expired(): void
    {
        // Arrange
        $mockResponse1 = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $mockResponse2 = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'refreshed.token.456']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($mockResponse1, $mockResponse2);

        // Premier appel pour obtenir le token (caché)
        $token1 = $this->authProvider->getAccessToken($this->validConfig);

        // Utiliser la réflexion pour modifier la propriété privée tokenExpiry
        // setAccessible() n'est plus nécessaire en PHP 8.1+
        $reflection = new \ReflectionClass($this->authProvider);
        $tokenExpiryProperty = $reflection->getProperty('tokenExpiry');
        $tokenExpiryProperty->setValue($this->authProvider, time() - 100); // Expiré il y a 100 secondes

        // Act - deuxième appel, devrait rafraîchir le token
        $token2 = $this->authProvider->getAccessToken($this->validConfig);

        // Assert
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals('refreshed.token.456', $token2);
    }

    /**
     * Test that cache is cleared when project changes.
     */
    public function test_cache_is_cleared_when_project_changes(): void
    {
        // Arrange
        $mockResponse1 = new HttpResponseData(
            statusCode: 200,
            data: FirebaseConfigFixture::getMockOAuthResponse()
        );

        $mockResponse2 = new HttpResponseData(
            statusCode: 200,
            data: array_merge(
                FirebaseConfigFixture::getMockOAuthResponse(),
                ['access_token' => 'different.project.token']
            )
        );

        $this->httpClient
            ->shouldReceive('post')
            ->twice()
            ->andReturn($mockResponse1, $mockResponse2);

        $config1 = $this->validConfig;

        $config2 = FirebaseConfigData::fromServiceAccount([
            'project_id' => 'different-project-456',
            'client_email' => 'admin@different-project.iam.gserviceaccount.com',
            'private_key' => $this->getValidPrivateKey(),
        ]);

        // Act
        $token1 = $this->authProvider->getAccessToken($config1);
        $token2 = $this->authProvider->getAccessToken($config2);

        // Assert
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals('different.project.token', $token2);
    }
}
