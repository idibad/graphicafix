<?php
/**
 * core/attendance_schema.php
 * Automated Schema Migration for Enhanced Attendance, Payroll & Deduction Dispute System
 * Safely creates and synchronizes tables:
 * - attendance_records
 * - attendance_overrides
 * - salaries
 * - salary_deductions
 * - deduction_tickets
 * - schema additions on users and attendance_overrides
 */

if (!function_exists('ensure_attendance_schema')) {
    function ensure_attendance_schema($conn) {
        if (!$conn || !($conn instanceof mysqli)) return;

        static $schema_ensured = false;
        if ($schema_ensured) return;
        $schema_ensured = true;

        // ── 0. Fix `users.user_id` AUTO_INCREMENT safely ─────────────────────
        try {
            $u_col_res = $conn->query("SHOW COLUMNS FROM `users` WHERE Field = 'user_id'");
            if ($u_col_res && $urow = $u_col_res->fetch_assoc()) {
                if (strpos(strtolower($urow['Extra'] ?? ''), 'auto_increment') === false) {
                    $zero_chk = $conn->query("SELECT user_id FROM `users` WHERE user_id = 0");
                    if ($zero_chk && $zero_chk->num_rows > 0) {
                        $m_res = $conn->query("SELECT MAX(user_id) as m FROM `users`");
                        $next_m = intval($m_res->fetch_assoc()['m'] ?? 100) + 1;
                        @$conn->query("UPDATE `users` SET user_id = {$next_m} WHERE user_id = 0");
                    }
                    @$conn->query("ALTER TABLE `users` MODIFY COLUMN `user_id` INT(11) NOT NULL AUTO_INCREMENT");
                }
            }
        } catch (Throwable $e) {
            error_log("users.user_id auto_increment check: " . $e->getMessage());
        }

        // ── 1. Create & Sync `attendance_records` table ────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `attendance_records` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `attendance_date` DATE NOT NULL,
                `check_in_time` TIME NULL,
                `check_out_time` TIME NULL,
                `last_heartbeat` DATETIME NULL,
                `status` ENUM('present', 'late', 'absent', 'half_day', 'on_leave', 'holiday') DEFAULT 'present',
                `is_late` TINYINT(1) DEFAULT 0,
                `late_minutes` INT DEFAULT 0,
                `is_grace_period` TINYINT(1) DEFAULT 0,
                `grace_count_month` INT DEFAULT 0,
                `penalty_amount` DECIMAL(10,2) DEFAULT 0.00,
                `penalty_reason` VARCHAR(255) NULL,
                `leave_type` VARCHAR(50) NULL,
                `medical_leave_month_count` INT DEFAULT 0,
                `location_type` ENUM('onsite', 'remote', 'denied', 'field_work', 'unknown') DEFAULT 'unknown',
                `latitude` DECIMAL(10,8) NULL,
                `longitude` DECIMAL(11,8) NULL,
                `location_accuracy` DECIMAL(8,2) NULL,
                `distance_meters` DECIMAL(10,2) NULL,
                `location_denied` TINYINT(1) DEFAULT 0,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_user_date` (`user_id`, `attendance_date`),
                KEY `idx_att_date` (`attendance_date`),
                KEY `idx_att_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            // Check existing columns in attendance_records
            $att_cols = [];
            $res = $conn->query("SHOW COLUMNS FROM `attendance_records`");
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $att_cols[] = strtolower($c['Field']);
                }
            }
            $att_missing_defs = [
                'check_out_time'            => "TIME NULL AFTER `check_in_time`",
                'last_heartbeat'            => "DATETIME NULL AFTER `check_out_time`",
                'late_minutes'              => "INT DEFAULT 0 AFTER `is_late`",
                'is_grace_period'           => "TINYINT(1) DEFAULT 0 AFTER `late_minutes`",
                'grace_count_month'         => "INT DEFAULT 0 AFTER `is_grace_period`",
                'penalty_amount'            => "DECIMAL(10,2) DEFAULT 0.00 AFTER `grace_count_month`",
                'penalty_reason'            => "VARCHAR(255) NULL AFTER `penalty_amount`",
                'leave_type'                => "VARCHAR(50) NULL AFTER `penalty_reason`",
                'medical_leave_month_count' => "INT DEFAULT 0 AFTER `leave_type`",
                'location_type'             => "VARCHAR(50) DEFAULT 'unknown' AFTER `medical_leave_month_count`",
                'latitude'                  => "DECIMAL(10,8) NULL AFTER `location_type`",
                'longitude'                 => "DECIMAL(11,8) NULL AFTER `latitude`",
                'location_accuracy'         => "DECIMAL(8,2) NULL AFTER `longitude`",
                'distance_meters'           => "DECIMAL(10,2) NULL AFTER `location_accuracy`",
                'location_denied'           => "TINYINT(1) DEFAULT 0 AFTER `distance_meters`",
                'ip_address'                => "VARCHAR(45) NULL AFTER `location_denied`",
                'user_agent'                => "TEXT NULL AFTER `ip_address`",
                'notes'                     => "TEXT NULL AFTER `user_agent`"
            ];
            foreach ($att_missing_defs as $col => $def) {
                if (!in_array($col, $att_cols)) {
                    try {
                        $conn->query("ALTER TABLE `attendance_records` ADD COLUMN `{$col}` {$def}");
                    } catch (Throwable $e_add) {
                        try {
                            $clean_def = preg_replace('/\s+AFTER\s+`[^`]+`/i', '', $def);
                            $conn->query("ALTER TABLE `attendance_records` ADD COLUMN `{$col}` {$clean_def}");
                        } catch (Throwable $e_add2) {
                            error_log("Failed to add column {$col} to attendance_records: " . $e_add2->getMessage());
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("attendance_records sync: " . $e->getMessage());
        }

        // ── 1b. Seamlessly Backfill Historical Records from `login_logs` ──────
        try {
            $chk_logs = $conn->query("SHOW TABLES LIKE 'login_logs'");
            if ($chk_logs && $chk_logs->num_rows > 0) {
                $has_att_type = false;
                try {
                    $chk_att_type = $conn->query("SHOW COLUMNS FROM `login_logs` LIKE 'attendance_type'");
                    $has_att_type = ($chk_att_type && $chk_att_type->num_rows > 0);
                } catch (Throwable $e_col) {}
                
                $loc_expr = $has_att_type ? "COALESCE(MAX(l.attendance_type), 'remote')" : "'remote'";

                $conn->query("INSERT IGNORE INTO `attendance_records` (
                    `user_id`,
                    `attendance_date`,
                    `check_in_time`,
                    `check_out_time`,
                    `status`,
                    `location_type`,
                    `ip_address`,
                    `created_at`
                )
                SELECT 
                    CAST(l.user_id AS UNSIGNED) as user_id,
                    DATE(l.login_time) as att_date,
                    MIN(TIME(l.login_time)) as in_time,
                    IF(MAX(TIME(l.login_time)) != MIN(TIME(l.login_time)), MAX(TIME(l.login_time)), NULL) as out_time,
                    'present' as status,
                    {$loc_expr} as location_type,
                    MAX(l.ip_address) as ip_address,
                    MIN(l.login_time) as created_at
                FROM `login_logs` l
                WHERE l.status = 'success' AND l.user_id IS NOT NULL AND l.user_id > 0
                GROUP BY l.user_id, DATE(l.login_time)");
            }
        } catch (Throwable $e_bfill) {
            error_log("attendance_records backfill notice: " . $e_bfill->getMessage());
        }

        // ── 2. Create & Sync `attendance_overrides` table ─────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `attendance_overrides` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `attendance_date` DATE NOT NULL,
                `attendance_type` ENUM('onsite', 'remote', 'leave', 'field_work') NOT NULL DEFAULT 'onsite',
                `leave_category` VARCHAR(50) DEFAULT 'casual',
                `reason` TEXT NULL,
                `approved_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_user_date_override` (`user_id`, `attendance_date`),
                KEY `idx_ov_date` (`attendance_date`),
                KEY `idx_ov_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $res_ov = $conn->query("SHOW COLUMNS FROM `attendance_overrides`");
            $override_cols = [];
            if ($res_ov) {
                while ($c = $res_ov->fetch_assoc()) {
                    $override_cols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('leave_category', $override_cols)) {
                try {
                    $conn->query("ALTER TABLE `attendance_overrides` ADD COLUMN `leave_category` VARCHAR(50) DEFAULT 'casual' AFTER `attendance_type`");
                } catch (Throwable $e_ov) {
                    try {
                        $conn->query("ALTER TABLE `attendance_overrides` ADD COLUMN `leave_category` VARCHAR(50) DEFAULT 'casual'");
                    } catch (Throwable $e_ov2) {}
                }
            }
        } catch (Throwable $e) {
            error_log("attendance_overrides sync: " . $e->getMessage());
        }

        // ── 3. Create & Sync `salaries` table ──────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `salaries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `salary_month` VARCHAR(7) NOT NULL,
                `employee_id` VARCHAR(50) NULL,
                `name` VARCHAR(150) NULL,
                `department` VARCHAR(100) NULL,
                `job_title` VARCHAR(100) NULL,
                `base_salary` DECIMAL(12,2) DEFAULT 0.00,
                `allowances` DECIMAL(12,2) DEFAULT 0.00,
                `gross_pay` DECIMAL(12,2) DEFAULT 0.00,
                `tax` DECIMAL(12,2) DEFAULT 0.00,
                `advance` DECIMAL(12,2) DEFAULT 0.00,
                `deductions` DECIMAL(12,2) DEFAULT 0.00,
                `previous_pending` DECIMAL(12,2) DEFAULT 0.00,
                `net_salary` DECIMAL(12,2) DEFAULT 0.00,
                `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
                `payment_status` ENUM('Pending', 'Paid', 'Partially Paid', 'On Hold') DEFAULT 'Pending',
                `payment_date` DATE NULL,
                `payment_method` VARCHAR(50) NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_user_month` (`user_id`, `salary_month`),
                KEY `idx_salary_month` (`salary_month`),
                KEY `idx_salary_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $res_sal = $conn->query("SHOW COLUMNS FROM `salaries`");
            $sal_cols = [];
            if ($res_sal) {
                while ($c = $res_sal->fetch_assoc()) {
                    $sal_cols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('amount_paid', $sal_cols)) {
                try {
                    $conn->query("ALTER TABLE `salaries` ADD COLUMN `amount_paid` DECIMAL(12,2) DEFAULT 0.00 AFTER `net_salary`");
                } catch (Throwable $e_sal) {
                    try {
                        $conn->query("ALTER TABLE `salaries` ADD COLUMN `amount_paid` DECIMAL(12,2) DEFAULT 0.00");
                    } catch (Throwable $e_sal2) {}
                }
            }
            // Ensure payment_status enum supports 'Partially Paid'
            try {
                $conn->query("ALTER TABLE `salaries` MODIFY COLUMN `payment_status` ENUM('Pending', 'Paid', 'Partially Paid', 'On Hold') DEFAULT 'Pending'");
            } catch (Throwable $e_enum) {}
        } catch (Throwable $e) {
            error_log("salaries sync: " . $e->getMessage());
        }

        // ── 4. Create `salary_deductions` table ────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `salary_deductions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `salary_month` VARCHAR(7) NOT NULL,
                `attendance_id` INT NULL,
                `deduction_type` VARCHAR(60) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `description` VARCHAR(255) NOT NULL,
                `date_incurred` DATE NOT NULL,
                `status` ENUM('applied', 'disputed', 'waived', 'refunded') DEFAULT 'applied',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ded_user_month` (`user_id`, `salary_month`),
                KEY `idx_ded_date` (`date_incurred`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("salary_deductions sync: " . $e->getMessage());
        }

        // ── 5. Create `deduction_tickets` table ────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `deduction_tickets` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ticket_number` VARCHAR(30) UNIQUE NOT NULL,
                `deduction_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `reason` TEXT NOT NULL,
                `attachment` VARCHAR(255) NULL,
                `status` ENUM('open', 'under_review', 'approved_waived', 'rejected') DEFAULT 'open',
                `admin_remarks` TEXT NULL,
                `reviewed_by` INT NULL,
                `reviewed_at` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_tkt_user` (`user_id`),
                KEY `idx_tkt_deduction` (`deduction_id`),
                KEY `idx_tkt_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("deduction_tickets sync: " . $e->getMessage());
        }

        // ── 6. Enhance `users` table with missing columns ──────────────────────
        try {
            $user_cols = [];
            $res = $conn->query("SHOW COLUMNS FROM `users`");
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $user_cols[] = strtolower($c['Field']);
                }
            }
            $user_missing_defs = [
                'status'       => "VARCHAR(20) DEFAULT 'active' AFTER `role`",
                'gender'       => "VARCHAR(10) DEFAULT 'Male' AFTER `name`",
                'employee_id'  => "VARCHAR(50) NULL AFTER `user_id`",
                'department'   => "VARCHAR(100) DEFAULT 'General' AFTER `role`",
                'job_title'    => "VARCHAR(100) NULL AFTER `department`",
                'work_mode'    => "VARCHAR(20) NOT NULL DEFAULT 'remote' AFTER `job_title`",
                'base_salary'  => "DECIMAL(12,2) DEFAULT 50000.00 AFTER `job_title`",
                'allowances'   => "DECIMAL(12,2) DEFAULT 0.00 AFTER `base_salary`"
            ];
            foreach ($user_missing_defs as $ucol => $udef) {
                if (!in_array($ucol, $user_cols)) {
                    try {
                        $conn->query("ALTER TABLE `users` ADD COLUMN `{$ucol}` {$udef}");
                    } catch (Throwable $e_ucol) {
                        try {
                            $clean_udef = preg_replace('/\s+AFTER\s+`[^`]+`/i', '', $udef);
                            $conn->query("ALTER TABLE `users` ADD COLUMN `{$ucol}` {$clean_udef}");
                        } catch (Throwable $e_ucol2) {
                            error_log("Failed to add column {$ucol} to users: " . $e_ucol2->getMessage());
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("users columns sync: " . $e->getMessage());
        }
    }
}
