<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();
header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$entryId = (int)($data['entry_id'] ?? 0);
$insight = trim($data['insight'] ?? '');
$uid  = Auth::id();
$user = Auth::user();

if (!$entryId || !$insight) { echo json_encode(['ok' => false]); exit; }

$pdo = Database::connect();
$stmt = $pdo->prepare("UPDATE journal_entries SET ai_insight = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND tenant_id = ?");
$stmt->execute([$insight, $entryId, $uid, $user['tenant_id']]);

echo json_encode(['ok' => $stmt->rowCount() > 0]);
