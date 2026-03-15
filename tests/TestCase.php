<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Tests;

use Andydefer\PushNotifier\Tests\Fixtures\FirebaseConfigFixture;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Base test case for PushNotifier package tests.
 */
abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var array<string, mixed> Default Firebase configuration for tests
     */
    protected array $defaultConfig;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultConfig = FirebaseConfigFixture::getValidConfig();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        \Mockery::close();
    }

    /**
     * Get a valid Firebase config array for testing.
     *
     * @return array<string, mixed>
     */
    protected function getValidConfigArray(): array
    {
        return FirebaseConfigFixture::getValidConfig();
    }

    /**
     * Get a valid private key string for testing.
     */
    protected function getValidPrivateKey(): string
    {
        return FirebaseConfigFixture::getValidPrivateKey();
    }

    /**
     * Get an invalid private key for testing.
     */
    protected function getInvalidPrivateKey(): string
    {
        return FirebaseConfigFixture::getInvalidPrivateKey();
    }

    /**
     * Create a mock HTTP response.
     *
     * @param int $statusCode
     * @param array<string, mixed> $data
     * @param array<string, array<string>> $headers
     * @return \Andydefer\PushNotifier\Http\HttpResponseData
     */
    protected function createMockHttpResponse(
        int $statusCode = 200,
        array $data = [],
        array $headers = []
    ): \Andydefer\PushNotifier\Http\HttpResponseData {
        return new \Andydefer\PushNotifier\Http\HttpResponseData(
            statusCode: $statusCode,
            headers: $headers,
            data: $data,
            rawBody: json_encode($data)
        );
    }
}
