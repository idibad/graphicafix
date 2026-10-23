<?php
/**
 * core/project_pm_schema.php
 * Automated Schema Migration for Enhanced Project Management Suite
 * Manages Milestones, Deliverables, Client Asset Requests, Client Queries, and Broadcasts
 */

if (!function_exists('ensure_project_pm_schema')) {
    function ensure_project_pm_schema($conn) {
        if (!$conn || !($conn instanceof mysqli)) return false;
        static $executed = false;
        if ($executed) return true;

        try {
            // 1. Milestones Table
            $conn->query("CREATE TABLE IF NOT EXISTS project_milestones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                due_date DATE NULL,
                order_num INT DEFAULT 1,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_project_id (project_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 2. Deliverables Table
            $conn->query("CREATE TABLE IF NOT EXISTS project_deliverables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                milestone_id INT NOT NULL,
                project_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                specs TEXT NULL,
                deliverable_file VARCHAR(255) NULL,
                live_preview_url VARCHAR(500) NULL,
                version VARCHAR(20) DEFAULT '1.0',
                signoff_status VARCHAR(50) DEFAULT 'draft',
                revision_notes TEXT NULL,
                revision_count INT DEFAULT 0,
                signed_off_at DATETIME NULL,
                signed_off_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_milestone_id (milestone_id),
                INDEX idx_project_id (project_id),
                INDEX idx_signoff_status (signoff_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Ensure all columns exist in project_deliverables (in case table pre-existed)
            $del_cols_res = $conn->query("SHOW COLUMNS FROM project_deliverables");
            if ($del_cols_res) {
                $del_cols = [];
                while ($dc = $del_cols_res->fetch_assoc()) {
                    $del_cols[] = strtolower($dc['Field']);
                }
                $del_defs = [
                    'milestone_id' => "INT NOT NULL DEFAULT 0",
                    'specs' => "TEXT NULL",
                    'deliverable_file' => "VARCHAR(255) NULL",
                    'live_preview_url' => "VARCHAR(500) NULL",
                    'version' => "VARCHAR(20) DEFAULT '1.0'",
                    'signoff_status' => "VARCHAR(50) DEFAULT 'draft'",
                    'revision_notes' => "TEXT NULL",
                    'revision_count' => "INT DEFAULT 0",
                    'signed_off_at' => "DATETIME NULL",
                    'signed_off_by' => "INT NULL"
                ];
                foreach ($del_defs as $col_name => $col_def) {
                    if (!in_array($col_name, $del_cols)) {
                        @$conn->query("ALTER TABLE project_deliverables ADD COLUMN $col_name $col_def");
                    }
                }
            }

            // 3. Client Asset & Information Requests (Blockers)
            $conn->query("CREATE TABLE IF NOT EXISTS project_client_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                request_title VARCHAR(255) NOT NULL,
                request_type VARCHAR(50) DEFAULT 'file',
                instructions TEXT NULL,
                priority VARCHAR(20) DEFAULT 'normal',
                status VARCHAR(50) DEFAULT 'requested',
                submitted_file VARCHAR(255) NULL,
                submitted_text TEXT NULL,
                rejection_reason TEXT NULL,
                submitted_at DATETIME NULL,
                verified_at DATETIME NULL,
                due_date DATE NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_project_id (project_id),
                INDEX idx_status (status),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 4. Client Queries / Tickets Table
            $conn->query("CREATE TABLE IF NOT EXISTS project_client_queries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                client_user_id INT NOT NULL,
                subject VARCHAR(255) NOT NULL,
                category VARCHAR(50) DEFAULT 'general',
                priority VARCHAR(20) DEFAULT 'normal',
                status VARCHAR(50) DEFAULT 'open',
                assigned_to INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                INDEX idx_project_id (project_id),
                INDEX idx_client_user_id (client_user_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 5. Query Discussion / Replies Table
            $conn->query("CREATE TABLE IF NOT EXISTS project_query_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                query_id INT NOT NULL,
                user_id INT NOT NULL,
                is_internal TINYINT(1) DEFAULT 0,
                message TEXT NOT NULL,
                attachment_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_query_id (query_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 6. Project Broadcasts / Client Progress Updates
            $conn->query("CREATE TABLE IF NOT EXISTS project_broadcasts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                completed_text TEXT NULL,
                in_progress_text TEXT NULL,
                next_up_text TEXT NULL,
                blockers_text TEXT NULL,
                created_by INT NOT NULL,
                sent_email TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_project_id (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 7. Ensure tasks table has milestone_id and deliverable_id
            $task_cols_res = $conn->query("SHOW COLUMNS FROM tasks");
            if ($task_cols_res) {
                $task_cols = [];
                while ($tc = $task_cols_res->fetch_assoc()) {
                    $task_cols[] = strtolower($tc['Field']);
                }
                if (!in_array('milestone_id', $task_cols)) {
                    @$conn->query("ALTER TABLE tasks ADD COLUMN milestone_id INT NULL DEFAULT NULL AFTER project_id");
                }
                if (!in_array('deliverable_id', $task_cols)) {
                    @$conn->query("ALTER TABLE tasks ADD COLUMN deliverable_id INT NULL DEFAULT NULL AFTER milestone_id");
                }
                if (!in_array('estimated_hours', $task_cols)) {
                    @$conn->query("ALTER TABLE tasks ADD COLUMN estimated_hours DECIMAL(6,2) DEFAULT 0.00 AFTER due_date");
                }
                if (!in_array('logged_hours', $task_cols)) {
                    @$conn->query("ALTER TABLE tasks ADD COLUMN logged_hours DECIMAL(6,2) DEFAULT 0.00 AFTER estimated_hours");
                }
            }

            $executed = true;
            return true;
        } catch (Throwable $e) {
            error_log("ensure_project_pm_schema error: " . $e->getMessage());
            return false;
        }
    }
}
