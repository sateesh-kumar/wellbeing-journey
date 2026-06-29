<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/assets/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['superadmin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── CSRF check (JSON-safe) ────────────────────────────────────────────────────
$sessionToken   = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token']    ?? '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    exit;
}

$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$slug  = trim($_POST['slug']  ?? '');

// ── Validation ─────────────────────────────────────────────────────────────────
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Organization name is required.']);
    exit;
}

if ($slug === '') {
    echo json_encode(['success' => false, 'message' => 'Slug is required.']);
    exit;
}

if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    echo json_encode(['success' => false, 'message' => 'Slug may only contain lowercase letters, numbers, and hyphens.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Check slug uniqueness
    $check = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => "Slug \"{$slug}\" is already taken. Choose another."]); 
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO tenants (name, email, slug, is_active, created_at)
        VALUES (?, ?, ?, TRUE, CURRENT_TIMESTAMP)
        RETURNING id, name, email, slug, is_active, created_at
    ");
    $stmt->execute([$name, $email ?: null, $slug]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tenant' => $tenant]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
