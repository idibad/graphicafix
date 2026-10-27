-- ==============================================================================
-- GRAPHICAFIX: Staff Attendance, Payroll, & Deduction Dispute System Migration
-- ==============================================================================
-- Run this script in phpMyAdmin or MySQL CLI on the live production database.
-- It safely creates all necessary tables, columns, indexes, and backfills
-- historical attendance data from login_logs.
-- ==============================================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- 1. Table: attendance_records
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_time` TIME DEFAULT NULL,
  `check_out_time` TIME DEFAULT NULL,
  `last_heartbeat` DATETIME DEFAULT NULL,
  `status` ENUM('present','late','absent','half_day','on_leave','holiday') DEFAULT 'present',
  `is_late` TINYINT(1) DEFAULT 0,
  `late_minutes` INT(11) DEFAULT 0,
  `is_grace_period` TINYINT(1) DEFAULT 0,
  `grace_count_month` INT(11) DEFAULT 0,
  `penalty_amount` DECIMAL(10,2) DEFAULT 0.00,
  `penalty_reason` VARCHAR(255) DEFAULT NULL,
  `leave_type` VARCHAR(50) DEFAULT NULL,
  `medical_leave_month_count` INT(11) DEFAULT 0,
  `location_type` VARCHAR(50) DEFAULT 'unknown',
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  `location_accuracy` DECIMAL(8,2) DEFAULT NULL,
  `distance_meters` DECIMAL(10,2) DEFAULT NULL,
  `location_denied` TINYINT(1) DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_date` (`user_id`, `attendance_date`),
  KEY `idx_att_date` (`attendance_date`),
  KEY `idx_att_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Table: attendance_overrides
CREATE TABLE IF NOT EXISTS `attendance_overrides` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `attendance_date` DATE NOT NULL,
  `attendance_type` ENUM('onsite','remote','leave','field_work') NOT NULL DEFAULT 'onsite',
  `leave_category` VARCHAR(50) DEFAULT 'casual',
  `reason` TEXT DEFAULT NULL,
  `approved_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_date_override` (`user_id`, `attendance_date`),
  KEY `idx_ov_date` (`attendance_date`),
  KEY `idx_ov_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Table: salaries
CREATE TABLE IF NOT EXISTS `salaries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `salary_month` VARCHAR(7) NOT NULL,
  `employee_id` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(150) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `base_salary` DECIMAL(12,2) DEFAULT 0.00,
  `allowances` DECIMAL(12,2) DEFAULT 0.00,
  `gross_pay` DECIMAL(12,2) DEFAULT 0.00,
  `tax` DECIMAL(12,2) DEFAULT 0.00,
  `advance` DECIMAL(12,2) DEFAULT 0.00,
  `deductions` DECIMAL(12,2) DEFAULT 0.00,
  `previous_pending` DECIMAL(12,2) DEFAULT 0.00,
  `net_salary` DECIMAL(12,2) DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) DEFAULT 0.00,
  `payment_status` ENUM('Pending','Paid','Partially Paid','On Hold') DEFAULT 'Pending',
  `payment_date` DATE DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_month` (`user_id`, `salary_month`),
  KEY `idx_salary_month` (`salary_month`),
  KEY `idx_salary_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Table: salary_deductions
CREATE TABLE IF NOT EXISTS `salary_deductions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `salary_month` VARCHAR(7) NOT NULL,
  `attendance_id` INT(11) DEFAULT NULL,
  `deduction_type` VARCHAR(60) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `description` VARCHAR(255) NOT NULL,
  `date_incurred` DATE NOT NULL,
  `status` ENUM('applied','disputed','waived','refunded') DEFAULT 'applied',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ded_user_month` (`user_id`, `salary_month`),
  KEY `idx_ded_date` (`date_incurred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Table: deduction_tickets
CREATE TABLE IF NOT EXISTS `deduction_tickets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30) NOT NULL,
  `deduction_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `reason` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('open','under_review','approved_waived','rejected') DEFAULT 'open',
  `admin_remarks` TEXT DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_tkt_user` (`user_id`),
  KEY `idx_tkt_deduction` (`deduction_id`),
  KEY `idx_tkt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Helper Stored Procedure to Add Missing Columns Safely
DROP PROCEDURE IF EXISTS AddColumnUnlessExists;
DELIMITER //
CREATE PROCEDURE AddColumnUnlessExists(
    IN tableName VARCHAR(64),
    IN colName VARCHAR(64),
    IN colDef TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tableName
          AND COLUMN_NAME = colName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', colName, '` ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Ensure columns exist in `users`
CALL AddColumnUnlessExists('users', 'gender', 'VARCHAR(10) DEFAULT "Male" AFTER `name`');
CALL AddColumnUnlessExists('users', 'status', 'VARCHAR(20) DEFAULT "active" AFTER `role`');
CALL AddColumnUnlessExists('users', 'employee_id', 'VARCHAR(50) NULL AFTER `user_id`');
CALL AddColumnUnlessExists('users', 'department', 'VARCHAR(100) DEFAULT "General" AFTER `role`');
CALL AddColumnUnlessExists('users', 'job_title', 'VARCHAR(100) NULL AFTER `department`');
CALL AddColumnUnlessExists('users', 'work_mode', 'VARCHAR(20) NOT NULL DEFAULT "remote" AFTER `job_title`');
CALL AddColumnUnlessExists('users', 'base_salary', 'DECIMAL(12,2) DEFAULT 50000.00 AFTER `job_title`');
CALL AddColumnUnlessExists('users', 'allowances', 'DECIMAL(12,2) DEFAULT 0.00 AFTER `base_salary`');

-- Ensure columns exist in `login_logs`
CALL AddColumnUnlessExists('login_logs', 'latitude', 'DECIMAL(10,8) NULL AFTER `ip_address`');
CALL AddColumnUnlessExists('login_logs', 'longitude', 'DECIMAL(11,8) NULL AFTER `latitude`');
CALL AddColumnUnlessExists('login_logs', 'attendance_type', 'ENUM("onsite","remote","field_work") DEFAULT "remote" AFTER `longitude`');

-- Clean up helper procedure
DROP PROCEDURE IF EXISTS AddColumnUnlessExists;

-- 7. Backfill Historical Records from login_logs (ONLY_FULL_GROUP_BY safe)
INSERT IGNORE INTO `attendance_records` (
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
    'remote' as location_type,
    MAX(l.ip_address) as ip_address,
    MIN(l.login_time) as created_at
FROM `login_logs` l
WHERE l.status = 'success' AND l.user_id IS NOT NULL AND l.user_id > 0
GROUP BY l.user_id, DATE(l.login_time);

SET foreign_key_checks = 1;
