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
$tenantId  = (int)($_SESSION['admin_tenant_id'] ?? 0);

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

// ── Fetch users for this tenant ───────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, name, email, is_admin, created_at
    FROM users
    WHERE tenant_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$tenantId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — <?= htmlspecialchars($tenantName) ?> · Happiness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0B0F17; --surface: #111720; --surface2: #161D29; --surface3: #1A2233;
            --border: rgba(255,255,255,0.07); --gold: #C9A84C; --gold-light: #E2C97E;
            --gold-dim: rgba(201,168,76,0.12); --border-gold: rgba(201,168,76,0.28);
            --danger: #C94C4C; --danger-dim: rgba(201,76,76,0.12);
            --green: #4CAF7D; --text: #F0EBE1; --muted: #4E5D72; --muted2: #8A9BB0; --radius: 14px;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
        body::before {
            content: ''; position: fixed; top: 0; right: 0; width: 700px; height: 700px;
            background: radial-gradient(circle at 80% 20%, rgba(201,168,76,0.04) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }
        .page-wrap { display: grid; grid-template-columns: 248px 1fr; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            background: var(--surface); border-right: 1px solid var(--border);
            padding: 28px 0; position: sticky; top: 0; height: 100vh;
            overflow-y: auto; display: flex; flex-direction: column; z-index: 10;
        }
        .sidebar-brand { padding: 0 20px 20px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .brand-inner { display: flex; align-items: center; gap: 10px; }
        .brand-icon {
            width: 34px; height: 34px; background: var(--gold-dim); border: 1px solid var(--border-gold);
            border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; color: var(--gold); flex-shrink: 0;
        }
        .brand-text .logo { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 600; color: var(--text); line-height: 1.2; }
        .brand-text .logo span { color: var(--gold); }
        .tenant-name {
            margin-top: 10px; font-size: 0.75rem; color: var(--muted2);
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: 6px; padding: 5px 10px; display: flex; align-items: center; gap: 6px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .admin-badge {
            display: inline-flex; align-items: center; gap: 4px; margin-top: 8px;
            font-size: 0.62rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--muted2); background: var(--gold-dim); border: 1px solid var(--border-gold);
            padding: 3px 9px; border-radius: 20px;
        }
        .sidebar-nav { padding: 0 10px; flex: 1; }
        .nav-group { margin-bottom: 24px; }
        .nav-group-label {
            font-size: 0.62rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--muted); padding: 0 10px; margin-bottom: 6px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 9px; padding: 9px 10px;
            border-radius: 9px; color: var(--muted2); text-decoration: none;
            font-size: 0.855rem; transition: all 0.16s; margin-bottom: 2px;
        }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .nav-icon {
            width: 24px; height: 24px; border-radius: 6px; background: var(--surface2);
            display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0;
        }
        .nav-item.active .nav-icon { background: var(--gold); color: #0B0F17; }
        .sidebar-footer { padding: 16px 20px 0; border-top: 1px solid var(--border); margin-top: auto; }
        .logout-link { display: flex; align-items: center; gap: 7px; font-size: 0.82rem; color: var(--muted); text-decoration: none; padding: 6px 0; transition: color 0.16s; }
        .logout-link:hover { color: var(--danger); }

        /* Main */
        .main { padding: 48px 52px 80px; position: relative; z-index: 1; }

        /* Page header */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 36px; }
        .breadcrumb { font-size: 0.78rem; color: var(--muted2); margin-bottom: 10px; }
        .breadcrumb a { color: var(--muted2); text-decoration: none; }
        .breadcrumb a:hover { color: var(--gold); }
        .breadcrumb span { margin: 0 6px; }
        .page-title-wrap .eyebrow { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 600; }
        .page-title span { color: var(--gold); }
        .page-subtitle { font-size: 0.85rem; color: var(--muted2); margin-top: 6px; }

        /* Add user button */
        .btn-add {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--gold); color: #0B0F17; border: none;
            padding: 11px 20px; border-radius: 9px; font-size: 0.88rem;
            font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.18s, transform 0.18s;
            white-space: nowrap; flex-shrink: 0; margin-top: 22px;
        }
        .btn-add:hover { background: var(--gold-light); transform: translateY(-1px); }

        /* Users table */
        .table-wrap {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
        }
        .table-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; border-bottom: 1px solid var(--border);
        }
        .table-title { font-size: 0.92rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .count-badge {
            background: var(--gold-dim); border: 1px solid var(--border-gold);
            color: var(--gold); font-size: 0.72rem; font-weight: 700;
            padding: 2px 9px; border-radius: 20px;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; font-size: 0.68rem; font-weight: 600;
            letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted);
            padding: 12px 20px; border-bottom: 1px solid var(--border);
        }
        tbody tr { border-bottom: 1px solid var(--border2); transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--surface2); }
        tbody td { padding: 14px 20px; font-size: 0.875rem; vertical-align: middle; }

        .user-name { font-weight: 500; }
        .user-email { color: var(--muted2); font-size: 0.8rem; font-family: monospace; }
        .role-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
            padding: 3px 10px; border-radius: 20px;
        }
        .role-admin { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--border-gold); }
        .role-user  { background: var(--surface2); color: var(--muted2); border: 1px solid var(--border); }
        .joined-date { color: var(--muted2); font-size: 0.82rem; }

        .actions { display: flex; gap: 8px; align-items: center; }
        .btn-icon {
            width: 30px; height: 30px; border-radius: 7px; border: 1px solid var(--border);
            background: var(--surface2); color: var(--muted2); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 0.75rem;
            transition: all 0.15s; font-family: 'DM Sans', sans-serif;
        }
        .btn-icon:hover { background: var(--surface3); color: var(--text); }
        .btn-icon.danger:hover { background: var(--danger-dim); color: var(--danger); border-color: rgba(201,76,76,0.3); }

        .empty-state {
            padding: 60px 24px; text-align: center;
            color: var(--muted2); font-size: 0.88rem;
        }
        .empty-state .icon { font-size: 2rem; margin-bottom: 12px; }

        /* Modal */
        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65);
            z-index: 500; align-items: center; justify-content: center;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 32px; width: 460px; max-width: 95vw;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
        }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 600; margin-bottom: 6px; }
        .modal-sub { font-size: 0.82rem; color: var(--muted2); margin-bottom: 24px; }
        .field-wrap { margin-bottom: 16px; }
        .field-label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted2); margin-bottom: 6px; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-input {
            width: 100%; background: var(--surface2); border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 13px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 0.875rem;
        }
        .field-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.1); }
        .field-hint { font-size: 0.73rem; color: var(--muted); margin-top: 4px; }
        .checkbox-wrap { display: flex; align-items: center; gap: 9px; margin-top: 4px; font-size: 0.875rem; color: var(--muted2); cursor: pointer; }
        .checkbox-wrap input { accent-color: var(--gold); width: 15px; height: 15px; cursor: pointer; }
        .modal-error {
            background: var(--danger-dim); border: 1px solid rgba(201,76,76,0.3);
            color: var(--danger); border-radius: 8px; padding: 10px 14px;
            font-size: 0.82rem; margin-bottom: 16px; display: none;
        }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; }
        .btn-cancel {
            padding: 10px 18px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--surface2); color: var(--muted2); font-size: 0.875rem;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.15s;
        }
        .btn-cancel:hover { color: var(--text); }
        .btn-submit {
            padding: 10px 22px; border-radius: 8px; border: none;
            background: var(--gold); color: #0B0F17; font-size: 0.875rem;
            font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.15s;
        }
        .btn-submit:hover { background: var(--gold-light); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-danger-confirm {
            padding: 10px 22px; border-radius: 8px; border: none;
            background: var(--danger); color: #fff; font-size: 0.875rem;
            font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif;
        }

        /* Toast */
        .toast {
            position: fixed; bottom: 28px; right: 28px; z-index: 9999;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 12px 20px; font-size: 0.85rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4); opacity: 0;
            transform: translateY(8px); transition: all 0.25s ease; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-color: rgba(76,175,125,0.4); color: #4CAF7D; }
        .toast.error   { border-color: rgba(201,76,76,0.4); color: var(--danger); }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3A4558; border-radius: 4px; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-inner">
                <div class="brand-icon">✦</div>
                <div class="brand-text"><div class="logo">Happiness <span>Audit</span></div></div>
            </div>
            <div class="tenant-name"><span>🏢</span><?= htmlspecialchars($tenantName) ?></div>
            <div class="admin-badge">⚙ Admin Panel</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-label">Admin</div>
                <a href="admin_dashboard.php" class="nav-item">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="admin_users.php" class="nav-item active">
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
        <div class="breadcrumb">
            <a href="admin_dashboard.php">Dashboard</a>
            <span>›</span>
            <?= htmlspecialchars($tenantName) ?>
        </div>

        <div class="page-header">
            <div class="page-title-wrap">
                <div class="eyebrow">🏢 <?= htmlspecialchars(strtoupper($tenantName)) ?></div>
                <h1 class="page-title">User <span>Management</span></h1>
                <p class="page-subtitle">View, add, edit, and remove users for this organisation. Changes take effect immediately.</p>
            </div>
            <button class="btn-add" onclick="openCreateModal()">+ Add User</button>
        </div>

        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">
                    Users
                    <span class="count-badge"><?= count($users) ?></span>
                </div>
            </div>
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div class="icon">👤</div>
                    No users yet. Add the first one!
                </div>
            <?php else: ?>
            <table>
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
                    $firstName = $nameParts[0] ?? '';
                    $lastName  = $nameParts[1] ?? '';
                    $joined    = date('d M Y', strtotime($u['created_at']));
                ?>
                <tr id="row-<?= $u['id'] ?>">
                    <td class="user-name"><?= htmlspecialchars($u['name']) ?></td>
                    <td><span class="user-email"><?= htmlspecialchars($u['email']) ?></span></td>
                    <td>
                        <span class="role-badge <?= $u['is_admin'] ? 'role-admin' : 'role-user' ?>">
                            <?= $u['is_admin'] ? '👑 Admin' : 'User' ?>
                        </span>
                    </td>
                    <td class="joined-date"><?= $joined ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon" title="Edit"
                                onclick="openEditModal(<?= $u['id'] ?>, <?= htmlspecialchars(json_encode($firstName)) ?>, <?= htmlspecialchars(json_encode($lastName)) ?>, <?= htmlspecialchars(json_encode($u['email'])) ?>, <?= (int)$u['is_admin'] ?>)">
                                ✏
                            </button>
                            <button class="btn-icon danger" title="Delete"
                                onclick="openDeleteModal(<?= $u['id'] ?>, <?= htmlspecialchars(json_encode($u['name'])) ?>)">
                                ✕
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Create Modal -->
<div class="modal-backdrop" id="create-modal">
    <div class="modal">
        <div class="modal-title">Add New User</div>
        <div class="modal-sub">Create a new account for <?= htmlspecialchars($tenantName) ?>.</div>
        <div class="modal-error" id="create-error"></div>
        <div class="field-row">
            <div class="field-wrap">
                <label class="field-label">First Name</label>
                <input class="field-input" type="text" id="c-first" placeholder="Jane">
            </div>
            <div class="field-wrap">
                <label class="field-label">Last Name</label>
                <input class="field-input" type="text" id="c-last" placeholder="Doe">
            </div>
        </div>
        <div class="field-wrap">
            <label class="field-label">Email Address</label>
            <input class="field-input" type="email" id="c-email" placeholder="jane@company.com">
        </div>
        <div class="field-wrap">
            <label class="field-label">Password</label>
            <input class="field-input" type="password" id="c-pass" placeholder="Min. 8 characters">
        </div>
        <div class="field-wrap">
            <label class="checkbox-wrap">
                <input type="checkbox" id="c-admin">
                Grant Admin privileges
            </label>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModals()">Cancel</button>
            <button class="btn-submit" id="c-submit" onclick="submitCreate()">Create User</button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="edit-modal">
    <div class="modal">
        <div class="modal-title">Edit User</div>
        <div class="modal-sub">Update user details. Leave password blank to keep it unchanged.</div>
        <div class="modal-error" id="edit-error"></div>
        <input type="hidden" id="e-id">
        <div class="field-row">
            <div class="field-wrap">
                <label class="field-label">First Name</label>
                <input class="field-input" type="text" id="e-first">
            </div>
            <div class="field-wrap">
                <label class="field-label">Last Name</label>
                <input class="field-input" type="text" id="e-last">
            </div>
        </div>
        <div class="field-wrap">
            <label class="field-label">Email Address</label>
            <input class="field-input" type="email" id="e-email">
        </div>
        <div class="field-wrap">
            <label class="field-label">New Password <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
            <input class="field-input" type="password" id="e-pass" placeholder="Leave blank to keep current">
            <div class="field-hint">Min. 8 characters if changing.</div>
        </div>
        <div class="field-wrap">
            <label class="checkbox-wrap">
                <input type="checkbox" id="e-admin">
                Grant Admin privileges
            </label>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModals()">Cancel</button>
            <button class="btn-submit" id="e-submit" onclick="submitEdit()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-backdrop" id="delete-modal">
    <div class="modal">
        <div class="modal-title">Delete User</div>
        <div class="modal-sub" id="delete-sub">This action is permanent and cannot be undone.</div>
        <input type="hidden" id="d-id">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModals()">Cancel</button>
            <button class="btn-danger-confirm" onclick="submitDelete()">Delete User</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
let userCount = <?= count($users) ?>;

// ── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

// ── Modals ───────────────────────────────────────────────────────────────────
function closeModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.modal-error').forEach(e => { e.style.display = 'none'; e.textContent = ''; });
}
document.querySelectorAll('.modal-backdrop').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModals(); }));

