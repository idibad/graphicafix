<?php
require_once '../core/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }

$action   = $_POST['action'] ?? '';
$user_id  = intval($_SESSION['user_id']);
$role     = strtolower($_SESSION['role'] ?? '');
$is_admin = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm']);

// Create assignment (teacher or admin)
if ($action === 'create') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';

    if (!$title || !$course_id) {
        echo json_encode(['success' => false, 'message' => 'Title and course are required']); exit;
    }

    // Verify teacher owns this course or is admin
    if (!$is_admin) {
        $stmt_check = $conn->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
        $stmt_check->bind_param("ii", $course_id, $user_id);
        $stmt_check->execute();
        $check = $stmt_check->get_result();
        if (!$check || $check->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'You are not assigned to this course']); exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO assignments (course_id, teacher_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $course_id, $user_id, $title, $description, $due_date);
    if ($stmt->execute()) {
        $new_assign_id = $conn->insert_id;
        require_once __DIR__ . '/../core/notification_mailer.php';
        if (function_exists('notifyCourseAssignmentPublished')) {
            @notifyCourseAssignmentPublished($conn, $course_id, $title, $due_date, $description);
        }
        logActivity($conn, 'create_assignment', "Created assignment '$title' for course #$course_id", 'assignment', $new_assign_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed']);
    }
    exit;
}

// Delete assignment (teacher or admin)
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

    // Check ownership or admin
    if (!$is_admin) {
        $stmt_chk = $conn->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
        $stmt_chk->bind_param("ii", $id, $user_id);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
    }

    // Delete submissions files first
    $stmt_subs = $conn->prepare("SELECT file_path FROM assignment_submissions WHERE assignment_id = ?");
    $stmt_subs->bind_param("i", $id);
    $stmt_subs->execute();
    $subs = $stmt_subs->get_result();
    if($subs) {
        while($s = $subs->fetch_assoc()) {
            if(!empty($s['file_path']) && file_exists($s['file_path'])) unlink($s['file_path']);
        }
    }

    $stmt_del_subs = $conn->prepare("DELETE FROM assignment_submissions WHERE assignment_id = ?");
    $stmt_del_subs->bind_param("i", $id);
    $stmt_del_subs->execute();
    
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        logActivity($conn, 'delete_assignment', "Deleted assignment #$id", 'assignment', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed']);
    }
    exit;
}

// Grade submission (teacher or admin)
if ($action === 'grade') {
    $sub_id = intval($_POST['submission_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');

    if (!$sub_id) { echo json_encode(['success' => false, 'message' => 'Invalid submission']); exit; }

    // Verify instructor owns this assignment or is admin
    if (!$is_admin) {
        $stmt_chk = $conn->prepare("SELECT s.id FROM assignment_submissions s 
            JOIN assignments a ON s.assignment_id = a.id 
            LEFT JOIN courses c ON a.course_id = c.id 
            WHERE s.id = ? AND (a.teacher_id = ? OR c.instructor_id = ?)");
        $stmt_chk->bind_param("iii", $sub_id, $user_id, $user_id);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized to grade this submission']); exit;
        }
    }

    $stmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("ssi", $grade, $feedback, $sub_id);
    if ($stmt->execute()) {
        require_once __DIR__ . '/../core/notification_mailer.php';
        if (function_exists('notifyAssignmentGraded')) {
            @notifyAssignmentGraded($conn, $sub_id, $grade, $feedback);
        }
        logActivity($conn, 'grade_assignment', "Graded assignment submission #$sub_id (Grade: $grade)", 'assignment_submission', $sub_id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Grade update failed']);
    }
    exit;
}

// Fetch submissions for modal
if ($action === 'get_submissions') {
    $aid = intval($_POST['assignment_id'] ?? 0);
    if (!$aid) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    
    // verify teacher owns or is admin
    if (!$is_admin) {
        $stmt_chk = $conn->prepare("SELECT a.id FROM assignments a 
            LEFT JOIN courses c ON a.course_id = c.id 
            WHERE a.id = ? AND (a.teacher_id = ? OR c.instructor_id = ?)");
        $stmt_chk->bind_param("iii", $aid, $user_id, $user_id);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
    }
    
    $stmt_subs = $conn->prepare("SELECT s.*, u.name as student_name FROM assignment_submissions s JOIN users u ON s.student_id = u.user_id WHERE s.assignment_id = ? ORDER BY s.submitted_at DESC");
    $stmt_subs->bind_param("i", $aid);
    $stmt_subs->execute();
    $subs = $stmt_subs->get_result();
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
