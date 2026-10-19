<?php
/**
 * Auto-migration & Schema synchronization helper for `team` table
 * Automatically executes on any environment (local, staging, production)
 * if any required column is missing, preventing "Unknown column" errors.
 */

function ensureTeamSchema($conn) {
    if (!$conn) return;

    // Check existing columns directly
    $existing = [];
    try {
        $cols_res = $conn->query("SHOW COLUMNS FROM `team`");
        if ($cols_res) {
            while ($r = $cols_res->fetch_assoc()) {
                $existing[] = strtolower($r['Field']);
            }
        }
    } catch (Throwable $e) {
        // Table probably does not exist yet, try to create it
    }

    if (empty($existing)) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `team` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `employee_id` varchar(50) DEFAULT NULL,
                `member_name` varchar(255) NOT NULL,
                `photo` varchar(255) DEFAULT NULL,
                `role` varchar(255) DEFAULT NULL,
                `department` varchar(100) DEFAULT NULL,
                `bio` text DEFAULT NULL,
                `gender` varchar(20) DEFAULT 'Male',
                `status` varchar(20) DEFAULT 'active',
                `show_on_about` tinyint(1) DEFAULT 1,
                `display_order` int(11) DEFAULT 0,
                `verification_code` varchar(64) DEFAULT NULL,
                `email` varchar(255) DEFAULT NULL,
                `phone` varchar(50) DEFAULT NULL,
                `linkedin` varchar(255) DEFAULT NULL,
                `github` varchar(255) DEFAULT NULL,
                `twitter` varchar(255) DEFAULT NULL,
                `blood_group` varchar(10) DEFAULT NULL,
                `emergency_contact` varchar(50) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `joined_at` date DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                UNIQUE KEY `idx_emp_id` (`employee_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) {}

        // Re-read columns
        try {
            $cols_res = $conn->query("SHOW COLUMNS FROM `team`");
            if ($cols_res) {
                while ($r = $cols_res->fetch_assoc()) {
                    $existing[] = strtolower($r['Field']);
                }
            }
        } catch (Throwable $e) {}
    }

    $needed = [
        'employee_id'        => "VARCHAR(50) DEFAULT NULL",
        'photo'              => "VARCHAR(255) DEFAULT NULL",
        'department'         => "VARCHAR(100) DEFAULT NULL",
        'bio'                => "TEXT DEFAULT NULL",
        'gender'             => "VARCHAR(20) DEFAULT 'Male'",
        'status'             => "VARCHAR(20) DEFAULT 'active'",
        'show_on_about'      => "TINYINT(1) DEFAULT 1",
        'display_order'      => "INT DEFAULT 0",
        'verification_code'  => "VARCHAR(64) DEFAULT NULL",
        'linkedin'           => "VARCHAR(255) DEFAULT NULL",
        'github'             => "VARCHAR(255) DEFAULT NULL",
        'twitter'            => "VARCHAR(255) DEFAULT NULL",
        'blood_group'        => "VARCHAR(10) DEFAULT NULL",
        'emergency_contact'  => "VARCHAR(50) DEFAULT NULL",
        'address'            => "VARCHAR(255) DEFAULT NULL"
    ];

    $has_missing = false;
    foreach ($needed as $col => $def) {
        if (!in_array(strtolower($col), $existing)) {
            $has_missing = true;
            try {
                $conn->query("ALTER TABLE `team` ADD COLUMN `{$col}` {$def}");
                $existing[] = strtolower($col);
            } catch (Throwable $e) {
                // Column might already exist or host restricted ALTER
            }
        }
    }

    // Ensure id is AUTO_INCREMENT
    try {
        $id_info = $conn->query("SHOW COLUMNS FROM `team` WHERE Field = 'id'")->fetch_assoc();
        if ($id_info && strpos(strtolower($id_info['Extra'] ?? ''), 'auto_increment') === false) {
            $conn->query("ALTER TABLE `team` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
        }
    } catch (Throwable $e) {}

    // Backfill missing employee_ids and verification_codes if columns exist
    if (in_array('employee_id', $existing)) {
        try {
            $rows = $conn->query("SELECT id, employee_id, member_name FROM `team`");
            if ($rows) {
                while ($row = $rows->fetch_assoc()) {
                    $rid = intval($row['id']);
                    $updates = [];
                    if (empty($row['employee_id'])) {
                        $new_id = sprintf("EMP%04d", $rid);
                        $updates[] = "`employee_id` = '{$new_id}'";
                        if (in_array('verification_code', $existing)) {
                            $vhash = hash('sha256', 'GF_' . $new_id . '_' . $rid . '_salt');
                            $updates[] = "`verification_code` = '{$vhash}'";
                        }
                    }
                    if (in_array('status', $existing)) {
                        $updates[] = "`status` = IF(status IS NULL OR status = '', 'active', status)";
                    }
                    if (in_array('show_on_about', $existing)) {
                        $updates[] = "`show_on_about` = IF(show_on_about IS NULL, 1, show_on_about)";
                    }
                    if (!empty($updates)) {
                        $conn->query("UPDATE `team` SET " . implode(", ", $updates) . " WHERE `id` = {$rid}");
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    // Seed Arslan Ahmed (EMP0033 from uploaded card) if not existing
    try {
        $chk_arslan = $conn->query("SELECT id FROM `team` WHERE `employee_id` = 'EMP0033' OR `member_name` = 'Arslan Ahmed'");
        if (!$chk_arslan || $chk_arslan->num_rows === 0) {
            $vcode_arslan = hash('sha256', 'GF_EMP0033_arslan_salt');
            $conn->query("INSERT INTO `team` 
                (`member_name`, `employee_id`, `photo`, `role`, `department`, `bio`, `gender`, `status`, `show_on_about`, `display_order`, `verification_code`, `email`, `phone`, `joined_at`, `blood_group`)
            VALUES 
                ('Arslan Ahmed', 'EMP0033', 'uploads/team/arslan_ahmed.png', 'Admin', 'Executive Leadership & Operations', 'Executive administrator leading operations, IT infrastructure, and digital creative systems across Graphica Fix.', 'Male', 'active', 1, 2, '{$vcode_arslan}', 'contact@graphicafix.com', '0345 4568986', '2023-01-10', 'O+')");
        }
    } catch (Throwable $e) {}
}
