<?php
// With this:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy admin session
unset($_SESSION['superadmin_authenticated']);

// Optional: destroy entire session
// session_destroy();

// Redirect to login
header('Location: superadmin_login.php');
exit;
