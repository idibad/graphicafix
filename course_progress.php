<?php
require_once 'core/config.php';
header('Content-Type: application/json');

$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$lecture_id    = intval($_POST['lecture_id']    ?? 0);
$completed     = intval($_POST['completed']     ?? 0);

if (!$enrollment_id || !$lecture_id) {
    echo json_encode(['success'=>false,'message'=>'Invalid data']); exit;
}

// Verify enrollment exists
$estmt = $conn->prepare("SELECT id FROM course_enrollments WHERE id = ?");
$estmt->bind_param("i", $enrollment_id);
$estmt->execute();
if (!$estmt->get_result()->fetch_assoc()) {
    echo json_encode(['success'=>false,'message'=>'Enrollment not found']); exit;
}

// Upsert progress
$completed_at = $completed ? date('Y-m-d H:i:s') : null;
$stmt = $conn->prepare("
    INSERT INTO course_progress (enrollment_id, lecture_id, is_completed, completed_at)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE is_completed = ?, completed_at = ?
");
$stmt->bind_param("iiisss", $enrollment_id, $lecture_id, $completed, $completed_at, $completed, $completed_at);
$stmt->execute();

// Recalculate progress %
$tot = $conn->query("SELECT COUNT(*) AS c FROM course_lectures cl JOIN course_enrollments ce ON ce.course_id = cl.course_id WHERE ce.id = $enrollment_id")->fetch_assoc()['c'] ?? 1;
$don = $conn->query("SELECT COUNT(*) AS c FROM course_progress WHERE enrollment_id = $enrollment_id AND is_completed = 1")->fetch_assoc()['c'] ?? 0;
$pct = $tot > 0 ? round($don / $tot * 100) : 0;

// Mark course completed if 100%
if ($pct === 100) {
    $conn->query("UPDATE course_enrollments SET status='completed' WHERE id=$enrollment_id");
}

echo json_encode(['success'=>true,'progress_pct'=>$pct,'completed'=>$completed]);
