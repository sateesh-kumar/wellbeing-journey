<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']); exit;
}

$tenantId = (int)($_SESSION['admin_tenant_id'] ?? 0);
$userId   = (int)($_POST['id'] ?? 0);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']); exit;
}

$pdo = Database::connect();

// Ensure user belongs to this tenant before deleting
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$userId, $tenantId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'User not found or access denied.']); exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$userId, $tenantId]);

echo json_encode(['success' => true]);
