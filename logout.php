<?php
// logout.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Log activity before destroying session if logged in
if (isset($_SESSION['admin_id'])) {
    log_activity($pdo, $_SESSION['admin_id'], "Admin Logout", "admin");
}

// Unset all session values
$_SESSION = [];

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to home
header("Location: index.php");
exit;
?>
