<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/assets/config.php';
require_once __DIR__ . '/bootstrap.php';


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

// Content-Security-Policy header
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'none';");

// Load password from environment — no hardcoded fallback
$superadminPassword = env('SUPERADMIN_PASSWORD', '');
if ($superadminPassword === '') {
    error_log('SUPERADMIN_PASSWORD environment variable is not set.');
    http_response_code(500);
    exit('Server configuration error.');
}

if (isset($_SESSION['superadmin_authenticated'])) {
    header('Location: superadmin_dashboard.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError = '';
if (isset($_POST['superadmin_login'])) {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid request. Please try again.';
    } else {
        // Brute-force: enforce a small delay on every attempt
        sleep(1);
        if (hash_equals($superadminPassword, $_POST['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['superadmin_authenticated'] = true;
            header('Location: superadmin_dashboard.php');
            exit;
        } else {
            $loginError = 'Incorrect password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
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
            --danger:      #C94C4C;
            --danger-dim:  rgba(201,76,76,0.12);
            --shadow:      0 4px 24px rgba(0,0,0,0.5);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 380px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .lock-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            background: var(--gold-dim);
            border: 1px solid var(--border-gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 24px;
        }
        .super-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.62rem; font-weight: 700; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--gold);
            background: var(--gold-dim); border: 1px solid var(--border-gold);
            padding: 4px 10px; border-radius: 20px; margin-bottom: 12px;
        }
        .login-card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--text);
            margin-bottom: 6px;
        }
        .login-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 28px;
        }
        .login-field {
            width: 100%;
            background: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            margin-bottom: 16px;
            text-align: left;
        }
        .login-field:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
        }
        .login-error {
            background: var(--danger-dim);
            border: 1px solid rgba(201,76,76,0.3);
            color: var(--danger);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            text-align: left;
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s ease;
            border: none;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-primary { background: var(--gold); color: #0B0F17; width: 100%; }
        .btn-primary:hover { background: var(--gold-light); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,168,76,0.3); }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3A4558; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="lock-icon">👑</div>
        <div class="super-badge">✦ Super Admin</div>
        <h2>Super Admin Access</h2>
        <p>Restricted area. Enter your super admin password to continue.</p>

        <?php if (!empty($loginError)): ?>
            <div class="login-error">⚠ &nbsp;<?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="superadmin_login" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input
                class="login-field"
                type="password"
                name="password"
                placeholder="Super admin password"
                autofocus
                autocomplete="current-password"
            >
            <button type="submit" class="btn btn-primary">
                Unlock Super Panel →
            </button>
        </form>
    </div>
</body>
</html>
