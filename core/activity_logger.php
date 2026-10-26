<?php
/**
 * core/activity_logger.php
 * Central Activity & Audit Trail Logger for Graphicafix
 * Records user, action, target entity, date/time, client IP, and details.
 */

if (!function_exists('logActivity')) {
    /**
     * Log a user or system action with date/time, actor, target entity, and client details.
     *
     * @param mysqli|null $conn Database connection
     * @param string $action Short identifier for action (e.g. 'create_notice', 'delete_service', 'login')
     * @param string $description Human-readable details
     * @param string|null $target_type Entity type (e.g. 'notice', 'service', 'invoice', 'task')
     * @param int|null $target_id Primary key of affected entity
     * @param int|null $user_id Explicit user ID (defaults to $_SESSION['user_id'])
     * @param string|null $username Explicit username (defaults to $_SESSION['username'])
     * @param string|null $role Explicit role (defaults to $_SESSION['role'])
     * @return bool True on success, false on failure
     */
    function logActivity(
        $conn,
        $action,
        $description = '',
        $target_type = null,
        $target_id = null,
        $user_id = null,
        $username = null,
        $role = null
    ) {
        try {
            // 1. Resolve User Context
            if ($user_id === null) {
                $user_id = intval($_SESSION['user_id'] ?? 0);
            }
            if ($username === null) {
                $username = $_SESSION['username'] ?? ($_SESSION['name'] ?? 'System/Guest');
            }
            if ($role === null) {
                $role = strtolower($_SESSION['role'] ?? 'guest');
            }

            // 2. Client & Environment Details
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 255);
            $target_id = ($target_id !== null && $target_id !== '') ? intval($target_id) : null;
            $now = date('Y-m-d H:i:s');

            // 3. Database Audit Record
            if ($conn && $conn instanceof mysqli && !$conn->connect_error) {
                static $activity_table_initialized = false;
                if (!$activity_table_initialized) {
                    $activity_table_initialized = true;
                    @$conn->query("CREATE TABLE IF NOT EXISTS `activity_logs` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NULL,
                        `username` VARCHAR(100) NULL,
                        `user_role` VARCHAR(50) NULL,
                        `action` VARCHAR(100) NOT NULL,
                        `description` TEXT NULL,
                        `target_type` VARCHAR(50) NULL,
                        `target_id` INT NULL,
                        `ip_address` VARCHAR(45) NULL,
                        `user_agent` VARCHAR(255) NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX (`user_id`),
                        INDEX (`action`),
                        INDEX (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                $stmt = $conn->prepare("INSERT INTO `activity_logs` (`user_id`, `username`, `user_role`, `action`, `description`, `target_type`, `target_id`, `ip_address`, `user_agent`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isssssisss", $user_id, $username, $role, $action, $description, $target_type, $target_id, $ip, $user_agent, $now);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // 4. File-Based Audit Log (logs/activity.log)
            $log_dir = __DIR__ . '/../logs';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
                @file_put_contents($log_dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
            }

            $log_line = sprintf(
                "[%s] [IP: %s] [User: %s (ID: %s, Role: %s)] ACTION: %s | Target: %s #%s | %s\n",
                $now,
                $ip,
                $username,
                $user_id ? $user_id : '0',
                $role,
                $action,
                $target_type ?? 'none',
                $target_id !== null ? $target_id : '-',
                $description
            );
            @file_put_contents($log_dir . '/activity.log', $log_line, FILE_APPEND | LOCK_EX);

            return true;
        } catch (Throwable $e) {
            error_log("logActivity error: " . $e->getMessage());
            return false;
        }
    }
}
