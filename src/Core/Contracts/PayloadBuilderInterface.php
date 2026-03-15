<?php

declare(strict_types=1);

namespace Andydefer\PushNotifier\Core\Contracts;

use Andydefer\PushNotifier\Dtos\FcmMessageData;

/**
 * Contract for building Firebase Cloud Messaging request payloads.
 *
 * Implementations transform domain message data into the specific JSON structure
 * required by the FCM API, handling platform-specific formatting for
 * Android, iOS, and web targets.
 */
interface PayloadBuilderInterface
{
    /**
     * Constructs a complete FCM API request payload for a single device.
     *
     * The resulting payload follows the official FCM HTTP v1 API format,
     * including all necessary fields for proper message delivery and handling.
     *
     * @param string $deviceToken Target device's registration token obtained from FCM SDK
     * @param FcmMessageData $message Domain message object containing notification content,
     *                                 data payload, and platform-specific configurations
     * @return array<string, mixed> Formatted FCM payload ready for JSON encoding,
     *                               following the structure defined in:
     *                               https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
     */
    public function build(string $deviceToken, FcmMessageData $message): array;
}
