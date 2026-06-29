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

$userId    = (int)($_POST['id'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';
$isAdmin   = isset($_POST['is_admin']) && $_POST['is_admin'] == '1' ? 1 : 0;

if (!$userId)    { echo json_encode(['success'=>false,'message'=>'Invalid user ID.']); exit; }
if (!$firstName) { echo json_encode(['success'=>false,'message'=>'First name is required.']); exit; }
if (!$lastName)  { echo json_encode(['success'=>false,'message'=>'Last name is required.']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email address.']); exit; }
if ($password !== '' && strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit; }

$pdo = getDBConnection();

// Fetch existing user to get tenant_id
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    echo json_encode(['success'=>false,'message'=>'User not found.']);
    exit;
}

// Check email uniqueness (excluding self)
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? AND id != ?");
$stmt->execute([$email, $existing['tenant_id'], $userId]);
if ($stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Another user with this email already exists in this organization.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);

if ($password !== '') {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, is_admin=? WHERE id=?");
    $stmt->execute([$fullName, $email, $hash, $isAdmin, $userId]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, is_admin=? WHERE id=?");
    $stmt->execute([$fullName, $email, $isAdmin, $userId]);
}

echo json_encode(['success' => true]);
