<?php
ob_start();
session_start();
require_once __DIR__ . '/../core/config.php';
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_res = $conn->query("SELECT role FROM users WHERE user_id = $user_id");
$user_data = $user_res ? $user_res->fetch_assoc() : null;
$role = strtolower($user_data['role'] ?? ($_SESSION['role'] ?? 'user'));
$is_manager = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'manager']);

$action = $_REQUEST['action'] ?? '';

// ── Save / Update DPR ────────────────────────────────────────────────────────
if ($action === 'save_dpr') {
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $challenges      = trim($_POST['challenges'] ?? '');
    $plan_tomorrow   = trim($_POST['plan_tomorrow'] ?? '');
    $report_date     = trim($_POST['report_date'] ?? date('Y-m-d'));
    
    // Validate date format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
        $report_date = date('Y-m-d');
    }
    
    // Regular users can only submit for today
    if (!$is_manager && $report_date !== date('Y-m-d')) {
        $report_date = date('Y-m-d');
    }

    if (empty($tasks_completed) || empty($plan_tomorrow)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please provide Tasks Completed and Plan for Tomorrow.'
        ]);
        exit;
    }
    
    // Check if report already exists for this user and date
    $stmt = $conn->prepare("SELECT id FROM daily_progress_reports WHERE user_id = ? AND report_date = ?");
    $stmt->bind_param("is", $user_id, $report_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $update_stmt = $conn->prepare("UPDATE daily_progress_reports SET tasks_completed = ?, challenges = ?, plan_tomorrow = ? WHERE user_id = ? AND report_date = ?");
        $update_stmt->bind_param("sssis", $tasks_completed, $challenges, $plan_tomorrow, $user_id, $report_date);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Progress report updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("INSERT INTO daily_progress_reports (user_id, report_date, tasks_completed, challenges, plan_tomorrow) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issss", $user_id, $report_date, $tasks_completed, $challenges, $plan_tomorrow);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Daily Progress Report submitted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
    exit;
}

// ── Delete DPR ───────────────────────────────────────────────────────────────
if ($action === 'delete_dpr') {
    $del_id = intval($_POST['id'] ?? 0);
    if ($del_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
        exit;
    }

    $chk = $conn->prepare("SELECT user_id, report_date FROM daily_progress_reports WHERE id = ?");
    $chk->bind_param("i", $del_id);
    $chk->execute();
    $report = $chk->get_result()->fetch_assoc();

    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found.']);
        exit;
    }

    // Permission check: Author or Manager/Admin
    if ($report['user_id'] != $user_id && !$is_manager) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this report.']);
        exit;
    }

    $del_stmt = $conn->prepare("DELETE FROM daily_progress_reports WHERE id = ?");
    $del_stmt->bind_param("i", $del_id);
    if ($del_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Report deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

// ── Get Single DPR ───────────────────────────────────────────────────────────
if ($action === 'get_dpr') {
    $dpr_id = intval($_GET['id'] ?? 0);
    if ($dpr_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email
        FROM daily_progress_reports dpr
        JOIN users u ON dpr.user_id = u.user_id
        WHERE dpr.id = ?
    ");
    $stmt->bind_param("i", $dpr_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Report not found.']);
        exit;
    }

    if ($row['user_id'] != $user_id && !$is_manager) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to view this report.']);
        exit;
    }

    echo json_encode(['success' => true, 'report' => $row]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
