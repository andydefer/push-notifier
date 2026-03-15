<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Dtos\FirebaseConfigData;

/**
 * Interface for Firebase authentication providers.
 *
 * This interface defines the contract for obtaining and managing
 * authentication tokens for Firebase Cloud Messaging.
 */
interface AuthProviderInterface
{
    /**
     * Get a valid access token for Firebase.
     *
     * This method should return a valid OAuth2 access token that can be used
     * to authenticate requests to the FCM API. The token should be cached
     * and automatically refreshed when expired.
     *
     * @param FirebaseConfigData $config Firebase configuration containing credentials
     * @return string Valid access token
     *
     * @throws \Andydefer\PushNotifier\Exceptions\FirebaseAuthException
     *         When authentication fails (invalid credentials, network error, etc.)
     */
    public function getAccessToken(FirebaseConfigData $config): string;

    /**
     * Get the project ID from configuration.
     *
     * This method returns the Firebase project ID that will be used
     * in API endpoints.
     *
     * @param FirebaseConfigData $config Firebase configuration
     * @return string Firebase project ID
     */
    public function getProjectId(FirebaseConfigData $config): string;

    /**
     * Clear cached token to force refresh.
     *
     * This method should clear any cached access tokens, forcing
     * a new token to be obtained on the next request.
     */
    public function clearCache(): void;
}
