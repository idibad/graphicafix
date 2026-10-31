<?php
/**
 * database/apply_task_delays_and_staging_migration.php
 * Standalone migration script for Task Delays, Penalties, Staging Pipeline & Workspaces.
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/task_delay_schema.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "========================================================\n";
echo " Graphicafix: Task Delays, Staging & Deductions Migration\n";
echo "========================================================\n\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "[ERROR] Database connection failed.\n";
    exit(1);
}

echo "1. Applying automated schema updates...\n";
ensure_task_delay_schema($conn);
echo "   -> Automated schema pass complete.\n\n";

echo "2. Verifying 'tasks' table schema:\n";
$task_cols = array_column($conn->query("SHOW COLUMNS FROM `tasks`")->fetch_all(MYSQLI_ASSOC), 'Field');
$expected_task_cols = [
    'stage', 'completed_at', 'submitted_for_review_at', 'delay_days',
    'delay_deduction_amount', 'delay_status', 'extension_status',
    'brief_status', 'brief_objective', 'production_status', 'production_progress',
    'internal_review_status', 'client_approval_status', 'client_token'
];
foreach ($expected_task_cols as $col) {
    $exists = in_array($col, $task_cols);
    echo "   [" . ($exists ? "OK" : "MISSING") . "] tasks.{$col}\n";
}

echo "\n3. Verifying 'salary_deductions' table schema:\n";
$ded_cols = array_column($conn->query("SHOW COLUMNS FROM `salary_deductions`")->fetch_all(MYSQLI_ASSOC), 'Field');
foreach (['task_id', 'delay_days_count'] as $col) {
    $exists = in_array($col, $ded_cols);
    echo "   [" . ($exists ? "OK" : "MISSING") . "] salary_deductions.{$col}\n";
}

echo "\n4. Verifying support tables:\n";
$tables = [
    'task_workflow_history',
    'task_subtasks',
    'task_comments',
    'task_files',
    'task_reviews',
    'task_deliveries',
    'task_delay_settings',
    'task_extension_history'
];
foreach ($tables as $tbl) {
    $check = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    $exists = ($check && $check->num_rows > 0);
    echo "   [" . ($exists ? "OK" : "MISSING") . "] {$tbl}\n";
}

echo "\n5. Verifying 'task_delay_settings' configuration:\n";
$settings = $conn->query("SELECT priority, daily_penalty_pkr, max_cap_pkr, grace_hours, monthly_free_graces FROM task_delay_settings ORDER BY id ASC");
if ($settings && $settings->num_rows > 0) {
    while ($s = $settings->fetch_assoc()) {
        echo "   - Priority: {$s['priority']} | Daily Penalty: PKR " . number_format($s['daily_penalty_pkr']) . " | Max Cap: PKR " . number_format($s['max_cap_pkr']) . " | Grace: {$s['grace_hours']}h\n";
    }
} else {
    echo "   [WARNING] No rows in task_delay_settings\n";
}

echo "\nMigration and verification completed successfully!\n";
