<?php
/**
 * Auth — session-based authentication helpers (TENANT-AWARE)
 * Uses native PHP sessions instead of Session wrapper
 */
class Auth {

    public static function login(array $user): void {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'] ?? null;
        $_SESSION['auth_user'] = [
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'tenant_id'  => $user['tenant_id'] ?? null,
            'created_at' => $user['created_at'] ?? null,
        ];
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id = $_SESSION['user_id'] ?? null;
        return $id ? (int)$id : null;
    }

    /**
     * Get tenant ID from session (set during login)
     */
    public static function tenantId(): ?int {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id = $_SESSION['tenant_id'] ?? null;
        return $id ? (int)$id : null;
    }

    /**
     * Redirect to login if not authenticated
     */
    public static function require(): void {
        if (!self::check()) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
            $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            header('Location: ' . $base . '/login.php');
            exit;
        }
    }

    /**
     * Verify tenant context matches current tenant
     */
    public static function requireTenant(): void {
        $sessionTenantId = self::tenantId();
        $currentTenantId = defined('CURRENT_TENANT_ID') ? CURRENT_TENANT_ID : null;

        if (!$sessionTenantId || !$currentTenantId || $sessionTenantId !== $currentTenantId) {
            http_response_code(403);
            die('Tenant mismatch or unauthorized access.');
        }
    }

    /**
     * Intended redirect after login (clears after reading)
     */
    public static function intendedRedirect(string $default = 'dashboard.php'): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $url = $_SESSION['redirect_after_login'] ?? $default;
        unset($_SESSION['redirect_after_login']);
        return $url;
    }
}
