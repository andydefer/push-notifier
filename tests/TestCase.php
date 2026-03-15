<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests;

use Andydefer\PushNotifier\Http\HttpResponseData;
use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Foundation test case for all PushNotifier package tests.
 *
 * Provides common utilities, fixtures, and Mockery integration
 * to ensure consistent test behavior across the test suite.
 */
abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Default Firebase configuration fixture for consistent test data.
     *
     * @var array<string, mixed>
     */
    protected array $defaultConfig;

    /**
     * Initializes test environment with standard fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultConfig = FirebaseConfigFixture::getValidConfig();
    }

    /**
     * Cleans up Mockery expectations after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    /**
     * Retrieves a valid Firebase service account configuration.
     *
     * @return array<string, mixed> Complete Firebase configuration array
     */
    protected function getValidConfigArray(): array
    {
        return FirebaseConfigFixture::getValidConfig();
    }

    /**
     * Provides a properly formatted RSA private key for testing.
     */
    protected function getValidPrivateKey(): string
    {
        return FirebaseConfigFixture::getValidPrivateKey();
    }

    /**
     * Provides an malformed private key for testing error scenarios.
     */
    protected function getInvalidPrivateKey(): string
    {
        return FirebaseConfigFixture::getInvalidPrivateKey();
    }

    /**
     * Factory method for creating mock HTTP responses in tests.
     *
     * @param int $statusCode HTTP status code (e.g., 200, 404, 500)
     * @param array<string, mixed> $data Response body as associative array
     * @param array<string, array<string>> $headers Response headers
     */
    protected function createMockHttpResponse(
        int $statusCode = 200,
        array $data = [],
        array $headers = []
    ): HttpResponseData {
        return new HttpResponseData(
            statusCode: $statusCode,
            headers: $headers,
            data: $data,
            rawBody: json_encode($data)
        );
    }
}
