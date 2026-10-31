<?php
// admin/update_task_status.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/task_delay_helper.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$role = strtolower($_SESSION['role'] ?? '');

if (empty($role)) {
    $u_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_row = $u_stmt->get_result()->fetch_assoc();
    $role = strtolower($u_row['role'] ?? '');
    $_SESSION['role'] = $role;
}

$is_admin = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm', 'sales']);

if (isset($_POST['id'], $_POST['status'])) {
    $task_id = intval($_POST['id']);
    $status = trim($_POST['status']);

    // Verify ownership/permissions
    $check = $conn->prepare("SELECT id, created_by, assigned_to, project_id FROM tasks WHERE id = ?");
    $check->bind_param("i", $task_id);
    $check->execute();
    $t = $check->get_result()->fetch_assoc();

    if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to modify this task']);
        exit;
    }

    // Restrict 'Completed' status to Super Admins, Admins, PMs, and Managers
    if ($status === 'Completed' && function_exists('canUserCompleteTask') && !canUserCompleteTask($role)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Restricted: Only Super Admins, Admins, or Project Managers can approve and mark a task as Completed. Please submit your task for "In Review" for manager verification.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $task_id);

    if ($stmt->execute()) {
        // Record completion / review timestamp and sync delay deductions
        if (function_exists('recordTaskStatusTimestamp')) {
            recordTaskStatusTimestamp($conn, $task_id, $status);
        }

        if (in_array($status, ['In Review', 'Completed'])) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyTaskStatusChanged')) {
                notifyTaskStatusChanged($conn, $task_id, $status, $user_id);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Updated task status to {$status}",
            'task_id' => $task_id,
            'status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "Database error: " . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
}
?>
