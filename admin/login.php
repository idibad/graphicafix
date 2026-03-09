<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/functions.php';


session_start();

$login_input = '';
$error_message = '';

if (isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']);
    $password = trim($_POST['password']);

    if (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, username FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hashed_password, $username);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                logLogin($conn, $user_id, 'success');

                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;

                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->bind_param("i", $user_id);
                $updateStmt->execute();
                $updateStmt->close();

                header("Location: index.php");
                exit();
            } else {
                // Failed login with NULL user_id
                logLogin($conn, NULL, 'failed');
                $error_message = 'Incorrect password. Please try again.';
            }
        } else {
            // User not found - log with NULL
            logLogin($conn, NULL, 'failed');
            $error_message = 'User not found. Please check your username or email.';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Graphicafix</title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/bootstrap.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/all.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Custom Alert Styles */
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
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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
</style>
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
                <img src="<?= BASE_URL ?>images/logo.png" alt="Graphicafix">
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
                           placeholder="Username or Email" 
                           value="<?= htmlspecialchars($login_input) ?>" 
                           class="<?= !empty($error_message) ? 'error' : '' ?>"
                           required>
                    <label class="label">Password</label>
                    <input type="password" 
                           name="password" 
                           placeholder="Password (min. 8 characters)" 
                           class="<?= !empty($error_message) ? 'error' : '' ?>"
                           required>
                    <input type="submit" name="login" value="Sign In" class="main-btn">
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$conn->close();
?>
</body>
</html>