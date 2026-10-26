<?php
ob_start();

// 1. Load .env variables (check both .env and env)
$env_paths = [__DIR__ . '/../.env', __DIR__ . '/../env'];
foreach ($env_paths as $env_path) {
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                putenv("$key=$val");
            }
        }
        break;
    }
}

// 2. Dynamically determine BASE_URL for both localhost (/graphicafix/) and live hosting (/)
if (!defined('BASE_URL')) {
    if (isset($_ENV['SITE_URL']) && !empty($_ENV['SITE_URL'])) {
        define('BASE_URL', rtrim($_ENV['SITE_URL'], '/') . '/');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            define('BASE_URL', $protocol . $host . '/graphicafix/');
        } else {
            define('BASE_URL', $protocol . $host . '/');
        }
    }
}

// 3. Configure Session & Cookie Lifetime
// Default idle timeout increased from 1800s (30m) to 86400s (24 hours).
// If Remember Me is checked, timeout is extended to 30 days (2,592,000s).
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

// 4. Database Connection
$server = $_ENV['DB_SERVER'] ?? ($_SERVER['DB_SERVER'] ?? (getenv('DB_SERVER') ?: "localhost"));
$username = $_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: "root"));
$password = $_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') ?: ""));
$db = $_ENV['DB_DATABASE'] ?? ($_SERVER['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: "graphica_fix_db"));

$conn = @mysqli_connect($server, $username, $password, $db);

if (!$conn) {
    echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
  Connection unsuccesful
  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
</div>";
}

date_default_timezone_set('Asia/Karachi');
if ($conn) {
    $conn->query("SET time_zone = '+05:00'");
}

// 5. Authentication & Remember-Me Helper
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/activity_logger.php';

// Auto-login via remember_token if not currently logged in
if (empty($_SESSION['user_id']) && $conn) {
    checkRememberTokenAutoLogin($conn);
}

// 6. Session Timeout Enforcement (only for authenticated users)
if (!empty($_SESSION['user_id']) || !empty($_SESSION['username'])) {
    $timeout_duration = (!empty($_SESSION['remember_me'])) ? (30 * 86400) : $default_timeout;

    if (
        isset($_SESSION['LAST_ACTIVITY']) &&
        (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration
    ) {
        if (function_exists('clearRememberToken') && isset($conn)) {
            clearRememberToken($conn);
        }
        session_unset();
        session_destroy();

        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        if (strpos($script, '/portal/') !== false || strpos($script, '/education/') !== false) {
            $login_redirect = (defined('PORTAL_URL') ? PORTAL_URL : BASE_URL . 'portal/') . 'login.php?expired=1';
        } else {
            $login_redirect = BASE_URL . 'admin/login.php?expired=1';
        }
        header("Location: " . $login_redirect);
        exit();
    }

    // Update last activity timestamp
    $_SESSION['LAST_ACTIVITY'] = time();
}

// 7. Payment Gateway Constants
define('SAFEPAY_ENVIRONMENT', $_ENV['SAFEPAY_ENVIRONMENT'] ?? ($_SERVER['SAFEPAY_ENVIRONMENT'] ?? (getenv('SAFEPAY_ENVIRONMENT') ?: 'sandbox')));
define('SAFEPAY_API_KEY', $_ENV['SAFEPAY_API_KEY'] ?? ($_SERVER['SAFEPAY_API_KEY'] ?? (getenv('SAFEPAY_API_KEY') ?: '')));
define('SAFEPAY_API_URL', SAFEPAY_ENVIRONMENT === 'sandbox' ? 'https://sandbox.api.getsafepay.com' : 'https://api.getsafepay.com');

// JazzCash Configuration
define('JAZZCASH_ENVIRONMENT', $_ENV['JAZZCASH_ENVIRONMENT'] ?? ($_SERVER['JAZZCASH_ENVIRONMENT'] ?? (getenv('JAZZCASH_ENVIRONMENT') ?: 'sandbox')));
define('JAZZCASH_MERCHANT_ID', $_ENV['JAZZCASH_MERCHANT_ID'] ?? ($_SERVER['JAZZCASH_MERCHANT_ID'] ?? (getenv('JAZZCASH_MERCHANT_ID') ?: '')));
define('JAZZCASH_PASSWORD', $_ENV['JAZZCASH_PASSWORD'] ?? ($_SERVER['JAZZCASH_PASSWORD'] ?? (getenv('JAZZCASH_PASSWORD') ?: '')));
define('JAZZCASH_INTEGRITY_SALT', $_ENV['JAZZCASH_INTEGRITY_SALT'] ?? ($_SERVER['JAZZCASH_INTEGRITY_SALT'] ?? (getenv('JAZZCASH_INTEGRITY_SALT') ?: '')));
define('JAZZCASH_POST_URL', JAZZCASH_ENVIRONMENT === 'sandbox' ? 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/' : 'https://jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/');
?>
