<?php
/**
 * Session — thin wrapper around $_SESSION
 * Prevents direct $_SESSION access scattered across files
 */
class Session {

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    // ── CSRF helpers ──────────────────────────────────────────────────────────

    public static function generateCsrfToken(): string {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }

    public static function verifyCsrfToken(string $token): bool {
        $stored = self::get('csrf_token');
        if (!$stored) return false;
        return hash_equals($stored, $token);
    }

    // ── Flash messages ────────────────────────────────────────────────────────
    // Flash messages are stored for one request only

    public static function flash(string $key, $value): void {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, $default = null) {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
