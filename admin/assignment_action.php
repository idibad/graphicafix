<?php
require_once '../core/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }

$action = $_POST['action'] ?? '';
$user_id = intval($_SESSION['user_id']);

// Create assignment (teacher)
if ($action === 'create') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';

    if (!$title || !$course_id) {
        echo json_encode(['success' => false, 'message' => 'Title and course are required']); exit;
    }

    // Verify teacher owns this course
    $check = $conn->query("SELECT id FROM courses WHERE id = $course_id AND instructor_id = $user_id");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this course']); exit;
    }

    $stmt = $conn->prepare("INSERT INTO assignments (course_id, teacher_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $course_id, $user_id, $title, $description, $due_date);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Insert failed']);
    exit;
}

// Delete assignment (teacher)
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

    // Delete submissions files first
    $subs = $conn->query("SELECT file_path FROM assignment_submissions WHERE assignment_id = $id");
    if($subs) {
        while($s = $subs->fetch_assoc()) {
            if(file_exists($s['file_path'])) unlink($s['file_path']);
        }
    }

    $conn->query("DELETE FROM assignment_submissions WHERE assignment_id = $id");
    
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Delete failed']);
    exit;
}

// Grade submission (teacher)
if ($action === 'grade') {
    $sub_id = intval($_POST['submission_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');

    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid submission']); exit; }

    $stmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("ssi", $grade, $feedback, $sub_id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Grade update failed']);
    exit;
}

// Fetch submissions for modal
if ($action === 'get_submissions') {
    $aid = intval($_POST['assignment_id'] ?? 0);
    if (!$aid) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    
    // verify teacher owns
    $check = $conn->query("SELECT id FROM assignments WHERE id = $aid AND teacher_id = $user_id");
    if(!$check || $check->num_rows===0) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    
    $subs = $conn->query("SELECT s.*, u.name as student_name FROM assignment_submissions s JOIN users u ON s.student_id = u.user_id WHERE s.assignment_id = $aid ORDER BY s.submitted_at DESC");
    $data = [];
    if($subs) {
        while($s = $subs->fetch_assoc()) {
            $data[] = [
                'id' => $s['id'],
                'student_name' => $s['student_name'],
                'file_path' => $s['file_path'],
                'file_name' => basename($s['file_path']),
                'notes' => $s['notes'],
                'grade' => $s['grade'],
                'feedback' => $s['feedback'],
                'submitted_at' => date('M d, Y g:i A', strtotime($s['submitted_at']))
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
