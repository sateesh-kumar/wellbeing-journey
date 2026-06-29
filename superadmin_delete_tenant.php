<?php
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

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid tenant ID.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Check tenant exists
    $check = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ?");
    $check->execute([$id]);
    $tenant = $check->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'Tenant not found.']);
        exit;
    }

    // Delete the tenant
    $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => "Tenant \"{$tenant['name']}\" deleted."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
