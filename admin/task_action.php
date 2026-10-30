<?php
// task_action.php — pure AJAX endpoint, zero includes, zero HTML possible
// Place this file in the same /admin/ folder as tasks.php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../core/config.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$user_id  = intval($_SESSION['user_id'] ?? 0);
$is_admin = in_array(strtolower($_SESSION['role'] ?? ''), ['super_admin', 'superadmin', 'admin']);
$action   = $_POST['ajax_action'] ?? '';
$task_id  = intval($_POST['task_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Bad request']); exit;
}

// ── Verify ownership if task_id provided ──────────────────────────────────────
$t = null;
if ($task_id > 0) {
    $check = $conn->prepare("SELECT id, created_by, assigned_to FROM tasks WHERE id=?");
    $check->bind_param("i", $task_id);
    $check->execute();
    $t = $check->get_result()->fetch_assoc();

    if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($action === 'create_task') {
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $priority        = $_POST['priority'] ?? 'Medium';
    $status          = $_POST['status'] ?? 'Pending';
    $due_date        = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+3 days'));
    $assigned_to     = intval($_POST['assigned_to'] ?? 0) ?: $user_id;
    $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
    $milestone_id    = intval($_POST['milestone_id'] ?? 0) ?: null;
    $task_type       = trim($_POST['task_type'] ?? 'Design');
    $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
    $logged_hours    = floatval($_POST['logged_hours'] ?? 0);

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Task title is required']); exit;
    }

    $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed)) $status = 'Pending';

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, project_id, milestone_id, task_type, estimated_hours, logged_hours, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssssiiisddi", $title, $description, $priority, $status, $due_date, $assigned_to, $project_id, $milestone_id, $task_type, $estimated_hours, $logged_hours, $user_id);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        if ($assigned_to && $assigned_to != $user_id) {
            if (file_exists(__DIR__ . '/../core/notification_mailer.php')) {
                require_once __DIR__ . '/../core/notification_mailer.php';
                if (function_exists('notifyTaskAssigned')) notifyTaskAssigned($conn, $new_id);
            }
        }
        echo json_encode(['success' => true, 'task_id' => $new_id, 'message' => 'Task created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;

} elseif ($action === 'edit_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $priority        = $_POST['priority'] ?? 'Medium';
    $status          = $_POST['status'] ?? 'Pending';
    $due_date        = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d');
    $assigned_to     = intval($_POST['assigned_to'] ?? 0) ?: $user_id;
    $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
    $milestone_id    = intval($_POST['milestone_id'] ?? 0) ?: null;
    $task_type       = trim($_POST['task_type'] ?? 'Design');
    $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
    $logged_hours    = floatval($_POST['logged_hours'] ?? 0);

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Task title is required']); exit;
    }

    $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed)) $status = 'Pending';

    $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, priority=?, status=?, due_date=?, assigned_to=?, project_id=?, milestone_id=?, task_type=?, estimated_hours=?, logged_hours=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssiiisddi", $title, $description, $priority, $status, $due_date, $assigned_to, $project_id, $milestone_id, $task_type, $estimated_hours, $logged_hours, $task_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;

} elseif ($action === 'get_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task_data = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'task' => $task_data]);
    exit;

} elseif ($action === 'update_status') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $allowed    = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    $new_status = $_POST['status'] ?? '';
    if (!in_array($new_status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
    }
    $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $new_status, $task_id);
    $stmt->execute();

    if (in_array($new_status, ['In Review', 'Completed'])) {
        if (file_exists(__DIR__ . '/../core/notification_mailer.php')) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyTaskStatusChanged')) {
                notifyTaskStatusChanged($conn, $task_id, $new_status, $user_id);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Status updated']);
    exit;

} elseif ($action === 'delete_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Task deleted']);
    exit;

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
