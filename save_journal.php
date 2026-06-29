<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$pillarId  = (int)($data['pillar_id'] ?? 0);
$category  = trim($data['category'] ?? '');
$entryText = trim($data['entry_text'] ?? '');
$mood      = (int)($data['mood'] ?? 0);
$uid  = Auth::id();
$user = Auth::user();

if (!$pillarId || !$category || !$entryText) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if ($mood < 1 || $mood > 5) $mood = null;

$pdo = Database::connect();

$stmt = $pdo->prepare("
    INSERT INTO journal_entries (user_id, tenant_id, pillar_id, category, mood, entry_text, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    RETURNING id, created_at
");
$stmt->execute([$uid, $user['tenant_id'], $pillarId, $category, $mood, $entryText]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save entry. Please try again.']);
    exit;
}

// Build prompt for the browser to call generate_summary.php directly
$categoryLabel = ucwords(str_replace('_', ' ', $category));
$moodLabels    = [1 => 'very low', 2 => 'low', 3 => 'neutral', 4 => 'good', 5 => 'great'];
$moodStr       = $mood ? "Their mood: {$moodLabels[$mood]}.\n" : '';

$prompt = "A user wrote this journal entry about '{$categoryLabel}' in their wellbeing journey:\n\n"
    . "\"{$entryText}\"\n\n"
    . $moodStr
    . "Write a warm, insightful 2-sentence reflection. Acknowledge what they shared and offer one small, actionable thought to help them grow. Plain text only.";

echo json_encode([
    'success'    => true,
    'entry_id'   => $row['id'],
    'created_at' => $row['created_at'],
    'prompt'     => $prompt,
]);
