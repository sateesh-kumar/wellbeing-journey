<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline';");

if (!isset($_SESSION['admin_authenticated'])) {
    header('Location: admin_login.php');
    exit;
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$pdo = getDBConnection();

// ── Fetch all pillars ─────────────────────────────────────────────────────────
$allPillars = $pdo->query("SELECT * FROM pillars ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build a lookup map: id → pillar row
$pillarMap = [];
foreach ($allPillars as $p) {
    $pillarMap[(int)$p['id']] = $p;
}

// ── Resolve selected pillar from ?pillar= ────────────────────────────────────
$selectedPillarId = isset($_GET['pillar']) ? (int)$_GET['pillar'] : null;
$selectedPillar   = ($selectedPillarId && isset($pillarMap[$selectedPillarId]))
                    ? $pillarMap[$selectedPillarId]
                    : null;

// If invalid ID was passed, clear it
if ($selectedPillarId && !$selectedPillar) {
    $selectedPillarId = null;
}

// ── Fetch questions, grouped by pillar_id then category_field ─────────────────
// When a pillar is selected, only fetch that pillar's questions.
if ($selectedPillar) {
    $stmt = $pdo->prepare("
        SELECT * FROM questions
        WHERE  pillar_id = ?
        ORDER  BY sort_order ASC, question_key ASC
    ");
    $stmt->execute([$selectedPillarId]);
    $questions = $stmt->fetchAll();
} else {
    $questions = $pdo->query("
        SELECT * FROM questions
        ORDER  BY pillar_id ASC, sort_order ASC, question_key ASC
    ")->fetchAll();
}

// ── Build nested structure: [pillar_id][category_field][question_key] => row ──
$tree = []; // [pillar_id][category_field][] = row
foreach ($questions as $q) {
    $pid = (int)($q['pillar_id'] ?? 0);
    $cf  = $q['category_field'];
    $tree[$pid][$cf][] = $q;
}

// ── Determine which pillars to render ────────────────────────────────────────
$renderPillarIds = $selectedPillar
    ? [$selectedPillarId]
    : array_keys($pillarMap);   // all pillars, in sort_order

// ── Category label map: fetched from questions table via pillar config ────────
// Matches the category_field slugs to their proper display labels.
$CATEGORY_LABELS = [
    // Pillar 1 — Connection & Love
    'self_awareness'       => 'Self-Awareness & Self-Compassion',
    'emotional_connection' => 'Emotional Connection & Intimacy',
    'family_friends'       => 'Family & Friends',
    'love_expression'      => 'Love Expression',
    'community'            => 'Community & Belonging',
    // Pillar 2 — Growth & Learning
    'joy'                  => 'Joy & Gratitude',
    'engagement'           => 'Engagement & Flow',
    'growth'               => 'Strengths & Personal Growth',
    'purpose'              => 'Purpose & Contribution',
    'achievement'          => 'Achievement & Resilience',
    // Pillar 3 — Contribution & Helping
    'proactive'            => 'Proactive Helping',
    'knowledge'            => 'Sharing Knowledge & Mentoring',
    'generosity'           => 'Generosity & Community Engagement',
    'impact'               => 'Impact-Oriented Contribution',
    'sustainable'          => 'Sustainable Helping & Resilience',
];

function fieldToTitle(string $field): string {
    global $CATEGORY_LABELS;
    return $CATEGORY_LABELS[$field] ?? ucwords(str_replace('_', ' ', $field));
}

// ── Session messages ─────────────────────────────────────────────────────────
$successMsg = $_SESSION['admin_success'] ?? null;
$errorMsg   = $_SESSION['admin_error']   ?? null;
unset($_SESSION['admin_success'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Question Manager<?= $selectedPillar ? ' · ' . htmlspecialchars($selectedPillar['label']) : '' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0B0F17;
            --surface:     #111720;
            --surface-alt: #161D29;
            --border:      rgba(255,255,255,0.07);
            --border-gold: rgba(201,168,76,0.30);
            --gold:        #C9A84C;
            --gold-light:  #E2C97E;
            --gold-dim:    rgba(201,168,76,0.12);
            --text:        #E8E2D6;
            --text-muted:  #5C6B82;
            --text-dim:    #3A4558;
            --danger:      #C94C4C;
            --danger-dim:  rgba(201,76,76,0.12);
            --success:     #4CAF76;
            --success-dim: rgba(76,175,118,0.12);
            --radius:      12px;
        }

        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; line-height: 1.6; }

        /* ── Layout ── */
        .page-wrap { display: grid; grid-template-columns: 268px 1fr; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--surface); border-right: 1px solid var(--border);
            padding: 32px 0; position: sticky; top: 0; height: 100vh;
            overflow-y: auto; display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 0 24px 28px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .sidebar-brand .logo { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--gold); letter-spacing: 0.02em; }
        .sidebar-brand .badge {
            display: inline-flex; align-items: center; gap: 5px; margin-top: 6px;
            font-size: 0.68rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--text-muted); background: var(--gold-dim); border: 1px solid var(--border-gold);
            padding: 3px 10px; border-radius: 20px;
        }
        .sidebar-nav { padding: 0 12px; flex: 1; }
        .nav-label {
            font-size: 0.65rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--text-dim); padding: 0 12px; margin-bottom: 8px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 8px; color: var(--text-muted);
            text-decoration: none; font-size: 0.875rem; transition: all 0.16s; margin-bottom: 2px;
        }
        .nav-item:hover  { background: var(--surface-alt); color: var(--text); }
        .nav-item.active { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .nav-icon {
            width: 22px; height: 22px; border-radius: 6px; background: var(--surface-alt);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem; flex-shrink: 0; color: var(--text-muted);
        }
        .nav-item.active .nav-icon { background: var(--gold); color: #0B0F17; }
        .nav-pillar-icon { font-size: 1rem; flex-shrink: 0; line-height: 1; }
        .nav-item .inactive-dot {
            width: 6px; height: 6px; border-radius: 50%; background: var(--text-dim); flex-shrink: 0; margin-left: auto;
        }
        .sidebar-footer { padding: 20px 24px 0; border-top: 1px solid var(--border); margin-top: auto; }
        .logout-link { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--text-muted); text-decoration: none; padding: 8px 0; transition: color 0.18s; }
        .logout-link:hover { color: var(--danger); }

        /* ── Main ── */
        .main { padding: 48px 56px; max-width: 960px; }

        .page-header { margin-bottom: 36px; }
        .page-header h1 { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 600; color: var(--text); line-height: 1.2; }
        .page-header h1 span { color: var(--gold); }
        .page-header p { margin-top: 8px; color: var(--text-muted); font-size: 0.9rem; }

        /* ── Pillar Banner (when filtered) ── */
        .pillar-banner {
            display: flex; align-items: center; gap: 16px;
            background: var(--surface); border: 1px solid var(--border-gold);
            border-radius: var(--radius); padding: 18px 22px; margin-bottom: 32px;
        }
        .pillar-banner-icon { font-size: 2rem; line-height: 1; flex-shrink: 0; }
        .pillar-banner-meta { flex: 1; }
        .pillar-banner-label { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); margin-bottom: 3px; }
        .pillar-banner-name { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--text); }
        .pillar-banner-desc { font-size: 0.82rem; color: var(--text-muted); margin-top: 2px; }
        .pillar-banner-back { font-size: 0.82rem; color: var(--text-muted); text-decoration: none; border: 1px solid var(--border); padding: 7px 15px; border-radius: 7px; transition: all 0.18s; white-space: nowrap; }
        .pillar-banner-back:hover { border-color: var(--border-gold); color: var(--text); }

        /* ── Alerts ── */
        .alert { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: var(--radius); margin-bottom: 28px; font-size: 0.9rem; font-weight: 500; }
        .alert-success { background: var(--success-dim); border: 1px solid rgba(76,175,118,0.3); color: var(--success); }
        .alert-error   { background: var(--danger-dim);  border: 1px solid rgba(201,76,76,0.3);  color: var(--danger); }

        /* ── Pillar Block (outer accordion) ── */
        .pillar-block { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 16px; overflow: hidden; transition: border-color 0.2s; }
        .pillar-block:hover { border-color: var(--border-gold); }
        .pillar-block-header {
            display: flex; align-items: center; gap: 14px;
            padding: 18px 22px; background: var(--surface-alt);
            border-bottom: 1px solid transparent;
            cursor: pointer; user-select: none;
            transition: border-color 0.2s;
        }
        .pillar-block.open .pillar-block-header { border-bottom-color: var(--border); }
        .pillar-header-icon { font-size: 1.35rem; line-height: 1; flex-shrink: 0; }
        .pillar-header-name { font-size: 0.98rem; font-weight: 600; color: var(--text); flex: 1; }
        .pillar-header-chips { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .chip {
            font-size: 0.72rem; font-weight: 500; font-family: 'DM Mono', monospace;
            padding: 3px 10px; border-radius: 20px; white-space: nowrap;
        }
        .chip-q    { background: var(--gold-dim); border: 1px solid var(--border-gold); color: var(--gold); }
        .chip-cat  { background: var(--surface); border: 1px solid var(--border); color: var(--text-muted); }
        .pillar-toggle { color: var(--text-dim); font-size: 1rem; transition: transform 0.25s; flex-shrink: 0; }
        .pillar-block.open .pillar-toggle { transform: rotate(180deg); }

        .pillar-block-body { display: none; padding: 20px 22px; flex-direction: column; gap: 16px; }
        .pillar-block.open .pillar-block-body { display: flex; }

        /* ── Category Sub-section ── */
        .cat-section { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .cat-section-header {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 18px; background: var(--surface-alt);
            border-bottom: 1px solid var(--border);
            cursor: pointer; user-select: none;
        }
        .cat-section-title { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); flex: 1; letter-spacing: 0.02em; }
        .cat-section-field { font-size: 0.72rem; font-family: 'DM Mono', monospace; color: var(--text-dim); }
        .cat-section-toggle { color: var(--text-dim); font-size: 0.85rem; transition: transform 0.2s; }
        .cat-section.open .cat-section-toggle { transform: rotate(180deg); }
        .cat-section-body { display: none; padding: 18px; flex-direction: column; gap: 16px; }
        .cat-section.open .cat-section-body { display: flex; }

        /* ── Question Row ── */
        .question-row { display: grid; grid-template-columns: 38px 1fr; gap: 12px; align-items: start; }
        .q-label {
            display: flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 6px;
            background: var(--surface-alt); border: 1px solid var(--border);
            font-family: 'DM Mono', monospace; font-size: 0.72rem;
            color: var(--text-muted); font-weight: 500; margin-top: 4px; flex-shrink: 0;
        }
        .q-field { display: flex; flex-direction: column; gap: 5px; }
        .q-meta { display: flex; align-items: center; gap: 7px; font-size: 0.7rem; color: var(--text-dim); font-family: 'DM Mono', monospace; }
        .q-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: var(--text-dim); }
        .q-input {
            width: 100%; background: var(--surface-alt); border: 1px solid var(--border);
            border-radius: 8px; padding: 11px 13px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem; line-height: 1.55;
            resize: vertical; min-height: 58px;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }
        .q-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12); background: #131A24; }
        .q-input.changed { border-color: rgba(201,168,76,0.5); background: rgba(201,168,76,0.04); }
        .char-count { font-size: 0.68rem; color: var(--text-dim); text-align: right; font-family: 'DM Mono', monospace; transition: color 0.18s; }
        .char-count.warn { color: var(--gold); }
        .q-divider { border: none; border-top: 1px dashed var(--border); margin: 2px 0; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .empty-state-icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5; }
        .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--text); margin-bottom: 8px; }
        .empty-state p { font-size: 0.88rem; line-height: 1.6; }

        /* ── Save Bar ── */
        .save-bar {
            position: sticky; bottom: 0;
            background: linear-gradient(to top, var(--bg) 60%, transparent);
            padding: 24px 0 32px;
            display: flex; align-items: center; gap: 16px; z-index: 50;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: all 0.18s; border: none; text-decoration: none; font-family: 'DM Sans', sans-serif;
        }
        .btn-primary { background: var(--gold); color: #0B0F17; }
        .btn-primary:hover { background: var(--gold-light); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,168,76,0.3); }
        .btn-secondary { background: transparent; color: var(--text-muted); border: 1px solid var(--border); }
        .btn-secondary:hover { border-color: var(--border-gold); color: var(--text); }
        .changes-indicator { font-size: 0.82rem; color: var(--text-muted); font-style: italic; }
        .changes-indicator.has-changes { color: var(--gold); }

        /* ── Add Question Button ── */
        .add-question-btn {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            width: 100%; padding: 10px 16px; margin-top: 4px;
            background: transparent; border: 1px dashed var(--border);
            border-radius: 8px; color: var(--text-muted);
            font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 500;
            cursor: pointer; transition: all 0.18s;
        }
        .add-question-btn:hover { border-color: var(--gold); color: var(--gold); background: var(--gold-dim); }
        .add-question-btn .plus { font-size: 1rem; line-height: 1; }

        /* ── Modal ── */
        .modal-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            z-index: 200; align-items: center; justify-content: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            background: var(--surface); border: 1px solid var(--border-gold);
            border-radius: 16px; padding: 32px; width: 100%; max-width: 520px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
            animation: modalIn 0.2s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 600; color: var(--text); }
        .modal-subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 3px; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 2px 6px; border-radius: 4px; transition: color 0.15s; }
        .modal-close:hover { color: var(--text); }
        .modal-field { margin-bottom: 18px; }
        .modal-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 7px; display: block; letter-spacing: 0.05em; text-transform: uppercase; }
        .modal-input {
            width: 100%; background: var(--surface-alt); border: 1px solid var(--border);
            border-radius: 8px; padding: 11px 13px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem; line-height: 1.55;
            resize: vertical; min-height: 80px;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .modal-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12); }
        .modal-meta { display: flex; gap: 10px; margin-bottom: 18px; }
        .modal-meta-chip { font-size: 0.72rem; font-family: 'DM Mono', monospace; padding: 4px 10px; border-radius: 6px; background: var(--gold-dim); border: 1px solid var(--border-gold); color: var(--gold); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .modal-error { background: var(--danger-dim); border: 1px solid rgba(201,76,76,0.3); color: var(--danger); border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 16px; display: none; }
        .modal-error.show { display: block; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--text-dim); border-radius: 4px; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .page-wrap { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; flex-direction: row; flex-wrap: wrap; padding: 16px; }
            .sidebar-brand { border-bottom: none; border-right: 1px solid var(--border); margin-bottom: 0; padding-right: 20px; }
            .sidebar-footer { border-top: none; padding-top: 0; margin-left: auto; }
            .main { padding: 24px 20px; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo">Happiness Audit</div>
            <div class="badge">⚙ Admin Panel</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label" style="margin-top:0">Admin Menu</div>
            <a href="admin_dashboard.php" class="nav-item">
                <span class="nav-icon">⌂</span> Dashboard
            </a>
            <a href="admin_questions.php" class="nav-item <?= $selectedPillarId === null ? 'active' : '' ?>">
                <span class="nav-icon">✎</span> Question Manager
            </a>

            <div class="nav-label" style="margin-top:24px">Pillars</div>
            <?php foreach ($allPillars as $p):
                $pid    = (int)$p['id'];
                $active = (bool)$p['is_active'];
            ?>
            <a href="<?= $active ? "admin_questions.php?pillar={$pid}" : '#' ?>"
               class="nav-item <?= $selectedPillarId === $pid ? 'active' : '' ?>"
               <?= !$active ? 'style="opacity:0.45;pointer-events:none"' : '' ?>>
                <span class="nav-pillar-icon"><?= htmlspecialchars($p['icon']) ?></span>
                <?= htmlspecialchars($p['label']) ?>
                <?php if (!$active): ?><span class="inactive-dot" title="Coming soon"></span><?php endif; ?>
            </a>
            <?php endforeach; ?>


        </nav>

        <div class="sidebar-footer">
            <a href="admin_logout.php" class="logout-link">⎋ &nbsp;Sign out</a>
        </div>
    </aside>

    <!-- ── Main ── -->
    <main class="main">

        <!-- Page Header -->
        <div class="page-header">
            <?php if ($selectedPillar): ?>
                <h1><?= htmlspecialchars($selectedPillar['icon']) ?> <span><?= htmlspecialchars($selectedPillar['label']) ?></span></h1>
                <p>Editing questions for this pillar. Changes take effect immediately on the live assessment.</p>
            <?php else: ?>
                <h1>Question <span>Manager</span></h1>
                <p>All pillar questions are shown below. Select a pillar from the sidebar to focus on just one.</p>
            <?php endif; ?>
        </div>

        <!-- Pillar Banner (filtered view) -->
        <?php if ($selectedPillar): ?>
        <div class="pillar-banner" style="border-color:<?= htmlspecialchars($selectedPillar['color']) ?>44">
            <div class="pillar-banner-icon"><?= htmlspecialchars($selectedPillar['icon']) ?></div>
            <div class="pillar-banner-meta">
                <div class="pillar-banner-label" style="color:<?= htmlspecialchars($selectedPillar['color']) ?>">
                    Pillar <?= $selectedPillar['sort_order'] ?>
                </div>
                <div class="pillar-banner-name"><?= htmlspecialchars($selectedPillar['label']) ?></div>
                <div class="pillar-banner-desc"><?= htmlspecialchars($selectedPillar['description']) ?></div>
            </div>
            <a href="admin_questions.php" class="pillar-banner-back">← All Pillars</a>
        </div>
        <?php endif; ?>

        <!-- Alerts -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><span>✓</span> <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><span>⚠</span> <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <form id="questionForm" action="save_questions.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($selectedPillarId): ?>
                <input type="hidden" name="pillar_id" value="<?= $selectedPillarId ?>">
            <?php endif; ?>

            <?php
            $hasAnyQuestions = false;
            foreach ($renderPillarIds as $pid):
                $pillar      = $pillarMap[$pid] ?? null;
                if (!$pillar) continue;

                // Only show pillars that have questions (skip empty ones in "all" view)
                $pillarTree = $tree[$pid] ?? [];
                if (empty($pillarTree) && !$selectedPillar) continue;

                $hasAnyQuestions = true;
                $totalQForPillar = array_sum(array_map('count', $pillarTree));
                $catCount        = count($pillarTree);
                $isOpen          = ($selectedPillar !== null); // auto-open in filtered view
            ?>
            <div class="pillar-block <?= $isOpen ? 'open' : '' ?>" id="pillar-<?= $pid ?>">
                <div class="pillar-block-header" onclick="togglePillar(this)">
                    <span class="pillar-header-icon"><?= htmlspecialchars($pillar['icon']) ?></span>
                    <span class="pillar-header-name"><?= htmlspecialchars($pillar['label']) ?></span>
                    <div class="pillar-header-chips">
                        <span class="chip chip-q"><?= $totalQForPillar ?> question<?= $totalQForPillar !== 1 ? 's' : '' ?></span>
                        <span class="chip chip-cat"><?= $catCount ?> category<?= $catCount !== 1 ? 'ies' : 'y' ?></span>
                    </div>
                    <span class="pillar-toggle">▾</span>
                </div>

                <div class="pillar-block-body">
                    <?php if (empty($pillarTree)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>No questions yet</h3>
                            <p>No questions are assigned to this pillar.<br>Assign questions by setting <code>pillar_id = <?= $pid ?></code> in the database.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pillarTree as $categoryField => $catQuestions):
                            $catTitle = fieldToTitle($categoryField);
                            $isOnlyCat = $catCount === 1;
                        ?>
                        <div class="cat-section <?= $isOnlyCat ? 'open' : '' ?>">
                            <div class="cat-section-header" onclick="toggleCat(this)">
                                <span class="cat-section-title"><?= htmlspecialchars($catTitle) ?></span>
                                <span class="cat-section-field"><?= htmlspecialchars($categoryField) ?></span>
                                <span class="cat-section-toggle">▾</span>
                            </div>
                            <div class="cat-section-body">
                                <?php $qi = 0; foreach ($catQuestions as $q): $qi++; ?>
                                    <?php if ($qi > 1): ?><hr class="q-divider"><?php endif; ?>
                                    <div class="question-row">
                                        <div class="q-label"><?= strtoupper($q['question_key']) ?></div>
                                        <div class="q-field">
                                            <div class="q-meta">
                                                <span><?= htmlspecialchars($categoryField) ?></span>
                                                <span class="dot"></span>
                                                <span><?= htmlspecialchars($q['question_key']) ?></span>
                                                <span class="dot"></span>
                                                <span>id #<?= (int)$q['id'] ?></span>
                                            </div>
                                            <textarea
                                                class="q-input"
                                                name="questions[<?= (int)$q['id'] ?>]"
                                                data-original="<?= htmlspecialchars($q['question_text']) ?>"
                                                rows="2"
                                                maxlength="500"
                                                oninput="handleInput(this)"
                                            ><?= htmlspecialchars($q['question_text']) ?></textarea>
                                            <div class="char-count" id="cc-<?= (int)$q['id'] ?>">
                                                <?= strlen($q['question_text']) ?> / 500
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Add Question Button -->
                            <button type="button"
                                class="add-question-btn"
                                data-category="<?= htmlspecialchars($categoryField) ?>"
                                data-pillar="<?= $pid ?>"
                                data-title="<?= htmlspecialchars($catTitle) ?>">
                                <span class="plus">＋</span> Add Question
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!$hasAnyQuestions): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No questions found</h3>
                    <p>No questions are assigned to any pillar yet.</p>
                </div>
            <?php else: ?>
            <!-- Sticky Save Bar -->
            <div class="save-bar">
                <button type="submit" class="btn btn-primary">💾 &nbsp;Save All Changes</button>
                <button type="button" class="btn btn-secondary" onclick="revertAll()">↺ &nbsp;Revert</button>
                <span class="changes-indicator" id="changeIndicator">No unsaved changes</span>
            </div>
            <?php endif; ?>

        </form>
    </main>
</div>

<!-- ── Add Question Modal ── -->
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-title">Add New Question</div>
                <div class="modal-subtitle" id="modalSubtitle"></div>
            </div>
            <button class="modal-close" onclick="closeAddModal()">✕</button>
        </div>
        <div class="modal-meta">
            <span class="modal-meta-chip" id="modalPillarChip"></span>
            <span class="modal-meta-chip" id="modalCategoryChip"></span>
        </div>
        <div class="modal-error" id="modalError"></div>
        <div class="modal-field">
            <label class="modal-label" for="newQuestionText">Question Text</label>
            <textarea class="modal-input" id="newQuestionText" rows="3" maxlength="500"
                placeholder="Write your question here..."></textarea>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="modalSaveBtn" onclick="saveNewQuestion()">＋ Add Question</button>
        </div>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

    // ── Pillar accordion ─────────────────────────────────────────
    function togglePillar(header) {
        header.closest('.pillar-block').classList.toggle('open');
    }

    // Auto-open first pillar block
    document.querySelector('.pillar-block:not(.open)')?.classList.add('open');

    // ── Category sub-accordion ───────────────────────────────────
    function toggleCat(header) {
        header.closest('.cat-section').classList.toggle('open');
    }

    // ── Change tracking ──────────────────────────────────────────
    let changedCount = 0;

    function handleInput(textarea) {
        const id       = textarea.name.match(/\d+/)[0];
        const len      = textarea.value.length;
        const cc       = document.getElementById('cc-' + id);
        const changed  = textarea.value !== textarea.dataset.original;

        if (cc) {
            cc.textContent = len + ' / 500';
            cc.classList.toggle('warn', len > 400);
        }
        textarea.classList.toggle('changed', changed);
        updateChangeCount();
    }

    function updateChangeCount() {
        changedCount = document.querySelectorAll('.q-input.changed').length;
        const el = document.getElementById('changeIndicator');
        if (!el) return;
        if (changedCount > 0) {
            el.textContent = `${changedCount} unsaved change${changedCount > 1 ? 's' : ''}`;
            el.classList.add('has-changes');
        } else {
            el.textContent = 'No unsaved changes';
            el.classList.remove('has-changes');
        }
    }

    function revertAll() {
        document.querySelectorAll('.q-input').forEach(ta => {
            ta.value = ta.dataset.original;
            ta.classList.remove('changed');
            const id = ta.name.match(/\d+/)[0];
            const cc = document.getElementById('cc-' + id);
            if (cc) cc.textContent = ta.value.length + ' / 500';
        });
        updateChangeCount();
    }

    // ── Warn on unsaved leave ────────────────────────────────────
    function beforeUnloadHandler(e) {
        if (changedCount > 0) { e.preventDefault(); e.returnValue = ''; }
    }
    window.addEventListener('beforeunload', beforeUnloadHandler);
    document.getElementById('questionForm')?.addEventListener('submit', () => {
        window.removeEventListener('beforeunload', beforeUnloadHandler);
    });

    // ── Add Question Button delegation (uses data-* instead of onclick) ─────
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.add-question-btn');
        if (btn) {
            openAddModal(btn.dataset.category, parseInt(btn.dataset.pillar), btn.dataset.title);
        }
    });

    // ── Add Question Modal ───────────────────────────────────────
    let _modalCategoryField = '';
    let _modalPillarId      = 0;

    function openAddModal(categoryField, pillarId, categoryTitle) {
        _modalCategoryField = categoryField;
        _modalPillarId      = pillarId;

        document.getElementById('modalSubtitle').textContent   = 'Adding to: ' + categoryTitle;
        document.getElementById('modalPillarChip').textContent  = 'pillar_id: ' + pillarId;
        document.getElementById('modalCategoryChip').textContent = categoryField;
        document.getElementById('newQuestionText').value        = '';
        document.getElementById('modalError').classList.remove('show');
        document.getElementById('modalSaveBtn').disabled        = false;
        document.getElementById('modalSaveBtn').textContent     = '＋ Add Question';

        document.getElementById('addModal').classList.add('open');
        setTimeout(() => document.getElementById('newQuestionText').focus(), 100);
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('open');
    }

    // Close on backdrop click
    document.getElementById('addModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAddModal();
    });

    async function saveNewQuestion() {
        const text = document.getElementById('newQuestionText').value.trim();
        const errEl = document.getElementById('modalError');
        const btn   = document.getElementById('modalSaveBtn');

        if (!text) {
            errEl.textContent = 'Please enter a question before saving.';
            errEl.classList.add('show');
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Saving…';
        errEl.classList.remove('show');

        try {
            const res  = await fetch('add_question.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    pillar_id:      _modalPillarId,
                    category_field: _modalCategoryField,
                    question_text:  text,
                    csrf_token:     CSRF_TOKEN
                })
            });
            const data = await res.json();

            if (!data.success) {
                errEl.textContent = data.message || 'Failed to save question.';
                errEl.classList.add('show');
                btn.disabled    = false;
                btn.textContent = '＋ Add Question';
                return;
            }

            // ── Inject the new question row into the DOM ──────────────
            const newId  = data.question.id;
            const newKey = data.question.question_key;

            // Find the cat-section-body using data-* attributes
            let targetBody = null;
            document.querySelectorAll('.add-question-btn').forEach(b => {
                if (b.dataset.category === _modalCategoryField && parseInt(b.dataset.pillar) === _modalPillarId) {
                    targetBody = b.closest('.cat-section').querySelector('.cat-section-body');
                }
            });

            if (targetBody) {
                // Add divider if questions already exist
                const existingRows = targetBody.querySelectorAll('.question-row');
                if (existingRows.length > 0) {
                    const divider = document.createElement('hr');
                    divider.className = 'q-divider';
                    targetBody.insertBefore(divider, targetBody.querySelector('.add-question-btn'));
                }

                const row = document.createElement('div');
                row.className = 'question-row';
                row.innerHTML = `
                    <div class="q-label">${newKey.toUpperCase()}</div>
                    <div class="q-field">
                        <div class="q-meta">
                            <span>${_modalCategoryField}</span>
                            <span class="dot"></span>
                            <span>${newKey}</span>
                            <span class="dot"></span>
                            <span>id #${newId}</span>
                        </div>
                        <textarea
                            class="q-input changed"
                            name="questions[${newId}]"
                            data-original=""
                            rows="2"
                            maxlength="500"
                            oninput="handleInput(this)"
                        >${text.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
                        <div class="char-count" id="cc-${newId}">${text.length} / 500</div>
                    </div>`;

                targetBody.insertBefore(row, targetBody.querySelector('.add-question-btn'));

                // Update the pillar question count chip
                updatePillarChip(_modalPillarId);
            }

            closeAddModal();
            showToast('✓ Question added successfully');

        } catch (err) {
            errEl.textContent = 'Network error. Please try again.';
            errEl.classList.add('show');
            btn.disabled    = false;
            btn.textContent = '＋ Add Question';
        }
    }

    function updatePillarChip(pillarId) {
        const pillarBlock = document.getElementById('pillar-' + pillarId);
        if (!pillarBlock) return;
        const count = pillarBlock.querySelectorAll('.question-row').length;
        const chip  = pillarBlock.querySelector('.chip-q');
        if (chip) chip.textContent = count + ' question' + (count !== 1 ? 's' : '');
    }

    // ── Toast ────────────────────────────────────────────────────
    let _toastTimer;
    function showToast(msg) {
        let t = document.getElementById('aq-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'aq-toast';
            t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(12px);background:var(--surface);border:1px solid rgba(76,175,118,0.4);border-radius:10px;padding:12px 20px;font-size:0.87rem;font-weight:500;color:#4CAF76;box-shadow:0 8px 32px rgba(0,0,0,0.4);opacity:0;transition:opacity 0.2s,transform 0.2s;z-index:999;white-space:nowrap;pointer-events:none;';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.opacity = '1';
        t.style.transform = 'translateX(-50%) translateY(0)';
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(-50%) translateY(12px)';
        }, 2800);
    }
</script>
</body>
</html>
