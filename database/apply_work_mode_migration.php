<?php
/**
 * database/apply_work_mode_migration.php
 * Sets default work_mode to remote, and sets designated users to onsite.
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/attendance_schema.php';

header('Content-Type: text/plain');

echo "Starting work_mode schema synchronization...\n";
ensure_attendance_schema($conn);

// 1. Ensure work_mode column exists
$cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
if (!in_array('work_mode', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN work_mode VARCHAR(20) NOT NULL DEFAULT 'remote' AFTER job_title");
    echo "Added column 'work_mode' to users table.\n";
} else {
    echo "Column 'work_mode' already exists.\n";
}

// 2. Set all users to remote initially
$conn->query("UPDATE users SET work_mode = 'remote'");
echo "All users initialized to 'remote'.\n";

// 3. Mark the designated on-site users
// ibad, bushra, arsalan, subhan, hanan, nouman
$onsiteQuery = "UPDATE users SET work_mode = 'onsite' WHERE 
    user_id IN (25, 41, 118, 128, 129, 130, 138, 139) 
    OR LOWER(name) LIKE '%ibad%' OR LOWER(username) LIKE '%ibad%'
    OR LOWER(name) LIKE '%bushra%' OR LOWER(username) LIKE '%bushra%'
    OR LOWER(name) LIKE '%arslan%' OR LOWER(name) LIKE '%arsalan%' OR LOWER(username) LIKE '%arslan%' OR LOWER(username) LIKE '%arsalan%'
    OR LOWER(name) LIKE '%subhan%' OR LOWER(username) LIKE '%subhan%'
    OR LOWER(name) LIKE '%hanan%' OR LOWER(username) LIKE '%hanan%'
    OR LOWER(name) LIKE '%nouman%' OR LOWER(name) LIKE '%noman%' OR LOWER(username) LIKE '%nouman%' OR LOWER(username) LIKE '%noman%'";

$conn->query($onsiteQuery);

// 4. Output results
echo "\n--- Registered On-site Employees ---\n";
$resOnsite = $conn->query("SELECT user_id, name, username, email, work_mode FROM users WHERE work_mode = 'onsite' ORDER BY user_id ASC");
while ($r = $resOnsite->fetch_assoc()) {
    echo "ID: {$r['user_id']} | {$r['name']} (@{$r['username']}) | {$r['email']} => {$r['work_mode']}\n";
}

$resRem = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE work_mode = 'remote'");
$remCount = $resRem->fetch_assoc()['cnt'];
echo "\nTotal Remote Employees: {$remCount}\n";
echo "Migration successfully completed.\n";
