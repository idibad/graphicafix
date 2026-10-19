<?php
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    header("Location: /"); // redirect to home
    exit();
}
ob_start();
session_start();

// Session timeout duration (in seconds)
$timeout_duration = 1800; // 15 minutes

// Check if session has timed out
if (
    isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration
) {

    session_unset();   // remove all session variables
    session_destroy(); // destroy the session

    // Redirect to login with a message
    header("Location: /admin/login.php?expired=1");
    exit();
}

// Update last activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();


// Load .env variables (check both .env and env)
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

// Dynamically determine BASE_URL for both localhost (/graphicafix/) and live hosting (/)
if (!defined('BASE_URL')) {
    if (isset($_ENV['SITE_URL']) && !empty($_ENV['SITE_URL'])) {
        define('BASE_URL', rtrim($_ENV['SITE_URL'], '/') . '/');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        if ($host === 'localhost' || $host === '127.0.0.1') {
            define('BASE_URL', $protocol . $host . '/graphicafix/');
        } else {
            define('BASE_URL', $protocol . $host . '/');
        }
    }
}

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
$conn->query("SET time_zone = '+05:00'");

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