function openCreateModal() {
    document.getElementById('c-first').value = '';
    document.getElementById('c-last').value  = '';
    document.getElementById('c-email').value = '';
    document.getElementById('c-pass').value  = '';
    document.getElementById('c-admin').checked = false;
    document.getElementById('create-modal').classList.add('open');
    document.getElementById('c-first').focus();
}

function openEditModal(id, first, last, email, isAdmin) {
    document.getElementById('e-id').value    = id;
    document.getElementById('e-first').value = first;
    document.getElementById('e-last').value  = last;
    document.getElementById('e-email').value = email;
    document.getElementById('e-pass').value  = '';
    document.getElementById('e-admin').checked = isAdmin === 1;
    document.getElementById('edit-modal').classList.add('open');
    document.getElementById('e-first').focus();
}

function openDeleteModal(id, name) {
    document.getElementById('d-id').value = id;
    document.getElementById('delete-sub').textContent = `Are you sure you want to delete "${name}"? This is permanent.`;
    document.getElementById('delete-modal').classList.add('open');
}

// ── Create ───────────────────────────────────────────────────────────────────
async function submitCreate() {
    const btn   = document.getElementById('c-submit');
    const errEl = document.getElementById('create-error');
    errEl.style.display = 'none';
    btn.disabled = true; btn.textContent = 'Creating…';

    const body = new URLSearchParams({
        csrf_token: CSRF,
        first_name: document.getElementById('c-first').value,
        last_name:  document.getElementById('c-last').value,
        email:      document.getElementById('c-email').value,
        password:   document.getElementById('c-pass').value,
        is_admin:   document.getElementById('c-admin').checked ? '1' : '0',
    });

    try {
        const res  = await fetch('admin_create_user.php', { method: 'POST', body });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const u = data.user;

        // If empty state visible, replace it
        const emptyState = document.querySelector('.empty-state');
        if (emptyState) {
            const table = document.createElement('table');
            table.innerHTML = `<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead><tbody id="users-tbody"></tbody>`;
            emptyState.replaceWith(table);
        }

        const row = document.createElement('tr');
        row.id = `row-${u.id}`;
        row.innerHTML = `
            <td class="user-name">${esc(u.first_name + ' ' + u.last_name)}</td>
            <td><span class="user-email">${esc(u.email)}</span></td>
            <td><span class="role-badge ${u.is_admin ? 'role-admin' : 'role-user'}">${u.is_admin ? '👑 Admin' : 'User'}</span></td>
            <td class="joined-date">${fmtDate(u.created_at)}</td>
            <td><div class="actions">
                <button class="btn-icon btn-edit" title="Edit">✏</button>
                <button class="btn-icon danger btn-delete" title="Delete">✕</button>
            </div></td>`;
        row.querySelector('.btn-edit').addEventListener('click', () =>
            openEditModal(u.id, u.first_name, u.last_name, u.email, u.is_admin));
        row.querySelector('.btn-delete').addEventListener('click', () =>
            openDeleteModal(u.id, u.first_name + ' ' + u.last_name));
        document.getElementById('users-tbody').appendChild(row);
        updateCount(++userCount);
        closeModals();
        showToast('✓ User created successfully');
    } catch (err) {
        errEl.textContent = err.message; errEl.style.display = 'block';
    } finally {
        btn.disabled = false; btn.textContent = 'Create User';
    }
}

