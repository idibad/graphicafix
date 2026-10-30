<?php
/**
 * visitor_counter.php
 * Track unique and total human visitors across public pages.
 */

// ── 1. Skip if error page (404, 403, 500, etc.) ─────────────────────────────
if (http_response_code() >= 400) {
    return;
}

// ── 2. Require DB config if not already loaded ──────────────────────────────
if (!isset($conn) || !($conn instanceof mysqli)) {
    $configFile = __DIR__ . '/core/config.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    }
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    return;
}

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ── 3. Skip counting for admin pages, API endpoints, or logged-in users ──────
$_currentURI = $_SERVER['REQUEST_URI'] ?? '';
$_adminPaths = ['/admin', '/dashboard', '/api', '/cron', '/ajax'];

foreach ($_adminPaths as $_path) {
    if (stripos($_currentURI, $_path) !== false) {
        return;
    }
}

// Skip if an authenticated system user is logged in
if (!empty($_SESSION['user_id'])) {
    return;
}

// ── 4. Skip static assets / file extensions ──────────────────────────────────
$urlPath = parse_url($_currentURI, PHP_URL_PATH) ?? '';
$ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
$ignoredExts = [
    'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
    'css', 'js', 'map', 'woff', 'woff2', 'ttf', 'eot',
    'json', 'xml', 'txt', 'pdf', 'mp4', 'webm', 'mp3', 'zip'
];

if (!empty($ext) && in_array($ext, $ignoredExts, true)) {
    return;
}

// ── 5. Skip automated bots, spiders, crawlers, and empty User-Agents ─────────
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (empty($userAgent)) {
    return;
}

$botPattern = '/(bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|curl|wget|python|postman|insomnia|headless|ahrefs|semrush|yandex|baidu|bingbot|googlebot|bytespider|duckduckgo|dotbot|mj12bot)/i';
if (preg_match($botPattern, $userAgent)) {
    return;
}

// ── 6. Get Client IP Address ────────────────────────────────────────────────
if (!function_exists('getVisitorIP')) {
    function getVisitorIP() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $raw = explode(',', $_SERVER[$key])[0];
                $ip = trim($raw);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// ── 7. Count this visit ──────────────────────────────────────────────────────
$today      = date('Y-m-d');
$ip         = getVisitorIP();
$uaStored   = substr($userAgent, 0, 500);
$pageStored = substr($_currentURI, 0, 255);
$sessionKey = 'visited_' . $today;

$isUniqueToday = !isset($_SESSION[$sessionKey]);

// Double-check if this IP has already visited today
if ($isUniqueToday) {
    if ($check = $conn->prepare("SELECT id FROM visitor_logs WHERE ip_address = ? AND DATE(visited_at) = ? LIMIT 1")) {
        $check->bind_param("ss", $ip, $today);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $isUniqueToday = false;
        }
        $check->close();
    }
}

// Log visit to visitor_logs
if ($logStmt = $conn->prepare("INSERT INTO visitor_logs (ip_address, user_agent, page) VALUES (?, ?, ?)")) {
    $logStmt->bind_param("sss", $ip, $uaStored, $pageStored);
    $logStmt->execute();
    $logStmt->close();
}

// Upsert daily stats into visitor_stats
if ($isUniqueToday) {
    $_SESSION[$sessionKey] = true;
    $conn->query("
        INSERT INTO visitor_stats (stat_date, unique_visits, total_visits)
        VALUES ('$today', 1, 1)
        ON DUPLICATE KEY UPDATE
            unique_visits = unique_visits + 1,
            total_visits  = total_visits  + 1
    ");
} else {
    $conn->query("
        INSERT INTO visitor_stats (stat_date, unique_visits, total_visits)
        VALUES ('$today', 0, 1)
        ON DUPLICATE KEY UPDATE
            total_visits = total_visits + 1
    ");
}