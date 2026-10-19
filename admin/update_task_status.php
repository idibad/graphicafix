<?php
// admin/update_task_status.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../core/config.php';

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

$is_admin = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm']);

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

    $stmt = $conn->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $task_id);

    if ($stmt->execute()) {
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
