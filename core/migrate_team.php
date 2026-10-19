<?php
/**
 * Migration Script: Updates the `team` table with verification, employee ID,
 * photo, department, and social fields, and backfills sample data.
 */

$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? 80;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/config.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "Migrating team table...\n";

// Get current columns in team table
$existing_cols = [];
$res = $conn->query("SHOW COLUMNS FROM `team`");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existing_cols[] = $row['Field'];
    }
} else {
    // If table doesn't exist, create it
    $conn->query("CREATE TABLE IF NOT EXISTS `team` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `member_name` varchar(255) NOT NULL,
        `role` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `phone` varchar(50) DEFAULT NULL,
        `joined_at` date DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $existing_cols = ['id', 'member_name', 'role', 'email', 'phone', 'joined_at', 'created_at'];
}

$columns_to_add = [
    'employee_id'        => "VARCHAR(50) DEFAULT NULL AFTER `id`",
    'photo'              => "VARCHAR(255) DEFAULT NULL AFTER `member_name`",
    'department'         => "VARCHAR(100) DEFAULT NULL AFTER `role`",
    'bio'                => "TEXT DEFAULT NULL AFTER `department`",
    'gender'             => "VARCHAR(20) DEFAULT 'Male' AFTER `bio`",
    'status'             => "VARCHAR(20) DEFAULT 'active' AFTER `gender`",
    'show_on_about'      => "TINYINT(1) DEFAULT 1 AFTER `status`",
    'display_order'      => "INT DEFAULT 0 AFTER `show_on_about`",
    'verification_code'  => "VARCHAR(64) DEFAULT NULL AFTER `display_order`",
    'linkedin'           => "VARCHAR(255) DEFAULT NULL AFTER `phone`",
    'github'             => "VARCHAR(255) DEFAULT NULL AFTER `linkedin`",
    'twitter'            => "VARCHAR(255) DEFAULT NULL AFTER `github`",
    'blood_group'        => "VARCHAR(10) DEFAULT NULL AFTER `twitter`",
    'emergency_contact'  => "VARCHAR(50) DEFAULT NULL AFTER `blood_group`",
    'address'            => "VARCHAR(255) DEFAULT NULL AFTER `emergency_contact`"
];

foreach ($columns_to_add as $col => $definition) {
    if (!in_array($col, $existing_cols)) {
        $sql = "ALTER TABLE `team` ADD COLUMN `{$col}` {$definition}";
        if ($conn->query($sql)) {
            echo "Added column: {$col}\n";
        } else {
            echo "Failed adding column {$col}: " . $conn->error . "\n";
        }
    }
}

// Ensure unique index on employee_id if needed
$indexes = [];
$idx_res = $conn->query("SHOW INDEX FROM `team`");
if ($idx_res) {
    while ($irow = $idx_res->fetch_assoc()) {
        $indexes[] = $irow['Key_name'];
    }
}
if (!in_array('idx_employee_id', $indexes) && !in_array('employee_id', $indexes)) {
    @$conn->query("ALTER TABLE `team` ADD UNIQUE KEY `idx_employee_id` (`employee_id`)");
}

// Backfill existing team members with employee IDs & verification codes
$members_res = $conn->query("SELECT * FROM `team` ORDER BY `id` ASC");
while ($mem = $members_res->fetch_assoc()) {
    $id = $mem['id'];
    $emp_id = $mem['employee_id'];
    $updates = [];

    if (empty($emp_id)) {
        $new_emp_id = sprintf("EMP%04d", $id);
        $updates[] = "`employee_id` = '{$new_emp_id}'";
        $emp_id = $new_emp_id;
    }

    if (empty($mem['verification_code'])) {
        $vcode = hash('sha256', 'GF_' . $emp_id . '_' . $id . '_salt');
        $updates[] = "`verification_code` = '{$vcode}'";
    }

    if (empty($mem['department'])) {
        $dept = 'Creative & Operations';
        $r = strtolower($mem['role'] ?? '');
        if (strpos($r, 'developer') !== false || strpos($r, 'web') !== false) {
            $dept = 'Web & Software Engineering';
        } elseif (strpos($r, 'design') !== false || strpos($r, 'ui') !== false) {
            $dept = 'Creative & UI/UX Design';
        } elseif (strpos($r, 'media') !== false || strpos($r, 'writer') !== false) {
            $dept = 'Digital Marketing & Content';
        } elseif (strpos($r, 'hr') !== false) {
            $dept = 'Human Resources';
        } elseif (strpos($r, 'ceo') !== false || strpos($r, 'admin') !== false) {
            $dept = 'Executive Leadership';
        }
        $updates[] = "`department` = '" . $conn->real_escape_string($dept) . "'";
    }

    if (empty($mem['bio'])) {
        $bio = "Passionate " . ($mem['role'] ?? 'professional') . " at Graphica Fix, committed to crafting impactful digital experiences and brand solutions.";
        $updates[] = "`bio` = '" . $conn->real_escape_string($bio) . "'";
    }

    if (!empty($updates)) {
        $conn->query("UPDATE `team` SET " . implode(", ", $updates) . " WHERE `id` = {$id}");
    }
}

// Now check if Arslan Ahmed (EMP0033 from the card) exists
$check_arslan = $conn->query("SELECT id FROM `team` WHERE `employee_id` = 'EMP0033' OR `member_name` = 'Arslan Ahmed'");
if ($check_arslan && $check_arslan->num_rows > 0) {
    $aid = $check_arslan->fetch_assoc()['id'];
    $conn->query("UPDATE `team` SET 
        `member_name` = 'Arslan Ahmed',
        `employee_id` = 'EMP0033',
        `role` = 'Admin',
        `department` = 'Executive Leadership & Operations',
        `email` = 'contact@graphicafix.com',
        `phone` = '0345 4568986',
        `photo` = 'uploads/team/arslan_ahmed.png',
        `bio` = 'Executive administrator leading operations, IT infrastructure, and digital creative systems across Graphica Fix.',
        `gender` = 'Male',
        `status` = 'active',
        `show_on_about` = 1,
        `display_order` = 2,
        `joined_at` = '2023-01-10',
        `blood_group` = 'O+'
    WHERE `id` = {$aid}");
    echo "Updated Arslan Ahmed (EMP0033).\n";
} else {
    $vcode = hash('sha256', 'GF_EMP0033_arslan_salt');
    $conn->query("INSERT INTO `team` 
        (`member_name`, `employee_id`, `photo`, `role`, `department`, `bio`, `gender`, `status`, `show_on_about`, `display_order`, `verification_code`, `email`, `phone`, `joined_at`, `blood_group`)
    VALUES 
        ('Arslan Ahmed', 'EMP0033', 'uploads/team/arslan_ahmed.png', 'Admin', 'Executive Leadership & Operations', 'Executive administrator leading operations, IT infrastructure, and digital creative systems across Graphica Fix.', 'Male', 'active', 1, 2, '{$vcode}', 'contact@graphicafix.com', '0345 4568986', '2023-01-10', 'O+')");
    echo "Inserted Arslan Ahmed (EMP0033).\n";
}

echo "Migration completed successfully!\n";
