
# PushNotifier

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)

**PushNotifier** est un package PHP puissant et flexible (compatible avec tout framework ou application brute) pour l'envoi de notifications push via **Firebase Cloud Messaging (FCM) v1**. Construit avec une architecture robuste et orientée contrat (interfaces), il simplifie l'authentification et l'envoi de notifications, vous permettant d'intégrer des notifications fiables en quelques minutes.

## 📦 Installation

```bash
composer require andydefer/push-notifier
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

require 'vendor/autoload.php';

use Andydefer\PushNotifier\Core\NotificationFactory;

// 1. Créer une factory
$factory = new NotificationFactory();

// 2. Créer un service Firebase à partir du fichier JSON
$firebaseService = $factory->makeFirebaseServiceFromJsonFile(
    __DIR__ . '/storage/firebase-credentials.json'
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

Le package est construit autour de plusieurs concepts puissants pour garantir flexibilité, testabilité et fiabilité dans n'importe quel environnement PHP.

### 1. La Factory comme Point d'Entrée Unique

La classe `NotificationFactory` est le point d'entrée principal. Elle centralise la création de vos services et vous permet d'injecter vos propres implémentations des composants de bas niveau (client HTTP, fournisseur d'auth). C'est le cœur de l'injection de dépendances du package.

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

*   `HttpClientInterface`: Pour utiliser un client HTTP différent.
*   `AuthProviderInterface`: Pour gérer l'authentification Firebase d'une manière différente.
*   `PayloadBuilderInterface`: Pour construire le payload FCM selon vos propres besoins (optionnel, une implémentation par défaut est fournie).

```php
// Exemple : Utiliser un client HTTP personnalisé
use App\Services\MyCustomHttpClient;

$factory = new NotificationFactory(
    new MyCustomHttpClient() // Injecté ici
);
$service = $factory->makeFirebaseServiceFromJsonFile($jsonPath);
```

### 3. DTOs pour la Clarté et la Cohérence

Toutes les données manipulées par le package sont encapsulées dans des DTOs (Data Transfer Objects).

*   **`FirebaseConfigData`** : Contient la configuration Firebase.
*   **`FcmMessageData`** : Représente le message à envoyer, avec une structure simple : `type` (string en SCREAMING_SNAKE_CASE) et `data` (array avec clés en camelCase).
*   **`FcmResponseData`** : Représente la réponse de l'API FCM.
*   **`HttpResponseData`** : Encapsule une réponse HTTP brute.

### 4. Exceptions Spécifiques

Le package lance des exceptions spécifiques pour chaque type d'erreur.

*   `FcmSendException`: Levée lors d'un échec d'envoi.
*   `FirebaseAuthException`: Levée en cas de problème d'authentification.
*   `InvalidConfigurationException`: Levée si la configuration Firebase est invalide.

## 📧 Création de Messages

Le DTO `FcmMessageData` a une structure simple et flexible :

- **`type`** : string en SCREAMING_SNAKE_CASE (libre choix)
- **`data`** : array avec clés en camelCase (contenu libre)

### Types de Notifications Prédéfinis

```php
use Andydefer\PushNotifier\Dtos\FcmMessageData;

// Notification d'information
$info = FcmMessageData::info('Mise à jour', 'Nouvelle version disponible.');

// Notification d'alerte
$alert = FcmMessageData::alert('Alerte', 'Paiement en attente.');

// Notification de succès
$success = FcmMessageData::success('Succès', 'Commande confirmée.');

// Notification d'erreur
$error = FcmMessageData::error('Erreur', 'Connexion perdue.');

// Notification silencieuse
$ping = FcmMessageData::ping(['sync' => true]);
```

### Données Personnalisées

Vous pouvez ajouter n'importe quelles données dans le tableau `data` :

```php
$message = FcmMessageData::make(
    type: 'CHAT_MESSAGE',
    data: [
        'title' => 'Nouveau message',
        'body' => 'Vous avez reçu un message',
        'senderId' => 123,
        'senderName' => 'Jean',
        'conversationId' => 456,
        'attachments' => [
            ['type' => 'image', 'url' => 'https://...']
        ]
    ]
);
```

## 🛠️ Utilisation Avancée

### Envoyer à plusieurs appareils

```php
$tokens = ['token1', 'token2', 'token3'];
$message = FcmMessageData::alert('Promo Flash', '-50% sur tout !');

$results = $firebaseService->sendMulticast($tokens, $message);

