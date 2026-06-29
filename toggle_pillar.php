<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Auth check — accept either admin or superadmin session
if (!isset($_SESSION['admin_authenticated']) && !isset($_SESSION['superadmin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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

$pillarId = isset($_POST['pillar_id']) ? (int)$_POST['pillar_id'] : 0;
$isActive = isset($_POST['is_active']) ? (int)(bool)$_POST['is_active'] : null;

if (!$pillarId || $isActive === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify pillar exists
    $stmt = $pdo->prepare("SELECT id, label FROM pillars WHERE id = ?");
    $stmt->execute([$pillarId]);
    $pillar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pillar) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pillar not found']);
        exit;
    }

    // Update is_active
    $update = $pdo->prepare("UPDATE pillars SET is_active = ? WHERE id = ?");
    $update->execute([$isActive, $pillarId]);

    echo json_encode([
        'success'   => true,
        'pillar_id' => $pillarId,
        'is_active' => $isActive,
        'label'     => $pillar['label'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
