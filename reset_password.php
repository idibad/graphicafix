<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include your database connection here
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

$error_msg = '';
$success_msg = '';
$valid_token = false;
$user_email = '';

// ==============================================================================
// 1. VERIFY THE TOKEN FROM THE URL
// ==============================================================================
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and isn't expired (Valid for 24 hours)
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at >= NOW() - INTERVAL 1 DAY");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
        $row = $result->fetch_assoc();
        $user_email = $row['email'];
    } else {
        $error_msg = "This password reset link is invalid or has expired. Please request a new one from the login page.";
    }
    $stmt->close();
} else {
    $error_msg = "No reset token provided. Please use the link sent to your email.";
}

// ==============================================================================
// 2. HANDLE THE PASSWORD UPDATE
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password']) && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 8) {
        $error_msg = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "Passwords do not match. Please type them carefully.";
    } else {
        // Hash the new password securely
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the user's password in your users table
        $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $user_email);
        
        if ($update->execute()) {
            // Delete the token so it cannot be reused by a hacker
            $conn->query("DELETE FROM password_resets WHERE email = '$user_email'");
            
            $success_msg = "Your password has been successfully updated!";
            $valid_token = false; // Hide the form now that it's done
        } else {
            $error_msg = "An error occurred while updating your password. Please try again.";
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password | Graphicafix</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { 
        background: #f4f6f8; 
        font-family: 'Inter', system-ui, -apple-system, sans-serif; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        min-height: 100vh; 
        margin: 0; 
        padding: 20px;
    }

    .reset-card { 
        background: #fff; 
        width: 100%; 
        max-width: 420px; 
        padding: 40px; 
        border-radius: 20px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        text-align: center; 
    }

    .reset-card img {
        max-height: 40px;
        margin-bottom: 24px;
    }

    h3 {
        font-weight: 800; 
        color: #0f172a; 
        margin: 0 0 24px 0;
        font-size: 24px;
    }

    label {
        display: block; 
        font-size: 13.5px; 
        font-weight: 700; 
        color: #0f172a; 
        margin-bottom: 8px; 
        text-align: left;
    }

    .modern-input {
        width: 100%; 
        box-sizing: border-box; 
        padding: 14px 16px; 
        border: 2px solid #e2e8f0; 
        border-radius: 12px; 
        font-size: 14.5px; 
        background: #f8fafc; 
        outline: none; 
        transition: all 0.3s ease; 
        font-family: inherit; 
        margin-bottom: 20px; 
        color: #333;
    }

    .modern-input:focus { 
        border-color: #024442; 
        background: #fff; 
        box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.1); 
    }

    .modern-btn {
        width: 100%; 
        box-sizing: border-box; 
        background: #024442; 
        color: #fff; 
        border: none; 
        padding: 15px; 
        border-radius: 12px; 
        font-size: 15px; 
        font-weight: 700; 
        cursor: pointer; 
        transition: all 0.3s ease; 
        box-shadow: 0 4px 14px rgba(2, 68, 66, 0.2); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 8px;
        text-decoration: none;
    }

    .modern-btn:hover { 
        background: #01302e; 
        transform: translateY(-2px); 
        box-shadow: 0 6px 20px rgba(2, 68, 66, 0.3); 
    }

    /* Alerts */
    .alert {
        padding: 14px 16px; 
        border-radius: 12px; 
        margin-bottom: 24px; 
        font-size: 14px; 
        font-weight: 600; 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        text-align: left;
    }
    .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
    .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }

    .password-wrapper { position: relative; display: block; width: 100%; }
    .password-wrapper .modern-input { padding-right: 50px; }
    
    .toggle-password-btn {
        position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: #94a3b8; font-size: 16px; cursor: pointer;
        padding: 0; margin: auto; transition: color 0.2s;
    }
    .toggle-password-btn:hover { color: #024442; }
    .toggle-password-btn:focus { outline: none; }
</style>
</head>
<body>

<div class="reset-card">
    <img src="/images/logo.png" alt="Graphicafix">
    <h3>Set New Password</h3>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle" style="font-size:18px;"></i> <?= $error_msg ?>
        </div>
        <?php if (!$valid_token): ?>
            <a href="admin/login.php" class="modern-btn" style="background:#f1f5f9; color:#475569; box-shadow:none;">Return to Login</a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size:18px;"></i> <?= $success_msg ?>
        </div>
        <a href="admin/login.php" class="modern-btn">Sign In Now <i class="fas fa-arrow-right"></i></a>
    <?php endif; ?>

    <?php if ($valid_token): ?>
        <form method="POST">
            <label>New Password</label>
            <div class="password-wrapper">
                <input type="password" id="newPass" name="new_password" class="modern-input" placeholder="Min. 8 characters" required>
                <button type="button" class="toggle-password-btn" onclick="togglePass('newPass', this)"><i class="fas fa-eye"></i></button>
            </div>

            <label>Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" id="confPass" name="confirm_password" class="modern-input" placeholder="Type password again" required>
                <button type="button" class="toggle-password-btn" onclick="togglePass('confPass', this)"><i class="fas fa-eye"></i></button>
            </div>

            <button type="submit" name="update_password" class="modern-btn" style="margin-top: 10px;">
                <i class="fas fa-lock"></i> Update Password
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>

<script src="assets/js/form-validator.js"></script>
</body>
</html>