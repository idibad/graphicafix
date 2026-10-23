<?php
require_once __DIR__ . '/config.php';
// functions.php is included via config.php when loaded from main core
if (!function_exists('logLogin') && file_exists(__DIR__ . '/../core/functions.php')) {
    require_once __DIR__ . '/../core/functions.php';
}

$login_input = '';
$error_message = '';

if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error_message = 'Your session has expired due to inactivity. Please sign in again.';
}

// Redirect if already logged in (unless displaying an expired notice)
if (!empty($_SESSION['user_id']) && empty($_GET['expired'])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']);
    $password = trim($_POST['password']);
    $remember_me = !empty($_POST['remember_me']);

    if (strlen($password) < 4) {
        $error_message = 'Please enter your account password.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, username, role, name, status FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows >= 1) {
            $stmt->bind_result($user_id, $hashed_password, $username, $role, $name, $status);
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

                    $role_lower = strtolower(trim($role ?? ''));
                    
                    // Allow students, teachers, admins, and PMs
                    if (in_array($role_lower, ['student', 'teacher', 'admin', 'pm'])) {
                        if (function_exists('logLogin')) {
                            logLogin($conn, $user_id, 'success');
                        }

                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = $role_lower;

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
                    } else {
                        $error_message = 'This portal is restricted to Students and Teachers.';
                    }
                }
            }

            if ($is_deactivated_account) {
                if (function_exists('logLogin')) logLogin($conn, NULL, 'failed');
                $error_message = 'Your account has been deactivated. Please contact support.';
            } else if (!$found_user && empty($error_message)) {
                if (function_exists('logLogin')) logLogin($conn, NULL, 'failed');
                $error_message = 'Incorrect password. Please try again.';
            }
        } else {
            if (function_exists('logLogin')) logLogin($conn, NULL, 'failed');
            $error_message = 'Account not found. Please check your username or email.';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Education Portal Login | Graphicafix</title>
<link rel="stylesheet" href="<?= MAIN_SITE_URL ?>assets/css/bootstrap.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= MAIN_SITE_URL ?>assets/css/style.css">
<link rel="stylesheet" href="<?= MAIN_SITE_URL ?>assets/css/dashboard.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?= MAIN_SITE_URL ?>assets/images/icon.png">
<style>
    /* Custom Alert Styles matching Graphicafix theme */
    .custom-alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease-out;
        border: none;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
    }

    .custom-alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    .custom-alert-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .custom-alert-message {
        flex: 1;
        line-height: 1.5;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Enhanced form styles */
    .form-cont {
        position: relative;
        z-index: 2;
    }

    .form-cont input[type="text"],
    .form-cont input[type="password"] {
        transition: all 0.3s;
    }

    .form-cont input[type="text"]:focus,
    .form-cont input[type="password"]:focus {
        border-color: #024442;
        box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
    }

    .form-cont input.error {
        border-color: #ef4444;
        background: #fef2f2;
    }

    .form-cont input.error:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    .password-wrapper {
        position: relative;
        display: block;
    }

    .password-wrapper input {
        width: 100%;
        padding-right: 60px; 
        box-sizing: border-box;
    }

    .toggle-password-btn {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #666;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        padding: 0;
        margin: auto;
    }

    .toggle-password-btn:hover { color: #111; }
    .toggle-password-btn:focus { outline: none; }

    /* Portal Specific Accent Badge */
    .portal-badge {
        background: #024442;
        color: #b8f35a;
        font-size: 11.5px;
        font-weight: 800;
        padding: 6px 16px;
        border-radius: 50px;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        display: inline-block;
        margin-bottom: 18px;
        box-shadow: 0 4px 12px rgba(2, 68, 66, 0.2);
    }

    /* Responsive adjustments */
    @media (max-width: 991px) {
        .login-right {
            min-height: 100vh;
            padding: 20px;
        }
        .form-cont {
            padding: 28px 22px;
            max-width: 100%;
        }
    }
</style>
</head>
<body>
<div class="container-fluid login-wrapper p-0">
    <div class="row g-0 min-vh-100">
        <!-- LEFT SIDE WITH BACKGROUND VIDEO -->
        <div class="col-lg-6 d-none d-lg-block login-left">
            <video autoplay loop muted playsinline class="login-video">
                <source src="<?= MAIN_SITE_URL ?>assets/images/bg-vid.mp4" type="video/mp4">
            </video>
            <div class="login-left-overlay"></div>
            <div class="login-left-content">
                <h2>Welcome to Graphicafix Education Portal</h2>
                <p>Access your student portal, recorded lectures, quizzes, and course resources easily.</p>
            </div>
        </div>
        <!-- RIGHT SIDE -->
        <div class="col-lg-6 col-12 login-right">
            <div class="login-overlay"></div>
            <div class="form-cont d-card border shadow-lg">
                <div class="text-center mb-3">
                    <img src="<?= MAIN_SITE_URL ?>assets/images/logo.png" alt="Graphicafix" style="max-height: 60px;">
                </div>
                
                <div class="text-center">
                    <span class="portal-badge"><i class="fas fa-graduation-cap me-1"></i> Education Portal</span>
                </div>

                <h3 class="text-center fw-bold mb-4">Sign In</h3>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mb-4">
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="d-flex flex-column gap-3">
                    <div class="form-group">
                        <label class="form-label">Username or Email</label>
                        <input type="text" 
                               name="login_input" 
                               placeholder="Enter username or registered email" 
                               value="<?= htmlspecialchars($login_input) ?>" 
                               class="form-control <?= !empty($error_message) ? 'is-invalid' : '' ?>"
                               required>
                    </div>
                
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" 
                                   id="passwordInput"
                                   name="password" 
                                   placeholder="Enter password" 
                                   class="form-control <?= !empty($error_message) ? 'is-invalid' : '' ?>"
                                   required>
                            <button type="button" id="togglePassword" class="toggle-password-btn" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" name="remember_me" id="portal_remember_me" value="1" style="cursor: pointer; accent-color: #024442;">
                            <label class="form-check-label" for="portal_remember_me" style="font-size: 13.5px; font-weight: 600; color: #475569; cursor: pointer; user-select: none;">
                                Remember me
                            </label>
                        </div>
                        <a href="<?= MAIN_SITE_URL ?>forgot_password.php" style="font-size: 13.5px; color: var(--primary-color); text-decoration: none; font-weight: 600;">Forgot Password?</a>
                    </div>
                
                    <button type="submit" name="login" class="btn btn-primary-custom w-100 py-3 mt-2 justify-content-center"><i class="fas fa-sign-in-alt"></i> Sign In to Classroom</button>
                </form>

                <div class="text-center mt-4 pt-3 border-top" style="font-size: 12.5px; color: var(--text-muted);">
                    <i class="fas fa-shield-alt text-success me-1"></i> Secure Graphicafix Academic Gateway
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    if (togglePassword && passwordInput) {
        const icon = togglePassword.querySelector('i');
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
</script>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    @$conn->close();
}
?>
</body>
</html>
