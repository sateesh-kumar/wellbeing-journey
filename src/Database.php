<?php
/**
 * Database — PDO singleton
 * Usage: $pdo = Database::connect();
 */
class Database {

    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', 5432);
        $name = env('DB_NAME');
        $user = env('DB_USER');
        $pass = env('DB_PASS');

        if (!$name || !$user) {
            throw new RuntimeException('Database credentials are not configured. Check your .env file.');
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--client_encoding=UTF8 --search_path=public'";

        self::$instance = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }

    // Prevent direct instantiation
    private function __construct() {}
    private function __clone() {}
}

// ── Backward-compatible wrapper ────────────────────────────────────────────────
if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO {
        return Database::connect();
    }
}
