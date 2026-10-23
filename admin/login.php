<?php 
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$login_input = '';
$error_message = '';
$reset_success = '';
$reset_error = '';

// Check session expired or deactivated notice
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error_message = 'Your session has expired due to inactivity. Please sign in again.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'deactivated') {
    $error_message = 'Your account has been deactivated. Please contact support.';
}

// Redirect if already logged in (unless displaying an expired notice)
if (!empty($_SESSION['user_id']) && empty($_GET['expired'])) {
    header("Location: index.php");
    exit();
}

// ==============================================================================
// 1. LOGIN LOGIC
// ==============================================================================
if (isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']);
    $password = trim($_POST['password']);
    $remember_me = !empty($_POST['remember_me']);

    if (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        $search_alias = ($login_input === 'ibad') ? 'ibadshalmany' : $login_input;
        $stmt = $conn->prepare("SELECT user_id, password, username, status FROM users WHERE username = ? OR username = ? OR email = ?");
        $stmt->bind_param("sss", $login_input, $search_alias, $login_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows >= 1) {
            $stmt->bind_result($user_id, $hashed_password, $username, $status);
            $found_user = false;
            $is_deactivated_account = false;

            while ($stmt->fetch()) {
                if (password_verify($password, $hashed_password)) {
                    $found_user = true;
                    $user_status = strtolower(trim($status ?? 'active'));
                    
                    if ($user_status !== 'active') {
                        $is_deactivated_account = true;
                        break;
                    }

                    logLogin($conn, $user_id, 'success');

                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;

                    if ($remember_me && function_exists('createRememberToken')) {
                        createRememberToken($conn, $user_id);
                        $_SESSION['remember_me'] = true;
                    } else if (function_exists('clearRememberToken')) {
                        clearRememberToken($conn);
                        unset($_SESSION['remember_me']);
                    }

                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->bind_param("i", $user_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    header("Location: index.php");
                    exit();
                }
            }

            if ($is_deactivated_account) {
                logLogin($conn, NULL, 'failed');
                $error_message = 'Your account has been deactivated. Please contact support.';
            } else if (!$found_user && empty($error_message)) {
                logLogin($conn, NULL, 'failed');
                $error_message = 'Incorrect password. Please try again.';
            }
        } else {
            logLogin($conn, NULL, 'failed');
            $error_message = 'User not found. Please check your username or email.';
        }
        $stmt->close();
    }
}

// ==============================================================================
// 2. FORGOT PASSWORD LOGIC
// ==============================================================================
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $reset_email = trim($_POST['reset_email'] ?? '');
    
    if (filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            
            $conn->query("DELETE FROM password_resets WHERE email = '$reset_email'");
            
            $insert = $conn->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
            $insert->bind_param("ss", $reset_email, $token);
            $insert->execute();
            
            $resetLink = "https://graphicafix.com/reset_password.php?token=" . $token;

            $html_content = "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
            <body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
            <div style='max-width:580px;margin:36px auto;'>
                <div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'>
                    <img src='https://graphicafix.com/images/logo.png' alt='Graphicafix' style='height:36px;margin-bottom:15px;'>
                    <h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>Password Reset</h1>
                </div>
                <div style='background:#fff;padding:32px 40px; font-size:15px; color:#333; line-height:1.7; text-align:center;'>
                    <p style='margin:0 0 20px;'>We received a request to reset your password. Click the button below to choose a new one. This link will expire in 24 hours.</p>
                    <a href='$resetLink' style='display:inline-block;background:#024442;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:bold;font-size:15px;'>Reset Password</a>
                    <p style='margin:24px 0 0;font-size:13px;color:#888;'>If you did not request this, please ignore this email. Your password will remain unchanged.</p>
                </div>
            </div></body></html>";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Graphicafix <noreply@graphicafix.com>\r\n";

            if (mail($reset_email, "Reset Your Graphicafix Password", $html_content, $headers)) {
                // Success message when email exists AND mail sends
                $reset_success = "A password reset link has been sent to your email address.";
            } else {
                $reset_error = "Failed to send email. Please check server mail configuration.";
            }
        } else {
            // Explicit error when email is not found
            $reset_error = "We could not find an account registered with that email address.";
        }
    } else {
        $reset_error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Graphicafix</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/all.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Top Navigation Links */
    .login-nav {
        position: absolute; top: 30px; left: 30px; display: flex; gap: 20px; z-index: 10;
    }

    .login-nav a {
        color: #64748b; text-decoration: none; font-size: 14px; font-weight: 600;
        display: flex; align-items: center; gap: 8px; transition: all 0.2s ease;
        background: rgba(255, 255, 255, 0.8); padding: 8px 16px; border-radius: 50px;
        backdrop-filter: blur(4px); border: 1px solid #e2e8f0;
    }

    .login-nav a:hover {
        color: #024442; background: #fff; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    .form-cont { position: relative; z-index: 2; }

    .form-cont .label {
        display: block; font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 8px; text-align: left;
    }

    .modern-input {
        width: 100%; box-sizing: border-box; padding: 14px 16px; border: 2px solid #e2e8f0;
        border-radius: 12px; font-size: 14.5px; background: #f8fafc; outline: none;
        transition: all 0.3s ease; font-family: inherit; margin-bottom: 20px; color: #333;
    }

    .modern-input:focus { border-color: #024442; background: #fff; box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.1); }
    .modern-input.error { border-color: #ef4444; background: #fef2f2; }
    .modern-input.error:focus { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1); }
    
    .password-wrapper { position: relative; display: block; width: 100%; margin-bottom: 20px; }
    .password-wrapper .modern-input { margin-bottom: 0; padding-right: 50px; }
    
    .toggle-password-btn {
        position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: #94a3b8; font-size: 16px; cursor: pointer;
        padding: 0; margin: auto; transition: color 0.2s;
    }

    .toggle-password-btn:hover { color: #024442; }
    .toggle-password-btn:focus { outline: none; }

    .modern-btn {
        width: 100%; box-sizing: border-box; background: #024442; color: #fff; border: none;
        padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer;
        transition: all 0.3s ease; box-shadow: 0 4px 14px rgba(2, 68, 66, 0.2);
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }

    .modern-btn:hover { background: #01302e; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(2, 68, 66, 0.3); }

    /* Custom Alert Styles */
    .custom-alert {
        padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px;
        font-weight: 600; display: flex; align-items: center; gap: 12px;
        animation: slideDown 0.3s ease-out; border: none; box-sizing: border-box; width: 100%;
    }
    .custom-alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1); }
    .custom-alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1); }

    .custom-alert-icon {
        width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
        color: white; border-radius: 50%; font-size: 12px; font-weight: 700; flex-shrink: 0;
    }
    .custom-alert-danger .custom-alert-icon { background: #ef4444; }
    .custom-alert-success .custom-alert-icon { background: #10b981; }

    .custom-alert-message { flex: 1; line-height: 1.5; text-align: left; }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 992px) { .login-nav { top: 15px; left: 15px; } }
</style>
</head>
<body>
<div class="container-fluid login-wrapper p-0">
    <div class="row g-0 min-vh-100">
        <div class="col-lg-6 d-none d-lg-block login-left">
            <video autoplay loop muted playsinline class="login-video">
                <source src="<?= BASE_URL ?>assets/images/bg-vid.mp4" type="video/mp4">
            </video>
            <div class="login-left-overlay"></div>
            <div class="login-left-content">
                <h2>Transforming Ideas into Digital Reality</h2>
                <p>Manage your projects, team, courses, and operations efficiently with Graphicafix.</p>
            </div>
        </div>
        
        <div class="col-lg-6 col-12 login-right" style="position: relative;">
            
            <div class="login-nav">
                <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Back</a>
                <a href="<?= BASE_URL ?>index.php"><i class="fas fa-home"></i> Home</a>
            </div>

            <div class="login-overlay"></div>
            
            <div class="form-cont">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Graphicafix" style="margin-bottom: 25px;">
                
                <?php if (!empty($error_message) || !empty($reset_error)): ?>
                <div class="custom-alert custom-alert-danger">
                    <div class="custom-alert-icon"><i class="fas fa-exclamation"></i></div>
                    <div class="custom-alert-message"><?= htmlspecialchars($error_message ?: $reset_error) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($reset_success)): ?>
                <div class="custom-alert custom-alert-success">
                    <div class="custom-alert-icon"><i class="fas fa-check"></i></div>
                    <div class="custom-alert-message"><?= htmlspecialchars($reset_success) ?></div>
                </div>
                <?php endif; ?>

                <div id="loginBlock" style="<?= isset($_POST['request_reset']) ? 'display:none;' : '' ?>">
                    <h3 style="font-weight: 800; color: #0f172a; margin-bottom: 30px;">Sign In</h3>
                    
                    <form method="POST">
                        <label class="label">Username or Email</label>
                        <input type="text" 
                               name="login_input" 
                               placeholder="Enter your username or email" 
                               value="<?= htmlspecialchars($login_input ?? '') ?>" 
                               class="modern-input <?= !empty($error_message) ? 'error' : '' ?>"
                               required>
                    
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label class="label" style="margin-bottom:0;">Password</label>
                            <a href="javascript:void(0)" onclick="toggleForms()" style="font-size:12.5px; font-weight:600; color:#024442; text-decoration:none; transition:color 0.2s;">Forgot Password?</a>
                        </div>
                        <div class="password-wrapper">
                            <input type="password" 
                                   id="passwordInput"
                                   name="password" 
                                   placeholder="Enter your password" 
                                   class="modern-input <?= !empty($error_message) ? 'error' : '' ?>"
                                   required>
                            <button type="button" id="togglePassword" class="toggle-password-btn" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:-8px; margin-bottom:24px;">
                            <label style="display:inline-flex; align-items:center; gap:8px; font-size:13.5px; font-weight:600; color:#475569; cursor:pointer; user-select:none; margin:0;">
                                <input type="checkbox" name="remember_me" id="rememberMe" value="1" style="width:17px; height:17px; accent-color:#024442; cursor:pointer; border-radius:4px;">
                                <span>Remember me</span>
                            </label>
                        </div>
                    
                        <button type="submit" name="login" class="modern-btn">
                            Sign In <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                </div>

                <div id="forgotBlock" style="<?= isset($_POST['request_reset']) ? '' : 'display:none;' ?>">
                    <h3 style="font-weight: 800; color: #0f172a; margin-bottom: 10px;">Reset Password</h3>
                    <p style="color:#64748b; font-size:14.5px; margin-bottom:25px; line-height:1.6;">Enter your email address and we'll send you a secure link to reset your password.</p>
                    
                    <form method="POST">
                        <label class="label">Email Address</label>
                        <input type="email" 
                               name="reset_email" 
                               class="modern-input <?= !empty($reset_error) ? 'error' : '' ?>" 
                               placeholder="Enter your registered email" 
                               required>
                    
                        <button type="submit" name="request_reset" class="modern-btn" style="margin-bottom: 20px;">
                            <i class="fas fa-paper-plane"></i> Send Reset Link
                        </button>
                        
                        <div style="text-align:center;">
                            <a href="javascript:void(0)" onclick="toggleForms()" style="font-size:14px; font-weight:600; color:#64748b; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:color 0.2s;">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    const icon = togglePassword.querySelector('i');

    if(togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            if (isPassword) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    }
});

function toggleForms() {
    const loginBlock = document.getElementById('loginBlock');
    const forgotBlock = document.getElementById('forgotBlock');
    
    // Clear alerts when switching views
    const alerts = document.querySelectorAll('.custom-alert');
    alerts.forEach(alert => alert.style.display = 'none');

    if (loginBlock.style.display === 'none') {
        loginBlock.style.display = 'block';
        forgotBlock.style.display = 'none';
    } else {
        loginBlock.style.display = 'none';
        forgotBlock.style.display = 'block';
    }
}
</script>

<?php
if(isset($conn)) {
    $conn->close();
}
?>
</body>
</html>
