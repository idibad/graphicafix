<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/functions.php';

$login_input = '';
$error_message = '';

if (isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']);
    $password = trim($_POST['password']);

    if (strlen($password) < 4) {
        $error_message = 'Please enter your account password.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, username, role, name FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hashed_password, $username, $role, $name);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $role_lower = strtolower(trim($role ?? ''));
                
                // Allow students, teachers, admins, and PMs
                if (in_array($role_lower, ['student', 'teacher', 'admin', 'pm'])) {
                    if (function_exists('logLogin')) {
                        logLogin($conn, $user_id, 'success');
                    }

                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role_lower;

                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $updateStmt->bind_param("i", $user_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    header("Location: index.php");
                    exit();
                } else {
                    $error_message = 'This portal is restricted to Students and Teachers.';
                }
            } else {
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
<title>LMS Portal Login | Graphicafix</title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/bootstrap.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="<?= BASE_URL ?>images/icon.png">
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
<div class="container-fluid login-wrapper">
    <div class="row min-vh-100">
        <!-- LEFT IMAGE (Matches admin gfix_bg.png exactly) -->
        <div class="col-lg-6 d-none d-lg-block login-left"></div>
        <!-- RIGHT SIDE -->
        <div class="col-lg-6 col-12 login-right">
            <div class="login-overlay"></div>
            <div class="form-cont">
                <img src="<?= BASE_URL ?>images/logo.png" alt="Graphicafix">
                
                <div class="text-center">
                    <span class="portal-badge"><i class="fas fa-graduation-cap me-1"></i> Classroom Portal</span>
                </div>

                <h3>Sign In</h3>

                <?php if (!empty($error_message)): ?>
                <div class="custom-alert custom-alert-danger">
                    <div class="custom-alert-icon">!</div>
                    <div class="custom-alert-message">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <label class="label">Username or Email</label>
                    <input type="text" 
                           name="login_input" 
                           placeholder="Enter username or registered email" 
                           value="<?= htmlspecialchars($login_input) ?>" 
                           class="<?= !empty($error_message) ? 'error' : '' ?>"
                           required>
                
                    <label class="label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" 
                               id="passwordInput"
                               name="password" 
                               placeholder="Enter password" 
                               class="<?= !empty($error_message) ? 'error' : '' ?>"
                               required>
                        <button type="button" id="togglePassword" class="toggle-password-btn" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                    </div>
                
                    <input type="submit" name="login" value="Sign In to Classroom" class="main-btn" style="margin-top: 15px; width: 100%;">
                </form>

                <div class="text-center mt-4 pt-3 border-top" style="font-size: 12.5px; color: #64748b;">
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
