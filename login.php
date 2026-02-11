<?php 
require_once('config.php');

require_once('functions.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Graphicafix</title>

<link rel="stylesheet" href="css/bootstrap.css">
<link rel="stylesheet" href="css/all.css">
<link rel="stylesheet" href="css/style.css">

<meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

<div class="container-fluid login-wrapper">
    <div class="row min-vh-100">

        <!-- LEFT IMAGE -->
        <div class="col-lg-6 d-none d-lg-block login-left"></div>

        <!-- RIGHT SIDE -->
        <div class="col-lg-6 col-12 login-right">

            <div class="login-overlay"></div>

            <div class="form-cont">

                <img src="images/logo.png" alt="Graphicafix">

                <h3>Sign In</h3>

                <form method="POST">

                    <label class="label">Username</label>
                    <input type="text" name="username" placeholder="Username" required>

                    <label class="label">Password</label>
                    <input type="password" name="password" placeholder="Password" required>

                    <input type="submit" name="login" value="Sign In" class="main-btn">

                </form>

            </div>

        </div>
    </div>
</div><?php
session_start();
require_once("config.php"); // your DB connection



if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Get user record
    $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed_password);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $hashed_password)) {
            // Success - log it
            logLogin($conn, $user_id, 'success');

            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;

            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $user_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Wrong password - log it
            logLogin($conn, $user_id, 'failed');
            echo "<script>alert('Password does NOT match!');</script>";
        }
    } else {
        // User not found - log it with user_id = 0
        logLogin($conn, 0, 'failed');
        echo "<script>alert('User not found');</script>";
    }

    $stmt->close();
}

$conn->close();
?>


</body>
</html>
