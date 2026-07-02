<?php
session_start();
include '../core/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    if ($role !== 'teacher') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id = intval($_POST['id']);
    
    // verify ownership and status
    $res = $conn->query("SELECT file_path FROM recorded_lectures WHERE id = $id AND teacher_id = $user_id AND status = 'pending'");
    if ($res && $res->num_rows > 0) {
        $file = $res->fetch_assoc()['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
        $conn->query("DELETE FROM recorded_lectures WHERE id = $id AND teacher_id = $user_id AND status = 'pending'");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lecture not found or already reviewed']);
    }
    exit;
}

if ($action === 'review') {
    if ($role !== 'admin' && $role !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $id = intval($_POST['id']);
    $status = $_POST['status'] === 'approve' ? 'approved' : 'rejected';
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $conn->prepare("UPDATE recorded_lectures SET status = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $notes, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
