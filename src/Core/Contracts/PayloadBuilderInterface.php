<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Dtos\FcmMessageData;

interface PayloadBuilderInterface
{
    /**
     * Build FCM payload for a device token.
     *
     * @param string $deviceToken Target device FCM token
     * @param FcmMessageData $message Notification message
     * @return array<string, mixed> Complete FCM payload
     */
    public function build(string $deviceToken, FcmMessageData $message): array;
}
