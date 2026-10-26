<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id && function_exists('logActivity') && isset($conn)) {
    logActivity($conn, 'portal_logout', "User logged out from portal", 'user', $user_id);
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

session_destroy();
header("Location: login.php");
exit;
?>
