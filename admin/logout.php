<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/attendance_helper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log activity upon session logout (Note: Daily checkout is explicit via header button or auto end-of-day)
$logout_user_id = $_SESSION['user_id'] ?? 0;
if ($logout_user_id && isset($conn)) {
    logActivity($conn, 'logout', "User logged out of session", 'user', $logout_user_id);
}

// Clear Remember Me token from DB and delete the persistent cookie
if (function_exists('clearRememberToken') && isset($conn)) {
    clearRememberToken($conn);
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
