<?php
require_once '../core/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }

$action = $_POST['action'] ?? '';
$user_id = intval($_SESSION['user_id']);

// Fetch user role
$role_q = $conn->query("SELECT role FROM users WHERE user_id = $user_id");
$user_role = strtolower($role_q->fetch_assoc()['role'] ?? '');

// Delete document (teacher - own pending only)
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    // get file path
    $file_q = $conn->query("SELECT file_path FROM teacher_documents WHERE id = $id AND teacher_id = $user_id AND status = 'pending'");
    if($file_q && $file_q->num_rows > 0) {
        $path = $file_q->fetch_assoc()['file_path'];
        if(file_exists($path)) unlink($path);
    }
    
    $stmt = $conn->prepare("DELETE FROM teacher_documents WHERE id = ? AND teacher_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Cannot delete — already reviewed or not found']);
    exit;
}

// Approve document (admin only)
if ($action === 'approve') {
    if ($user_role !== 'admin') { echo json_encode(['success' => false, 'message' => 'Admin only']); exit; }
    $id = intval($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $stmt = $conn->prepare("UPDATE teacher_documents SET status = 'approved', admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $notes, $id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Update failed']);
    exit;
}

// Reject document (admin only)
if ($action === 'reject') {
    if ($user_role !== 'admin') { echo json_encode(['success' => false, 'message' => 'Admin only']); exit; }
    $id = intval($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if (!$notes) { echo json_encode(['success' => false, 'message' => 'Please provide a reason for rejection']); exit; }
    $stmt = $conn->prepare("UPDATE teacher_documents SET status = 'rejected', admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $notes, $id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Update failed']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
