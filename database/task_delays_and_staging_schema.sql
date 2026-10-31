-- ==============================================================================
-- Graphicafix: Task Delays, Staging Pipeline, Subtasks & Penalty Deductions Schema
-- ==============================================================================

-- 1. Tasks Table Modifications (Columns & Indexes)
ALTER TABLE `tasks`
    ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `submitted_for_review_at` DATETIME NULL AFTER `completed_at`,
    ADD COLUMN IF NOT EXISTS `delay_days` INT DEFAULT 0 AFTER `submitted_for_review_at`,
    ADD COLUMN IF NOT EXISTS `delay_deduction_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `delay_days`,
    ADD COLUMN IF NOT EXISTS `delay_status` ENUM('on_time', 'grace_period', 'delayed', 'penalized', 'exempted', 'extended') DEFAULT 'on_time' AFTER `delay_deduction_amount`,
    ADD COLUMN IF NOT EXISTS `delay_exempted_by` INT NULL AFTER `delay_status`,
    ADD COLUMN IF NOT EXISTS `delay_exemption_reason` VARCHAR(255) NULL AFTER `delay_exempted_by`,
    ADD COLUMN IF NOT EXISTS `delay_last_calculated_at` DATETIME NULL AFTER `delay_exemption_reason`,
    ADD COLUMN IF NOT EXISTS `extension_status` ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'none' AFTER `delay_status`,
    ADD COLUMN IF NOT EXISTS `extension_requested_date` DATE NULL AFTER `extension_status`,
    ADD COLUMN IF NOT EXISTS `extension_reason_category` VARCHAR(100) NULL DEFAULT 'General' AFTER `extension_requested_date`,
    ADD COLUMN IF NOT EXISTS `extension_reason` TEXT NULL AFTER `extension_reason_category`,
    ADD COLUMN IF NOT EXISTS `extension_reviewed_by` INT NULL AFTER `extension_reason`,
    ADD COLUMN IF NOT EXISTS `extension_admin_notes` TEXT NULL AFTER `extension_reviewed_by`,
    ADD COLUMN IF NOT EXISTS `stage` VARCHAR(50) NOT NULL DEFAULT 'Brief' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `brief_status` VARCHAR(50) NOT NULL DEFAULT 'Draft' AFTER `stage`,
    ADD COLUMN IF NOT EXISTS `brief_objective` TEXT NULL AFTER `brief_status`,
    ADD COLUMN IF NOT EXISTS `brief_deliverables` TEXT NULL AFTER `brief_objective`,
    ADD COLUMN IF NOT EXISTS `brief_references` TEXT NULL AFTER `brief_deliverables`,
    ADD COLUMN IF NOT EXISTS `brief_specs` TEXT NULL AFTER `brief_references`,
    ADD COLUMN IF NOT EXISTS `brief_copy` TEXT NULL AFTER `brief_specs`,
    ADD COLUMN IF NOT EXISTS `production_status` VARCHAR(50) NOT NULL DEFAULT 'Not Started' AFTER `brief_copy`,
    ADD COLUMN IF NOT EXISTS `production_progress` INT DEFAULT 0 AFTER `production_status`,
    ADD COLUMN IF NOT EXISTS `internal_review_status` VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER `production_progress`,
    ADD COLUMN IF NOT EXISTS `client_feedback` TEXT NULL AFTER `internal_review_status`,
    ADD COLUMN IF NOT EXISTS `client_approval_status` VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER `client_feedback`,
    ADD COLUMN IF NOT EXISTS `current_revision` INT DEFAULT 1 AFTER `client_approval_status`,
    ADD COLUMN IF NOT EXISTS `client_token` VARCHAR(64) DEFAULT NULL AFTER `current_revision`;

-- 2. Salary Deductions Linkage
ALTER TABLE `salary_deductions`
    ADD COLUMN IF NOT EXISTS `task_id` BIGINT(20) UNSIGNED NULL AFTER `attendance_id`,
    ADD COLUMN IF NOT EXISTS `delay_days_count` INT DEFAULT 0 AFTER `task_id`;

-- 3. Task Workflow History
CREATE TABLE IF NOT EXISTS `task_workflow_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT(20) UNSIGNED NOT NULL,
    `from_stage` VARCHAR(50) NOT NULL,
    `to_stage` VARCHAR(50) NOT NULL,
    `changed_by` INT NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_twh_task` (`task_id`),
    KEY `idx_twh_user` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Task Subtasks (Checklists)
CREATE TABLE IF NOT EXISTS `task_subtasks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Task Comments (Discussion Feed)
CREATE TABLE IF NOT EXISTS `task_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT(20) UNSIGNED NOT NULL,
    `subtask_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `comment_text` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tc_task` (`task_id`),
    KEY `idx_tc_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Task Files & Deliverables
CREATE TABLE IF NOT EXISTS `task_files` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Task Reviews & Approvals
CREATE TABLE IF NOT EXISTS `task_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` BIGINT(20) UNSIGNED NOT NULL,
    `reviewer_id` INT NOT NULL,
    `review_type` ENUM('Internal', 'Client') NOT NULL DEFAULT 'Internal',
    `decision` VARCHAR(50) NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `revision` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_tr_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Task Deliveries & Multi-channel Campaigns
CREATE TABLE IF NOT EXISTS `task_deliveries` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Task Delay Penalty Settings
CREATE TABLE IF NOT EXISTS `task_delay_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `priority` VARCHAR(20) NOT NULL UNIQUE,
    `daily_penalty_pkr` DECIMAL(10,2) NOT NULL,
    `max_cap_pkr` DECIMAL(10,2) NOT NULL,
    `grace_hours` INT NOT NULL DEFAULT 24,
    `monthly_free_graces` INT NOT NULL DEFAULT 1,
    `max_monthly_salary_deduction_percent` DECIMAL(5,2) DEFAULT 15.00,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `task_delay_settings` 
    (`priority`, `daily_penalty_pkr`, `max_cap_pkr`, `grace_hours`, `monthly_free_graces`, `max_monthly_salary_deduction_percent`) 
VALUES 
    ('Low', 100.00, 500.00, 24, 1, 15.00),
    ('Medium', 200.00, 1000.00, 24, 1, 15.00),
    ('High', 400.00, 2000.00, 24, 1, 15.00);

-- 10. Task Extension History
CREATE TABLE IF NOT EXISTS `task_extension_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
