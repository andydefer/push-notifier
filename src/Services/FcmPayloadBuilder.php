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
        // Ajouter le type aux données pour que le client puisse déterminer la structure
        $allData = array_merge(
            ['type' => $message->type],
            $message->data
        );

        return [
            'message' => [
                'token' => $deviceToken,
                'data' => $this->formatDataForFcm($allData),
            ]
        ];
    }

    /**
     * Format data for FCM (all values must be strings).
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function formatDataForFcm(array $data): array
    {
        $formatted = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $formatted[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $formatted[$key] = json_encode($value);
            } elseif (is_null($value)) {
                $formatted[$key] = '';
            } else {
                $formatted[$key] = (string) $value;
            }
        }

        return $formatted;
    }
}