// ── Edit ─────────────────────────────────────────────────────────────────────
async function submitEdit() {
    const btn   = document.getElementById('e-submit');
    const errEl = document.getElementById('edit-error');
    errEl.style.display = 'none';
    btn.disabled = true; btn.textContent = 'Saving…';

    const body = new URLSearchParams({
        csrf_token: CSRF,
        id:         document.getElementById('e-id').value,
        first_name: document.getElementById('e-first').value,
        last_name:  document.getElementById('e-last').value,
        email:      document.getElementById('e-email').value,
        password:   document.getElementById('e-pass').value,
        is_admin:   document.getElementById('e-admin').checked ? '1' : '0',
    });

    try {
        const res  = await fetch('admin_edit_user.php', { method: 'POST', body });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const id      = document.getElementById('e-id').value;
        const first   = document.getElementById('e-first').value;
        const last    = document.getElementById('e-last').value;
        const email   = document.getElementById('e-email').value;
        const isAdmin = document.getElementById('e-admin').checked;
        const row     = document.getElementById(`row-${id}`);

        if (row) {
            row.cells[0].textContent = first + ' ' + last;
            row.cells[1].innerHTML   = `<span class="user-email">${esc(email)}</span>`;
            row.cells[2].innerHTML   = `<span class="role-badge ${isAdmin ? 'role-admin' : 'role-user'}">${isAdmin ? '👑 Admin' : 'User'}</span>`;
            // Update edit button's onclick with new values
            row.cells[4].querySelector('.btn-icon').setAttribute('onclick',
                `openEditModal(${id}, ${JSON.stringify(first)}, ${JSON.stringify(last)}, ${JSON.stringify(email)}, ${isAdmin ? 1 : 0})`);
        }

        closeModals();
        showToast('✓ User updated successfully');
    } catch (err) {
        errEl.textContent = err.message; errEl.style.display = 'block';
    } finally {
        btn.disabled = false; btn.textContent = 'Save Changes';
    }
}

// ── Delete ───────────────────────────────────────────────────────────────────
async function submitDelete() {
    const id = document.getElementById('d-id').value;
    const body = new URLSearchParams({ csrf_token: CSRF, id });

    try {
        const res  = await fetch('admin_delete_user.php', { method: 'POST', body });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const row = document.getElementById(`row-${id}`);
        if (row) row.remove();
        updateCount(--userCount);
        closeModals();
        showToast('✓ User deleted');
    } catch (err) {
        showToast('⚠ ' + err.message, 'error');
        closeModals();
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function fmtDate(str) {
    const d = new Date(str);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
function updateCount(n) {
    const badge = document.querySelector('.count-badge');
    if (badge) badge.textContent = n;
}
</script>
</body>
</html>