foreach ($results as $token => $response) {
    if ($response->success) {
        // Succès
    } else if ($response->isInvalidToken()) {
        // Token invalide, à supprimer
    }
}
```

### Valider un token

```php
if ($firebaseService->validateToken($deviceToken)) {
    // Token valide
} else {
    // Token invalide, à supprimer
}
```

### Analyser les réponses d'erreur

```php
try {
    $response = $firebaseService->send($token, $message);
} catch (FcmSendException $e) {
    $errorResponse = FcmResponseData::fromError(
        $e->getErrorCode() ?? 'UNKNOWN',
        $e->getMessage(),
        $e->getStatusCode()
    );

    if ($errorResponse->isInvalidToken()) {
        // Marquer le token comme invalide
    } elseif ($errorResponse->isQuotaExceeded()) {
        // Gérer le dépassement de quota
    }
}
```

## ⚙️ Configuration

### Méthodes de Configuration

```php
// Via fichier JSON (recommandé)
$service = $factory->makeFirebaseServiceFromJsonFile('/path/to/credentials.json');

// Via chaîne JSON
$jsonContent = file_get_contents('/path/to/credentials.json');
$service = $factory->makeFirebaseServiceFromJsonString($jsonContent);

// Via tableau PHP
$config = [
    'project_id' => 'votre-projet-id',
    'client_email' => 'firebase-adminsdk-xxx@...',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nMII...\n-----END PRIVATE KEY-----\n",
];
$service = $factory->makeFirebaseServiceFromArray($config);

// Via variables d'environnement
$service = $factory->makeFirebaseServiceFromEnv($_ENV);
```

## 🧪 Tests

```bash
# Exécuter tous les tests
composer test

# Exécuter les tests avec couverture
composer test-coverage
```

## 📚 API Complète

### `NotificationFactory`

| Méthode | Description |
| :--- | :--- |
| `__construct(?HttpClientInterface, ?AuthProviderInterface)` | Constructeur avec injection optionnelle. |
| `makeFirebaseServiceFromJsonFile(string $path): FirebaseService` | Crée un service depuis un fichier JSON. |
| `makeFirebaseServiceFromJsonString(string $json): FirebaseService` | Crée un service depuis une chaîne JSON. |
| `makeFirebaseServiceFromArray(array $config): FirebaseService` | Crée un service depuis un tableau. |
| `makeFirebaseServiceFromEnv(array $env): FirebaseService` | Crée un service depuis les variables d'env. |
| `makeFirebaseService(FirebaseConfigData $config): FirebaseService` | Crée un service depuis un DTO. |

### `FirebaseService`

| Méthode | Description |
| :--- | :--- |
| `send(string $token, FcmMessageData $message): FcmResponseData` | Envoie une notification. |
| `sendMulticast(array $tokens, FcmMessageData $message): array` | Envoie à plusieurs appareils. |
| `sendInfo(string $token, string $title, string $body, array $data = []): FcmResponseData` | Envoie une notification "info". |
| `sendAlert(...)` | Envoie une notification "alert". |
| `sendWarning(...)` | Envoie une notification "warning". |
| `sendSuccess(...)` | Envoie une notification "success". |
| `sendError(...)` | Envoie une notification "error". |
| `ping(string $token): FcmResponseData` | Envoie une notification silencieuse. |
| `validateToken(string $token): bool` | Vérifie si un token est valide. |

### `FcmMessageData`

| Méthode Statique | Description |
| :--- | :--- |
| `make(string $type, array $data = []): self` | Crée un message personnalisé. |
| `info(string $title, string $body, array $data = []): self` | Crée un message "INFO". |
| `alert(string $title, string $body, array $data = []): self` | Crée un message "ALERT". |
| `warning(string $title, string $body, array $data = []): self` | Crée un message "WARNING". |
| `success(string $title, string $body, array $data = []): self` | Crée un message "SUCCESS". |
| `error(string $title, string $body, array $data = []): self` | Crée un message "ERROR". |
| `ping(array $data = []): self` | Crée un message "PING". |

### `FcmResponseData`

| Méthode | Description |
| :--- | :--- |
| `fromFcmResponse(array $response, int $statusCode): self` | Crée depuis une réponse FCM. |
| `fromError(string $code, string $message, ?int $statusCode): self` | Crée depuis une erreur. |
| `isInvalidToken(): bool` | Vérifie si le token est invalide. |
| `isQuotaExceeded(): bool` | Vérifie si le quota est dépassé. |
| `isAuthError(): bool` | Vérifie s'il s'agit d'une erreur d'auth. |

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/ma-fonctionnalite`)
3. Commitez (`git commit -m 'Ajoute une fonctionnalité'`)
4. Poussez (`git push origin feature/ma-fonctionnalite`)
5. Ouvrez une Pull Request

## 📄 Licence

MIT

---
**PushNotifier** - Des notifications push simples et fiables avec PHP et Firebase. 🚀