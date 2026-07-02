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


define('BASE_URL', 'http://localhost:8000/graphicafix/');

$server = "localhost";
$username = "root";
$password = "root";
$db = "graphica_fix_db";



$conn = @mysqli_connect($server, $username, $password, $db);

if (!$conn) {

    echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
  Connection unsuccesful
  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
</div>";
}
date_default_timezone_set('Asia/Karachi');
$conn->query("SET time_zone = '+05:00'");

define('SAFEPAY_ENVIRONMENT', 'sandbox');
define('SAFEPAY_API_KEY', 'sec_34f28f5b-da25-439c-bdee-ed067cc9eded');
define('SAFEPAY_API_URL', SAFEPAY_ENVIRONMENT === 'sandbox' ? 'https://sandbox.api.getsafepay.com' : 'https://api.getsafepay.com');
?>


<link href="<?= BASE_URL ?>css/bootstrap.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= BASE_URL ?>images/icon.png">