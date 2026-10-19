<?php
// c:\xampp2\htdocs\graphicafix\core\migrate_tasks.php
require_once __DIR__ . '/config.php';

echo "Starting migration...\n";

// 1. Alter tasks table
$cols = array_column($conn->query("SHOW COLUMNS FROM tasks")->fetch_all(MYSQLI_ASSOC), 'Field');

$alters = [
    'client_id' => "ALTER TABLE tasks ADD COLUMN client_id INT(11) DEFAULT NULL",
    'campaign_name' => "ALTER TABLE tasks ADD COLUMN campaign_name VARCHAR(255) DEFAULT NULL",
    'task_type' => "ALTER TABLE tasks ADD COLUMN task_type VARCHAR(100) DEFAULT NULL",
    'estimated_effort' => "ALTER TABLE tasks ADD COLUMN estimated_effort INT(11) DEFAULT 0",
    'stage' => "ALTER TABLE tasks ADD COLUMN stage VARCHAR(50) NOT NULL DEFAULT 'Brief'",
    'brief_status' => "ALTER TABLE tasks ADD COLUMN brief_status VARCHAR(50) NOT NULL DEFAULT 'Draft'",
    'brief_objective' => "ALTER TABLE tasks ADD COLUMN brief_objective TEXT DEFAULT NULL",
    'brief_deliverables' => "ALTER TABLE tasks ADD COLUMN brief_deliverables TEXT DEFAULT NULL",
    'brief_references' => "ALTER TABLE tasks ADD COLUMN brief_references TEXT DEFAULT NULL",
    'brief_specs' => "ALTER TABLE tasks ADD COLUMN brief_specs TEXT DEFAULT NULL",
    'brief_copy' => "ALTER TABLE tasks ADD COLUMN brief_copy TEXT DEFAULT NULL",
    'production_status' => "ALTER TABLE tasks ADD COLUMN production_status VARCHAR(50) NOT NULL DEFAULT 'Not Started'",
    'production_progress' => "ALTER TABLE tasks ADD COLUMN production_progress INT(11) DEFAULT 0",
    'client_feedback' => "ALTER TABLE tasks ADD COLUMN client_feedback TEXT DEFAULT NULL",
    'client_approval_status' => "ALTER TABLE tasks ADD COLUMN client_approval_status VARCHAR(50) NOT NULL DEFAULT 'Pending'",
    'reporting_required' => "ALTER TABLE tasks ADD COLUMN reporting_required TINYINT(1) DEFAULT 0",
    'current_revision' => "ALTER TABLE tasks ADD COLUMN current_revision INT(11) DEFAULT 1",
    'client_token' => "ALTER TABLE tasks ADD COLUMN client_token VARCHAR(64) DEFAULT NULL"
];

foreach ($alters as $col => $query) {
    if (!in_array($col, $cols)) {
        if ($conn->query($query)) {
            echo "Added column '$col' to tasks table.\n";
        } else {
            echo "Error adding column '$col': " . $conn->error . "\n";
        }
    }
}

// Modify status column from ENUM to VARCHAR to allow custom statuses
$conn->query("ALTER TABLE tasks MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Active'");

