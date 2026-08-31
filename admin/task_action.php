<?php
// task_action.php — pure AJAX endpoint, zero includes, zero HTML possible
// Place this file in the same /admin/ folder as tasks.php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../core/config.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$user_id  = intval($_SESSION['user_id'] ?? 0);
$is_admin = (strtolower($_SESSION['role'] ?? '') === 'admin');
$action   = $_POST['ajax_action'] ?? '';
$task_id  = intval($_POST['task_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Bad request']); exit;
}

// ── Verify ownership ──────────────────────────────────────────────────────────
$check = $conn->prepare("SELECT id, created_by, assigned_to FROM tasks WHERE id=?");
$check->bind_param("i", $task_id);
$check->execute();
$t = $check->get_result()->fetch_assoc();

if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $allowed    = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    $new_status = $_POST['status'] ?? '';
    if (!in_array($new_status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
    }
    $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $new_status, $task_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Status updated']);

} elseif ($action === 'delete_task') {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Task deleted']);

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
exit;