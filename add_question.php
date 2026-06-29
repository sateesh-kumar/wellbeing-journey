<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

// ── Only POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Parse JSON body first (CSRF token lives inside it) ────────────────────────
$input         = json_decode(file_get_contents('php://input'), true) ?? [];
$pillarId      = isset($input['pillar_id'])      ? (int)$input['pillar_id']      : 0;
$categoryField = isset($input['category_field']) ? trim($input['category_field']) : '';
$questionText  = isset($input['question_text'])  ? trim($input['question_text'])  : '';

// ── CSRF check (token travels in the JSON body, not $_POST) ───────────────────
$sessionToken   = $_SESSION['csrf_token'] ?? '';
$submittedToken = $input['csrf_token']    ?? '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    exit;
}

// ── Validate ──────────────────────────────────────────────────────────────────
if (!$pillarId || !$categoryField || !$questionText) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (strlen($questionText) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Question text exceeds 500 characters.']);
    exit;
}

// Whitelist category_field to prevent SQL injection (only allow lowercase + underscore)
if (!preg_match('/^[a-z_]+$/', $categoryField)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category field.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // ── Verify pillar exists ──────────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT id FROM pillars WHERE id = ?");
    $stmt->execute([$pillarId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pillar not found.']);
        exit;
    }

    // ── Determine next question_key (q1, q2, q3 ...) for this category ───────
    $stmt = $pdo->prepare("
        SELECT question_key FROM questions
        WHERE category_field = ?
        ORDER BY question_key ASC
    ");
    $stmt->execute([$categoryField]);
    $existingKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find the highest numeric suffix and increment
    $maxNum = 0;
    foreach ($existingKeys as $key) {
        if (preg_match('/^q(\d+)$/', $key, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $newKey = 'q' . ($maxNum + 1);

    // ── Get sort_order from existing questions in this category ───────────────
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE category_field = ?
    ");
    $stmt->execute([$categoryField]);
    $sortOrder = (int)$stmt->fetchColumn();

    // ── Map pillar_id to its assessment table ────────────────────────────────
    $pillarTableMap = [
        1 => 'connection_love_assessments',
        2 => 'growth_learning_assessments',
        3 => 'contribution_assessments',
        4 => 'freedom_autonomy_assessments',
    ];

    $assessmentTable = $pillarTableMap[$pillarId] ?? null;

    // colName is safe: built from whitelisted category_field (^[a-z_]+$) + q\d+ key
    $colName = $categoryField . '_' . $newKey; // e.g. time_freedom_q3

    // ── Add column directly to the assessment table ───────────────────────────
    // ADD COLUMN IF NOT EXISTS is idempotent — no information_schema lookup needed.
    if ($assessmentTable) {
        $pdo->exec("
            ALTER TABLE {$assessmentTable}
            ADD COLUMN IF NOT EXISTS {$colName} SMALLINT DEFAULT 0
        ");
    }

    // ── Insert new question ───────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO questions (pillar_id, category_field, question_key, question_text, sort_order)
        VALUES (?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([$pillarId, $categoryField, $newKey, $questionText, $sortOrder]);
    $newId = $stmt->fetchColumn();

    echo json_encode([
        'success'  => true,
        'question' => [
            'id'             => $newId,
            'pillar_id'      => $pillarId,
            'category_field' => $categoryField,
            'question_key'   => $newKey,
            'question_text'  => $questionText,
            'sort_order'     => $sortOrder,
            'column_added'   => $assessmentTable ? $colName : null,
        ],
    ]);

} catch (PDOException $e) {
    error_log('add_question.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
