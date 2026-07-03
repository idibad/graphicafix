<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$lecture_id = intval($_POST['lecture_id'] ?? 0);
$course_id = intval($_POST['course_id'] ?? 0);
$state = intval($_POST['state'] ?? 1);

if (!$lecture_id || !$course_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Verify enrollment
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($_SESSION['email'] ?? '')));
$enroll = $conn->query("SELECT id FROM course_enrollments WHERE course_id = $course_id AND LOWER(TRIM(student_email)) = '$safe_email' AND LOWER(status) = 'active'");
if (!$enroll || $enroll->num_rows === 0) {
    // If instructor or staff previewing, allow progress
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'teacher', 'pm'])) {
        echo json_encode(['success' => false, 'error' => 'Not enrolled in this course']);
        exit;
    }
}

if ($state === 1) {
    $stmt = $conn->prepare("INSERT INTO student_video_progress (student_id, lecture_id, course_id, is_completed, completed_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()");
    $stmt->bind_param("iii", $user_id, $lecture_id, $course_id);
    $stmt->execute();
} else {
    $stmt = $conn->prepare("UPDATE student_video_progress SET is_completed = 0 WHERE student_id = ? AND lecture_id = ?");
    $stmt->bind_param("ii", $user_id, $lecture_id);
    $stmt->execute();
}

// Calculate new progress percentage
$tot = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE course_id = $course_id AND status = 'approved'")->fetch_assoc()['c'] ?? 0;
$com = $conn->query("SELECT COUNT(*) as c FROM student_video_progress WHERE course_id = $course_id AND student_id = $user_id AND is_completed = 1")->fetch_assoc()['c'] ?? 0;
$pct = $tot > 0 ? round(($com / $tot) * 100) : 0;

echo json_encode([
    'success' => true,
    'completed' => ($state === 1),
    'completed_count' => $com,
    'total_count' => $tot,
    'percentage' => $pct,
    'certificate_ready' => ($pct >= 100 && $tot > 0)
]);
exit;
?>
