<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core;

use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Http\GuzzleHttpClient;
use Andydefer\PushNotifier\Services\FirebaseAuthProvider;
use Andydefer\PushNotifier\Services\FirebaseService;
use Andydefer\PushNotifier\Services\FcmPayloadBuilder;

class NotificationFactory
{
    private HttpClientInterface $httpClient;
    private AuthProviderInterface $authProvider;
    private PayloadBuilderInterface $payloadBuilder;

    /**
     * @param HttpClientInterface|null $httpClient Custom HTTP client
     * @param AuthProviderInterface|null $authProvider Custom auth provider
     * @param PayloadBuilderInterface|null $payloadBuilder Custom payload builder
     */
    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?AuthProviderInterface $authProvider = null,
        ?PayloadBuilderInterface $payloadBuilder = null
    ) {
        $this->httpClient = $httpClient ?? new GuzzleHttpClient();
        $this->authProvider = $authProvider ?? new FirebaseAuthProvider($this->httpClient);
        $this->payloadBuilder = $payloadBuilder ?? new FcmPayloadBuilder();
    }

    /**
     * Create a Firebase service instance from a service account JSON file.
     * This is the recommended way - it preserves the private key exactly as in the file.
     *
     * @param string $jsonPath Path to service account JSON file
     * @throws \InvalidArgumentException If file not found or invalid
     */
    public function makeFirebaseServiceFromJsonFile(string $jsonPath): FirebaseService
    {
        $config = FirebaseConfigData::fromJsonFile($jsonPath);
        return $this->makeFirebaseService($config);
    }

    /**
     * Create a Firebase service instance from a service account JSON string.
     *
     * @param string $jsonContent Raw JSON content from service account file
     */
    public function makeFirebaseServiceFromJsonString(string $jsonContent): FirebaseService
    {
        $config = FirebaseConfigData::fromJsonString($jsonContent);
        return $this->makeFirebaseService($config);
    }

    /**
     * Create a Firebase service instance from a config array.
     * WARNING: Be careful with private key newlines when using this method!
     * Prefer makeFirebaseServiceFromJsonFile() instead.
     *
     * @param array{
     *     project_id: string,
     *     client_email: string,
     *     private_key: string,
     *     token_uri?: string
     * } $config
     */
    public function makeFirebaseServiceFromArray(array $config): FirebaseService
    {
        $configData = FirebaseConfigData::fromServiceAccount($config);
        return $this->makeFirebaseService($configData);
    }

    /**
     * Create a Firebase service instance from environment variables.
     * WARNING: Be careful with private key newlines in .env files!
     *
     * @param array<string, string> $env
     */
    public function makeFirebaseServiceFromEnv(array $env): FirebaseService
    {
        $configData = FirebaseConfigData::fromEnv($env);
        return $this->makeFirebaseService($configData);
    }

    /**
     * Create a Firebase service instance from a config object.
     */
    public function makeFirebaseService(FirebaseConfigData $config): FirebaseService
    {
        return new FirebaseService(
            httpClient: $this->httpClient,
            authProvider: $this->authProvider,
            payloadBuilder: $this->payloadBuilder,
            config: $config
        );
    }

    /**
     * Get the HTTP client instance.
     */
    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    /**
     * Get the auth provider instance.
     */
    public function getAuthProvider(): AuthProviderInterface
    {
        return $this->authProvider;
    }

    /**
     * Get the payload builder instance.
     */
    public function getPayloadBuilder(): PayloadBuilderInterface
    {
        return $this->payloadBuilder;
    }
}
