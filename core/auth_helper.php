<?php
/**
 * Authentication and Persistent Session ("Remember Me") Helper
 * Graphicafix System
 */

if (!function_exists('initRememberTokensTable')) {
    function initRememberTokensTable($conn) {
        if (!$conn || !($conn instanceof mysqli)) return false;
        static $initialized = false;
        if ($initialized) return true;

        $sql = "CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector VARCHAR(64) NOT NULL UNIQUE,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_selector (selector),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $conn->query($sql);
            $initialized = true;
            return true;
        } catch (Throwable $e) {
            error_log("initRememberTokensTable error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('createRememberToken')) {
    function createRememberToken($conn, $user_id, $days = 30) {
        if (!$conn || !($conn instanceof mysqli) || empty($user_id)) return false;
        initRememberTokensTable($conn);

        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $validator);
            $expires = time() + ($days * 86400);
            $expires_dt = date('Y-m-d H:i:s', $expires);

            // Clean up old expired tokens
            @$conn->query("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");

            $stmt = $conn->prepare("INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isss", $user_id, $selector, $token_hash, $expires_dt);
                $stmt->execute();
                $stmt->close();
            }

            $cookie_val = $selector . ':' . $validator;
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            
            // Set remember_token cookie for 30 days
            setcookie('remember_token', $cookie_val, [
                'expires' => $expires,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Also extend the session cookie lifetime if session is active
            if (session_id()) {
                setcookie(session_name(), session_id(), [
                    'expires' => $expires,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            return true;
        } catch (Throwable $e) {
            error_log("createRememberToken error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('checkRememberTokenAutoLogin')) {
    function checkRememberTokenAutoLogin($conn) {
        // If already authenticated or no remember cookie, skip
        if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_token'])) {
            return false;
        }
        if (!$conn || !($conn instanceof mysqli)) return false;

        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            clearRememberToken($conn);
            return false;
        }

        $selector = $parts[0];
        $validator = $parts[1];

        try {
            $stmt = $conn->prepare("SELECT id, user_id, token_hash, expires_at FROM user_remember_tokens WHERE selector = ? LIMIT 1");
            if (!$stmt) return false;

            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result();
            $token_row = $result->fetch_assoc();
            $stmt->close();

            if (!$token_row) {
                clearRememberToken($conn);
                return false;
            }

            // Check if expired
            if (strtotime($token_row['expires_at']) < time()) {
                clearRememberToken($conn);
                return false;
            }

            // Verify token hash
            if (!hash_equals($token_row['token_hash'], hash('sha256', $validator))) {
                clearRememberToken($conn);
                return false;
            }

            // Fetch user
            $user_id = intval($token_row['user_id']);
            $u_stmt = $conn->prepare("SELECT user_id, username, role, name, status FROM users WHERE user_id = ? LIMIT 1");
            if (!$u_stmt) return false;

            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $u_result = $u_stmt->get_result();
            $user = $u_result->fetch_assoc();
            $u_stmt->close();

            if (!$user || strtolower(trim($user['status'] ?? 'active')) !== 'active') {
                clearRememberToken($conn);
                return false;
            }

            // Populate session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = strtolower(trim($user['role'] ?? 'user'));
            if (!empty($user['name'])) {
                $_SESSION['name'] = $user['name'];
            }
            $_SESSION['remember_me'] = true;
            $_SESSION['LAST_ACTIVITY'] = time();

            // Rotate validator token for security
            $new_validator = bin2hex(random_bytes(32));
            $new_hash = hash('sha256', $new_validator);
            $new_expires = time() + (30 * 86400);
            $new_expires_dt = date('Y-m-d H:i:s', $new_expires);

            $rot_stmt = $conn->prepare("UPDATE user_remember_tokens SET token_hash = ?, expires_at = ? WHERE id = ?");
            if ($rot_stmt) {
                $rot_stmt->bind_param("ssi", $new_hash, $new_expires_dt, $token_row['id']);
                $rot_stmt->execute();
                $rot_stmt->close();
            }

            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('remember_token', $selector . ':' . $new_validator, [
                'expires' => $new_expires,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            return true;
        } catch (Throwable $e) {
            error_log("checkRememberTokenAutoLogin error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('clearRememberToken')) {
    function clearRememberToken($conn = null) {
        if (!empty($_COOKIE['remember_token'])) {
            $parts = explode(':', $_COOKIE['remember_token'], 2);
            $selector = $parts[0] ?? '';
            if (!empty($selector) && $conn && ($conn instanceof mysqli)) {
                try {
                    $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $selector);
                        $stmt->execute();
                        $stmt->close();
                    }
                } catch (Throwable $e) {
                    error_log("clearRememberToken error: " . $e->getMessage());
                }
            }
        }

        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (isset($_COOKIE['remember_token'])) {
            unset($_COOKIE['remember_token']);
        }
        if (isset($_SESSION['remember_me'])) {
            unset($_SESSION['remember_me']);
        }
    }
}
