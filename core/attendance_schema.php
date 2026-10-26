<?php
/**
 * core/attendance_schema.php
 * Automated Schema Migration for Enhanced Attendance, Payroll & Deduction Dispute System
 * Safely creates and synchronizes tables:
 * - attendance_records
 * - salaries
 * - salary_deductions
 * - deduction_tickets
 * - schema additions on users and attendance_overrides
 * - staff roster sync across users and team
 */

if (!function_exists('ensure_attendance_schema')) {
    function ensure_attendance_schema($conn) {
        if (!$conn) return;

        static $schema_ensured = false;
        if ($schema_ensured) return;
        $schema_ensured = true;

        try {
            // ── 0. Fix `users.user_id` AUTO_INCREMENT ─────────────────────────────
            $u_col_res = $conn->query("SHOW COLUMNS FROM `users` WHERE Field = 'user_id'");
            if ($u_col_res && $urow = $u_col_res->fetch_assoc()) {
                if (strpos(strtolower($urow['Extra']), 'auto_increment') === false) {
                    $zero_chk = $conn->query("SELECT user_id FROM `users` WHERE user_id = 0");
                    if ($zero_chk && $zero_chk->num_rows > 0) {
                        $m_res = $conn->query("SELECT MAX(user_id) as m FROM `users`");
                        $next_m = intval($m_res->fetch_assoc()['m'] ?? 100) + 1;
                        @$conn->query("UPDATE `users` SET user_id = {$next_m} WHERE user_id = 0");
                    }
                    @$conn->query("ALTER TABLE `users` MODIFY COLUMN `user_id` INT(11) NOT NULL AUTO_INCREMENT");
                }
            }

            // ── 1. Create `attendance_records` table ──────────────────────────────
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Check existing columns in attendance_records
            $att_cols = [];
            $res = $conn->query("SHOW COLUMNS FROM `attendance_records`");
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $att_cols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('last_heartbeat', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `last_heartbeat` DATETIME NULL AFTER `check_out_time`");
            }
            if (!in_array('is_grace_period', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `is_grace_period` TINYINT(1) DEFAULT 0 AFTER `late_minutes`");
            }
            if (!in_array('grace_count_month', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `grace_count_month` INT DEFAULT 0 AFTER `is_grace_period`");
            }
            if (!in_array('penalty_amount', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `penalty_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grace_count_month`");
            }
            if (!in_array('penalty_reason', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `penalty_reason` VARCHAR(255) NULL AFTER `penalty_amount`");
            }
            if (!in_array('location_denied', $att_cols)) {
                @$conn->query("ALTER TABLE `attendance_records` ADD COLUMN `location_denied` TINYINT(1) DEFAULT 0 AFTER `distance_meters`");
            }

            // ── 2. Create `salaries` table ─────────────────────────────────────────
            $conn->query("CREATE TABLE IF NOT EXISTS `salaries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `salary_month` VARCHAR(7) NOT NULL, -- Format YYYY-MM
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
                `payment_status` ENUM('Pending', 'Paid', 'Partially Paid', 'On Hold') DEFAULT 'Pending',
                `payment_date` DATE NULL,
                `payment_method` VARCHAR(50) NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_user_month` (`user_id`, `salary_month`),
                KEY `idx_salary_month` (`salary_month`),
                KEY `idx_salary_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ── 3. Create `salary_deductions` table ────────────────────────────────
            $conn->query("CREATE TABLE IF NOT EXISTS `salary_deductions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `salary_month` VARCHAR(7) NOT NULL, -- Format YYYY-MM
                `attendance_id` INT NULL,
                `deduction_type` VARCHAR(60) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `description` VARCHAR(255) NOT NULL,
                `date_incurred` DATE NOT NULL,
                `status` ENUM('applied', 'disputed', 'waived', 'refunded') DEFAULT 'applied',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ded_user_month` (`user_id`, `salary_month`),
                KEY `idx_ded_date` (`date_incurred`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ── 4. Create `deduction_tickets` table ────────────────────────────────
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ── 5. Enhance `users` table with gender, employee_id, salary fields ──
            $user_cols = [];
            $res = $conn->query("SHOW COLUMNS FROM `users`");
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $user_cols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('gender', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `gender` VARCHAR(10) DEFAULT 'Male' AFTER `name`");
            }
            if (!in_array('employee_id', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `employee_id` VARCHAR(50) NULL AFTER `user_id`");
            }
            if (!in_array('department', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `department` VARCHAR(100) DEFAULT 'General' AFTER `role`");
            }
            if (!in_array('job_title', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `job_title` VARCHAR(100) NULL AFTER `department`");
            }
            if (!in_array('base_salary', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `base_salary` DECIMAL(12,2) DEFAULT 50000.00 AFTER `job_title`");
            }
            if (!in_array('allowances', $user_cols)) {
                @$conn->query("ALTER TABLE `users` ADD COLUMN `allowances` DECIMAL(12,2) DEFAULT 0.00 AFTER `base_salary`");
            }

            // ── 6. Enhance `attendance_overrides` with `leave_category` ───────────
            $res = $conn->query("SHOW COLUMNS FROM `attendance_overrides`");
            $override_cols = [];
            if ($res) {
                while ($c = $res->fetch_assoc()) {
                    $override_cols[] = strtolower($c['Field']);
                }
            }
            if (!in_array('leave_category', $override_cols)) {
                @$conn->query("ALTER TABLE `attendance_overrides` ADD COLUMN `leave_category` VARCHAR(50) DEFAULT 'casual' AFTER `attendance_type`");
            }

            // ── 7. Automatic Sync of Staff Roster (Super Admins, Admins, HR, PM, Staff) ──
            sync_staff_roster_accounts($conn);

        } catch (Throwable $e) {
            error_log("ensure_attendance_schema non-fatal: " . $e->getMessage());
        }
    }
}

/**
 * Ensures all staff across all core roles (super_admin, admin, hrm, pm, staff/user, teacher)
 * are properly synchronized between `team` and `users` with accurate roles, titles, and salaries.
 */
function sync_staff_roster_accounts($conn) {
    if (!$conn) return;

    $staff_definitions = [
        // Super Admins
        [
            'match_user_id' => 26,
            'username'      => 'sbano',
            'email'         => 'sbano0775@gmail.com',
            'name'          => 'Shah Jahan Bano (CEO)',
            'role'          => 'super_admin',
            'gender'        => 'Female',
            'employee_id'   => 'EMP0007',
            'department'    => 'Executive Leadership',
            'job_title'     => 'Chief Executive Officer (CEO)',
            'base_salary'   => 150000.00,
            'allowances'    => 15000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => 25,
            'username'      => 'ibadshalmany',
            'email'         => 'ibadshalmany@gmail.com',
            'name'          => 'Ibad Shalmany',
            'role'          => 'super_admin',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0004',
            'department'    => 'Creative & UI/UX Design',
            'job_title'     => 'Creative Director & Super Admin',
            'base_salary'   => 110000.00,
            'allowances'    => 10000.00,
            'status'        => 'active'
        ],

        // Admin
        [
            'match_user_id' => null,
            'username'      => 'arslan_admin',
            'email'         => 'contact@graphicafix.com',
            'name'          => 'Arslan Ahmed',
            'role'          => 'admin',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0033',
            'department'    => 'Executive Leadership & Operations',
            'job_title'     => 'Operations Administrator',
            'base_salary'   => 75000.00,
            'allowances'    => 5000.00,
            'status'        => 'active'
        ],

        // HR
        [
            'match_user_id' => 43,
            'username'      => 'danial_hr',
            'email'         => 'danialmukhtiar@gmail.com',
            'name'          => 'Danial Mukhtiar',
            'role'          => 'hrm',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0010',
            'department'    => 'Human Resources',
            'job_title'     => 'Human Resources Manager',
            'base_salary'   => 70000.00,
            'allowances'    => 5000.00,
            'status'        => 'active'
        ],

        // PM (Project Manager)
        [
            'match_user_id' => null,
            'username'      => 'hassan_pm',
            'email'         => 'hassan@example.com',
            'name'          => 'Hassan Shafi',
            'role'          => 'pm',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0001',
            'department'    => 'Creative & UI/UX Design',
            'job_title'     => 'Project Manager & Lead Designer',
            'base_salary'   => 85000.00,
            'allowances'    => 8000.00,
            'status'        => 'active'
        ],

        // Active Users / Staff
        [
            'match_user_id' => null,
            'username'      => 'ayesha_noor',
            'email'         => 'ayesha@example.com',
            'name'          => 'Ayesha Noor',
            'role'          => 'user',
            'gender'        => 'Female',
            'employee_id'   => 'EMP0002',
            'department'    => 'Web & Software Engineering',
            'job_title'     => 'Frontend Developer',
            'base_salary'   => 65000.00,
            'allowances'    => 5000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => 31,
            'username'      => 'ikram_khan',
            'email'         => 'ikram.dataengineer.info@gmail.com',
            'name'          => 'Muhammad Ikram',
            'role'          => 'user',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0008',
            'department'    => 'Web & Software Engineering',
            'job_title'     => 'Web Developer',
            'base_salary'   => 65000.00,
            'allowances'    => 5000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => 30,
            'username'      => 'fatima_hassan',
            'email'         => 'fatimahassan@graphicafix.com',
            'name'          => 'Fatima Hassan',
            'role'          => 'user',
            'gender'        => 'Female',
            'employee_id'   => 'EMP0006',
            'department'    => 'Digital Marketing & Content',
            'job_title'     => 'Social Media Manager',
            'base_salary'   => 52000.00,
            'allowances'    => 3000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => 29,
            'username'      => 'sabakareem',
            'email'         => 'sabakareem@graphciafix.com',
            'name'          => 'Saba Kareem',
            'role'          => 'user',
            'gender'        => 'Female',
            'employee_id'   => 'EMP0005',
            'department'    => 'Digital Marketing & Content',
            'job_title'     => 'Social Media Optimizer',
            'base_salary'   => 48000.00,
            'allowances'    => 3000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => null,
            'username'      => 'bilal_mehmood',
            'email'         => 'bilal@example.com',
            'name'          => 'Bilal Mehmood',
            'role'          => 'user',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0003',
            'department'    => 'Digital Marketing & Content',
            'job_title'     => 'Content Writer',
            'base_salary'   => 45000.00,
            'allowances'    => 3000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => null,
            'username'      => 'kashifullah',
            'email'         => 'kashif@example.com',
            'name'          => 'Kashifullah Khan',
            'role'          => 'user',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0009',
            'department'    => 'Creative & UI/UX Design',
            'job_title'     => 'UI Designer',
            'base_salary'   => 55000.00,
            'allowances'    => 4000.00,
            'status'        => 'active'
        ],

        // Instructors
        [
            'match_user_id' => 41,
            'username'      => 'ibadullah',
            'email'         => 'idibad5321@gmail.com',
            'name'          => 'Ibadullah Khan',
            'role'          => 'teacher',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0011',
            'department'    => 'LMS & Academics',
            'job_title'     => 'Senior Instructor',
            'base_salary'   => 55000.00,
            'allowances'    => 3000.00,
            'status'        => 'active'
        ],
        [
            'match_user_id' => 44,
            'username'      => 'suliman_khan',
            'email'         => 'sulimank418@gmail.com',
            'name'          => 'Suliman Khan',
            'role'          => 'teacher',
            'gender'        => 'Male',
            'employee_id'   => 'EMP0012',
            'department'    => 'LMS & Academics',
            'job_title'     => 'Course Instructor',
            'base_salary'   => 50000.00,
            'allowances'    => 2500.00,
            'status'        => 'active'
        ],
    ];

    $default_hash = password_hash('Graphica@123', PASSWORD_DEFAULT);

    foreach ($staff_definitions as $s) {
        $found_uid = null;

        if (!empty($s['match_user_id'])) {
            $c1 = $conn->query("SELECT user_id FROM users WHERE user_id = {$s['match_user_id']}");
            if ($c1 && $c1->num_rows > 0) $found_uid = $s['match_user_id'];
        }

        if (!$found_uid) {
            $c2 = $conn->query("SELECT user_id FROM users WHERE username = '{$s['username']}' OR email = '{$s['email']}'");
            if ($c2 && $c2->num_rows > 0) $found_uid = $c2->fetch_assoc()['user_id'];
        }

        if (!$found_uid && !empty($s['employee_id'])) {
            $c3 = $conn->query("SELECT user_id FROM users WHERE employee_id = '{$s['employee_id']}'");
            if ($c3 && $c3->num_rows > 0) $found_uid = $c3->fetch_assoc()['user_id'];
        }

        if ($found_uid) {
            $stmt = $conn->prepare("UPDATE users SET 
                name = ?, 
                role = ?, 
                gender = ?, 
                employee_id = ?, 
                department = ?, 
                job_title = ?, 
                base_salary = ?, 
                allowances = ?, 
                status = ? 
                WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssssddsi", 
                    $s['name'], 
                    $s['role'], 
                    $s['gender'], 
                    $s['employee_id'], 
                    $s['department'], 
                    $s['job_title'], 
                    $s['base_salary'], 
                    $s['allowances'], 
                    $s['status'], 
                    $found_uid
                );
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO users 
                (name, username, email, password, role, gender, employee_id, department, job_title, base_salary, allowances, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("sssssssssdds",
                    $s['name'],
                    $s['username'],
                    $s['email'],
                    $default_hash,
                    $s['role'],
                    $s['gender'],
                    $s['employee_id'],
                    $s['department'],
                    $s['job_title'],
                    $s['base_salary'],
                    $s['allowances'],
                    $s['status']
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
