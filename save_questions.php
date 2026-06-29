<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_authenticated'])) {
    header('Location: admin_questions.php');
    exit;
}

// ── Only accept POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_questions.php');
    exit;
}

// ── CSRF check ─────────────────────────────────────────────────────────────
verifyCsrf();

// ── Validate & sanitize ────────────────────────────────────────────────────
if (empty($_POST['questions']) || !is_array($_POST['questions'])) {
    $_SESSION['admin_error'] = 'No question data received.';
    header('Location: admin_questions.php');
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        UPDATE questions
        SET    question_text = :text,
               updated_at    = NOW()
        WHERE  id            = :id
    ");

    $updated = 0;
    $skipped = 0;

    foreach ($_POST['questions'] as $rawId => $rawText) {
        $id   = (int) $rawId;
        $text = trim($rawText);

        // Skip empty or overly long values
        if ($id <= 0 || $text === '' || mb_strlen($text) > 500) {
            $skipped++;
            continue;
        }

        // Only update rows that actually belong to this app (row must exist)
        $check = $pdo->prepare("SELECT id FROM questions WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            $skipped++;
            continue;
        }

        $stmt->execute([':text' => $text, ':id' => $id]);
        $updated += $stmt->rowCount(); // rowCount() = 1 only if value actually changed
    }

    $msg = "Questions updated successfully.";
    if ($updated > 0)  $msg .= " ($updated row" . ($updated > 1 ? 's' : '') . " modified.)";
    if ($skipped > 0)  $msg .= " ($skipped skipped due to validation.)";

    $_SESSION['admin_success'] = $msg;

} catch (PDOException $e) {
    error_log("save_questions error: " . $e->getMessage());
    $_SESSION['admin_error'] = 'Database error while saving questions. Please try again.';
}

header('Location: admin_questions.php');
exit;
