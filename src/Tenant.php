<?php
/**
 * Tenant — Multi-tenancy helper
 * 
 * Handles tenant identification, validation, and data isolation.
 * Uses row-level security pattern (single database, tenant_id column on all tables).
 */
class Tenant {

    private static ?array $current = null;

    /**
     * Initialize tenant from request (domain, subdomain, or parameter)
     * Called early in bootstrap before any database queries
     */
    public static function detect(): ?array {
        if (self::$current !== null) {
            return self::$current;
        }

        // Strategy 1: Subdomain-based (e.g., acme.app.com)
        $tenant = self::detectFromSubdomain();
        
        // Strategy 2: Path-based (e.g., app.com/acme/)
        if (!$tenant) {
            $tenant = self::detectFromPath();
        }

        // Strategy 3: Query parameter (e.g., ?tenant=acme)
        if (!$tenant) {
            $tenant = self::detectFromParameter();
        }

        self::$current = $tenant;
        return $tenant;
    }

    /**
     * Detect from subdomain (e.g., acme.app.com → acme)
     */
    private static function detectFromSubdomain(): ?array {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);

        // If not enough parts, not a subdomain setup
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Skip common subdomains
        if (in_array($subdomain, ['www', 'mail', 'ftp', 'api'], true)) {
            return null;
        }

        return self::getTenantBySlug($subdomain);
    }

    /**
     * Detect from URL path (e.g., /acme/dashboard.php → acme)
     */
    private static function detectFromPath(): ?array {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $segments = array_filter(explode('/', $path));

        if (empty($segments)) {
            return null;
        }

        $slug = array_values($segments)[0];

        // Skip if it looks like a file
        if (strpos($slug, '.') !== false) {
            return null;
        }

        return self::getTenantBySlug($slug);
    }

    /**
     * Detect from query parameter (e.g., ?tenant=acme)
     */
    private static function detectFromParameter(): ?array {
        $slug = $_GET['tenant'] ?? null;
        if (!$slug) {
            return null;
        }

        return self::getTenantBySlug($slug);
    }

    /**
     * Fetch tenant by slug from database
     */
    private static function getTenantBySlug(string $slug): ?array {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare('SELECT * FROM tenants WHERE slug = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$slug]);
            $tenant = $stmt->fetch();
            return $tenant ?: null;
        } catch (Exception $e) {
            // Table might not exist yet during migration
            return null;
        }
    }

    /**
     * Get current tenant
     */
    public static function current(): ?array {
        if (self::$current === null) {
            self::detect();
        }
        return self::$current;
    }

    /**
     * Get current tenant ID
     */
    public static function id(): ?int {
        $tenant = self::current();
        return $tenant ? (int)$tenant['id'] : null;
    }

    /**
     * Check if tenant is set
     */
    public static function check(): bool {
        return self::current() !== null;
    }

    /**
     * Require tenant to be set (fail early if not)
     */
    public static function require(): void {
        if (!self::check()) {
            http_response_code(400);
            die('Invalid or missing tenant.');
        }
    }

    /**
     * Set tenant manually (for testing or explicit control)
     */
    public static function set(array $tenant): void {
        self::$current = $tenant;
        Session::set('tenant_id', $tenant['id']);
        Session::set('tenant_slug', $tenant['slug']);
    }

    /**
     * Verify user belongs to current tenant
     */
    public static function userBelongsToTenant(int $userId): bool {
        try {
            $tenantId = self::id();
            if (!$tenantId) {
                return false;
            }

            $pdo = Database::connect();
            $stmt = $pdo->prepare('
                SELECT 1 FROM users 
                WHERE id = ? AND tenant_id = ? 
                LIMIT 1
            ');
            $stmt->execute([$userId, $tenantId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create new tenant
     */
    public static function create(array $data): ?int {
        try {
            $pdo = Database::connect();
            
            $stmt = $pdo->prepare('
                INSERT INTO tenants (name, slug, is_active, created_at)
                VALUES (?, ?, ?, NOW())
            ');

            $slug = $data['slug'] ?? self::generateSlug($data['name'] ?? 'tenant');
            
            $stmt->execute([
                $data['name'] ?? 'New Tenant',
                $slug,
                $data['is_active'] ?? 1,
            ]);

            return (int)$pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('Tenant creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate URL-safe slug
     */
    private static function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
