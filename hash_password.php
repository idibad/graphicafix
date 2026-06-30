<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

$password = 'pass@python2026';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users 
        SET password = '$hashedPassword' 
        WHERE user_id BETWEEN 46 AND 116";

if (mysqli_query($conn, $sql)) {

    echo "Passwords updated successfully.<br>";
    echo "Affected Rows: " . mysqli_affected_rows($conn);

} else {

    echo "SQL Error: " . mysqli_error($conn);

}

mysqli_close($conn);

?>