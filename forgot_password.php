<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/task_email_helper.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($user_id, $name);
                $stmt->fetch();
                
                // Generate a secure token
                $token = bin2hex(random_bytes(32));
                $hashed_token = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE user_id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("ssi", $hashed_token, $expires_at, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Send Email
                    $reset_link = BASE_URL . "reset_password.php?token=" . $token . "&email=" . urlencode($email);
                    
                    $subject = "Password Reset Request";
                    $heading = "Reset Your Password";
                    $bodyHtml = "
<p style='margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #0f172a;'>Hello " . htmlspecialchars($name) . ",</p>
<p style='margin: 0 0 24px 0; color: #475569;'>We received a request to reset your password for your Graphicafix account. Click the button below to set a new password.</p>
<div style='margin: 32px 0 20px 0; text-align: center;'>
    <a href='{$reset_link}' style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 14px rgba(2,68,66,0.25); letter-spacing: 0.3px;'>Reset Password &rarr;</a>
</div>
<p style='margin: 20px 0 0 0; color: #475569; font-size: 13px;'>If you did not request this, you can safely ignore this email. This link will expire in 1 hour.</p>";
                    
                    sendTaskEmail($email, $subject, $heading, $bodyHtml, 0);
                    $success_message = 'If this email exists in our system, a password reset link has been sent.';
                }
            } else {
                // Prevent email enumeration
                $success_message = 'If this email exists in our system, a password reset link has been sent.';
            }
            $stmt->close();
        } else {
            // Check if column exists, if not, show helpful error
            $error_message = 'Database error. Please run: ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL, ADD COLUMN reset_expires_at DATETIME DEFAULT NULL;';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password | Graphicafix</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .login-container { width: 100%; max-width: 420px; padding: 20px; }
    .login-card { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .login-logo { text-align: center; margin-bottom: 30px; }
    .login-logo img { max-height: 50px; }
    .login-header { text-align: center; margin-bottom: 24px; }
    .login-header h2 { font-size: 24px; font-weight: 700; color: #024442; margin-bottom: 8px; }
    .login-header p { color: #64748b; font-size: 14px; margin: 0; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #334155; font-size: 14px; }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 15px; outline: none; transition: border-color 0.2s; }
    .form-control:focus { border-color: #024442; box-shadow: 0 0 0 3px rgba(2,68,66,0.1); }
    .btn-login { width: 100%; padding: 14px; background: #024442; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .btn-login:hover { background: #035e5b; }
    .back-to-login { text-align: center; margin-top: 24px; font-size: 14px; }
    .back-to-login a { color: #024442; text-decoration: none; font-weight: 600; }
    .back-to-login a:hover { text-decoration: underline; }
    .alert { padding: 14px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce3; color: #166534; border: 1px solid #bbf7d0; }
</style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Graphicafix">
            </div>
            
            <div class="login-header">
                <h2>Forgot Password</h2>
                <p>Enter your email to receive a reset link.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php else: ?>
                <form action="forgot_password.php" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                    
                    <button type="submit" class="btn-login">Send Reset Link</button>
                </form>
            <?php endif; ?>
            
            <div class="back-to-login">
                <a href="admin/login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