// 2. Create new tables
$tables = [
    'task_user' => "CREATE TABLE IF NOT EXISTS task_user (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        user_id INT(11) NOT NULL,
        role VARCHAR(50) NOT NULL,
        assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT(11) DEFAULT NULL,
        UNIQUE KEY uniq_task_user_role (task_id, user_id, role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_workflow_history' => "CREATE TABLE IF NOT EXISTS task_workflow_history (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        from_stage VARCHAR(50) NOT NULL,
        to_stage VARCHAR(50) NOT NULL,
        changed_by INT(11) NOT NULL,
        comment TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_reviews' => "CREATE TABLE IF NOT EXISTS task_reviews (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        reviewer_id INT(11) NOT NULL,
        review_type ENUM('Internal', 'Client') NOT NULL,
        decision VARCHAR(50) NOT NULL,
        comment TEXT DEFAULT NULL,
        revision INT(11) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_subtasks' => "CREATE TABLE IF NOT EXISTS task_subtasks (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        assigned_to INT(11) DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_comments' => "CREATE TABLE IF NOT EXISTS task_comments (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        subtask_id INT(11) DEFAULT NULL,
        user_id INT(11) NOT NULL,
        comment_text TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_files' => "CREATE TABLE IF NOT EXISTS task_files (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        subtask_id INT(11) DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        uploaded_by INT(11) NOT NULL,
        revision INT(11) NOT NULL DEFAULT 1,
        is_client_deliverable TINYINT(1) DEFAULT 0,
        uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_deliveries' => "CREATE TABLE IF NOT EXISTS task_deliveries (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        platform VARCHAR(100) DEFAULT NULL,
        publication_date DATE DEFAULT NULL,
        publication_time TIME DEFAULT NULL,
        post_url TEXT DEFAULT NULL,
        campaign_name VARCHAR(255) DEFAULT NULL,
        published_by INT(11) DEFAULT NULL,
        delivery_date DATE DEFAULT NULL,
        delivery_method VARCHAR(100) DEFAULT NULL,
        recipient VARCHAR(255) DEFAULT NULL,
        delivery_confirmation TEXT DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Ready for Publication',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_reports' => "CREATE TABLE IF NOT EXISTS task_reports (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        task_id BIGINT(20) UNSIGNED NOT NULL,
        reach INT(11) DEFAULT 0,
        impressions INT(11) DEFAULT 0,
        engagement INT(11) DEFAULT 0,
        clicks INT(11) DEFAULT 0,
        leads INT(11) DEFAULT 0,
        conversions INT(11) DEFAULT 0,
        revenue DECIMAL(15,2) DEFAULT 0.00,
        ad_spend DECIMAL(15,2) DEFAULT 0.00,
        cpl DECIMAL(15,2) DEFAULT 0.00,
        roas DECIMAL(15,2) DEFAULT 0.00,
        kpis TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'task_types' => "CREATE TABLE IF NOT EXISTS task_types (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables as $name => $query) {
    if ($conn->query($query)) {
        echo "Table '$name' created or already exists.\n";
    } else {
        echo "Error creating table '$name': " . $conn->error . "\n";
    }
}

// Add constraints
$constraints = [
    "ALTER TABLE task_user ADD CONSTRAINT fk_tu_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_user ADD CONSTRAINT fk_tu_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE",
    "ALTER TABLE task_workflow_history ADD CONSTRAINT fk_twh_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_reviews ADD CONSTRAINT fk_tr_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_subtasks ADD CONSTRAINT fk_ts_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_comments ADD CONSTRAINT fk_tc_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_comments ADD CONSTRAINT fk_tc_subtask FOREIGN KEY (subtask_id) REFERENCES task_subtasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_files ADD CONSTRAINT fk_tf_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_files ADD CONSTRAINT fk_tf_subtask FOREIGN KEY (subtask_id) REFERENCES task_subtasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_deliveries ADD CONSTRAINT fk_td_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE",
    "ALTER TABLE task_reports ADD CONSTRAINT fk_trp_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE"
];

foreach ($constraints as $query) {
    try {
        @$conn->query($query);
    } catch (Exception $e) {
        // Ignored if duplicate constraint
    }
}

// 3. Migrate existing tasks assigned_to column
$res = $conn->query("SELECT id, assigned_to, created_by FROM tasks WHERE assigned_to IS NOT NULL");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tid = intval($row['id']);
        $uid = intval($row['assigned_to']);
        $creator = intval($row['created_by']);
        
        $chk = $conn->query("SELECT id FROM task_user WHERE task_id=$tid AND user_id=$uid AND role='primary_assignee'");
        if ($chk && $chk->num_rows === 0) {
            $assigned_by_val = $creator > 0 ? $creator : "NULL";
            $conn->query("INSERT INTO task_user (task_id, user_id, role, assigned_by) VALUES ($tid, $uid, 'primary_assignee', $assigned_by_val)");
            echo "Migrated task $tid assignee to task_user primary_assignee.\n";
        }
    }
}

// 4. Migrate overall task status and stage from old columns
// Check if we already migrated (we use a simple check: is there any task with stage other than 'Brief')
$check_mig = $conn->query("SELECT id FROM tasks WHERE stage != 'Brief' LIMIT 1");
if ($check_mig && $check_mig->num_rows === 0) {
    $conn->query("UPDATE tasks SET stage = 'Completed', status = 'Completed' WHERE status = 'Completed'");
    $conn->query("UPDATE tasks SET stage = 'Internal Review', status = 'Active' WHERE status = 'In Review'");
    $conn->query("UPDATE tasks SET stage = 'Production', status = 'Active' WHERE status = 'In Progress'");
    $conn->query("UPDATE tasks SET stage = 'Assignment', status = 'Active' WHERE status = 'Pending'");
    $conn->query("UPDATE tasks SET status = 'Cancelled' WHERE status = 'Cancelled'");
    echo "Migrated tasks statuses and stages.\n";
}

// 5. Seed default task types
$types = [
    'Graphic Design', 'Social Media Post', 'Reel / Video', 'Photography', 'Videography',
    'Website Development', 'Development', 'Content Writing', 'SEO', 'Marketing',
    'Advertising', 'Product Upload', 'Client Communication', 'Reporting', 'Other'
];
foreach ($types as $type) {
    $stmt = $conn->prepare("INSERT IGNORE INTO task_types (name) VALUES (?)");
    $stmt->bind_param("s", $type);
    $stmt->execute();
}
echo "Seeded task types.\n";

echo "Migration finished successfully!\n";
?>
