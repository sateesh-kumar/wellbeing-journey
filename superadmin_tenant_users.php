<?php
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$pdo = getDBConnection();

$tenantId = (int)($_GET['tenant_id'] ?? 0);
if (!$tenantId) {
    header('Location: superadmin_dashboard.php');
    exit;
}

// Fetch tenant
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    header('Location: superadmin_dashboard.php');
    exit;
}

// Fetch users for this tenant
$stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt->execute([$tenantId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — <?= htmlspecialchars($tenant['name']) ?> · Happiness Audit</title>
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

        /* ── Breadcrumb ── */
        .breadcrumb {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; color: var(--muted2); margin-bottom: 28px;
        }
        .breadcrumb a { color: var(--muted2); text-decoration: none; transition: color 0.16s; }
        .breadcrumb a:hover { color: var(--gold); }
        .breadcrumb-sep { color: var(--muted); }
        .breadcrumb-current { color: var(--text); font-weight: 500; }

        /* ── Hero ── */
        .hero { margin-bottom: 36px; }
        .hero-eyebrow { font-size: 0.78rem; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: var(--gold); margin-bottom: 10px; }
        .hero-title { font-family: 'Playfair Display', serif; font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 700; line-height: 1.15; letter-spacing: -0.02em; color: var(--text); margin-bottom: 8px; }
        .hero-sub { font-size: 0.9rem; color: var(--muted2); line-height: 1.6; }

        /* ── Section ── */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; margin-bottom: 20px;
            padding-bottom: 16px; border-bottom: 1px solid var(--border);
        }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 600; color: var(--text); }
        .user-count-badge {
            font-size: 0.72rem; font-weight: 600; font-family: 'DM Mono', monospace;
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--muted2); padding: 3px 10px; border-radius: 99px;
        }

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
        .btn-outline {
            background: transparent; border: 1px solid var(--border);
            color: var(--muted2); flex: 1;
        }
        .btn-outline:hover { border-color: var(--muted2); color: var(--text); }
        .btn-submit { flex: 2; }
        .btn-loading { opacity: 0.6; pointer-events: none; }
        .btn-danger { background: var(--danger); color: #fff; flex: 2; }
        .btn-danger:hover { background: #d96060; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,76,76,0.3); }

        /* ── Users Table ── */
        .users-table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .users-table {
            width: 100%; border-collapse: collapse; font-size: 0.875rem;
        }
        .users-table thead tr {
            background: var(--surface2); border-bottom: 1px solid var(--border);
        }
        .users-table th {
            padding: 12px 18px; text-align: left;
            font-size: 0.7rem; font-weight: 600;
            letter-spacing: 0.09em; text-transform: uppercase; color: var(--muted);
        }
        .users-table td {
            padding: 14px 18px; border-bottom: 1px solid var(--border2);
            color: var(--text); vertical-align: middle;
        }
        .users-table tbody tr:last-child td { border-bottom: none; }
        .users-table tbody tr:hover td { background: rgba(255,255,255,0.02); }
        .user-name-cell { font-weight: 600; }
        .user-email-cell { color: var(--muted2); font-size: 0.85rem; font-family: 'DM Mono', monospace; }
        .user-date { font-size: 0.8rem; color: var(--muted2); font-family: 'DM Mono', monospace; }
        .admin-badge-cell {
            font-size: 0.7rem; font-weight: 600; padding: 3px 9px; border-radius: 99px;
            white-space: nowrap; display: inline-block;
        }
        .admin-badge-cell.is-admin { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .admin-badge-cell.is-user  { background: var(--surface2); color: var(--muted2); border: 1px solid var(--border); }

        /* ── Action Buttons ── */
        .user-actions { white-space: nowrap; }
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 7px;
            border: 1px solid var(--border); background: var(--surface2);
            color: var(--muted2); font-size: 0.85rem;
            cursor: pointer; transition: all 0.16s; margin-right: 4px;
        }
        .action-btn:hover { color: var(--text); border-color: var(--muted2); }
        .edit-btn:hover   { border-color: var(--gold); color: var(--gold); background: var(--gold-dim); }
        .delete-btn:hover { border-color: rgba(201,76,76,0.5); color: var(--danger); background: rgba(201,76,76,0.1); }

        /* ── Empty State ── */
        .empty-users { padding: 56px 20px; text-align: center; color: var(--muted); font-size: 0.9rem; }
        .empty-users .empty-icon { font-size: 2.2rem; margin-bottom: 12px; }
        .empty-users p { margin-bottom: 18px; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 18px; padding: 36px; width: 100%; max-width: 480px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
            transform: translateY(16px) scale(0.98); transition: transform 0.22s ease;
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
            width: 100%; background: var(--surface2); border: 1px solid var(--border);
            border-radius: 8px; padding: 11px 14px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
        }
        .form-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12); }
        .form-input::placeholder { color: var(--muted); }
        .form-hint { font-size: 0.75rem; color: var(--muted); margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-error {
            background: rgba(201,76,76,0.10); border: 1px solid rgba(201,76,76,0.3);
            color: var(--danger); border-radius: 8px; padding: 10px 14px;
            font-size: 0.84rem; margin-bottom: 16px; display: none;
        }
        .modal-actions { display: flex; gap: 10px; margin-top: 24px; }

        /* Checkbox toggle */
        .checkbox-field { display: flex; align-items: center; gap: 10px; padding: 10px 0; }
        .checkbox-field input[type="checkbox"] {
            width: 18px; height: 18px; accent-color: var(--gold); cursor: pointer; flex-shrink: 0;
        }
        .checkbox-label { font-size: 0.88rem; color: var(--muted2); cursor: pointer; }

        /* ── Modal Danger ── */
        .modal-danger .modal-icon { background: rgba(201,76,76,0.12); border-color: rgba(201,76,76,0.3); }

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
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .users-table th:nth-child(4),
            .users-table td:nth-child(4) { display: none; }
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
                <a href="superadmin_dashboard.php" class="nav-item">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <a href="superadmin_dashboard.php#tenants-section" class="nav-item">
                    <span class="nav-icon">🏢</span> Tenants
                </a>
                <a href="#" class="nav-item active">
                    <span class="nav-icon">👥</span> Users
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="superadmin_logout.php" class="logout-link">⎋ &nbsp;Sign out</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="superadmin_dashboard.php">Dashboard</a>
            <span class="breadcrumb-sep">›</span>
            <a href="superadmin_dashboard.php#tenants-section">Tenants</a>
            <span class="breadcrumb-sep">›</span>
            <span class="breadcrumb-current"><?= htmlspecialchars($tenant['name']) ?></span>
        </div>

        <!-- Hero -->
        <div class="hero">
            <p class="hero-eyebrow">🏢 <?= htmlspecialchars($tenant['name']) ?></p>
            <h1 class="hero-title">User <span style="color:var(--gold)">Management</span></h1>
            <p class="hero-sub">View, add, edit, and remove users for this organization. Changes take effect immediately.</p>
        </div>

        <!-- Users Section -->
        <div class="section-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <h2 class="section-title">Users</h2>
                <span class="user-count-badge" id="user-count"><?= count($users) ?></span>
            </div>
            <button class="btn btn-gold" onclick="openAddUser()">+ Add User</button>
        </div>

        <div class="users-table-wrap">
            <?php if (empty($users)): ?>
                <div class="empty-users">
                    <div class="empty-icon">👤</div>
                    <p>No users in this organization yet.</p>
                    <button class="btn btn-gold" onclick="openAddUser()">+ Add First User</button>
                </div>
            <?php else: ?>
            <table class="users-table" id="users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <?php foreach ($users as $u):
                        $nameParts = explode(' ', $u['name'], 2);
                        $firstName = $nameParts[0];
                        $lastName  = isset($nameParts[1]) ? $nameParts[1] : '';
                    ?>
                    <tr id="user-row-<?= (int)$u['id'] ?>">
                        <td class="user-name-cell"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="user-email-cell"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="admin-badge-cell <?= $u['is_admin'] ? 'is-admin' : 'is-user' ?>">
                                <?= $u['is_admin'] ? '👑 Admin' : 'User' ?>
                            </span>
                        </td>
                        <td class="user-date"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="user-actions">
                            <button class="action-btn edit-btn"
                                data-id="<?= (int)$u['id'] ?>"
                                data-firstname="<?= htmlspecialchars($firstName, ENT_QUOTES) ?>"
                                data-lastname="<?= htmlspecialchars($lastName, ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                data-isadmin="<?= $u['is_admin'] ? '1' : '0' ?>"
                                title="Edit user">✎</button>
                            <button class="action-btn delete-btn"
                                data-id="<?= (int)$u['id'] ?>"
                                data-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                                title="Delete user">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<div class="toast" id="toast"></div>

<!-- ── Add User Modal ── -->
<div class="modal-overlay" id="add-modal-overlay" onclick="handleAddOverlayClick(event)">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-icon">👤</div>
                <div class="modal-title">Add New User</div>
                <div class="modal-subtitle">Create a user for <?= htmlspecialchars($tenant['name']) ?>.</div>
            </div>
            <button class="modal-close" onclick="closeAddUser()">✕</button>
        </div>
        <div class="modal-error" id="add-modal-error"></div>
        <div class="form-row">
            <div class="form-field">
                <label class="form-label" for="add-firstname">First Name <span>*</span></label>
                <input class="form-input" type="text" id="add-firstname" placeholder="Jane" autocomplete="off">
            </div>
            <div class="form-field">
                <label class="form-label" for="add-lastname">Last Name <span>*</span></label>
                <input class="form-input" type="text" id="add-lastname" placeholder="Doe" autocomplete="off">
            </div>
        </div>
        <div class="form-field">
            <label class="form-label" for="add-email">Email Address <span>*</span></label>
            <input class="form-input" type="email" id="add-email" placeholder="jane@example.com" autocomplete="off">
        </div>
        <div class="form-field">
            <label class="form-label" for="add-password">Password <span>*</span></label>
            <input class="form-input" type="password" id="add-password" placeholder="Min. 8 characters">
            <div class="form-hint">User can change their password after signing in.</div>
        </div>
        <div class="checkbox-field">
            <input type="checkbox" id="add-isadmin">
            <label class="checkbox-label" for="add-isadmin">Is Admin</label>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeAddUser()">Cancel</button>
            <button class="btn btn-gold btn-submit" id="add-submit-btn" onclick="submitAddUser()">Create User →</button>
        </div>
    </div>
</div>

<!-- ── Edit User Modal ── -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="handleEditOverlayClick(event)">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-icon">✎</div>
                <div class="modal-title">Edit User</div>
                <div class="modal-subtitle">Update user details.</div>
            </div>
            <button class="modal-close" onclick="closeEditUser()">✕</button>
        </div>
        <div class="modal-error" id="edit-modal-error"></div>
        <input type="hidden" id="edit-user-id">
        <div class="form-row">
            <div class="form-field">
                <label class="form-label" for="edit-firstname">First Name <span>*</span></label>
                <input class="form-input" type="text" id="edit-firstname" autocomplete="off">
            </div>
            <div class="form-field">
                <label class="form-label" for="edit-lastname">Last Name <span>*</span></label>
                <input class="form-input" type="text" id="edit-lastname" autocomplete="off">
            </div>
        </div>
        <div class="form-field">
            <label class="form-label" for="edit-email">Email Address <span>*</span></label>
            <input class="form-input" type="email" id="edit-email" autocomplete="off">
        </div>
        <div class="form-field">
            <label class="form-label" for="edit-password">New Password</label>
            <input class="form-input" type="password" id="edit-password" placeholder="Leave blank to keep current password">
        </div>
        <div class="checkbox-field">
            <input type="checkbox" id="edit-isadmin">
            <label class="checkbox-label" for="edit-isadmin">Is Admin</label>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeEditUser()">Cancel</button>
            <button class="btn btn-gold btn-submit" id="edit-submit-btn" onclick="submitEditUser()">Save Changes →</button>
        </div>
    </div>
</div>

<!-- ── Delete User Modal ── -->
<div class="modal-overlay" id="delete-modal-overlay" onclick="handleDeleteOverlayClick(event)">
    <div class="modal modal-danger">
        <div class="modal-header">
            <div>
                <div class="modal-icon">🗑</div>
                <div class="modal-title">Delete User</div>
                <div class="modal-subtitle">This action cannot be undone.</div>
            </div>
            <button class="modal-close" onclick="closeDeleteUser()">✕</button>
        </div>
        <p style="font-size:0.88rem;color:var(--muted2);line-height:1.6;margin-bottom:8px;">
            Are you sure you want to delete <strong id="delete-user-name-display" style="color:var(--text)"></strong>?
            All their data will be permanently removed.
        </p>
        <input type="hidden" id="delete-user-id">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="closeDeleteUser()">Cancel</button>
            <button class="btn btn-danger" id="delete-submit-btn" onclick="submitDeleteUser()">Delete User</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const TENANT_ID  = <?= json_encode($tenantId) ?>;

// ── Delegated click ────────────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    const editBtn   = e.target.closest('.edit-btn');
    const deleteBtn = e.target.closest('.delete-btn');
    if (editBtn) {
        openEditUser(
            editBtn.dataset.id,
            editBtn.dataset.firstname,
            editBtn.dataset.lastname,
            editBtn.dataset.email,
            editBtn.dataset.isadmin === '1'
        );
    }
    if (deleteBtn) {
        openDeleteUser(deleteBtn.dataset.id, deleteBtn.dataset.name);
    }
});

