<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;

/**
 * Contract for Firebase authentication token management.
 *
 * Implementations handle OAuth2 authentication with Firebase Cloud Messaging,
 * including token acquisition, caching, and automatic refresh mechanisms.
 */
interface AuthProviderInterface
{
    /**
     * Retrieves a valid OAuth2 access token for Firebase Cloud Messaging.
     *
     * Implementations should cache tokens and automatically request new ones
     * when the current token expires, ensuring continuous authentication.
     *
     * @param FirebaseConfigData $config Firebase project credentials and settings
     * @return string Valid OAuth2 access token
     *
     * @throws FirebaseAuthException When authentication fails due to:
     *                               - Invalid credentials
     *                               - Network connectivity issues
     *                               - Malformed configuration
     */
    public function getAccessToken(FirebaseConfigData $config): string;

    /**
     * Extracts the Firebase project identifier from configuration.
     *
     * The project ID is essential for constructing API endpoints and
     * identifying the target Firebase project for push notifications.
     *
     * @param FirebaseConfigData $config Firebase project configuration
     * @return string Firebase project identifier
     */
    public function getProjectId(FirebaseConfigData $config): string;

    /**
     * Purges the cached authentication token.
     *
     * Forces a fresh token retrieval on the next access request. Useful for:
     * - Testing scenarios
     * - Manual token invalidation
     * - Configuration changes
     */
    public function clearCache(): void;
}
