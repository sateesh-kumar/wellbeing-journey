<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/assets/config.php';

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

if (!isset($_SESSION['superadmin_authenticated'])) {
    header('Location: superadmin_login.php');
    exit;
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$pdo = getDBConnection();

// ── Fetch all pillars ──────────────────────────────────────────────────────────
$pillars       = $pdo->query("SELECT * FROM pillars ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$activePillars = array_sum(array_column($pillars, 'is_active'));
$totalPillars  = count($pillars);

// ── Aggregate stats ────────────────────────────────────────────────────────────
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM total_scores")->fetchColumn();
$avgScore      = round((float)$pdo->query("SELECT AVG(total_score) FROM total_scores")->fetchColumn(), 1);
$totalQuestions = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();

// ── Per-pillar stats ───────────────────────────────────────────────────────────
$pillarStats = [];
foreach ($pdo->query("
    SELECT pillar_id,
           AVG(total_score) AS avg_score,
           COUNT(*)         AS submissions
    FROM   total_scores
    GROUP  BY pillar_id
")->fetchAll() as $row) {
    $pillarStats[(int)$row['pillar_id']] = $row;
}

$pillarQCount = [];
foreach ($pdo->query("
    SELECT pillar_id, COUNT(*) AS cnt
    FROM   questions
    WHERE  pillar_id IS NOT NULL
    GROUP  BY pillar_id
")->fetchAll() as $row) {
    $pillarQCount[(int)$row['pillar_id']] = (int)$row['cnt'];
}

// ── Tenants ────────────────────────────────────────────────────────────────────
$tenants     = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalTenants = count($tenants);

// User count per tenant
$tenantUserCounts = [];
foreach ($pdo->query("
    SELECT tenant_id, COUNT(*) AS cnt
    FROM   users
    WHERE  tenant_id IS NOT NULL
    GROUP  BY tenant_id
")->fetchAll() as $row) {
    $tenantUserCounts[(int)$row['tenant_id']] = (int)$row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — Dashboard · Happiness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0B0F17;
            --surface:     #111720;
            --surface2:    #161D29;
            --surface3:    #1A2233;
            --border:      rgba(255,255,255,0.07);
            --border2:     rgba(255,255,255,0.04);
            --gold:        #C9A84C;
            --gold-light:  #E2C97E;
            --gold-dim:    rgba(201,168,76,0.12);
            --gold-glow:   rgba(201,168,76,0.20);
            --border-gold: rgba(201,168,76,0.28);
            --danger:      #C94C4C;
            --green:       #4CAF7D;
            --text:        #F0EBE1;
            --muted:       #4E5D72;
            --muted2:      #8A9BB0;
            --radius:      14px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed; top: 0; right: 0;
            width: 700px; height: 700px;
            background: radial-gradient(circle at 80% 20%, rgba(201,168,76,0.04) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }

        /* ── Layout ── */
        .page-wrap { display: grid; grid-template-columns: 248px 1fr; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 28px 0;
            position: sticky; top: 0; height: 100vh;
            overflow-y: auto;
            display: flex; flex-direction: column; z-index: 10;
        }
        .sidebar-brand { padding: 0 20px 24px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .brand-inner { display: flex; align-items: center; gap: 10px; }
        .brand-icon {
            width: 34px; height: 34px;
            background: var(--gold-dim); border: 1px solid var(--border-gold); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: var(--gold); flex-shrink: 0;
        }
        .brand-text .logo { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 600; color: var(--text); line-height: 1.2; }
        .brand-text .logo span { color: var(--gold); }
        .admin-badge {
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: 8px; font-size: 0.62rem; font-weight: 600;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--gold); background: var(--gold-dim);
            border: 1px solid var(--border-gold); padding: 3px 9px; border-radius: 20px;
        }
        .sidebar-nav { padding: 0 10px; flex: 1; }
        .nav-group { margin-bottom: 24px; }
        .nav-group-label {
            font-size: 0.62rem; font-weight: 600;
            letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--muted); padding: 0 10px; margin-bottom: 6px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 9px;
            padding: 9px 10px; border-radius: 9px;
            color: var(--muted2); text-decoration: none;
            font-size: 0.855rem; transition: all 0.16s; margin-bottom: 2px;
        }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .nav-icon {
            width: 24px; height: 24px; border-radius: 6px;
            background: var(--surface2);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; flex-shrink: 0;
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

        /* ── Hero ── */
        .hero { margin-bottom: 44px; }
        .hero-eyebrow { font-size: 0.78rem; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: var(--gold); margin-bottom: 10px; }
        .hero-title { font-family: 'Playfair Display', serif; font-size: clamp(1.7rem, 3vw, 2.4rem); font-weight: 700; line-height: 1.15; letter-spacing: -0.02em; color: var(--text); margin-bottom: 12px; }
        .hero-sub { font-size: 0.92rem; color: var(--muted2); max-width: 500px; line-height: 1.65; }

        /* ── Stats Row ── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 52px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 22px; transition: border-color 0.2s; }
        .stat-card:hover { border-color: var(--border-gold); }
        .stat-label { font-size: 0.72rem; font-weight: 500; letter-spacing: 0.09em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
        .stat-value { font-family: 'Playfair Display', serif; font-size: 1.9rem; font-weight: 700; line-height: 1; color: var(--text); margin-bottom: 4px; }
        .stat-value .unit { font-size: 1rem; color: var(--muted); font-weight: 400; }
        .stat-sub { font-size: 0.76rem; color: var(--muted); }

        /* ── Section ── */
        .section { margin-bottom: 52px; }
        .section-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid var(--border); }
        .section-header-left { display: flex; align-items: flex-start; gap: 14px; }
        .section-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; margin-top: 2px; }
        .section-meta { flex: 1; }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 600; color: var(--text); margin-bottom: 4px; }
        .section-desc { font-size: 0.85rem; color: var(--muted2); line-height: 1.55; }

        /* ── Pillar Cards Grid ── */
        .pillars-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        .pillar-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 22px;
            display: flex; flex-direction: column; gap: 10px;
            transition: border-color 0.2s, transform 0.15s, box-shadow 0.2s;
            position: relative; overflow: hidden; min-height: 195px;
            text-decoration: none; color: var(--text);
        }
        .pillar-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,0.25); }
        .pillar-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .pillar-icon { font-size: 1.55rem; line-height: 1; }
        .pillar-badge { font-size: 0.7rem; font-weight: 600; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
        .pillar-badge.active   { background: rgba(76,175,125,0.12); color: var(--green); border: 1px solid rgba(76,175,125,0.28); }
        .pillar-badge.inactive { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
        .pillar-name { font-size: 0.95rem; font-weight: 600; color: var(--text); line-height: 1.3; }
        .pillar-desc { font-size: 0.8rem; color: var(--muted2); line-height: 1.5; flex: 1; }
        .pillar-stats-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .pillar-stat-chip {
            font-size: 0.72rem; font-weight: 500; font-family: 'DM Mono', monospace;
            color: var(--muted2); background: var(--surface2);
            border: 1px solid var(--border2); padding: 3px 8px; border-radius: 6px;
        }
        .pillar-bar { height: 3px; border-radius: 99px; background: var(--surface2); overflow: hidden; }
        .pillar-bar-fill { height: 100%; border-radius: 99px; transition: width 1s cubic-bezier(0.16,1,0.3,1); }
        .pillar-card--inactive { opacity: 0.55; }
        .pillar-card--inactive:hover { opacity: 0.8; }
        .pillar-edit-cta {
            font-size: 0.78rem; font-weight: 600; color: var(--gold); letter-spacing: 0.04em;
            text-decoration: none; transition: color 0.18s;
        }
        .pillar-edit-cta:hover { color: var(--gold-light); }

        /* ── Active Toggle ── */
        .pillar-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 4px; }
        .active-toggle-label {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; user-select: none;
            font-size: 0.8rem; font-weight: 500; color: var(--muted2); transition: color 0.18s;
        }
        .active-toggle-label:hover { color: var(--text); }
        .active-toggle-label input[type="checkbox"] { display: none; }
        .toggle-track {
            width: 36px; height: 20px; border-radius: 99px;
            background: var(--surface3); border: 1px solid var(--border);
            position: relative; transition: background 0.22s, border-color 0.22s; flex-shrink: 0;
        }
        .toggle-track::after {
            content: '';
            position: absolute; top: 3px; left: 3px;
            width: 12px; height: 12px; border-radius: 50%;
            background: var(--muted); transition: transform 0.22s, background 0.22s;
        }
        input:checked ~ .toggle-track { background: var(--green); border-color: rgba(76,175,125,0.5); }
        input:checked ~ .toggle-track::after { transform: translateX(16px); background: #fff; }
        .toggle-saving { opacity: 0.5; pointer-events: none; }

        /* ── Tenants Table ── */
        .tenants-table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .tenants-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .tenants-table thead tr {
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
        }
        .tenants-table th {
            padding: 12px 18px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .tenants-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border2);
            color: var(--text);
            vertical-align: middle;
        }
        .tenants-table tbody tr:last-child td { border-bottom: none; }
        .tenants-table tbody tr:hover td { background: rgba(255,255,255,0.02); }
        .tenant-name-cell { font-weight: 600; }
        .tenant-slug-chip {
            font-family: 'DM Mono', monospace;
            font-size: 0.76rem;
            color: var(--muted2);
            background: var(--surface2);
            border: 1px solid var(--border2);
            padding: 2px 8px; border-radius: 5px;
        }
        .tenant-status { font-size: 0.72rem; font-weight: 600; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
        .tenant-status.active   { background: rgba(76,175,125,0.12); color: var(--green); border: 1px solid rgba(76,175,125,0.28); }
        .tenant-status.inactive { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
        .tenant-date { font-size: 0.8rem; color: var(--muted2); font-family: 'DM Mono', monospace; }
        .tenant-users { font-family: 'DM Mono', monospace; color: var(--muted2); font-size: 0.85rem; }
        .user-count-link {
            font-family: 'DM Mono', monospace; font-size: 0.85rem;
            color: var(--gold); text-decoration: none;
            border-bottom: 1px dashed rgba(201,168,76,0.4);
            transition: color 0.16s, border-color 0.16s;
        }
        .user-count-link:hover { color: var(--gold-light); border-color: var(--gold-light); }
        .empty-tenants {
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .empty-tenants .empty-icon { font-size: 2rem; margin-bottom: 10px; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 9px 18px; border-radius: 8px;
            font-size: 0.85rem; font-weight: 600;
            cursor: pointer; transition: all 0.18s ease;
            border: none; font-family: 'DM Sans', sans-serif; text-decoration: none;
        }
        .btn-gold { background: var(--gold); color: #0B0F17; }
        .btn-gold:hover { background: var(--gold-light); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,168,76,0.3); }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
            opacity: 0; pointer-events: none;
            transition: opacity 0.2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 36px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
            transform: translateY(16px) scale(0.98);
            transition: transform 0.22s ease;
        }
        .modal-overlay.open .modal { transform: translateY(0) scale(1); }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
        .modal-icon {
            width: 44px; height: 44px; border-radius: 11px;
            background: var(--gold-dim); border: 1px solid var(--border-gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 12px;
        }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 600; color: var(--text); margin-bottom: 4px; }
        .modal-subtitle { font-size: 0.82rem; color: var(--muted2); }
        .modal-close {
            background: none; border: none; color: var(--muted); font-size: 1.2rem;
            cursor: pointer; padding: 4px; transition: color 0.16s; line-height: 1;
        }
        .modal-close:hover { color: var(--text); }
        .form-field { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted2); margin-bottom: 6px; letter-spacing: 0.04em; }
        .form-label span { color: var(--danger); margin-left: 2px; }
        .form-input {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 14px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
        }
        .form-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12); }
        .form-input::placeholder { color: var(--muted); }
        .form-hint { font-size: 0.75rem; color: var(--muted); margin-top: 5px; }
        .modal-error {
            background: rgba(201,76,76,0.10);
            border: 1px solid rgba(201,76,76,0.3);
            color: var(--danger);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.84rem;
            margin-bottom: 16px;
            display: none;
        }
        .modal-actions { display: flex; gap: 10px; margin-top: 24px; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted2);
            flex: 1;
        }
        .btn-outline:hover { border-color: var(--muted2); color: var(--text); }
        .btn-submit { flex: 2; }
        .btn-loading { opacity: 0.6; pointer-events: none; }

        /* ── Toast ── */
        .toast {
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(12px);
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 12px 20px;
            font-size: 0.87rem; font-weight: 500; color: var(--text);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            opacity: 0; transition: opacity 0.2s, transform 0.2s;
            z-index: 999; white-space: nowrap; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.error { border-color: rgba(201,76,76,0.4); color: var(--danger); }

        /* ── Action Buttons ── */
        .tenant-actions { white-space: nowrap; }
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 7px;
            border: 1px solid var(--border); background: var(--surface2);
            color: var(--muted2); font-size: 0.85rem;
            cursor: pointer; transition: all 0.16s; margin-right: 4px;
        }
        .action-btn:hover { color: var(--text); border-color: var(--muted2); }
        .edit-btn:hover { border-color: var(--gold); color: var(--gold); background: var(--gold-dim); }
        .delete-btn:hover { border-color: rgba(201,76,76,0.5); color: var(--danger); background: rgba(201,76,76,0.1); }

        /* ── Confirm Delete Modal ── */
        .modal-danger .modal-icon { background: rgba(201,76,76,0.12); border-color: rgba(201,76,76,0.3); }
        .btn-danger { background: var(--danger); color: #fff; flex: 2; }
        .btn-danger:hover { background: #d96060; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,76,76,0.3); }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--muted); border-radius: 4px; }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .page-wrap { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; flex-direction: row; flex-wrap: wrap; padding: 14px; gap: 12px; }
            .sidebar-brand { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
            .sidebar-footer { border-top: none; padding-top: 0; margin-left: auto; align-self: center; }
            .sidebar-nav { display: none; }
            .main { padding: 28px 20px 60px; }
        }
        @media (max-width: 540px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .pillars-grid { grid-template-columns: 1fr; }
            .tenants-table th:nth-child(3),
            .tenants-table td:nth-child(3) { display: none; }
            .tenants-table th:nth-child(7),
            .tenants-table td:nth-child(7) { display: none; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-inner">
                <div class="brand-icon">✦</div>
                <div class="brand-text">
                    <div class="logo">Happiness <span>Audit</span></div>
                </div>
            </div>
            <div class="admin-badge">👑 Super Admin</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-label">Super Admin</div>
                <a href="superadmin_dashboard.php" class="nav-item active">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <a href="#tenants-section" class="nav-item">
                    <span class="nav-icon">🏢</span> Tenants
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="superadmin_logout.php" class="logout-link">⎋ &nbsp;Sign out</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">

        <!-- Hero -->
        <div class="hero">
            <p class="hero-eyebrow">👑 Super Admin Dashboard</p>
            <h1 class="hero-title">Platform <span style="color:var(--gold)">Control Centre</span></h1>
            <p class="hero-sub">Manage tenants, assessment pillars, questions, and review aggregated results across all organizations.</p>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Tenants</div>
                <div class="stat-value" id="stat-total-tenants"><?= number_format($totalTenants) ?></div>
                <div class="stat-sub">organizations</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-sub">registered accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Assessments Taken</div>
                <div class="stat-value"><?= number_format($totalSessions) ?></div>
                <div class="stat-sub">total submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg. Score</div>
                <div class="stat-value"><?= $avgScore ?: '—' ?><span class="unit"><?= $avgScore ? '/25' : '' ?></span></div>
                <div class="stat-sub">across all submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Pillars</div>
                <div class="stat-value"><?= $activePillars ?><span class="unit">/<?= $totalPillars ?></span></div>
                <div class="stat-sub" id="stat-coming-soon"><?= $totalPillars - $activePillars ?> coming soon</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Questions</div>
                <div class="stat-value"><?= $totalQuestions ?></div>
                <div class="stat-sub">across all pillars</div>
            </div>
        </div>

        <!-- ── Tenants ── -->
        <section class="section" id="tenants-section">
            <div class="section-header">
                <div class="section-header-left">
                    <div class="section-icon" style="background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.25)">🏢</div>
                    <div class="section-meta">
                        <h2 class="section-title">Tenants / Organizations</h2>
                        <p class="section-desc">
                            <span id="tenant-count"><?= $totalTenants ?></span> organization<?= $totalTenants !== 1 ? 's' : '' ?> registered on the platform.
                        </p>
                    </div>
                </div>
                <button class="btn btn-gold" onclick="openAddTenant()">
                    + Add Tenant
                </button>
            </div>

            <div class="tenants-table-wrap">
                <?php if (empty($tenants)): ?>
                    <div class="empty-tenants">
                        <div class="empty-icon">🏢</div>
                        <div>No tenants yet. Add your first organization above.</div>
                    </div>
                <?php else: ?>
                <table class="tenants-table" id="tenants-table">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Slug</th>
                            <th>Contact Email</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tenants-tbody">
                        <?php foreach ($tenants as $t): ?>
                        <tr id="tenant-row-<?= $t['id'] ?>">
                            <td class="tenant-name-cell"><?= htmlspecialchars($t['name']) ?></td>
                            <td><span class="tenant-slug-chip"><?= htmlspecialchars($t['slug']) ?></span></td>
                            <td><?= !empty($t['email']) ? htmlspecialchars($t['email']) : '<span style="color:var(--muted)">—</span>' ?></td>
                            <td class="tenant-users">
                                <a href="superadmin_tenant_users.php?tenant_id=<?= (int)$t['id'] ?>" class="user-count-link">
                                    <?= $tenantUserCounts[$t['id']] ?? 0 ?>
                                </a>
                            </td>
                            <td>
                                <span class="tenant-status <?= $t['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="tenant-date"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                            <td class="tenant-actions">
                                <button class="action-btn edit-btn"
                                    data-id="<?= (int)$t['id'] ?>"
                                    data-name="<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>"
                                    data-slug="<?= htmlspecialchars($t['slug'], ENT_QUOTES) ?>"
                                    data-email="<?= htmlspecialchars($t['email'] ?? '', ENT_QUOTES) ?>"
                                    title="Edit tenant">✎</button>
                                <button class="action-btn delete-btn"
                                    data-id="<?= (int)$t['id'] ?>"
                                    data-name="<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>"
                                    title="Delete tenant">✕</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── Life Dimensions (Pillars) ── -->
        <section class="section">
            <div class="section-header">
                <div class="section-header-left">
                    <div class="section-icon" style="background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.25)">◈</div>
                    <div class="section-meta">
                        <h2 class="section-title">Life Dimensions</h2>
                        <p class="section-desc">
                            <?= $totalPillars ?> pillars — <?= $activePillars ?> active, <?= $totalPillars - $activePillars ?> coming soon.
                        </p>
                    </div>
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
                     id="pillar-card-<?= $pid ?>"
                     data-pid="<?= $pid ?>"
                     data-color="<?= htmlspecialchars($color) ?>"
                     data-active="<?= $active ? '1' : '0' ?>">

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
                        <div class="pillar-bar-fill"
                             style="width:<?= $pct ?>%;background:<?= htmlspecialchars($color) ?>;opacity:<?= $avgP ? 1 : 0.18 ?>">
                        </div>
                    </div>

                    <div class="pillar-card-footer">
                        <label class="active-toggle-label" id="toggle-label-<?= $pid ?>"
                               onclick="event.stopPropagation()"
                               title="<?= $active ? 'Deactivate pillar' : 'Activate pillar' ?>">
                            <input type="checkbox"
                                   id="toggle-<?= $pid ?>"
                                   <?= $active ? 'checked' : '' ?>
                                   onchange="togglePillar(<?= $pid ?>, this)">
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

<!-- ── Add Tenant Modal ── -->
<div class="modal-overlay" id="modal-overlay" onclick="handleOverlayClick(event)">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-icon">🏢</div>
                <div class="modal-title">Add New Tenant</div>
                <div class="modal-subtitle">Create a new organization on the platform.</div>
            </div>
            <button class="modal-close" onclick="closeAddTenant()">✕</button>
        </div>

        <div class="modal-error" id="modal-error"></div>

        <div class="form-field">
            <label class="form-label" for="tenant-name">Organization Name <span>*</span></label>
            <input class="form-input" type="text" id="tenant-name" placeholder="e.g. Acme Corp" autocomplete="off">
        </div>

        <div class="form-field">
            <label class="form-label" for="tenant-slug">Slug <span>*</span></label>
            <input class="form-input" type="text" id="tenant-slug" placeholder="e.g. acme-corp" autocomplete="off">
            <div class="form-hint">Lowercase letters, numbers, and hyphens only. Used as a unique identifier.</div>
        </div>

        <div class="form-field">
            <label class="form-label" for="tenant-email">Contact Email</label>
            <input class="form-input" type="email" id="tenant-email" placeholder="e.g. admin@acmecorp.com" autocomplete="off">
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeAddTenant()">Cancel</button>
            <button class="btn btn-gold btn-submit" id="submit-btn" onclick="submitTenant()">Create Tenant →</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<!-- ── Edit Tenant Modal ── -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="handleEditOverlayClick(event)">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-icon">✎</div>
                <div class="modal-title">Edit Tenant</div>
                <div class="modal-subtitle">Update organization details.</div>
            </div>
            <button class="modal-close" onclick="closeEditTenant()">✕</button>
        </div>
        <div class="modal-error" id="edit-modal-error"></div>
        <input type="hidden" id="edit-tenant-id">
        <div class="form-field">
            <label class="form-label" for="edit-tenant-name">Organization Name <span>*</span></label>
            <input class="form-input" type="text" id="edit-tenant-name" autocomplete="off">
        </div>
        <div class="form-field">
            <label class="form-label" for="edit-tenant-slug">Slug <span>*</span></label>
            <input class="form-input" type="text" id="edit-tenant-slug" autocomplete="off">
            <div class="form-hint">Lowercase letters, numbers, and hyphens only.</div>
        </div>
        <div class="form-field">
            <label class="form-label" for="edit-tenant-email">Contact Email</label>
            <input class="form-input" type="email" id="edit-tenant-email" autocomplete="off">
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeEditTenant()">Cancel</button>
            <button class="btn btn-gold btn-submit" id="edit-submit-btn" onclick="submitEditTenant()">Save Changes →</button>
        </div>
    </div>
</div>

<!-- ── Delete Tenant Modal ── -->
<div class="modal-overlay" id="delete-modal-overlay" onclick="handleDeleteOverlayClick(event)">
    <div class="modal modal-danger">
        <div class="modal-header">
            <div>
                <div class="modal-icon">🗑</div>
                <div class="modal-title">Delete Tenant</div>
                <div class="modal-subtitle" id="delete-modal-subtitle">This action cannot be undone.</div>
            </div>
            <button class="modal-close" onclick="closeDeleteTenant()">✕</button>
        </div>
        <p style="font-size:0.88rem;color:var(--muted2);line-height:1.6;margin-bottom:8px;">
            Are you sure you want to delete <strong id="delete-tenant-name-display" style="color:var(--text)"></strong>?
            All associated data will be permanently removed.
        </p>
        <input type="hidden" id="delete-tenant-id">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeDeleteTenant()">Cancel</button>
            <button class="btn btn-danger" id="delete-submit-btn" onclick="submitDeleteTenant()">Delete Tenant</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

// ── Delegated click handlers for tenant action buttons ────────────────────────
document.addEventListener('click', function(e) {
    const editBtn   = e.target.closest('.edit-btn');
    const deleteBtn = e.target.closest('.delete-btn');
    if (editBtn) {
        openEditTenant(
            editBtn.dataset.id,
            editBtn.dataset.name,
            editBtn.dataset.slug,
            editBtn.dataset.email
        );
    }
    if (deleteBtn) {
        openDeleteTenant(deleteBtn.dataset.id, deleteBtn.dataset.name);
    }
});

// ── Card hover colour effect ──────────────────────────────────────────────────
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

// ── Toast ─────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Toggle pillar ─────────────────────────────────────────────────────────────
async function togglePillar(pid, checkbox) {
    const label    = document.getElementById(`toggle-label-${pid}`);
    const badge    = document.getElementById(`badge-${pid}`);
    const toggleTx = document.getElementById(`toggle-text-${pid}`);
    const card     = document.getElementById(`pillar-card-${pid}`);
    const newState = checkbox.checked ? 1 : 0;
    label.classList.add('toggle-saving');
    try {
        const res  = await fetch('toggle_pillar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
        const statSoon    = document.getElementById('stat-coming-soon');
        if (statSoon) statSoon.textContent = `${totalCount - activeCount} coming soon`;
        showToast(isActive ? `✓ "${data.label}" is now active` : `"${data.label}" set to coming soon`);
    } catch (err) {
        checkbox.checked = !checkbox.checked;
        showToast('⚠ Could not update pillar: ' + err.message, 'error');
    } finally {
        label.classList.remove('toggle-saving');
    }
}

// ── Add Tenant Modal ──────────────────────────────────────────────────────────
function openAddTenant() {
    document.getElementById('modal-overlay').classList.add('open');
    document.getElementById('tenant-name').focus();
    document.getElementById('modal-error').style.display = 'none';
}

function closeAddTenant() {
    document.getElementById('modal-overlay').classList.remove('open');
    document.getElementById('tenant-name').value  = '';
    document.getElementById('tenant-slug').value  = '';
    document.getElementById('tenant-email').value = '';
    document.getElementById('modal-error').style.display = 'none';
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('modal-overlay')) closeAddTenant();
}

// Auto-generate slug from name
document.getElementById('tenant-name').addEventListener('input', function () {
    const slugField = document.getElementById('tenant-slug');
    if (slugField._touched) return; // don't overwrite if user edited manually
    slugField.value = this.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
});

document.getElementById('tenant-slug').addEventListener('input', function () {
    this._touched = this.value !== '';
});

async function submitTenant() {
    const name  = document.getElementById('tenant-name').value.trim();
    const slug  = document.getElementById('tenant-slug').value.trim();
    const email = document.getElementById('tenant-email').value.trim();
    const errEl = document.getElementById('modal-error');
    const btn   = document.getElementById('submit-btn');

    errEl.style.display = 'none';

    if (!name) { showModalError('Organization name is required.'); return; }
    if (!slug)  { showModalError('Slug is required.'); return; }

    btn.classList.add('btn-loading');
    btn.textContent = 'Creating…';

    try {
        const res  = await fetch('superadmin_create_tenant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `name=${encodeURIComponent(name)}&slug=${encodeURIComponent(slug)}&email=${encodeURIComponent(email)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) { showModalError(data.message); return; }

        // ── Append new row to table ───────────────────────────────────────────
        const t      = data.tenant;
        const tbody  = document.getElementById('tenants-tbody');
        const wrap   = document.querySelector('.tenants-table-wrap');

        // If table was empty, rebuild it
        if (!tbody) {
            wrap.innerHTML = `
                <table class="tenants-table" id="tenants-table">
                    <thead><tr>
                        <th>Organization</th><th>Slug</th><th>Contact Email</th>
                        <th>Users</th><th>Status</th><th>Created</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="tenants-tbody"></tbody>
                </table>`;
        }

        const newTbody = document.getElementById('tenants-tbody');
        const date     = new Date(t.created_at);
        const dateStr  = date.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        const tr       = document.createElement('tr');
        tr.id          = `tenant-row-${t.id}`;
        tr.innerHTML   = `
            <td class="tenant-name-cell">${escHtml(t.name)}</td>
            <td><span class="tenant-slug-chip">${escHtml(t.slug)}</span></td>
            <td>${t.email ? escHtml(t.email) : '<span style="color:var(--muted)">—</span>'}</td>
            <td class="tenant-users"><a href="superadmin_tenant_users.php?tenant_id=${escHtml(String(t.id))}" class="user-count-link">0</a></td>
            <td><span class="tenant-status active">Active</span></td>
            <td class="tenant-date">${dateStr}</td>
            <td class="tenant-actions">
                <button class="action-btn edit-btn"
                    data-id="${escHtml(String(t.id))}"
                    data-name="${escHtml(t.name)}"
                    data-slug="${escHtml(t.slug)}"
                    data-email="${escHtml(t.email || '')}"
                    title="Edit tenant">✎</button>
                <button class="action-btn delete-btn"
                    data-id="${escHtml(String(t.id))}"
                    data-name="${escHtml(t.name)}"
                    title="Delete tenant">✕</button>
            </td>
        `;
        newTbody.insertBefore(tr, newTbody.firstChild);

        // Update counters
        const countEl = document.getElementById('tenant-count');
        const statEl  = document.getElementById('stat-total-tenants');
        if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
        if (statEl)  statEl.textContent  = parseInt(statEl.textContent)  + 1;

        closeAddTenant();
        showToast(`✓ Tenant "${t.name}" created successfully`);

    } catch (err) {
        showModalError('Unexpected error. Please try again.');
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Create Tenant →';
    }
}

function showModalError(msg) {
    const el = document.getElementById('modal-error');
    el.textContent = '⚠ ' + msg;
    el.style.display = 'block';
    document.getElementById('submit-btn').classList.remove('btn-loading');
    document.getElementById('submit-btn').textContent = 'Create Tenant →';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modal on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeAddTenant();
        closeEditTenant();
        closeDeleteTenant();
    }
});

// ── Edit Tenant ───────────────────────────────────────────────────────────────
function openEditTenant(id, name, slug, email) {
    document.getElementById('edit-tenant-id').value    = id;
    document.getElementById('edit-tenant-name').value  = name;
    document.getElementById('edit-tenant-slug').value  = slug;
    document.getElementById('edit-tenant-email').value = email || '';
    document.getElementById('edit-modal-error').style.display = 'none';
    document.getElementById('edit-modal-overlay').classList.add('open');
    document.getElementById('edit-tenant-name').focus();
}

function closeEditTenant() {
    document.getElementById('edit-modal-overlay').classList.remove('open');
    document.getElementById('edit-modal-error').style.display = 'none';
}

function handleEditOverlayClick(e) {
    if (e.target === document.getElementById('edit-modal-overlay')) closeEditTenant();
}

async function submitEditTenant() {
    const id    = document.getElementById('edit-tenant-id').value;
    const name  = document.getElementById('edit-tenant-name').value.trim();
    const slug  = document.getElementById('edit-tenant-slug').value.trim();
    const email = document.getElementById('edit-tenant-email').value.trim();
    const btn   = document.getElementById('edit-submit-btn');

    if (!name) { showEditModalError('Organization name is required.'); return; }
    if (!slug)  { showEditModalError('Slug is required.'); return; }

    btn.classList.add('btn-loading');
    btn.textContent = 'Saving…';

    try {
        const res  = await fetch('superadmin_edit_tenant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&name=${encodeURIComponent(name)}&slug=${encodeURIComponent(slug)}&email=${encodeURIComponent(email)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) { showEditModalError(data.message); return; }

        // Update the row in the table
        const row = document.getElementById(`tenant-row-${id}`);
        if (row) {
            row.cells[0].textContent = name;
            row.cells[1].innerHTML   = `<span class="tenant-slug-chip">${escHtml(slug)}</span>`;
            row.cells[2].innerHTML   = email ? escHtml(email) : '<span style="color:var(--muted)">—</span>';
            // Update edit button data attributes with new values
            const editBtn = row.querySelector('.edit-btn');
            if (editBtn) {
                editBtn.dataset.name  = name;
                editBtn.dataset.slug  = slug;
                editBtn.dataset.email = email;
            }
            const deleteBtn = row.querySelector('.delete-btn');
            if (deleteBtn) {
                deleteBtn.dataset.name = name;
            }
        }

        closeEditTenant();
        showToast(`✓ "${name}" updated successfully`);
    } catch (err) {
        showEditModalError('Unexpected error. Please try again.');
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Save Changes →';
    }
}

function showEditModalError(msg) {
    const el = document.getElementById('edit-modal-error');
    el.textContent = '⚠ ' + msg;
    el.style.display = 'block';
    document.getElementById('edit-submit-btn').classList.remove('btn-loading');
    document.getElementById('edit-submit-btn').textContent = 'Save Changes →';
}

// ── Delete Tenant ─────────────────────────────────────────────────────────────
function openDeleteTenant(id, name) {
    document.getElementById('delete-tenant-id').value = id;
    document.getElementById('delete-tenant-name-display').textContent = name;
    document.getElementById('delete-modal-overlay').classList.add('open');
}

function closeDeleteTenant() {
    document.getElementById('delete-modal-overlay').classList.remove('open');
}

function handleDeleteOverlayClick(e) {
    if (e.target === document.getElementById('delete-modal-overlay')) closeDeleteTenant();
}

async function submitDeleteTenant() {
    const id  = document.getElementById('delete-tenant-id').value;
    const btn = document.getElementById('delete-submit-btn');

    btn.classList.add('btn-loading');
    btn.textContent = 'Deleting…';

    try {
        const res  = await fetch('superadmin_delete_tenant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) {
            showToast('⚠ ' + data.message, 'error');
            closeDeleteTenant();
            return;
        }

        // Remove the row from the table
        const row = document.getElementById(`tenant-row-${id}`);
        if (row) row.remove();

        // Update counters
        const countEl = document.getElementById('tenant-count');
        const statEl  = document.getElementById('stat-total-tenants');
        if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
        if (statEl)  statEl.textContent  = Math.max(0, parseInt(statEl.textContent)  - 1);

        closeDeleteTenant();
        showToast(`✓ Tenant deleted successfully`);
    } catch (err) {
        showToast('⚠ Unexpected error. Please try again.', 'error');
        closeDeleteTenant();
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Delete Tenant';
    }
}
</script>
</body>
</html>