// ── Toast ──────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Keyboard ───────────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAddUser(); closeEditUser(); closeDeleteUser(); }
});

// ── Add User ───────────────────────────────────────────────────────────────────
function openAddUser() {
    document.getElementById('add-modal-overlay').classList.add('open');
    document.getElementById('add-modal-error').style.display = 'none';
    document.getElementById('add-firstname').focus();
}
function closeAddUser() {
    document.getElementById('add-modal-overlay').classList.remove('open');
    document.getElementById('add-modal-error').style.display = 'none';
    ['add-firstname','add-lastname','add-email','add-password'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('add-isadmin').checked = false;
}
function handleAddOverlayClick(e) {
    if (e.target === document.getElementById('add-modal-overlay')) closeAddUser();
}

async function submitAddUser() {
    const firstname = document.getElementById('add-firstname').value.trim();
    const lastname  = document.getElementById('add-lastname').value.trim();
    const email     = document.getElementById('add-email').value.trim();
    const password  = document.getElementById('add-password').value;
    const isAdmin   = document.getElementById('add-isadmin').checked ? 1 : 0;
    const btn       = document.getElementById('add-submit-btn');

    if (!firstname) { showAddError('First name is required.'); return; }
    if (!lastname)  { showAddError('Last name is required.'); return; }
    if (!email)     { showAddError('Email address is required.'); return; }
    if (!password || password.length < 8) { showAddError('Password must be at least 8 characters.'); return; }

    btn.classList.add('btn-loading');
    btn.textContent = 'Creating…';

    try {
        const res  = await fetch('superadmin_create_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `tenant_id=${encodeURIComponent(TENANT_ID)}&first_name=${encodeURIComponent(firstname)}&last_name=${encodeURIComponent(lastname)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&is_admin=${isAdmin}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) { showAddError(data.message); return; }

        const u = data.user;
        // Ensure table exists
        const wrap = document.querySelector('.users-table-wrap');
        if (!document.getElementById('users-tbody')) {
            wrap.innerHTML = `
                <table class="users-table" id="users-table">
                    <thead><tr>
                        <th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="users-tbody"></tbody>
                </table>`;
        }
        const tbody = document.getElementById('users-tbody');
        const tr    = document.createElement('tr');
        tr.id = `user-row-${u.id}`;
        tr.innerHTML = buildUserRow(u);
        tbody.insertBefore(tr, tbody.firstChild);

        // Update counter
        const cEl = document.getElementById('user-count');
        if (cEl) cEl.textContent = parseInt(cEl.textContent) + 1;

        closeAddUser();
        showToast(`✓ User "${firstname} ${lastname}" created successfully`);
    } catch(err) {
        showAddError('Unexpected error. Please try again.');
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Create User →';
    }
}
function showAddError(msg) {
    const el = document.getElementById('add-modal-error');
    el.textContent = '⚠ ' + msg;
    el.style.display = 'block';
    const btn = document.getElementById('add-submit-btn');
    btn.classList.remove('btn-loading');
    btn.textContent = 'Create User →';
}

// ── Edit User ──────────────────────────────────────────────────────────────────
function openEditUser(id, firstname, lastname, email, isAdmin) {
    document.getElementById('edit-user-id').value   = id;
    document.getElementById('edit-firstname').value = firstname;
    document.getElementById('edit-lastname').value  = lastname;
    document.getElementById('edit-email').value     = email;
    document.getElementById('edit-password').value  = '';
    document.getElementById('edit-isadmin').checked = isAdmin;
    document.getElementById('edit-modal-error').style.display = 'none';
    document.getElementById('edit-modal-overlay').classList.add('open');
    document.getElementById('edit-firstname').focus();
}
function closeEditUser() {
    document.getElementById('edit-modal-overlay').classList.remove('open');
    document.getElementById('edit-modal-error').style.display = 'none';
}
function handleEditOverlayClick(e) {
    if (e.target === document.getElementById('edit-modal-overlay')) closeEditUser();
}

async function submitEditUser() {
    const id        = document.getElementById('edit-user-id').value;
    const firstname = document.getElementById('edit-firstname').value.trim();
    const lastname  = document.getElementById('edit-lastname').value.trim();
    const email     = document.getElementById('edit-email').value.trim();
    const password  = document.getElementById('edit-password').value;
    const isAdmin   = document.getElementById('edit-isadmin').checked ? 1 : 0;
    const btn       = document.getElementById('edit-submit-btn');

    if (!firstname) { showEditError('First name is required.'); return; }
    if (!lastname)  { showEditError('Last name is required.'); return; }
    if (!email)     { showEditError('Email address is required.'); return; }
    if (password && password.length < 8) { showEditError('Password must be at least 8 characters.'); return; }

    btn.classList.add('btn-loading');
    btn.textContent = 'Saving…';

    try {
        const res  = await fetch('superadmin_edit_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&first_name=${encodeURIComponent(firstname)}&last_name=${encodeURIComponent(lastname)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&is_admin=${isAdmin}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) { showEditError(data.message); return; }

        // Update row in DOM
        const row = document.getElementById(`user-row-${id}`);
        if (row) {
            row.cells[0].textContent = firstname + ' ' + lastname;
            row.cells[1].textContent = email;
            row.cells[2].innerHTML   = `<span class="admin-badge-cell ${isAdmin ? 'is-admin' : 'is-user'}">${isAdmin ? '👑 Admin' : 'User'}</span>`;
            const editBtn = row.querySelector('.edit-btn');
            if (editBtn) {
                editBtn.dataset.firstname = firstname;
                editBtn.dataset.lastname  = lastname;
                editBtn.dataset.email     = email;
                editBtn.dataset.isadmin   = String(isAdmin);
            }
            const deleteBtn = row.querySelector('.delete-btn');
            if (deleteBtn) deleteBtn.dataset.name = firstname + ' ' + lastname;
        }

        closeEditUser();
        showToast(`✓ "${firstname} ${lastname}" updated successfully`);
    } catch(err) {
        showEditError('Unexpected error. Please try again.');
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Save Changes →';
    }
}
function showEditError(msg) {
    const el = document.getElementById('edit-modal-error');
    el.textContent = '⚠ ' + msg;
    el.style.display = 'block';
    const btn = document.getElementById('edit-submit-btn');
    btn.classList.remove('btn-loading');
    btn.textContent = 'Save Changes →';
}

// ── Delete User ────────────────────────────────────────────────────────────────
function openDeleteUser(id, name) {
    document.getElementById('delete-user-id').value = id;
    document.getElementById('delete-user-name-display').textContent = name;
    document.getElementById('delete-modal-overlay').classList.add('open');
}
function closeDeleteUser() {
    document.getElementById('delete-modal-overlay').classList.remove('open');
}
function handleDeleteOverlayClick(e) {
    if (e.target === document.getElementById('delete-modal-overlay')) closeDeleteUser();
}

async function submitDeleteUser() {
    const id  = document.getElementById('delete-user-id').value;
    const btn = document.getElementById('delete-submit-btn');

    btn.classList.add('btn-loading');
    btn.textContent = 'Deleting…';

    try {
        const res  = await fetch('superadmin_delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
        });
        const data = await res.json();
        if (!data.success) { showToast('⚠ ' + data.message, 'error'); closeDeleteUser(); return; }

        const row = document.getElementById(`user-row-${id}`);
        if (row) row.remove();

        const cEl = document.getElementById('user-count');
        if (cEl) cEl.textContent = Math.max(0, parseInt(cEl.textContent) - 1);

        closeDeleteUser();
        showToast('✓ User deleted successfully');
    } catch(err) {
        showToast('⚠ Unexpected error. Please try again.', 'error');
        closeDeleteUser();
    } finally {
        btn.classList.remove('btn-loading');
        btn.textContent = 'Delete User';
    }
}

// ── Helper to build a new user row's inner HTML ────────────────────────────────
function buildUserRow(u) {
    const date = new Date(u.created_at);
    const dateStr = date.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
    const isAdmin = u.is_admin == 1;
    return `
        <td class="user-name-cell">${escHtml(u.first_name)} ${escHtml(u.last_name)}</td>
        <td class="user-email-cell">${escHtml(u.email)}</td>
        <td><span class="admin-badge-cell ${isAdmin ? 'is-admin' : 'is-user'}">${isAdmin ? '👑 Admin' : 'User'}</span></td>
        <td class="user-date">${dateStr}</td>
        <td class="user-actions">
            <button class="action-btn edit-btn"
                data-id="${escHtml(String(u.id))}"
                data-firstname="${escHtml(u.first_name)}"
                data-lastname="${escHtml(u.last_name)}"
                data-email="${escHtml(u.email)}"
                data-isadmin="${isAdmin ? '1' : '0'}"
                title="Edit user">✎</button>
            <button class="action-btn delete-btn"
                data-id="${escHtml(String(u.id))}"
                data-name="${escHtml(u.first_name + ' ' + u.last_name)}"
                title="Delete user">✕</button>
        </td>`;
}
</script>
</body>
</html>
