<?php
/**
 * visitor_counter.php
 * Include this file on any page where you want to count visitors.
 *
**/

// ── Require config if $conn not already set ───────────────────────────────────
if (!isset($conn)) {
    require_once 'core/config.php';
}



// ── Skip counting for admin pages or logged-in users ─────────────────────────
$_currentURI = $_SERVER['REQUEST_URI'] ?? '';
$_adminPaths = ['/admin', '/dashboard'];   // add any other admin paths here

foreach ($_adminPaths as $_path) {
    if (stripos($_currentURI, $_path) !== false) return;
}

// Also skip if a user is logged in (they're not a public visitor)
if (!empty($_SESSION['user_id'])) return;

// ── Get real IP (handles proxies) ─────────────────────────────────────────────
function getVisitorIP() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ── Count this visit ──────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$ip         = getVisitorIP();
$userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$page       = substr($_SERVER['REQUEST_URI']     ?? '', 0, 255);
$sessionKey = 'visited_' . $today;  // resets each day — same person counts once per day

$isUniqueToday = !isset($_SESSION[$sessionKey]);

// Check if this IP already visited today (double-check even if session exists)
if ($isUniqueToday) {
    $check = $conn->prepare("
        SELECT id FROM visitor_logs
        WHERE ip_address = ? AND DATE(visited_at) = ?
        LIMIT 1
    ");
    $check->bind_param("ss", $ip, $today);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // IP already logged today — session must have been cleared, just mark it
        $isUniqueToday = false;
    }
    $check->close();
}

// Log the visit
$logStmt = $conn->prepare("
    INSERT INTO visitor_logs (ip_address, user_agent, page)
    VALUES (?, ?, ?)
");
$logStmt->bind_param("sss", $ip, $userAgent, $page);
$logStmt->execute();
$logStmt->close();

// Upsert daily stats
if ($isUniqueToday) {
    // New unique visitor today
    $_SESSION[$sessionKey] = true;

    $conn->query("
        INSERT INTO visitor_stats (stat_date, unique_visits, total_visits)
        VALUES ('$today', 1, 1)
        ON DUPLICATE KEY UPDATE
            unique_visits = unique_visits + 1,
            total_visits  = total_visits  + 1
    ");
} else {
    // Returning visitor today — only increment total
    $conn->query("
        INSERT INTO visitor_stats (stat_date, unique_visits, total_visits)
        VALUES ('$today', 0, 1)
        ON DUPLICATE KEY UPDATE
            total_visits = total_visits + 1
    ");
}
?>