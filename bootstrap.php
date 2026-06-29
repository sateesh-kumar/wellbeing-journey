<?php
/**
 * bootstrap.php - Application bootstrap
 * Loads .env, defines helpers, and requires core classes.
 */

// Load .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die('Missing .env file in project root: ' . __DIR__);
}
$parsed = parse_ini_file($envFile, false, INI_SCANNER_RAW);
if ($parsed === false) {
    die('Failed to parse .env file. Check for special characters like : or & in unquoted values.');
}
foreach ($parsed as $key => $value) {
    $_ENV[$key] = $value;
}

// env() helper
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

// Require core classes
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

// Tenant helper class
if (!class_exists('Tenant')) {
    class Tenant {
        private static ?array $current = null;

        public static function set(array $tenant): void {
            self::$current = $tenant;
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['tenant'] = $tenant;
        }

        public static function get(): ?array {
            if (self::$current) return self::$current;
            if (session_status() === PHP_SESSION_NONE) session_start();
            return $_SESSION['tenant'] ?? null;
        }

        public static function id(): ?int {
            $t = self::get();
            return $t ? (int)$t['id'] : null;
        }
    }
}

// CSRF helpers
if (!function_exists('csrfField')) {
    function csrfField(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = htmlspecialchars($_SESSION['csrf_token']);
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Invalid CSRF token. Please go back and try again.');
        }
    }
}

// currentUser() helper
if (!function_exists('currentUser')) {
    function currentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['auth_user'] ?? null;
    }
}

// currentTenantId() helper
if (!function_exists('currentTenantId')) {
    function currentTenantId(): ?int {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
    }
}

// tenantFilter() helper - Returns SQL fragment: "alias.tenant_id = ?" or "tenant_id = ?"
if (!function_exists('tenantFilter')) {
    function tenantFilter(string $alias = '', string $column = 'tenant_id'): string {
        $col = $alias !== '' ? "{$alias}.{$column}" : $column;
        return "{$col} = ?";
    }
}

// tenantParam() helper - Returns the current tenant ID to bind as a query parameter
if (!function_exists('tenantParam')) {
    function tenantParam(): ?int {
        return currentTenantId();
    }
}
