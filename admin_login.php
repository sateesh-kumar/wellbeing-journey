<?php
require_once __DIR__ . '/bootstrap.php';

// Secure session cookie before starting session
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

// If already authenticated, go straight to dashboard
if (isset($_SESSION['admin_authenticated'])) {
    header('Location: admin_dashboard.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError = '';

if (isset($_POST['admin_login'])) {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid request. Please try again.';
    } else {
        // Small delay to slow brute-force attempts
        sleep(1);

        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $loginError = 'Please enter both email and password.';
        } else {
            try {
                $pdo  = Database::connect();
                $stmt = $pdo->prepare(
                    "SELECT id, email, password, is_admin
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password']) && !empty($user['is_admin'])) {
                    // All good — regenerate session and log in
                    session_regenerate_id(true);
                    $_SESSION['admin_authenticated'] = true;
                    $_SESSION['admin_id']            = $user['id'];
                    $_SESSION['admin_email']         = $user['email'];
                    $_SESSION['admin_tenant_id']     = $user['tenant_id'];
                    header('Location: admin_dashboard.php');
                    exit;
                } else {
                    // Deliberately vague — don't reveal whether email exists or is_admin is false
                    $loginError = 'Invalid credentials or insufficient privileges.';
                }
            } catch (PDOException $e) {
                error_log('Admin login DB error: ' . $e->getMessage());
                $loginError = 'A server error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Login</title>
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
            width: 400px;
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
        .field-label {
            display: block;
            text-align: left;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .field-wrap {
            margin-bottom: 16px;
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
        .btn-primary { background: var(--gold); color: #0B0F17; width: 100%; margin-top: 8px; }
        .btn-primary:hover { background: var(--gold-light); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,168,76,0.3); }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3A4558; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="lock-icon">🔐</div>
        <h2>Admin Access</h2>
        <p>Sign in with your admin credentials to continue.</p>

        <?php if (!empty($loginError)): ?>
            <div class="login-error">⚠ &nbsp;<?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="admin_login" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="field-wrap">
                <label class="field-label" for="email">Email Address</label>
                <input
                    class="login-field"
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autofocus
                    autocomplete="email"
                    required
                >
            </div>

            <div class="field-wrap">
                <label class="field-label" for="password">Password</label>
                <input
                    class="login-field"
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Unlock Panel →
            </button>
        </form>
    </div>
</body>
</html>
