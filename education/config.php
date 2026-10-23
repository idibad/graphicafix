<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Education Portal — Self-Contained Configuration
//  Production-safe: works on same root OR separate domain/hosting account.
// ══════════════════════════════════════════════════════════════════════════════

// ── Cache-control headers ────────────────────────────────────────────────────
if (!headers_sent()) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ── Try loading the main site's core/config.php (same-server / same-root) ───
//    If the portal is on a separate domain with its own document root,
//    this file won't exist and we fall through to our own bootstrap below.
$_main_config = __DIR__ . '/../core/config.php';
if (file_exists($_main_config)) {
    require_once $_main_config;
    // Main config already started a session and connected to DB.
    // We just need to define the portal-specific constants below.
} else {
    // ── Standalone bootstrap (separate domain / isolated document root) ─────
    ob_start();

    // Session timeout: 24 hours default, 30 days if remember_me
    $default_timeout = isset($_ENV['SESSION_TIMEOUT']) ? intval($_ENV['SESSION_TIMEOUT']) : 86400; // 24 hours
    $has_remember = (!empty($_COOKIE['remember_token']) || (!empty($_SESSION) && !empty($_SESSION['remember_me'])));
    $session_lifetime = $has_remember ? (30 * 86400) : $default_timeout;

    ini_set('session.gc_maxlifetime', (string)max(30 * 86400, $session_lifetime));

    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        session_set_cookie_params([
            'lifetime' => $has_remember ? (30 * 86400) : 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (!empty($_SESSION['user_id']) || !empty($_SESSION['username'])) {
        $timeout_duration = (!empty($_SESSION['remember_me'])) ? (30 * 86400) : $default_timeout;
        if (
            isset($_SESSION['LAST_ACTIVITY']) &&
            (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration
        ) {
            session_unset();
            session_destroy();
            header("Location: login.php?expired=1");
            exit();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }

    // Load .env / env file (look in portal dir first, then one level up)
    $env_paths = [
        __DIR__ . '/.env',
        __DIR__ . '/../.env',
        __DIR__ . '/../env',
    ];
    foreach ($env_paths as $env_path) {
        if (file_exists($env_path)) {
            $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    $_ENV[$key]    = $val;
                    $_SERVER[$key] = $val;
                    putenv("$key=$val");
                }
            }
            break;
        }
    }

    // Dynamically determine BASE_URL
    if (!defined('BASE_URL')) {
        if (isset($_ENV['SITE_URL']) && !empty($_ENV['SITE_URL'])) {
            define('BASE_URL', rtrim($_ENV['SITE_URL'], '/') . '/');
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443)
                ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            if ($host === 'localhost' || $host === '127.0.0.1') {
                define('BASE_URL', $protocol . $host . '/graphicafix/');
            } else {
                define('BASE_URL', $protocol . $host . '/');
            }
        }
    }

    // DB connection
    $server   = $_ENV['DB_SERVER']   ?? ($_SERVER['DB_SERVER']   ?? (getenv('DB_SERVER')   ?: 'localhost'));
    $username = $_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: 'root'));
    $password = $_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') ?: ''));
    $db       = $_ENV['DB_DATABASE'] ?? ($_SERVER['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: 'graphica_fix_db'));

    $conn = @mysqli_connect($server, $username, $password, $db);
    if (!$conn) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
  Database connection failed. Please contact support.
  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
</div>";
    }

    date_default_timezone_set('Asia/Karachi');
    if ($conn) {
        $conn->query("SET time_zone = '+05:00'");
    }

    // Payment gateway constants (mirror from main config)
    if (!defined('SAFEPAY_ENVIRONMENT')) {
        define('SAFEPAY_ENVIRONMENT', $_ENV['SAFEPAY_ENVIRONMENT'] ?? ($_SERVER['SAFEPAY_ENVIRONMENT'] ?? (getenv('SAFEPAY_ENVIRONMENT') ?: 'sandbox')));
        define('SAFEPAY_API_KEY',     $_ENV['SAFEPAY_API_KEY']     ?? ($_SERVER['SAFEPAY_API_KEY']     ?? (getenv('SAFEPAY_API_KEY')     ?: '')));
        define('SAFEPAY_API_URL',     SAFEPAY_ENVIRONMENT === 'sandbox' ? 'https://sandbox.api.getsafepay.com' : 'https://api.getsafepay.com');
    }
    if (!defined('JAZZCASH_ENVIRONMENT')) {
        define('JAZZCASH_ENVIRONMENT',    $_ENV['JAZZCASH_ENVIRONMENT']    ?? ($_SERVER['JAZZCASH_ENVIRONMENT']    ?? (getenv('JAZZCASH_ENVIRONMENT')    ?: 'sandbox')));
        define('JAZZCASH_MERCHANT_ID',    $_ENV['JAZZCASH_MERCHANT_ID']    ?? ($_SERVER['JAZZCASH_MERCHANT_ID']    ?? (getenv('JAZZCASH_MERCHANT_ID')    ?: '')));
        define('JAZZCASH_PASSWORD',       $_ENV['JAZZCASH_PASSWORD']       ?? ($_SERVER['JAZZCASH_PASSWORD']       ?? (getenv('JAZZCASH_PASSWORD')       ?: '')));
        define('JAZZCASH_INTEGRITY_SALT', $_ENV['JAZZCASH_INTEGRITY_SALT'] ?? ($_SERVER['JAZZCASH_INTEGRITY_SALT'] ?? (getenv('JAZZCASH_INTEGRITY_SALT') ?: '')));
        define('JAZZCASH_POST_URL',       JAZZCASH_ENVIRONMENT === 'sandbox'
            ? 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/'
            : 'https://jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  Portal-Specific Constants
//  These are defined regardless of whether we loaded main config or standalone.
// ══════════════════════════════════════════════════════════════════════════════

// PORTAL_URL — the web-accessible base URL of this portal directory.
// Reads from .env PORTAL_URL, or falls back to BASE_URL . 'portal/'
if (!defined('PORTAL_URL')) {
    if (!empty($_ENV['PORTAL_URL'])) {
        define('PORTAL_URL', rtrim($_ENV['PORTAL_URL'], '/') . '/');
    } else {
        define('PORTAL_URL', rtrim(BASE_URL, '/') . '/portal/');
    }
}

// MAIN_SITE_URL — the main Graphicafix website URL.
// Used for links like "Back to Main Site", "Forgot Password", and certificate logos.
if (!defined('MAIN_SITE_URL')) {
    if (!empty($_ENV['MAIN_SITE_URL'])) {
        define('MAIN_SITE_URL', rtrim($_ENV['MAIN_SITE_URL'], '/') . '/');
    } else {
        // Fallback: same as BASE_URL (works for same-domain setups)
        define('MAIN_SITE_URL', BASE_URL);
    }
}

// PORTAL_UPLOADS_PATH — absolute filesystem path to portal/uploads/
// All file uploads (assignments, lectures) go here instead of ../admin/uploads/
if (!defined('PORTAL_UPLOADS_PATH')) {
    define('PORTAL_UPLOADS_PATH', rtrim(__DIR__, '/') . '/uploads/');
}

// PORTAL_UPLOADS_URL — web-accessible URL to portal/uploads/
// Used to generate public download/stream links for uploaded files.
if (!defined('PORTAL_UPLOADS_URL')) {
    define('PORTAL_UPLOADS_URL', rtrim(PORTAL_URL, '/') . '/uploads/');
}
?>
