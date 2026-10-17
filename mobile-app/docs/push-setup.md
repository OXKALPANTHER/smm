# Optional Firebase push setup

The app deliberately compiles without Firebase. To enable order and wallet notifications, create an Android Firebase app with package name `com.t20tech.royalsmm`, download `google-services.json`, and place it at `mobile-app/android/app/google-services.json`. Keep that file out of Git if it contains project credentials.

Add the Firebase Messaging dependency and Google Services plugin versions to the Gradle project, then request `POST_NOTIFICATIONS` on Android 13+ after the customer has authenticated. Create notification channels named `Orders`, `Wallet`, and `Promotions`. Notification taps should open the corresponding website path, for example `/orders.php`, `/addfunds.php`, or `/notifications.php`.

The website needs an authenticated token registration endpoint. A minimal PHP shape is:

```php
// POST /api/mobile/fcm-token.php, authenticated session required
$body = json_decode(file_get_contents('php://input'), true);
$token = trim((string)($body['token'] ?? ''));
if ($token === '' || strlen($token) > 4096) { http_response_code(422); exit; }
$stmt = $pdo->prepare('INSERT INTO device_tokens (user_id, token, platform) VALUES (?, ?, ?) ON CONFLICT (user_id, token) DO UPDATE SET updated_at = NOW()');
$stmt->execute([(int)$_SESSION['user_id'], $token, 'android']);
header('Content-Type: application/json'); echo json_encode(['ok' => true]);
```

Send notifications from a trusted server using Firebase Admin SDK, never from the APK. Store the FCM server credential only on the server. The current build includes no Firebase SDK and therefore does not claim push delivery until these files and endpoint are supplied.
