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


// Load .env variables
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

define('BASE_URL', 'http://localhost:8000/graphicafix/');

$server = $_ENV['DB_SERVER'] ?? "localhost";
$username = $_ENV['DB_USERNAME'] ?? "root";
$password = $_ENV['DB_PASSWORD'] ?? "root";
$db = $_ENV['DB_DATABASE'] ?? "graphica_fix_db";



$conn = @mysqli_connect($server, $username, $password, $db);

if (!$conn) {

    echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
  Connection unsuccesful
  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
</div>";
}
date_default_timezone_set('Asia/Karachi');
$conn->query("SET time_zone = '+05:00'");

define('SAFEPAY_ENVIRONMENT', $_ENV['SAFEPAY_ENVIRONMENT'] ?? 'sandbox');
define('SAFEPAY_API_KEY', $_ENV['SAFEPAY_API_KEY'] ?? '');
define('SAFEPAY_API_URL', SAFEPAY_ENVIRONMENT === 'sandbox' ? 'https://sandbox.api.getsafepay.com' : 'https://api.getsafepay.com');

// JazzCash Configuration
define('JAZZCASH_ENVIRONMENT', $_ENV['JAZZCASH_ENVIRONMENT'] ?? 'sandbox');
define('JAZZCASH_MERCHANT_ID', $_ENV['JAZZCASH_MERCHANT_ID'] ?? '');
define('JAZZCASH_PASSWORD', $_ENV['JAZZCASH_PASSWORD'] ?? '');
define('JAZZCASH_INTEGRITY_SALT', $_ENV['JAZZCASH_INTEGRITY_SALT'] ?? '');
define('JAZZCASH_POST_URL', JAZZCASH_ENVIRONMENT === 'sandbox' ? 'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/' : 'https://jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/');
?>


<link href="<?= BASE_URL ?>assets/css/bootstrap.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/icon.png">