<?php
// includes/auth.php

if (session_status() == PHP_SESSION_NONE) {
    // Set secure session parameters
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Regenerate session ID periodically to prevent session fixation
 */
if (!isset($_SESSION['created_time'])) {
    $_SESSION['created_time'] = time();
} elseif (time() - $_SESSION['created_time'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created_time'] = time();
}

/**
 * Check if the admin is logged in. Redirect to login page if not.
 */
function require_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Check if the admin is logged in (from root directory files)
 */
function require_admin_login_root() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Generate a CSRF token and store it in session
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the session token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
