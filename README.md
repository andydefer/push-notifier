# PushNotifier

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-11%2F12-orange)
![License](https://img.shields.io/badge/license-MIT-green)

**PushNotifier** est un package Laravel puissant et flexible pour l'envoi de notifications push via **Firebase Cloud Messaging (FCM)**. Construit avec une architecture robuste et orientée contrat, il simplifie l'authentification, la construction de payloads et la gestion des réponses, vous permettant d'intégrer des notifications riches et fiables en quelques minutes.

## 📦 Installation

```bash
composer require andydefer/push-notifier
```

Le package utilise la découverte automatique de Laravel. Si vous utilisez une version plus ancienne de Laravel, ajoutez manuellement le `ServiceProvider` dans votre `config/app.php` :

```php
'providers' => [
    // ...
    Andydefer\PushNotifier\PushNotifierServiceProvider::class,
],
```

Vous pouvez également publier le fichier de configuration (optionnel) :

```bash
php artisan vendor:publish --tag=pushnotifier-config
```

## 🚀 Démarrage Rapide

### 1. Obtenir les identifiants Firebase

1.  Allez dans la [Console Firebase](https://console.firebase.google.com/).
2.  Sélectionnez votre projet.
3.  Allez dans **Paramètres du projet** > **Comptes de service**.
4.  Cliquez sur **Générer une nouvelle clé privée** pour télécharger le fichier JSON du compte de service.
5.  Placez ce fichier dans un endroit sécurisé de votre projet (par exemple, `storage/app/firebase-credentials.json`).

### 2. Envoyer votre première notification

```php
<?php

use Andydefer\PushNotifier\Core\NotificationFactory;

// 1. Créer une factory
$factory = new NotificationFactory();

// 2. Créer un service Firebase à partir du fichier JSON
$firebaseService = $factory->makeFirebaseServiceFromJsonFile(
    storage_path('app/firebase-credentials.json')
);

// 3. Envoyer une notification simple
$deviceToken = 'votre_token_appareil_fcm';

$response = $firebaseService->sendInfo(
    $deviceToken,
    'Bonjour !',
    'Ceci est ma première notification push.'
);

if ($response->success) {
    echo "Notification envoyée avec succès ! ID : " . $response->messageId;
} else {
    echo "Échec de l'envoi : " . $response->errorMessage;
}
```

## 🔗 Architecture et Concepts Clés

Le package est construit autour de plusieurs concepts puissants pour garantir flexibilité, testabilité et fiabilité.

### 1. La Factory comme Point d'Entrée Unique

La classe `NotificationFactory` est le point d'entrée principal. Elle centralise la création de vos services et vous permet d'injecter vos propres implémentations des composants de bas niveau (client HTTP, fournisseur d'auth, constructeur de payload). C'est le cœur de l'injection de dépendances du package.

```php
$factory = new NotificationFactory();

// Créer un service de différentes manières
$service = $factory->makeFirebaseServiceFromJsonFile($jsonPath);
$service = $factory->makeFirebaseServiceFromJsonString($jsonContent);
$service = $factory->makeFirebaseServiceFromArray($configArray);
$service = $factory->makeFirebaseServiceFromEnv($_ENV);
```

### 2. Contrats pour la Flexibilité (Interfaces)

Le package est défini par des **contrats (interfaces)**. Cela vous permet de remplacer n'importe quel composant interne par votre propre logique sans modifier le cœur du package.

*   `HttpClientInterface`: Pour utiliser un client HTTP différent (par exemple, si vous n'utilisez pas Guzzle).
*   `AuthProviderInterface`: Pour gérer l'authentification Firebase d'une manière différente.
*   `PayloadBuilderInterface`: Pour construire le payload FCM selon vos propres besoins.

```php
// Exemple : Utiliser un client HTTP personnalisé
use App\Services\MyCustomHttpClient;

$factory = new NotificationFactory(
    new MyCustomHttpClient(), // Injecté ici
    // null, // Garde l'auth par défaut
    // null  // Garde le builder par défaut
);
$service = $factory->makeFirebaseServiceFromJsonFile($jsonPath);
```

### 3. DTOs pour la Clarté et la Cohérence (Data Transfer Objects)

Toutes les données manipulées par le package sont encapsulées dans des DTOs (Data Transfer Objects), rendant le code plus prévisible et auto-documenté.

*   **`FirebaseConfigData`** : Contient la configuration Firebase (project ID, email, clé privée).
*   **`FcmMessageData`** : Représente le message à envoyer (titre, corps, type, données additionnelles...). Des méthodes statiques comme `info()`, `alert()`, `ping()` permettent une création rapide et expressive.
*   **`FcmResponseData`** : Représente la réponse de l'API FCM, avec des méthodes utiles comme `isInvalidToken()`, `isQuotaExceeded()`, etc.
*   **`HttpResponseData`** : Encapsule une réponse HTTP brute.

### 4. Exceptions Spécifiques pour une Gestion d'Erreurs Fine

Le package lance des exceptions spécifiques pour chaque type d'erreur, ce qui vous permet de les capturer et de les traiter de manière granulaire.

*   `FcmSendException`: Levée lors d'un échec d'envoi de notification (contient le code HTTP et le code d'erreur FCM).
*   `FirebaseAuthException`: Levée en cas de problème d'authentification avec Firebase.
*   `InvalidConfigurationException`: Levée si la configuration Firebase est invalide.

## 📧 Création de Messages Riches

Le DTO `FcmMessageData` est très puissant et vous permet de créer des notifications riches pour Android et iOS.

### Types de Notifications Prédéfinis

Utilisez les méthodes statiques pour créer des messages avec des priorités et comportements par défaut adaptés.

```php
use Andydefer\PushNotifier\Dtos\FcmMessageData;

// Notification d'information (priorité normale)
$info = FcmMessageData::info('Mise à jour', 'Une nouvelle version est disponible.');

// Notification d'alerte (priorité élevée)
$alert = FcmMessageData::alert('Alerte', 'Paiement en attente.');

// Notification de succès (priorité normale)
$success = FcmMessageData::success('Succès', 'Votre commande est confirmée.');

// Notification d'erreur (priorité élevée)
$error = FcmMessageData::error('Erreur', 'Connexion perdue.');

// Notification silencieuse pour réveiller l'application en arrière-plan
$ping = FcmMessageData::ping();
```

### Personnalisation Avancée

Le constructeur de `FcmMessageData` accepte de nombreux paramètres pour un contrôle total.

```php
$message = new FcmMessageData(
    type: \Andydefer\PushNotifier\Enums\NotificationType::INFO,
    title: 'Nouveau message',
    body: 'Vous avez reçu un message de Jean.',
    data: ['user_id' => '123', 'conversation_id' => '456'], // Données personnalisées
    imageUrl: 'https://example.com/avatar.jpg',
    clickAction: 'OPEN_CONVERSATION', // Action au clic sur Android
    channelId: 'messages', // Canal de notification Android
    badge: 5, // Badge sur l'icône iOS
    sound: 'message.wav', // Son personnalisé
    ttl: 86400 // Temps de vie en secondes (24h)
);
```

## 🛠️ Utilisation Avancée du Service Firebase

### Envoyer à de multiples appareils

La méthode `sendMulticast` gère l'envoi à plusieurs tokens, avec une petite pause pour éviter le rate-limiting.

```php
$tokens = ['token1', 'token2', 'token3', ...];
$message = FcmMessageData::alert('Promo Flash', '-50% sur tout !');

$results = $firebaseService->sendMulticast($tokens, $message);

foreach ($results as $token => $response) {
    if ($response->success) {
        // Succès pour ce token
    } else if ($response->isInvalidToken()) {
        // Le token est invalide, à supprimer de votre base de données
        $this->deleteInvalidToken($token);
    }
}
```

### Valider un token

Vérifiez si un token est toujours valide avant de l'utiliser.

```php
if ($firebaseService->validateToken($deviceToken)) {
    // Le token est valide
} else {
    // Le token est invalide, à supprimer
}
```

### Analyser les réponses d'erreur

Le DTO `FcmResponseData` fournit des méthodes pour vous aider à réagir en fonction du type d'erreur.

```php
try {
    $response = $firebaseService->send($token, $message);
} catch (FcmSendException $e) {
    // Vous pouvez aussi récupérer un objet FcmResponseData à partir de l'exception
    $errorResponse = FcmResponseData::fromError(
        $e->getErrorCode() ?? 'UNKNOWN',
        $e->getMessage(),
        $e->getStatusCode()
    );

    if ($errorResponse->isInvalidToken()) {
        // Marquer le token comme invalide
    } elseif ($errorResponse->isQuotaExceeded()) {
        // Gérer le dépassement de quota (mettre en file d'attente, réessayer plus tard)
    } elseif ($errorResponse->isAuthError()) {
        // Problème d'authentification, peut-être que les identifiants ont expiré/changé
        Log::error('Problème d\'authentification Firebase', ['error' => $errorResponse]);
    }
}
```

### Gérer les tokens invalides et le nettoyage

Voici un exemple d'utilisation typique dans un contexte Laravel pour nettoyer les tokens expirés.

```php
use Andydefer\PushNotifier\Exceptions\FcmSendException;

class NotificationController extends Controller
{
    public function send(User $user)
    {
        $firebaseService = app(NotificationFactory::class)
            ->makeFirebaseServiceFromJsonFile(storage_path('firebase.json'));

        $token = $user->fcm_token;

        if (!$token) {
            return;
        }

        try {
            $response = $firebaseService->sendInfo($token, 'Titre', 'Corps');
            // Log du succès
        } catch (FcmSendException $e) {
            // Si l'erreur indique un token invalide, on le supprime de la base de données
            if ($e->getErrorCode() === 'UNREGISTERED') {
                $user->fcm_token = null;
                $user->save();
                Log::info("Token FCM invalide supprimé pour l'utilisateur {$user->id}");
            } else {
                // Gérer les autres types d'erreurs
                Log::error("Erreur FCM pour l'utilisateur {$user->id}: " . $e->getMessage());
            }
        }
    }
}
```

## ⚙️ Configuration et Authentification

### Méthodes de Configuration

Plusieurs façons de configurer le service, par ordre de préférence :

1.  **Via un fichier JSON (recommandé)** :
    ```php
    // Méthode recommandée : préserve le format de la clé privée
    $service = $factory->makeFirebaseServiceFromJsonFile('/path/to/service-account.json');
    ```

2.  **Via une chaîne JSON** :
    ```php
    $jsonContent = file_get_contents('/path/to/service-account.json');
    $service = $factory->makeFirebaseServiceFromJsonString($jsonContent);
    ```

3.  **Via un tableau PHP** :
    ```php
    // ⚠️ Attention : soyez très vigilant avec les retours à la ligne de la clé privée !
    $config = [
        'project_id' => 'votre-projet-id',
        'client_email' => 'firebase-adminsdk-xxx@...',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMII...\n-----END PRIVATE KEY-----\n",
    ];
    $service = $factory->makeFirebaseServiceFromArray($config);
    ```

4.  **Via les variables d'environnement** :
    ```php
    // Dans votre fichier .env :
    // FIREBASE_PROJECT_ID=...
    // FIREBASE_CLIENT_EMAIL=...
    // FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMII...\n-----END PRIVATE KEY-----\n"
    // FIREBASE_TOKEN_URI="https://oauth2.googleapis.com/token"

    $service = $factory->makeFirebaseServiceFromEnv($_ENV);
    ```

### Validation de la Configuration

Le package valide automatiquement votre configuration. Si la clé privée n'a pas le bon format (avec les marqueurs `BEGIN` et `END`), une exception `InvalidConfigurationException` sera levée.

## 🧪 Tests et Fiabilités

Le package est livré avec une suite de tests exhaustive.

### Lancement des Tests

```bash
# Exécuter tous les tests
composer test

# Exécuter les tests avec couverture de code
composer test-coverage
```

Les tests couvrent :

*   ✅ Création et validation des DTOs (`FirebaseConfigData`, `FcmMessageData`...)
*   ✅ Fonctionnement des helpers ( `info()`, `alert()`, `ping()`...)
*   ✅ Construction correcte du payload FCM pour Android et iOS.
*   ✅ Génération et échange de JWT pour l'authentification OAuth2.
*   ✅ Envoi réussi de notifications.
*   ✅ Gestion des erreurs HTTP et des réponses FCM (token invalide, quota dépassé...).
*   ✅ Mécanisme de cache des tokens d'accès.
*   ✅ Envoi multicast et validation de token.

## 🚨 Gestion des Erreurs

Voici comment capturer et traiter les exceptions de manière élégante.

```php
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;

try {
    $response = $firebaseService->send($token, $message);
} catch (FcmSendException $e) {
    // Erreur lors de l'envoi (mauvaise requête, token invalide, quota...)
    report($e); // Envoyer à Laravel Log / Sentry
    return back()->withErrors(['fcm' => "Erreur d'envoi: " . $e->getMessage()]);
} catch (FirebaseAuthException $e) {
    // Erreur d'authentification (mauvaise clé, projet inexistant...)
    report($e);
    // Alerter l'administrateur
    return back()->withErrors(['fcm' => "Erreur de configuration Firebase."]);
} catch (InvalidConfigurationException $e) {
    // Erreur dans la configuration fournie
    report($e);
    return back()->withErrors(['fcm' => "Configuration Firebase invalide."]);
} catch (\Exception $e) {
    // Autres erreurs (timeout, réseau...)
    report($e);
    return back()->withErrors(['fcm' => "Erreur réseau inattendue."]);
}
```

## 📚 API Complète

### `NotificationFactory`

| Méthode | Description |
| :--- | :--- |
| `__construct(?HttpClientInterface, ?AuthProviderInterface, ?PayloadBuilderInterface)` | Constructeur avec injection de dépendances optionnelle. |
| `makeFirebaseServiceFromJsonFile(string $jsonPath): FirebaseService` | Crée un service à partir d'un fichier JSON (recommandé). |
| `makeFirebaseServiceFromJsonString(string $jsonContent): FirebaseService` | Crée un service à partir d'une chaîne JSON. |
| `makeFirebaseServiceFromArray(array $config): FirebaseService` | Crée un service à partir d'un tableau de config. |
| `makeFirebaseServiceFromEnv(array $env): FirebaseService` | Crée un service à partir des variables d'environnement. |
| `makeFirebaseService(FirebaseConfigData $config): FirebaseService` | Crée un service à partir d'un DTO de configuration. |
| `getHttpClient(): HttpClientInterface` | Récupère le client HTTP. |
| `getAuthProvider(): AuthProviderInterface` | Récupère le fournisseur d'auth. |
| `getPayloadBuilder(): PayloadBuilderInterface` | Récupère le constructeur de payload. |

### `FirebaseService`

| Méthode | Description |
| :--- | :--- |
| `send(string $deviceToken, FcmMessageData $message): FcmResponseData` | Envoie un message à un appareil. |
| `sendMulticast(array $deviceTokens, FcmMessageData $message): array` | Envoie un message à plusieurs appareils. |
| `sendInfo(string $deviceToken, string $title, string $body, array $data = []): FcmResponseData` | Envoie une notification de type "info". |
| `sendAlert(...)` | Envoie une notification de type "alert". |
| `sendWarning(...)` | Envoie une notification de type "warning". |
| `sendSuccess(...)` | Envoie une notification de type "success". |
| `sendError(...)` | Envoie une notification de type "error". |
| `ping(string $deviceToken): FcmResponseData` | Envoie une notification silencieuse (content-available). |
| `validateToken(string $deviceToken): bool` | Vérifie si un token est valide. |

### `FcmMessageData`

| Méthode Statique | Description |
| :--- | :--- |
| `info(string $title, string $body, array $data = []): self` | Crée un message d'information. |
| `alert(...)` | Crée un message d'alerte (priorité haute). |
| `warning(...)` | Crée un message d'avertissement (priorité haute). |
| `success(...)` | Crée un message de succès. |
| `error(...)` | Crée un message d'erreur (priorité haute). |
| `ping(string $title = 'Connectivity Check', string $body = ''): self` | Crée un message de ping silencieux. |

### `FcmResponseData`

| Méthode | Description |
| :--- | :--- |
| `fromFcmResponse(array $response, int $statusCode): self` | Crée une instance à partir d'une réponse FCM. |
| `fromError(string $errorCode, string $errorMessage, ?int $statusCode): self` | Crée une instance à partir d'une erreur. |
| `isInvalidToken(): bool` | Vérifie si l'erreur est due à un token invalide. |
| `isQuotaExceeded(): bool` | Vérifie si le quota est dépassé. |
| `isAuthError(): bool` | Vérifie s'il s'agit d'une erreur d'authentification. |

## 🔧 Configuration

### Fichier de configuration `config/pushnotifier.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration par défaut de Firebase
    |--------------------------------------------------------------------------
    |
    | Vous pouvez pré-configurer votre projet ici, mais il est souvent plus sûr
    | de passer la configuration directement via la Factory.
    |
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'token_uri' => env('FIREBASE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client HTTP par défaut
    |--------------------------------------------------------------------------
    |
    | Configuration passée au client Guzzle par défaut.
    |
    */
    'http' => [
        'timeout' => env('PUSH_NOTIFIER_TIMEOUT', 30),
        'connect_timeout' => env('PUSH_NOTIFIER_CONNECT_TIMEOUT', 5),
    ],
];
```

### Variables d'environnement (`.env`)

```env
# Configuration Firebase (optionnelle si vous passez un fichier JSON)
FIREBASE_PROJECT_ID=votre-projet-id
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxx@votre-projet.iam.gserviceaccount.com
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIEvQIBA...\n-----END PRIVATE KEY-----\n"

# Timeouts HTTP
PUSH_NOTIFIER_TIMEOUT=30
PUSH_NOTIFIER_CONNECT_TIMEOUT=5
