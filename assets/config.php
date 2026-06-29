<?php
// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load .env if not already loaded
if (empty($_ENV['DB_HOST'])) {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $parsed = parse_ini_file($envFile, false, INI_SCANNER_RAW);
        if ($parsed) {
            foreach ($parsed as $key => $value) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Anthropic API
if (!defined('ANTHROPIC_API_KEY')) {
    define('ANTHROPIC_API_KEY', $_ENV['ANTHROPIC_API_KEY'] ?? '');
}

// Database (PostgreSQL)
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? '');
    define('DB_NAME', $_ENV['DB_NAME'] ?? '');
    define('DB_USER', $_ENV['DB_USER'] ?? '');
    define('DB_PASS', $_ENV['DB_PASS'] ?? '');
    define('DB_PORT', $_ENV['DB_PORT'] ?? 5432);
}

// Legacy single-user constant (kept for backward compat)
if (!defined('DEFAULT_USER_ID')) {
    define('DEFAULT_USER_ID', $_SESSION['user_id'] ?? 1);
}

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO {
        static $pdo = null;
        if ($pdo !== null) return $pdo;
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET NAMES 'UTF8'");
        return $pdo;
    }
}

if (!function_exists('requireAuth')) {
    function requireAuth(): void {
        if (empty($_SESSION['user_id'])) {
            header('Location: auth.php');
            exit;
        }
    }
}

if (!function_exists('currentUser')) {
    function currentUser(): ?array {
        return $_SESSION['auth_user'] ?? null;
    }
}

if (!function_exists('loginUser')) {
    function loginUser(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['auth_user'] = [
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'created_at' => $user['created_at'],
        ];
    }
}

if (!function_exists('logoutUser')) {
    function logoutUser(): void {
        $_SESSION = [];
        session_destroy();
    }
}
