<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Services;

use Andydefer\PushNotifier\Core\Contracts\PayloadBuilderInterface;
use Andydefer\PushNotifier\Dtos\FcmMessageData;

/**
 * Builds Firebase Cloud Messaging (FCM) payloads conforming to the FCM HTTP v1 API.
 */
class FcmPayloadBuilder implements PayloadBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(string $deviceToken, FcmMessageData $message): array
    {
        return [
            'message' => [
                'token' => $deviceToken,
                'data' => $this->formatDataForFcm($message->data),
            ]
        ];
    }

    /**
     * Format data for FCM (all values must be strings).
     */
    private function formatDataForFcm(array $data): array
    {
        $formatted = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $formatted[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $formatted[$key] = json_encode($value);
            } else {
                $formatted[$key] = (string) $value;
            }
        }

        return $formatted;
    }
}
