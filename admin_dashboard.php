<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline';");

if (!isset($_SESSION['admin_authenticated'])) {
    header('Location: admin_login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$pdo = Database::connect();

// ── Resolve tenant ID (from session or DB fallback) ───────────────────────────
$tenantId = (int)($_SESSION['admin_tenant_id'] ?? 0);
if (!$tenantId && !empty($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $tenantId = (int)$stmt->fetchColumn();
    $_SESSION['admin_tenant_id'] = $tenantId;
}

// ── Tenant name ───────────────────────────────────────────────────────────────
$tenantName = 'Your Organisation';
if ($tenantId) {
    $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $tenantName = $stmt->fetchColumn() ?: 'Your Organisation';
}

// ── Pillars (global) ──────────────────────────────────────────────────────────
$pillars       = $pdo->query("SELECT * FROM pillars ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$activePillars = array_sum(array_column($pillars, 'is_active'));
$totalPillars  = count($pillars);

// ── Tenant-scoped stats ───────────────────────────────────────────────────────
$totalUsers = $tenantId
    ? (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?")->execute([$tenantId]) && 0
    : 0;

// Use proper fetch for scoped queries
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$totalUsers = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM total_scores ts
    JOIN users u ON u.id = ts.user_id
    WHERE u.tenant_id = ?
");
$stmt->execute([$tenantId]);
$totalSessions = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT AVG(ts.total_score) FROM total_scores ts
    JOIN users u ON u.id = ts.user_id
    WHERE u.tenant_id = ?
");
$stmt->execute([$tenantId]);
$avgScore = round((float)$stmt->fetchColumn(), 1);

// ── Questions count (global) ──────────────────────────────────────────────────
$totalQuestions = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

// ── Per-pillar stats ──────────────────────────────────────────────────────────
$pillarStats = [];
$stmt = $pdo->prepare("
    SELECT ts.pillar_id, AVG(ts.total_score) AS avg_score, COUNT(*) AS submissions
    FROM total_scores ts
    JOIN users u ON u.id = ts.user_id
    WHERE u.tenant_id = ?
    GROUP BY ts.pillar_id
");
$stmt->execute([$tenantId]);
foreach ($stmt->fetchAll() as $row) {
    $pillarStats[(int)$row['pillar_id']] = $row;
}

$pillarQCount = [];
foreach ($pdo->query("
    SELECT pillar_id, COUNT(*) AS cnt FROM questions
    WHERE pillar_id IS NOT NULL GROUP BY pillar_id
")->fetchAll() as $row) {
    $pillarQCount[(int)$row['pillar_id']] = (int)$row['cnt'];
}

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Dashboard · Happiness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0B0F17; --surface: #111720; --surface2: #161D29; --surface3: #1A2233;
            --border: rgba(255,255,255,0.07); --border2: rgba(255,255,255,0.04);
            --gold: #C9A84C; --gold-light: #E2C97E; --gold-dim: rgba(201,168,76,0.12);
            --gold-glow: rgba(201,168,76,0.20); --border-gold: rgba(201,168,76,0.28);
            --danger: #C94C4C; --green: #4CAF7D;
            --text: #F0EBE1; --muted: #4E5D72; --muted2: #8A9BB0; --radius: 14px;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
        body::before {
            content: ''; position: fixed; top: 0; right: 0;
            width: 700px; height: 700px;
            background: radial-gradient(circle at 80% 20%, rgba(201,168,76,0.04) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }
        .page-wrap { display: grid; grid-template-columns: 248px 1fr; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--surface); border-right: 1px solid var(--border);
            padding: 28px 0; position: sticky; top: 0; height: 100vh;
            overflow-y: auto; display: flex; flex-direction: column; z-index: 10;
        }
        .sidebar-brand { padding: 0 20px 20px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .brand-inner { display: flex; align-items: center; gap: 10px; }
        .brand-icon {
            width: 34px; height: 34px; background: var(--gold-dim); border: 1px solid var(--border-gold);
            border-radius: 9px; display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: var(--gold); flex-shrink: 0;
        }
        .brand-text .logo { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 600; color: var(--text); line-height: 1.2; }
        .brand-text .logo span { color: var(--gold); }
        .tenant-name {
            margin-top: 10px; font-size: 0.75rem; color: var(--muted2);
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: 6px; padding: 5px 10px; display: flex; align-items: center; gap: 6px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tenant-name span { color: var(--gold); font-size: 0.7rem; }
        .admin-badge {
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: 8px; font-size: 0.62rem; font-weight: 600;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--muted2); background: var(--gold-dim);
            border: 1px solid var(--border-gold); padding: 3px 9px; border-radius: 20px;
        }
        .sidebar-nav { padding: 0 10px; flex: 1; }
        .nav-group { margin-bottom: 24px; }
        .nav-group-label {
            font-size: 0.62rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--muted); padding: 0 10px; margin-bottom: 6px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 9px;
            padding: 9px 10px; border-radius: 9px; color: var(--muted2);
            text-decoration: none; font-size: 0.855rem; transition: all 0.16s; margin-bottom: 2px;
        }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .nav-icon {
            width: 24px; height: 24px; border-radius: 6px; background: var(--surface2);
            display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0;
        }
        .nav-item.active .nav-icon { background: var(--gold); color: #0B0F17; }
        .sidebar-footer { padding: 16px 20px 0; border-top: 1px solid var(--border); margin-top: auto; }
        .logout-link {
            display: flex; align-items: center; gap: 7px;
            font-size: 0.82rem; color: var(--muted); text-decoration: none; padding: 6px 0; transition: color 0.16s;
        }
        .logout-link:hover { color: var(--danger); }

        /* ── Main ── */
        .main { padding: 48px 52px 80px; position: relative; z-index: 1; }
        .hero { margin-bottom: 44px; }
        .hero-eyebrow { font-size: 0.78rem; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: var(--gold); margin-bottom: 10px; }
        .hero-title { font-family: 'Playfair Display', serif; font-size: 2.4rem; font-weight: 600; line-height: 1.18; }
        .hero-title span { color: var(--gold); }
        .hero-desc { margin-top: 12px; font-size: 0.9rem; color: var(--muted2); max-width: 520px; line-height: 1.6; }

        /* ── Stats grid ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 44px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 22px 24px;
        }
        .stat-label { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
        .stat-value { font-family: 'DM Mono', monospace; font-size: 2.1rem; font-weight: 500; color: var(--text); line-height: 1; }
        .stat-value .unit { font-size: 1rem; color: var(--muted2); }
        .stat-sub { font-size: 0.78rem; color: var(--muted2); margin-top: 6px; }

        /* ── Section ── */
        .section { margin-bottom: 40px; }
        .section-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px; }
        .section-icon {
            width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center;
            justify-content: center; font-size: 0.95rem; flex-shrink: 0;
        }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 600; }
        .section-desc { font-size: 0.82rem; color: var(--muted2); margin-top: 3px; }

        /* ── Pillars grid ── */
        .pillars-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .pillar-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 14px 16px; cursor: default;
            transition: border-color 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; gap: 7px;
        }
        .pillar-card--inactive { opacity: 0.55; }
        .pillar-card-top { display: flex; align-items: center; justify-content: space-between; }
        .pillar-icon { font-size: 1.1rem; }
        .pillar-badge { font-size: 0.62rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; padding: 2px 7px; border-radius: 20px; }
        .pillar-badge.active { background: rgba(76,175,125,0.12); color: #4CAF7D; border: 1px solid rgba(76,175,125,0.25); }
        .pillar-badge.inactive { background: var(--surface2); color: var(--muted2); border: 1px solid var(--border); }
        .pillar-name { font-family: 'Playfair Display', serif; font-size: 0.92rem; font-weight: 600; }
        .pillar-desc { font-size: 0.75rem; color: var(--muted2); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .pillar-stats-row { display: flex; flex-wrap: wrap; gap: 4px; }
        .pillar-stat-chip { font-size: 0.68rem; color: var(--muted2); background: var(--surface2); border: 1px solid var(--border); border-radius: 20px; padding: 1px 7px; }
        .pillar-bar { height: 2px; background: var(--surface2); border-radius: 4px; overflow: hidden; margin-top: 2px; }
        .pillar-bar-fill { height: 100%; border-radius: 4px; transition: width 0.4s ease; }
        .pillar-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 2px; }
        .active-toggle-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.74rem; color: var(--muted2); }
        .active-toggle-label input[type="checkbox"] { display: none; }
        .toggle-track {
            width: 32px; height: 18px; border-radius: 20px; background: var(--surface3);
            border: 1px solid var(--border); position: relative; transition: background 0.2s;
        }
        .toggle-track::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 12px; height: 12px; border-radius: 50%; background: var(--muted);
            transition: transform 0.2s, background 0.2s;
        }
        input[type="checkbox"]:checked + .toggle-track { background: rgba(76,175,125,0.15); border-color: #4CAF7D44; }
        input[type="checkbox"]:checked + .toggle-track::after { transform: translateX(14px); background: #4CAF7D; }
        .toggle-saving { opacity: 0.5; pointer-events: none; }
        .pillar-edit-cta { font-size: 0.78rem; font-weight: 600; color: var(--gold); text-decoration: none; opacity: 0.7; transition: opacity 0.15s; }
        .pillar-edit-cta:hover { opacity: 1; }

        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 28px; right: 28px; z-index: 9999;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 12px 20px; font-size: 0.85rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4); opacity: 0;
            transform: translateY(8px); transition: all 0.25s ease; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-color: rgba(76,175,125,0.4); color: #4CAF7D; }
        .toast.error   { border-color: rgba(201,76,76,0.4);  color: var(--danger); }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3A4558; border-radius: 4px; }

        /* ── Mobile Responsive ── */
        @media (max-width: 768px) {
            .page-wrap { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed; left: -260px; top: 0; height: 100vh;
                width: 248px; transition: left 0.25s ease; z-index: 100;
            }
            .sidebar.open { left: 0; }
            .sidebar-overlay {
                display: none; position: fixed; inset: 0;
                background: rgba(0,0,0,0.5); z-index: 99;
            }
            .sidebar-overlay.show { display: block; }
            .mobile-topbar {
                display: flex; align-items: center; justify-content: space-between;
                padding: 14px 20px; background: var(--surface);
                border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50;
            }
            .mobile-brand { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 600; }
            .mobile-brand span { color: var(--gold); }
            .hamburger {
                background: none; border: none; cursor: pointer;
                color: var(--text); font-size: 1.3rem; padding: 4px;
            }
            .main { padding: 24px 16px 60px; }
            .hero { margin-bottom: 28px; }
            .hero-title { font-size: 1.7rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 28px; }
            .stat-card { padding: 14px 16px; }
            .stat-value { font-size: 1.6rem; }
            .pillars-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .pillars-grid { grid-template-columns: 1fr; }
            .hero-title { font-size: 1.4rem; }
        }

        /* Hide mobile topbar on desktop */
        .mobile-topbar { display: none; }

        @media (min-width: 769px) {
            .sidebar { left: 0 !important; position: sticky; width: 248px; }
            .sidebar-overlay { display: none !important; }
            .mobile-topbar { display: none !important; }
            .page-wrap { grid-template-columns: 248px 1fr; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile topbar -->
<div class="mobile-topbar">
    <button class="hamburger" onclick="openSidebar()">☰</button>
    <div class="mobile-brand">Happiness <span>Audit</span></div>
    <div style="width:28px"></div>
</div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-inner">
                <div class="brand-icon">✦</div>
                <div class="brand-text">
                    <div class="logo">Happiness <span>Audit</span></div>
                </div>
            </div>
            <div class="tenant-name">
                <span>🏢</span>
                <?= htmlspecialchars($tenantName) ?>
            </div>
            <div class="admin-badge">⚙ Admin Panel</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-label">Admin</div>
                <a href="admin_dashboard.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="admin_users.php" class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> Users
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a href="admin_logout.php" class="logout-link">⟳ &nbsp;Sign out</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">
        <div class="hero">
            <div class="hero-eyebrow">⚙ Admin Dashboard</div>
            <h1 class="hero-title">Platform <span>Overview</span></h1>
            <p class="hero-desc">Manage assessment pillars, questions, and review aggregated results across all users in <strong><?= htmlspecialchars($tenantName) ?></strong>.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-sub">registered accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Assessments Taken</div>
                <div class="stat-value"><?= $totalSessions ?></div>
                <div class="stat-sub">total submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg. Score</div>
                <div class="stat-value"><?= $avgScore ?><span class="unit">/25</span></div>
                <div class="stat-sub">across all submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Pillars</div>
                <div class="stat-value" id="stat-active-pillars"><?= $activePillars ?><span class="unit">/<?= $totalPillars ?></span></div>
                <div class="stat-sub" id="stat-coming-soon"><?= $totalPillars - $activePillars ?> coming soon</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Questions</div>
                <div class="stat-value"><?= $totalQuestions ?></div>
                <div class="stat-sub">across all pillars</div>
            </div>
        </div>

        <!-- Life Dimensions -->
        <section class="section">
            <div class="section-header">
                <div class="section-icon" style="background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.25)">◈</div>
                <div class="section-meta">
                    <h2 class="section-title">Life Dimensions</h2>
                    <p class="section-desc"><?= $totalPillars ?> pillars — <?= $activePillars ?> active, <?= $totalPillars - $activePillars ?> coming soon. Click any active pillar to edit its questions.</p>
                </div>
            </div>
            <div class="pillars-grid">
                <?php foreach ($pillars as $pillar):
                    $active   = (bool)$pillar['is_active'];
                    $color    = $pillar['color'] ?? '#C9A84C';
                    $pid      = (int)$pillar['id'];
                    $avgP     = isset($pillarStats[$pid]) ? round((float)$pillarStats[$pid]['avg_score'], 1) : null;
                    $subCount = isset($pillarStats[$pid]) ? (int)$pillarStats[$pid]['submissions'] : 0;
                    $qCount   = $pillarQCount[$pid] ?? 0;
                    $pct      = $avgP !== null ? ($avgP / 25 * 100) : 0;
                ?>
                <div class="pillar-card <?= $active ? '' : 'pillar-card--inactive' ?>"
                     id="pillar-card-<?= $pid ?>" data-pid="<?= $pid ?>"
                     data-color="<?= htmlspecialchars($color) ?>" data-active="<?= $active ? '1' : '0' ?>">
                    <div class="pillar-card-top">
                        <span class="pillar-icon"><?= htmlspecialchars($pillar['icon']) ?></span>
                        <span class="pillar-badge <?= $active ? 'active' : 'inactive' ?>" id="badge-<?= $pid ?>">
                            <?= $active ? 'Active' : 'Coming soon' ?>
                        </span>
                    </div>
                    <div class="pillar-name"><?= htmlspecialchars($pillar['label']) ?></div>
                    <div class="pillar-desc"><?= htmlspecialchars($pillar['description']) ?></div>
                    <div class="pillar-stats-row" id="stats-<?= $pid ?>">
                        <?php if ($active): ?>
                            <span class="pillar-stat-chip">avg <?= $avgP !== null ? $avgP . '/25' : '—' ?></span>
                            <span class="pillar-stat-chip"><?= $subCount ?> <?= $subCount === 1 ? 'submission' : 'submissions' ?></span>
                        <?php endif; ?>
                        <span class="pillar-stat-chip"><?= $qCount ?> <?= $qCount === 1 ? 'question' : 'questions' ?></span>
                    </div>
                    <div class="pillar-bar">
                        <div class="pillar-bar-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($color) ?>;opacity:<?= $avgP ? 1 : 0.18 ?>"></div>
                    </div>
                    <div class="pillar-card-footer">
                        <label class="active-toggle-label" id="toggle-label-<?= $pid ?>" onclick="event.stopPropagation()">
                            <input type="checkbox" id="toggle-<?= $pid ?>" <?= $active ? 'checked' : '' ?> onchange="togglePillar(<?= $pid ?>, this)">
                            <span class="toggle-track"></span>
                            <span id="toggle-text-<?= $pid ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<div class="toast" id="toast"></div>
<script>
function openSidebar() {
    document.querySelector('.sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
}
function closeSidebar() {
    document.querySelector('.sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}

const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

document.querySelectorAll('.pillar-card').forEach(card => {
    const color = card.dataset.color;
    if (!color) return;
    card.addEventListener('mouseenter', () => {
        card.style.borderColor = color + '55';
        card.style.boxShadow   = `0 8px 32px rgba(0,0,0,0.3), 0 0 0 1px ${color}22`;
    });
    card.addEventListener('mouseleave', () => {
        card.style.borderColor = '';
        card.style.boxShadow   = '';
    });
});

let toastTimer;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

async function togglePillar(pid, checkbox) {
    const label    = document.getElementById(`toggle-label-${pid}`);
    const badge    = document.getElementById(`badge-${pid}`);
    const toggleTx = document.getElementById(`toggle-text-${pid}`);
    const card     = document.getElementById(`pillar-card-${pid}`);
    const newState = checkbox.checked ? 1 : 0;
    label.classList.add('toggle-saving');
    try {
        const res  = await fetch('toggle_pillar.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `pillar_id=${pid}&is_active=${newState}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unknown error');
        const isActive = newState === 1;
        card.classList.toggle('pillar-card--inactive', !isActive);
        badge.textContent = isActive ? 'Active' : 'Coming soon';
        badge.className   = `pillar-badge ${isActive ? 'active' : 'inactive'}`;
        toggleTx.textContent = isActive ? 'Active' : 'Inactive';
        const activeCount = document.querySelectorAll('.pillar-card:not(.pillar-card--inactive)').length;
        const totalCount  = document.querySelectorAll('.pillar-card').length;
        const statActive  = document.getElementById('stat-active-pillars');
        if (statActive) statActive.textContent = activeCount;
        const statSoon = document.getElementById('stat-coming-soon');
        if (statSoon) statSoon.textContent = `${totalCount - activeCount} coming soon`;
        showToast(isActive ? `✓ "${data.label}" is now active` : `"${data.label}" set to coming soon`);
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast('⚠ Could not update pillar: ' + err.message, 'error');
    } finally {
        label.classList.remove('toggle-saving');
    }
}
</script>
</body>
</html>
