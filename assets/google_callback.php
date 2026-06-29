<?php
// Enable error logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$logFile = __DIR__ . '/google_oauth.log';

function logDebug($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logDebug("=== Started ===");
logDebug("GET: " . json_encode($_GET));

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

function env($key, $default = null) {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return $default;
    
    $lines = file($envFile);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$envKey, $envValue] = explode('=', $line, 2);
        if (trim($envKey) === $key) {
            return trim($envValue);
        }
    }
    return $default;
}

try {
    logDebug("Connecting to database...");
    $pdo = Database::connect();
    logDebug("✓ Database connected");
} catch (Exception $e) {
    logDebug("✗ DB Error: " . $e->getMessage());
    header('Location: login.php?error=db');
    exit;
}

$clientId = env('GOOGLE_CLIENT_ID');
$clientSecret = env('GOOGLE_CLIENT_SECRET');
$redirectUri = env('GOOGLE_REDIRECT_URI');

logDebug("ClientID set: " . (!empty($clientId) ? 'yes' : 'no'));
logDebug("ClientSecret set: " . (!empty($clientSecret) ? 'yes' : 'no'));
logDebug("RedirectURI: $redirectUri");

if (empty($clientId) || empty($clientSecret)) {
    logDebug("✗ Missing credentials");
    header('Location: login.php?error=credentials');
    exit;
}

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

logDebug("✓ Client created");

$sslOptions = env('APP_ENV') === 'development' ? ['verify' => false] : [];
$httpClient = new \GuzzleHttp\Client($sslOptions);
$client->setHttpClient($httpClient);

logDebug("✓ HTTP client set");

if (!isset($_GET['code'])) {
    logDebug("✗ No code in GET");
    header('Location: login.php');
    exit;
}

logDebug("✓ Code found");

try {
    logDebug("Fetching token...");
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    logDebug("✓ Token fetched");
    logDebug("Token data: " . json_encode($token));

    if (isset($token['error'])) {
        logDebug("✗ Token error: " . $token['error']);
        header('Location: login.php?error=token');
        exit;
    }

    $client->setAccessToken($token);
    logDebug("✓ Token set");

    $oauth = new Google\Service\Oauth2($client);
    logDebug("✓ OAuth service created");
    
    $googleUser = $oauth->userinfo->get();
    logDebug("✓ User info got");
    logDebug("Email: " . $googleUser->email);
    logDebug("Name: " . $googleUser->name);

    $email = $googleUser->email;
    $name = $googleUser->name;

    // Find user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        logDebug("Creating new user: $email");
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, tenant_id) VALUES (?, ?, ?, ?) RETURNING id, name, email, tenant_id, created_at"
        );
        $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), 1]);
        $user = $stmt->fetch();
        logDebug("✓ User created: " . $user['id']);
    } else {
        logDebug("✓ User exists: " . $user['id']);
    }

    logDebug("User data: " . json_encode($user));

    logDebug("Calling Auth::login...");
    Auth::login($user);
    logDebug("✓ Auth::login succeeded");

    $redirect = Auth::intendedRedirect('dashboard.php');
    logDebug("Redirecting to: $redirect");
    
    header('Location: ' . $redirect);
    exit;

} catch (Exception $e) {
    logDebug("✗ Exception: " . $e->getMessage());
    logDebug("Trace: " . $e->getTraceAsString());
    header('Location: login.php?error=exception');
    exit;
}
