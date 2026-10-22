<?php
/**
 * core/dpr_schema.php
 * Automated Schema Migration for Daily Progress Reports (DPR)
 * Safely verifies and auto-migrates missing columns in daily_progress_reports
 */

if (!function_exists('ensure_dpr_schema')) {
    function ensure_dpr_schema($conn) {
        if (!$conn) return;

        // 1. Create table if it does not exist at all
        $conn->query("CREATE TABLE IF NOT EXISTS daily_progress_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_date DATE NOT NULL,
            hours_worked DECIMAL(4,1) DEFAULT 8.0,
            project_id INT NULL,
            tasks_completed TEXT NOT NULL,
            tasks_in_progress TEXT NULL,
            challenges TEXT,
            blocker_severity VARCHAR(20) DEFAULT 'none',
            waiting_on VARCHAR(255) NULL,
            plan_tomorrow TEXT,
            deliverable_links TEXT NULL,
            productivity_rating TINYINT DEFAULT 5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (user_id, report_date)
        )");

        // 2. Fetch existing column names
        $existing_cols = [];
        $cols_res = $conn->query("SHOW COLUMNS FROM daily_progress_reports");
        if ($cols_res) {
            while ($c = $cols_res->fetch_assoc()) {
                $existing_cols[] = strtolower($c['Field']);
            }
        }

        // 3. Dynamically add any missing columns for backwards compatibility
        if (!in_array('hours_worked', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN hours_worked DECIMAL(4,1) DEFAULT 8.0 AFTER report_date");
        }
        if (!in_array('project_id', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN project_id INT NULL AFTER hours_worked");
        }
        if (!in_array('tasks_in_progress', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN tasks_in_progress TEXT NULL AFTER tasks_completed");
        }
        if (!in_array('blocker_severity', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN blocker_severity VARCHAR(20) DEFAULT 'none' AFTER challenges");
        }
        if (!in_array('waiting_on', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN waiting_on VARCHAR(255) NULL AFTER blocker_severity");
        }
        if (!in_array('deliverable_links', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN deliverable_links TEXT NULL AFTER plan_tomorrow");
        }
        if (!in_array('productivity_rating', $existing_cols)) {
            @$conn->query("ALTER TABLE daily_progress_reports ADD COLUMN productivity_rating TINYINT DEFAULT 5 AFTER deliverable_links");
        }

        // 4. Ensure projects table fallback exists
        @$conn->query("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
