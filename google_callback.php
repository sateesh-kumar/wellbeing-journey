<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

$pdo = Database::connect();

// Google OAuth client — credentials from .env
$client = new Google\Client();
$client->setClientId(env('GOOGLE_CLIENT_ID'));
$client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

// SSL: skip verification in local development
if (env('APP_ENV') === 'development') {
//   $httpClient = new \GuzzleHttp\Client(['verify' => false]);
//    $client->setHttpClient($httpClient);
}

if (!isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        error_log('Google OAuth error: ' . ($token['error_description'] ?? $token['error']));
        header('Location: login.php?error=google_failed');
        exit;
    }

    $client->setAccessToken($token);

    $oauth      = new Google\Service\Oauth2($client);
    $googleUser = $oauth->userinfo->get();

    $email = $googleUser->email;
    $name  = $googleUser->name;

    // Find or create user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // New user — create account with tenant_id = 0 (no organisation yet)
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, tenant_id, created_at) 
             VALUES (?, ?, ?, 0, NOW()) 
             RETURNING id, name, email, tenant_id, created_at"
        );
        $stmt->execute([
            $name,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    Auth::login($user);

    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    error_log('Google OAuth exception: ' . $e->getMessage());
    header('Location: login.php?error=google_failed');
    exit;
}
