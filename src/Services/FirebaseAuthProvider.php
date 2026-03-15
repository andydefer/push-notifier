<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Google OAuth2 authentication provider for Firebase Cloud Messaging.
 *
 * Implements service account authentication flow using JWT assertions
 * to obtain and cache access tokens for Firebase Cloud Messaging API.
 * Automatically handles token refresh and project switching.
 */
class FirebaseAuthProvider implements AuthProviderInterface
{
    /**
     * OAuth2 scopes required for Firebase Cloud Messaging.
     */
    private const SCOPES = ['https://www.googleapis.com/auth/firebase.messaging'];

    /**
     * Token lifetime in seconds (1 hour).
     */
    private const TOKEN_LIFETIME = 3600;

    private HttpClientInterface $httpClient;
    private ?string $cachedToken = null;
    private ?int $cachedTokenExpiry = null;
    private ?string $cachedProjectId = null;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(FirebaseConfigData $config): string
    {
        try {
            $this->invalidateCacheIfProjectChanged($config);

            if ($this->hasValidCachedToken()) {
                return $this->cachedToken;
            }

            return $this->requestNewAccessToken($config);
        } catch (RuntimeException $exception) {
            throw new FirebaseAuthException(
                "Failed to obtain access token: HTTP 0 - " . $exception->getMessage(),
                $exception
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectId(FirebaseConfigData $config): string
    {
        return $config->projectId;
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        $this->cachedToken = null;
        $this->cachedTokenExpiry = null;
        $this->cachedProjectId = null;
    }

    /**
     * Generates a JWT assertion for service account authentication.
     *
     * Creates a signed JWT containing the required claims for OAuth2
     * token exchange using the service account's private key.
     *
     * @param FirebaseConfigData $config Service account credentials
     * @return string Signed JWT assertion
     *
     * @throws FirebaseAuthException When JWT signing fails
     */
    private function generateJwtAssertion(FirebaseConfigData $config): string
    {
        $encodedHeader = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $currentTimestamp = CarbonImmutable::now()->getTimestamp();

        $encodedClaims = $this->base64UrlEncode(json_encode([
            'iss' => $config->clientEmail,
            'scope' => implode(' ', self::SCOPES),
            'aud' => $config->tokenUri,
            'iat' => $currentTimestamp,
            'exp' => $currentTimestamp + self::TOKEN_LIFETIME,
        ]));

        $signature = $this->signJwtPayload(
            payload: $encodedHeader . '.' . $encodedClaims,
            privateKey: $config->privateKey
        );

        return $encodedHeader . '.' . $encodedClaims . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Signs a JWT payload using the provided private key.
     *
     * @param string $payload The encoded header and claims
     * @param string $privateKey RSA private key for signing
     * @return string Raw signature
     *
     * @throws FirebaseAuthException When signing fails
     */
    private function signJwtPayload(string $payload, string $privateKey): string
    {
        $signature = '';
        $signingSuccessful = openssl_sign(
            data: $payload,
            signature: $signature,
            private_key: $privateKey,
            algorithm: 'SHA256'
        );

        if (!$signingSuccessful) {
            $errorMessage = openssl_error_string() ?: 'Unknown OpenSSL error';
            throw new FirebaseAuthException(
                "Failed to sign JWT with private key: {$errorMessage}"
            );
        }

        return $signature;
    }

    /**
     * Exchanges a JWT assertion for an OAuth2 access token.
     *
     * @param FirebaseConfigData $config Service account configuration
     * @param string $jwtAssertion Signed JWT assertion
     * @return array OAuth2 token response data
     *
     * @throws FirebaseAuthException When token exchange fails
     */
    private function exchangeJwtForAccessToken(FirebaseConfigData $config, string $jwtAssertion): array
    {
        try {
            $response = $this->httpClient->post(
                url: $config->tokenUri,
                options: [
                    'form_params' => [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwtAssertion,
                    ],
                ]
            );

            $this->validateTokenResponse($response);

            return $response->data;
        } catch (RuntimeException $exception) {
            throw new FirebaseAuthException(
                "Failed to obtain access token: HTTP 0 - " . $exception->getMessage(),
                $exception
            );
        }
    }

    /**
     * Validates the OAuth2 token response.
     *
     * @param object $response HTTP client response object
     *
     * @throws FirebaseAuthException When response is invalid
     */
    private function validateTokenResponse(object $response): void
    {
        if (!$response->isSuccessful() || $response->data === null) {
            $error = $response->data['error'] ?? 'Unknown error';
            $description = $response->data['error_description'] ?? '';

            throw new FirebaseAuthException(
                "Failed to obtain access token: HTTP {$response->statusCode} - {$error} {$description}"
            );
        }

        if (!isset($response->data['access_token'])) {
            throw new FirebaseAuthException('OAuth response missing access_token');
        }
    }

    /**
     * Invalidates the cache if the Firebase project has changed.
     *
     * @param FirebaseConfigData $config Current Firebase configuration
     */
    private function invalidateCacheIfProjectChanged(FirebaseConfigData $config): void
    {
        if ($this->cachedProjectId !== null && $this->cachedProjectId !== $config->projectId) {
            $this->clearCache();
        }
    }

    /**
     * Checks if there's a valid cached token available.
     *
     * @return bool True if a valid token exists in cache
     */
    private function hasValidCachedToken(): bool
    {
        return $this->cachedToken !== null
            && $this->cachedTokenExpiry !== null
            && time() < $this->cachedTokenExpiry;
    }

    /**
     * Requests and caches a new access token from Google OAuth2 server.
     *
     * @param FirebaseConfigData $config Firebase configuration
     * @return string New access token
     *
     * @throws FirebaseAuthException When token acquisition fails
     */
    private function requestNewAccessToken(FirebaseConfigData $config): string
    {
        $jwtAssertion = $this->generateJwtAssertion($config);
        $tokenResponse = $this->exchangeJwtForAccessToken($config, $jwtAssertion);

        $this->cachedToken = $tokenResponse['access_token'];
        $this->cachedTokenExpiry = time() + ($tokenResponse['expires_in'] ?? self::TOKEN_LIFETIME);
        $this->cachedProjectId = $config->projectId;

        return $this->cachedToken;
    }

    /**
     * Encodes data to Base64URL format.
     *
     * Base64URL is a URL-safe variant of Base64 where '+' and '/' are replaced
     * with '-' and '_' respectively, and padding '=' characters are removed.
     *
     * @param string $data Raw data to encode
     * @return string Base64URL encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(
            string: strtr(
                string: base64_encode($data),
                from: '+/',
                to: '-_'
            ),
            characters: '='
        );
    }
}
