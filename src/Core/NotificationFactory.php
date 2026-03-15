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

/**
 * Factory for creating configured Firebase notification services.
 *
 * Provides multiple convenient methods to instantiate FirebaseService
 * from various configuration sources while maintaining consistent
 * dependency injection across all created instances.
 */
class NotificationFactory
{
    private HttpClientInterface $httpClient;
    private AuthProviderInterface $authProvider;
    private PayloadBuilderInterface $payloadBuilder;
    /**
     * Initializes the factory with optional custom implementations.
     *
     * @param HttpClientInterface|null $httpClient Custom HTTP client for API requests
     * @param AuthProviderInterface|null $authProvider Custom authentication provider
     * @param PayloadBuilderInterface|null $payloadBuilder Custom FCM payload builder
     */
    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?AuthProviderInterface $authProvider = null,
        ?PayloadBuilderInterface $payloadBuilder = null
    ) {
        $this->httpClient = $httpClient ?? new GuzzleHttpClient();

        if ($authProvider === null) {
            $this->authProvider = new FirebaseAuthProvider(
                httpClient: $this->httpClient
            );
        } else {
            $this->authProvider = $authProvider;
        }

        $this->payloadBuilder = $payloadBuilder ?? new FcmPayloadBuilder();
    }
    /**
     * Creates a Firebase service from a service account JSON file.
     *
     * This is the recommended approach as it preserves private key formatting
     * exactly as stored in the file, preventing common newline issues.
     *
     * @param string $jsonFilePath Absolute or relative path to service account JSON
     * @return FirebaseService Fully configured Firebase service instance
     *
     * @throws \InvalidArgumentException When file is missing, unreadable, or contains invalid JSON
     */
    public function makeFirebaseServiceFromJsonFile(string $jsonFilePath): FirebaseService
    {
        $config = FirebaseConfigData::fromJsonFile($jsonFilePath);
        return $this->makeFirebaseService($config);
    }

    /**
     * Creates a Firebase service from a service account JSON string.
     *
     * Useful when configuration is stored in databases, environment variables,
     * or retrieved from external services.
     *
     * @param string $jsonContent Raw JSON string containing service account credentials
     * @return FirebaseService Fully configured Firebase service instance
     *
     * @throws \InvalidArgumentException When JSON is malformed or missing required fields
     */
    public function makeFirebaseServiceFromJsonString(string $jsonContent): FirebaseService
    {
        $config = FirebaseConfigData::fromJsonString($jsonContent);
        return $this->makeFirebaseService($config);
    }

    /**
     * Creates a Firebase service from a configuration array.
     *
     * ⚠️ WARNING: Be cautious with private key newline characters when using this method.
     * The private key must contain literal newlines (\n), not escaped sequences.
     * Prefer makeFirebaseServiceFromJsonFile() when possible.
     *
     * @param array{
     *     project_id: string,
     *     client_email: string,
     *     private_key: string,
     *     token_uri?: string
     * } $config Service account configuration array
     * @return FirebaseService Fully configured Firebase service instance
     *
     * @throws \InvalidArgumentException When required fields are missing
     */
    public function makeFirebaseServiceFromArray(array $config): FirebaseService
    {
        $configData = FirebaseConfigData::fromServiceAccount($config);
        return $this->makeFirebaseService($configData);
    }

    /**
     * Creates a Firebase service from environment variables.
     *
     * ⚠️ WARNING: Pay special attention to private key newlines in .env files.
     * Environment variables often strip or escape newlines incorrectly.
     *
     * @param array<string, string> $environmentVariables Associative array of environment variables
     * @return FirebaseService Fully configured Firebase service instance
     *
     * @throws \InvalidArgumentException When required environment variables are missing
     */
    public function makeFirebaseServiceFromEnv(array $environmentVariables): FirebaseService
    {
        $configData = FirebaseConfigData::fromEnv($environmentVariables);
        return $this->makeFirebaseService($configData);
    }

    /**
     * Creates a Firebase service from a pre-configured configuration object.
     *
     * This is the core factory method that all other creation methods delegate to.
     * It assembles the Firebase service with all dependencies and configuration.
     *
     * @param FirebaseConfigData $config Validated Firebase configuration
     * @return FirebaseService Ready-to-use Firebase service instance
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
     * Returns the HTTP client instance used by the factory.
     *
     * @return HttpClientInterface Configured HTTP client for API communication
     */
    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    /**
     * Returns the authentication provider instance used by the factory.
     *
     * @return AuthProviderInterface Authentication provider for token management
     */
    public function getAuthProvider(): AuthProviderInterface
    {
        return $this->authProvider;
    }

    /**
     * Returns the payload builder instance used by the factory.
     *
     * @return PayloadBuilderInterface Builder for creating FCM message payloads
     */
    public function getPayloadBuilder(): PayloadBuilderInterface
    {
        return $this->payloadBuilder;
    }
}
