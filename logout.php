<?php
require_once __DIR__ . '/bootstrap.php';

// Auth::logout() already destroys the session
Auth::logout();

// Redirect to login
header('Location: login.php');
exit;
