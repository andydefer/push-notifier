<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;
use Andydefer\PushNotifier\Dtos\FirebaseConfigData;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Carbon\CarbonImmutable;
use RuntimeException;

class FirebaseAuthProvider implements AuthProviderInterface
{
    private const SCOPES = ['https://www.googleapis.com/auth/firebase.messaging'];
    private const TOKEN_LIFETIME = 3600; // 1 hour

    private HttpClientInterface $httpClient;
    private ?string $cachedToken = null;
    private ?int $tokenExpiry = null;
    private ?string $lastProjectId = null;

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
            // Clear cache if project changed
            if ($this->lastProjectId !== null && $this->lastProjectId !== $config->projectId) {
                $this->clearCache();
            }

            // Return cached token if still valid
            if ($this->cachedToken !== null && $this->tokenExpiry !== null && time() < $this->tokenExpiry) {
                return $this->cachedToken;
            }

            $jwt = $this->generateJwt($config);
            $tokenData = $this->exchangeJwtForToken($config, $jwt);

            $this->cachedToken = $tokenData['access_token'];
            $this->tokenExpiry = time() + ($tokenData['expires_in'] ?? self::TOKEN_LIFETIME);
            $this->lastProjectId = $config->projectId;

            return $this->cachedToken;
        } catch (RuntimeException $exception) {
            // Catch and transform any HTTP client errors
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
        $this->tokenExpiry = null;
    }

    /**
     * Generate JWT for service account authentication.
     *
     * @throws FirebaseAuthException
     */
    public function generateJwt(FirebaseConfigData $config): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $now = CarbonImmutable::now()->getTimestamp();

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $config->clientEmail,
            'scope' => implode(' ', self::SCOPES),
            'aud' => $config->tokenUri,
            'iat' => $now,
            'exp' => $now + self::TOKEN_LIFETIME,
        ]));

        $signature = '';
        $success = openssl_sign(
            $header . '.' . $claims,
            $signature,
            $config->privateKey,
            'SHA256'
        );

        if (!$success) {
            $error = openssl_error_string();
            throw new FirebaseAuthException(
                "Failed to sign JWT with private key: " . ($error ?: 'Unknown error')
            );
        }

        return $header . '.' . $claims . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Exchange JWT for OAuth2 access token.
     *
     * @throws FirebaseAuthException
     */
    private function exchangeJwtForToken(FirebaseConfigData $config, string $jwt): array
    {
        try {
            $response = $this->httpClient->post($config->tokenUri, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

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

            return $response->data;
        } catch (RuntimeException $exception) {
            // Catch and transform HTTP client errors during token exchange
            throw new FirebaseAuthException(
                "Failed to obtain access token: HTTP 0 - " . $exception->getMessage(),
                $exception
            );
        }
    }

    /**
     * Base64URL encode a string.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
