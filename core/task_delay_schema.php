<?php
/**
 * core/task_delay_schema.php
 * Automated Schema Migration for Task Staging, Workflow Pipeline, Subtasks, Comments, Files & Delay Deductions.
 */

if (!function_exists('ensure_task_delay_schema')) {
    function ensure_task_delay_schema($conn = null) {
        if (!$conn) {
            global $conn;
        }
        if (!$conn || !($conn instanceof mysqli)) return;

        static $task_delay_schema_ensured = false;
        if ($task_delay_schema_ensured) return;
        $task_delay_schema_ensured = true;

        // ── 1. Enhance `tasks` table with staging and delay tracking ──────────
        try {
            $task_cols = [];
            $res = $conn->query("SHOW COLUMNS FROM `tasks`");
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $task_cols[] = strtolower($c['Field']);
                }
            }

            $missing_task_cols = [
                'completed_at'             => "DATETIME NULL AFTER `updated_at`",
                'submitted_for_review_at'  => "DATETIME NULL AFTER `completed_at`",
                'delay_days'               => "INT DEFAULT 0 AFTER `submitted_for_review_at`",
                'delay_deduction_amount'   => "DECIMAL(10,2) DEFAULT 0.00 AFTER `delay_days`",
                'delay_status'             => "ENUM('on_time', 'grace_period', 'delayed', 'penalized', 'exempted', 'extended') DEFAULT 'on_time' AFTER `delay_deduction_amount`",
                'delay_exempted_by'        => "INT NULL AFTER `delay_status`",
                'delay_exemption_reason'   => "VARCHAR(255) NULL AFTER `delay_exempted_by`",
                'delay_last_calculated_at' => "DATETIME NULL AFTER `delay_exemption_reason`",
                'extension_status'         => "ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none' AFTER `delay_status`",
                'extension_requested_date' => "DATE NULL AFTER `extension_status`",
                'extension_reason_category'=> "VARCHAR(100) NULL DEFAULT 'General' AFTER `extension_requested_date`",
                'extension_reason'         => "TEXT NULL AFTER `extension_reason_category`",
                'extension_reviewed_by'    => "INT NULL AFTER `extension_reason`",
                'extension_admin_notes'    => "TEXT NULL AFTER `extension_reviewed_by`",
                // ── Workflow Staging & Creative Brief Columns ─────────────────
                'stage'                    => "VARCHAR(50) NOT NULL DEFAULT 'Brief' AFTER `status`",
                'brief_status'             => "VARCHAR(50) NOT NULL DEFAULT 'Draft' AFTER `stage`",
                'brief_objective'          => "TEXT NULL AFTER `brief_status`",
                'brief_deliverables'       => "TEXT NULL AFTER `brief_objective`",
                'brief_references'         => "TEXT NULL AFTER `brief_deliverables`",
                'brief_specs'              => "TEXT NULL AFTER `brief_references`",
                'brief_copy'               => "TEXT NULL AFTER `brief_specs`",
                'production_status'        => "VARCHAR(50) NOT NULL DEFAULT 'Not Started' AFTER `brief_copy`",
                'production_progress'      => "INT DEFAULT 0 AFTER `production_status`",
                'internal_review_status'   => "VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER `production_progress`",
                'client_feedback'          => "TEXT NULL AFTER `internal_review_status`",
                'client_approval_status'   => "VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER `client_feedback`",
                'current_revision'         => "INT DEFAULT 1 AFTER `client_approval_status`",
                'client_token'             => "VARCHAR(64) DEFAULT NULL AFTER `current_revision`"
            ];

            foreach ($missing_task_cols as $col => $def) {
                if (!in_array($col, $task_cols)) {
                    try {
                        $conn->query("ALTER TABLE `tasks` ADD COLUMN `{$col}` {$def}");
                    } catch (Throwable $e) {
                        try {
                            $clean_def = preg_replace('/\s+AFTER\s+`[^`]+`/i', '', $def);
                            $conn->query("ALTER TABLE `tasks` ADD COLUMN `{$col}` {$clean_def}");
                        } catch (Throwable $e2) {
                            error_log("Failed to add column {$col} to tasks: " . $e2->getMessage());
                        }
                    }
                }
            }

            // Ensure index on due_date and status for high-performance cron scans
            try {
                $idx_check = $conn->query("SHOW INDEX FROM `tasks` WHERE Key_name = 'idx_task_due_status'");
                if (!$idx_check || $idx_check->num_rows === 0) {
                    @$conn->query("ALTER TABLE `tasks` ADD KEY `idx_task_due_status` (`due_date`, `status`)");
                }
            } catch (Throwable $e_idx) {}

            try {
                $idx_stage = $conn->query("SHOW INDEX FROM `tasks` WHERE Key_name = 'idx_task_stage'");
                if (!$idx_stage || $idx_stage->num_rows === 0) {
                    @$conn->query("ALTER TABLE `tasks` ADD KEY `idx_task_stage` (`stage`)");
                }
            } catch (Throwable $e_stg) {}

        } catch (Throwable $e) {
            error_log("tasks table columns sync error: " . $e->getMessage());
        }

        // ── 2. Enhance `salary_deductions` table with task_id linkage ─────────
        try {
            $ded_cols = [];
            $res_ded = $conn->query("SHOW COLUMNS FROM `salary_deductions`");
            if ($res_ded) {
                while ($c = $res_ded->fetch_assoc()) {
                    $ded_cols[] = strtolower($c['Field']);
                }
            }

            if (!in_array('task_id', $ded_cols)) {
                try {
                    $conn->query("ALTER TABLE `salary_deductions` ADD COLUMN `task_id` BIGINT(20) UNSIGNED NULL AFTER `attendance_id`");
                    $conn->query("ALTER TABLE `salary_deductions` ADD KEY `idx_ded_task` (`task_id`)");
                } catch (Throwable $e_task_id) {
                    try {
                        $conn->query("ALTER TABLE `salary_deductions` ADD COLUMN `task_id` BIGINT(20) UNSIGNED NULL");
                    } catch (Throwable $e_task_id2) {}
                }
            }

            if (!in_array('delay_days_count', $ded_cols)) {
                try {
                    $conn->query("ALTER TABLE `salary_deductions` ADD COLUMN `delay_days_count` INT DEFAULT 0 AFTER `task_id`");
                } catch (Throwable $e_delay_days) {
                    try {
                        $conn->query("ALTER TABLE `salary_deductions` ADD COLUMN `delay_days_count` INT DEFAULT 0");
                    } catch (Throwable $e_delay_days2) {}
                }
            }
        } catch (Throwable $e) {
            error_log("salary_deductions delay columns sync error: " . $e->getMessage());
        }

        // ── 3. Create `task_workflow_history` table ───────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_workflow_history` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `from_stage` VARCHAR(50) NOT NULL,
                `to_stage` VARCHAR(50) NOT NULL,
                `changed_by` INT NOT NULL,
                `comment` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_twh_task` (`task_id`),
                KEY `idx_twh_user` (`changed_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_workflow_history sync error: " . $e->getMessage());
        }

        // ── 4. Create `task_subtasks` table ───────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_subtasks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `assigned_to` INT DEFAULT NULL,
                `due_date` DATE DEFAULT NULL,
                `status` ENUM('Pending', 'Completed') NOT NULL DEFAULT 'Pending',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_subtask_task` (`task_id`),
                KEY `idx_subtask_assignee` (`assigned_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_subtasks sync error: " . $e->getMessage());
        }

        // ── 5. Create `task_comments` table ───────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_comments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `subtask_id` INT DEFAULT NULL,
                `user_id` INT NOT NULL,
                `comment_text` TEXT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_tc_task` (`task_id`),
                KEY `idx_tc_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_comments sync error: " . $e->getMessage());
        }

        // ── 6. Create `task_files` table ──────────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_files` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `subtask_id` INT DEFAULT NULL,
                `file_path` VARCHAR(255) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `file_size` INT DEFAULT 0,
                `uploaded_by` INT NOT NULL,
                `revision` INT NOT NULL DEFAULT 1,
                `is_client_deliverable` TINYINT(1) DEFAULT 0,
                `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_tf_task` (`task_id`),
                KEY `idx_tf_user` (`uploaded_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_files sync error: " . $e->getMessage());
        }

        // ── 7. Create `task_reviews` table ────────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_reviews` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `reviewer_id` INT NOT NULL,
                `review_type` ENUM('Internal', 'Client') NOT NULL DEFAULT 'Internal',
                `decision` VARCHAR(50) NOT NULL,
                `comment` TEXT DEFAULT NULL,
                `revision` INT NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_tr_task` (`task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_reviews sync error: " . $e->getMessage());
        }

        // ── 8. Create `task_deliveries` table ─────────────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_deliveries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `platform` VARCHAR(100) DEFAULT NULL,
                `publication_date` DATE DEFAULT NULL,
                `publication_time` TIME DEFAULT NULL,
                `post_url` TEXT DEFAULT NULL,
                `campaign_name` VARCHAR(255) DEFAULT NULL,
                `published_by` INT DEFAULT NULL,
                `delivery_date` DATE DEFAULT NULL,
                `delivery_method` VARCHAR(100) DEFAULT NULL,
                `recipient` VARCHAR(255) DEFAULT NULL,
                `delivery_confirmation` TEXT DEFAULT NULL,
                `status` VARCHAR(50) NOT NULL DEFAULT 'Ready for Publication',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_td_task` (`task_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_deliveries sync error: " . $e->getMessage());
        }

        // ── 9. Create & Seed `task_delay_settings` table ───────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_delay_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `priority` VARCHAR(20) NOT NULL UNIQUE,
                `daily_penalty_pkr` DECIMAL(10,2) NOT NULL,
                `max_cap_pkr` DECIMAL(10,2) NOT NULL,
                `grace_hours` INT NOT NULL DEFAULT 24,
                `monthly_free_graces` INT NOT NULL DEFAULT 1,
                `max_monthly_salary_deduction_percent` DECIMAL(5,2) DEFAULT 15.00,
                `updated_by` INT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $cnt_res = $conn->query("SELECT COUNT(*) as cnt FROM `task_delay_settings`");
            $has_rows = ($cnt_res && intval($cnt_res->fetch_assoc()['cnt'] ?? 0) > 0);

            if (!$has_rows) {
                $conn->query("INSERT IGNORE INTO `task_delay_settings` 
                    (`priority`, `daily_penalty_pkr`, `max_cap_pkr`, `grace_hours`, `monthly_free_graces`, `max_monthly_salary_deduction_percent`) 
                    VALUES 
                    ('Low', 100.00, 500.00, 24, 1, 15.00),
                    ('Medium', 200.00, 1000.00, 24, 1, 15.00),
                    ('High', 400.00, 2000.00, 24, 1, 15.00)");
            }
        } catch (Throwable $e) {
            error_log("task_delay_settings table sync error: " . $e->getMessage());
        }

        // ── 10. Create `task_extension_history` table ─────────────────────────
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `task_extension_history` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` BIGINT(20) UNSIGNED NOT NULL,
                `user_id` INT NOT NULL,
                `original_due_date` DATE NOT NULL,
                `requested_due_date` DATE NOT NULL,
                `reason_category` VARCHAR(50) NOT NULL,
                `reason` TEXT NOT NULL,
                `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                `reviewed_by` INT NULL,
                `reviewed_at` DATETIME NULL,
                `admin_notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ext_task` (`task_id`),
                KEY `idx_ext_user` (`user_id`),
                KEY `idx_ext_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (Throwable $e) {
            error_log("task_extension_history table sync error: " . $e->getMessage());
        }
    }
}
