<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$entryId = (int)($data['entry_id'] ?? 0);
$uid  = Auth::id();
$user = Auth::user();

if (!$entryId) { http_response_code(400); echo json_encode(['error' => 'Missing entry_id']); exit; }

$pdo  = Database::connect();
$stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ? AND user_id = ? AND tenant_id = ?");
$stmt->execute([$entryId, $uid, $user['tenant_id']]);
$entry = $stmt->fetch();

if (!$entry) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }

// Return cached insight if already generated
if (!empty($entry['ai_insight'])) {
    echo json_encode(['insight' => $entry['ai_insight']]); exit;
}

$categoryLabel = ucwords(str_replace('_', ' ', $entry['category']));
$moodLabels    = [1 => 'very low', 2 => 'low', 3 => 'neutral', 4 => 'good', 5 => 'great'];
$moodStr       = $entry['mood'] ? "Their mood: {$moodLabels[$entry['mood']]}.\n" : '';

$prompt = "A user wrote this journal entry about '{$categoryLabel}' in their wellbeing journey:\n\n"
    . "\"{$entry['entry_text']}\"\n\n"
    . $moodStr
    . "Write a warm, insightful 2-sentence reflection. Acknowledge what they shared and offer one small, actionable thought to help them grow. Plain text only.";

// Proxy through generate_summary.php (reuses same Anthropic API setup)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/generate_summary.php';

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nCookie: " . ($_SERVER['HTTP_COOKIE'] ?? '') . "\r\n",
        'content' => json_encode(['prompt' => $prompt]),
        'timeout' => 30,
    ]
]);

$fallback = 'Keep reflecting — every entry is a step forward.';
$insight  = $fallback;

$raw = @file_get_contents($baseUrl, false, $ctx);
if ($raw) {
    $parsed  = json_decode($raw, true);
    $insight = $parsed['summary'] ?? $fallback;
}

// Cache in DB
$pdo->prepare("UPDATE journal_entries SET ai_insight = ?, updated_at = NOW() WHERE id = ?")
    ->execute([$insight, $entryId]);

echo json_encode(['insight' => $insight]);
