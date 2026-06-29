<?php
/**
 * login.php — MULTI-TENANT VERSION
 * 
 * Works with:
 * - Tenant::detect() for tenant context
 * - Auth class for authentication
 * - Session for CSRF protection
 * 
 * Features:
 * - Signup creates new tenant automatically
 * - Login validates user in current tenant context
 * - CSRF protection on all forms
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

// Already logged in → redirect to dashboard
if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = Database::connect();

// ── Google OAuth client — credentials from .env ───────────────────────────────
$googleClient = new Google\Client();
$googleClient->setClientId(env('GOOGLE_CLIENT_ID'));
$googleClient->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
$googleClient->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
$googleClient->addScope('email');
$googleClient->addScope('profile');
$googleAuthUrl = $googleClient->createAuthUrl();

$mode   = $_GET['mode'] ?? 'login';   // 'login' | 'signup'
$errors = [];
$values = ['name' => '', 'email' => ''];

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $mode = $_POST['mode'] ?? 'login';

    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if ($mode === 'signup') {
        // ── Sign-up ────────────────────────────────────────────────────────
        $name            = trim($_POST['name'] ?? '');
        $passwordConfirm =       $_POST['password_confirm'] ?? '';
        $values['name']  = htmlspecialchars($name);
        $values['email'] = htmlspecialchars($email);

        if ($name === '')                               $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if (strlen($password) < 8)                     $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $passwordConfirm)             $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // ── Check if email exists (across all tenants) ───────────────────
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                try {
                    // ── Create user with tenant_id = 0 (no organisation yet) ──
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $userStmt = $pdo->prepare(
                        "INSERT INTO users (name, email, password, tenant_id, created_at)
                         VALUES (?, ?, ?, 0, NOW())"
                    );
                    $userStmt->execute([$name, $email, $hash]);
                    $userId = $pdo->lastInsertId();

                    // ── Fetch user data ──────────────────────────────────────
                    $fetchStmt = $pdo->prepare(
                        "SELECT id, tenant_id, name, email, created_at FROM users WHERE id = ?"
                    );
                    $fetchStmt->execute([$userId]);
                    $user = $fetchStmt->fetch(PDO::FETCH_ASSOC);

                    // ── Log in and go straight to dashboard ──────────────────
                    if ($user) {
                        Auth::login($user);
                        header('Location: dashboard.php');
                        exit;
                    }

                } catch (Exception $e) {
                    $errors[] = 'Unable to create account. Please try again.';
                }
            }
        }

    } else {
        // ── Login ──────────────────────────────────────────────────────────
        $values['email'] = htmlspecialchars($email);

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter your email and password.';
        } else {
            // ── Find user by email ─────────────────────────────────────────
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Incorrect email or password.';
            } else {
                // ── Login user and go straight to dashboard ────────────────
                Auth::login($user);
                header('Location: dashboard.php');
                exit;
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
    <title><?= $mode === 'signup' ? 'Create Account' : 'Sign In' ?> — Mental Wellness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0D1117;
            --surface:   #141B24;
            --surface2:  #1A2332;
            --border:    rgba(255,255,255,0.07);
            --gold:      #C9A84C;
            --gold-dim:  rgba(201,168,76,0.15);
            --text:      #F0EBE1;
            --muted:     #4E5D72;
            --muted2:    #8A9BB0;
            --error:     #D15C5C;
            --radius:    14px;
        }

        html, body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -20%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 70%);
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -20%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(76,175,125,0.04) 0%, transparent 70%);
            pointer-events: none;
        }

        .auth-card {
            width: 100%; max-width: 460px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 48px 44px;
            position: relative;
            animation: fadeUp .45s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .wordmark {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 36px;
        }
        .wordmark-icon {
            width: 38px; height: 38px;
            background: var(--gold-dim);
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .wordmark-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem; font-weight: 600; color: var(--text);
        }
        .wordmark-text span { color: var(--gold); }

        .auth-heading {
            font-family: 'Playfair Display', serif;
            font-size: 1.85rem; font-weight: 700;
            line-height: 1.2; margin-bottom: 8px;
            color: var(--text); letter-spacing: -0.02em;
        }
        .auth-subheading {
            font-size: 0.9rem; color: var(--muted2);
            margin-bottom: 32px; line-height: 1.5;
        }

        .auth-tabs {
            display: flex;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px; padding: 4px;
            margin-bottom: 28px;
        }
        .auth-tab {
            flex: 1; text-align: center;
            padding: 9px 0; border-radius: 7px;
            font-size: 0.875rem; font-weight: 500;
            color: var(--muted); cursor: pointer;
            text-decoration: none; transition: all 0.2s;
        }
        .auth-tab.active {
            background: var(--gold-dim); color: var(--gold);
            border: 1px solid rgba(201,168,76,0.25);
        }
        .auth-tab:hover:not(.active) { color: var(--muted2); }

        .error-list {
            background: rgba(209,92,92,0.08);
            border: 1px solid rgba(209,92,92,0.25);
            border-radius: 10px; padding: 12px 16px; margin-bottom: 20px;
        }
        .error-list p { color: var(--error); font-size: 0.85rem; line-height: 1.5; }
        .error-list p + p { margin-top: 4px; }

        .form-group { margin-bottom: 18px; }
        label {
            display: block; font-size: 0.8rem; font-weight: 500;
            color: var(--muted2); letter-spacing: 0.06em;
            text-transform: uppercase; margin-bottom: 7px;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px; padding: 13px 15px;
            font-size: 0.95rem; font-family: inherit;
            color: var(--text); outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus {
            border-color: rgba(201,168,76,0.5);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.08);
        }
        input::placeholder { color: var(--muted); }

        .password-wrap { position: relative; }
        .password-toggle {
            position: absolute; right: 13px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer;
            font-size: 0.8rem; font-family: inherit;
            padding: 4px 6px; border-radius: 5px;
            transition: color 0.15s;
        }
        .password-toggle:hover { color: var(--muted2); }

        .btn-auth {
            width: 100%;
            background: linear-gradient(135deg, #C9A84C, #a8873a);
            border: none; border-radius: 10px; padding: 14px;
            font-size: 0.95rem; font-family: inherit; font-weight: 600;
            color: #0D1117; cursor: pointer; letter-spacing: 0.01em;
            transition: opacity 0.2s, transform 0.15s; margin-top: 6px;
        }
        .btn-auth:hover  { opacity: 0.92; transform: translateY(-1px); }
        .btn-auth:active { opacity: 1;    transform: translateY(0); }

        .auth-footer {
            text-align: center; margin-top: 24px;
            font-size: 0.85rem; color: var(--muted);
        }
        .auth-footer a { color: var(--gold); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }

        .divider {
            display: flex; align-items: center; gap: 12px; margin: 22px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .divider span { font-size: 0.75rem; color: var(--muted); letter-spacing: 0.05em; }

        .btn-google {
            width: 100%; display: flex; align-items: center;
            justify-content: center; gap: 10px;
            background: #fff; border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px; padding: 13px;
            font-size: 0.95rem; font-family: inherit; font-weight: 500;
            color: #1f1f1f; cursor: pointer; text-decoration: none;
            transition: background 0.2s, transform 0.15s; margin-bottom: 6px;
        }
        .btn-google:hover { background: #f5f5f5; transform: translateY(-1px); }
        .btn-google svg { width: 20px; height: 20px; flex-shrink: 0; }

        @media (max-width: 480px) { .auth-card { padding: 36px 24px; } }
    </style>
</head>
<body>

<div class="auth-card">

    <div class="wordmark">
        <div class="wordmark-icon">✦</div>
        <div class="wordmark-text">Mental Wellness <span>Audit</span></div>
    </div>

    <h1 class="auth-heading">
        <?= $mode === 'signup' ? 'Create your account' : 'Welcome back' ?>
    </h1>
    <p class="auth-subheading">
        <?= $mode === 'signup'
            ? 'Start measuring and growing your wellbeing.'
            : 'Sign in to continue your wellbeing journey.' ?>
    </p>

    <div class="auth-tabs">
        <a href="login.php?mode=login"  class="auth-tab <?= $mode === 'login'  ? 'active' : '' ?>">Sign In</a>
        <a href="login.php?mode=signup" class="auth-tab <?= $mode === 'signup' ? 'active' : '' ?>">Create Account</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-list">
            <?php foreach ($errors as $e): ?>
                <p>⚠ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Google Login -->
    <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="btn-google">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continue with Google
    </a>

    <div class="divider"><span>or</span></div>

    <form method="POST" action="login.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="mode" value="<?= $mode ?>">

        <?php if ($mode === 'signup'): ?>
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= $values['name'] ?>"
                   placeholder="Jane Doe" autocomplete="name" required>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $values['email'] ?>"
                   placeholder="you@example.com" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password<?= $mode === 'signup' ? ' (min 8 characters)' : '' ?></label>
            <div class="password-wrap">
                <input type="password" id="password" name="password"
                       placeholder="••••••••" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required>
                <button type="button" class="password-toggle" onclick="togglePw('password', this)">Show</button>
            </div>
        </div>

        <?php if ($mode === 'signup'): ?>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <div class="password-wrap">
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="••••••••" autocomplete="new-password" required>
                <button type="button" class="password-toggle" onclick="togglePw('password_confirm', this)">Show</button>
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-auth">
            <?= $mode === 'signup' ? 'Create Account →' : 'Sign In →' ?>
        </button>
    </form>

    <div class="auth-footer">
        <?php if ($mode === 'login'): ?>
            Don't have an account? <a href="login.php?mode=signup">Sign up free</a>
        <?php else: ?>
            Already have an account? <a href="login.php?mode=login">Sign in</a>
        <?php endif; ?>
    </div>

</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? 'Hide' : 'Show';
}
</script>
</body>
</html>
