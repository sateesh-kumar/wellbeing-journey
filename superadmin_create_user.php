<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/assets/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['superadmin_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$tenantId  = (int)($_POST['tenant_id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';
$isAdmin   = isset($_POST['is_admin']) && $_POST['is_admin'] == '1' ? 1 : 0;

if (!$tenantId)              { echo json_encode(['success'=>false,'message'=>'Invalid tenant.']); exit; }
if (!$firstName)             { echo json_encode(['success'=>false,'message'=>'First name is required.']); exit; }
if (!$lastName)              { echo json_encode(['success'=>false,'message'=>'Last name is required.']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email address.']); exit; }
if (strlen($password) < 8)  { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }

$pdo = getDBConnection();

// Check tenant exists
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Tenant not found.']);
    exit;
}

// Check email uniqueness within tenant
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
$stmt->execute([$email, $tenantId]);
if ($stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'A user with this email already exists in this organization.']);
    exit;
}

$hash     = password_hash($password, PASSWORD_BCRYPT);
$now      = date('Y-m-d H:i:s');
$fullName = trim($firstName . ' ' . $lastName);

$stmt = $pdo->prepare("
    INSERT INTO users (tenant_id, name, email, password, is_admin, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$tenantId, $fullName, $email, $hash, $isAdmin, $now]);
$newId = (int)$pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'user'    => [
        'id'         => $newId,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'email'      => $email,
        'is_admin'   => $isAdmin,
        'created_at' => $now,
    ]
]);
