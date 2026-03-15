# PushNotifier - Firebase Cloud Messaging for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/push-notifier.svg)](https://packagist.org/packages/andydefer/push-notifier)
[![Total Downloads](https://img.shields.io/packagist/dt/andydefer/push-notifier.svg)](https://packagist.org/packages/andydefer/push-notifier)
[![License](https://img.shields.io/packagist/l/andydefer/push-notifier.svg)](https://packagist.org/packages/andydefer/push-notifier)

A robust PHP package for sending push notifications via Firebase Cloud Messaging (FCM) to Android and iOS devices.

## 🚀 Features

- ✅ Simple, clean API for sending notifications
- ✅ Type-safe DTOs using Laravel Data
- ✅ Automatic JWT generation and token refresh
- ✅ Support for Android (FCM) and iOS (APNs)
- ✅ Silent notifications (content-available)
- ✅ Batch sending with rate limiting
- ✅ Fully tested and SOLID architecture
- ✅ PHP 8.1+ with strict typing

## 📦 Installation

```bash
composer require andydefer/push-notifier
```

## 🔧 Configuration

### 1. Get Firebase credentials

1. Go to [Firebase Console](https://console.firebase.google.com)
2. Project Settings → Service Accounts
3. Generate new private key (download JSON)

### 2. Initialize the SDK

#### ✅ **RECOMMENDED: Load from JSON file**

```php
use Andydefer\PushNotifier\Core\NotificationFactory;

$factory = new NotificationFactory();
$firebase = $factory->makeFirebaseServiceFromJsonFile('/path/to/firebase-credentials.json');
```

#### Load from JSON string

```php
$jsonContent = file_get_contents('/path/to/firebase-credentials.json');
$firebase = $factory->makeFirebaseServiceFromJsonString($jsonContent);
```

#### Load from environment variables

```php
// .env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CLIENT_EMAIL=firebase-adminsdk@project.iam.gserviceaccount.com
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIEv...\n-----END PRIVATE KEY-----\n"

// In your code
$firebase = $factory->makeFirebaseServiceFromEnv($_ENV);
```

#### ⚠️ **WARNING: Manual array configuration**

```php
// Only use this if you ABSOLUTELY know what you're doing!
// The private key MUST preserve newlines exactly as in the original file.
$firebase = $factory->makeFirebaseServiceFromArray([
    'project_id' => 'your-project-id',
    'client_email' => 'firebase-adminsdk@project.iam.gserviceaccount.com',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEv...\n-----END PRIVATE KEY-----\n",
]);
```

## 📱 Sending Notifications

### Simple Info Notification

```php
$deviceToken = 'fcm-device-token-here';

$response = $firebase->sendInfo(
    $deviceToken,
    'Hello!',
    'This is a test notification'
);

if ($response->success) {
    echo "✅ Sent! Message ID: {$response->messageId}";
}
```

### Different Notification Types

```php
// Alert (high priority)
$firebase->sendAlert(
    $deviceToken,
    '⚠️ Security Alert',
    'New login detected'
);

// Success notification
$firebase->sendSuccess(
    $deviceToken,
    '✅ Payment Received',
    'Your payment was successful'
);

// Warning notification
$firebase->sendWarning(
    $deviceToken,
    '⚠️ Low Battery',
    'Your device battery is below 20%'
);

// Error notification
$firebase->sendError(
    $deviceToken,
    '❌ Connection Failed',
    'Unable to connect to server'
);
```

### Silent Notification (Ping)

```php
// Background notification to wake app
$response = $firebase->ping($deviceToken);

// Validate token
if ($firebase->validateToken($deviceToken)) {
    echo "✅ Token is valid";
}
```

### Advanced Usage with Custom Data

```php
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Enums\NotificationType;

$message = new FcmMessageData(
    type: NotificationType::ALERT,
    title: 'Special Offer!',
    body: '50% off on all items today',
    data: ['promo_code' => 'SAVE50', 'expires' => '2025-12-31'],
    imageUrl: 'https://example.com/promo.jpg',
    clickAction: 'OPEN_PROMO',
    channelId: 'promotions',
    badge: 5,
    sound: 'notification.wav',
    contentAvailable: true,
    ttl: 86400 // 24 hours
);

$response = $firebase->send($deviceToken, $message);
```

### Batch Sending

```php
$tokens = ['token1', 'token2', 'token3'];

$results = $firebase->sendMulticast($tokens, FcmMessageData::info(
    'System Update',
    'Maintenance scheduled'
));

foreach ($results as $token => $response) {
    if ($response->success) {
        echo "✓ Sent to {$token}: {$response->messageId}\n";
    } else {
        echo "✗ Failed: {$response->errorMessage}\n";
    }
}
```

## 📊 Response Handling

```php
$response = $firebase->send($deviceToken, $message);

if ($response->success) {
    echo "✅ Sent! Message ID: {$response->messageId}\n";
    echo "Full name: {$response->name}\n";
} else {
    echo "❌ Failed: {$response->errorMessage} (Code: {$response->errorCode})\n";

    if ($response->isInvalidToken()) {
        // Remove this token from your database
    }

    if ($response->isQuotaExceeded()) {
        // Implement backoff strategy
    }

    if ($response->isAuthError()) {
        // Force token refresh
        $firebase = $factory->makeFirebaseServiceFromJsonFile($path);
    }
}
```

## 🏗 Architecture

```
PushNotifier/
├── Core/
│   ├── NotificationFactory.php
│   └── Contracts/
│       ├── HttpClientInterface.php
│       ├── AuthProviderInterface.php
│       └── PayloadBuilderInterface.php
├── Dtos/          # Laravel Data DTOs
│   ├── FirebaseConfigData.php
│   ├── FcmMessageData.php
│   └── FcmResponseData.php
├── Enums/
│   └── NotificationType.php
├── Exceptions/
│   ├── FirebaseAuthException.php
│   ├── FcmSendException.php
│   └── InvalidConfigurationException.php
├── Http/
│   ├── HttpResponseData.php
│   └── GuzzleHttpClient.php
└── Services/
    ├── FirebaseAuthProvider.php
    ├── FirebaseService.php
    └── FcmPayloadBuilder.php
```

## 🔄 Custom Implementation

### Custom HTTP Client

```php
use Andydefer\PushNotifier\Core\Contracts\HttpClientInterface;

class MyHttpClient implements HttpClientInterface
{
    public function post(string $url, array $options = []): HttpResponseData
    {
        // Your implementation
    }

    public function get(string $url, array $options = []): HttpResponseData
    {
        // Your implementation
    }
}

$factory = new NotificationFactory(new MyHttpClient());
```

### Custom Auth Provider

```php
use Andydefer\PushNotifier\Core\Contracts\AuthProviderInterface;

class CustomAuthProvider implements AuthProviderInterface
{
    public function getAccessToken(FirebaseConfigData $config): string
    {
        // Your implementation
    }

    public function getProjectId(FirebaseConfigData $config): string
    {
        return $config->projectId;
    }

    public function clearCache(): void
    {
        // Your implementation
    }
}
```

## 🧪 Testing

```bash
# Run tests
composer test

# Run with coverage
composer test-coverage
```

## 🤝 Contributing

Pull requests are welcome! Please ensure:

- PSR-12 coding standard
- Add tests for new features
- Update documentation
- Maintain backward compatibility

## 📝 License

MIT License - see LICENSE file for details

## 👨‍💻 Author

**Andy Kani** - [andykanidimbu@gmail.com](mailto:andykanidimbu@gmail.com)

## ⚠️ Important Notes

### Private Key Handling
The Firebase private key contains newlines (`\n`) that MUST be preserved exactly as in the original file.
**Always prefer loading from the JSON file directly** to avoid corruption issues.

### Rate Limiting
FCM has rate limits. Use `sendMulticast()` with caution and implement proper backoff strategies.

### Token Validation
Regularly validate and clean up invalid tokens using `validateToken()` or checking `isInvalidToken()` in responses.
