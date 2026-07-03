<?php
ob_start();
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php';
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action  = $_REQUEST['action'] ?? '';

if ($action === 'save_dpr') {
    $tasks_completed = trim($_POST['tasks_completed'] ?? '');
    $challenges      = trim($_POST['challenges'] ?? '');
    $plan_tomorrow   = trim($_POST['plan_tomorrow'] ?? '');
    $report_date     = date('Y-m-d'); // Always today
    
    if (empty($tasks_completed) || empty($plan_tomorrow)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill all required fields.',
            'debug_post' => $_POST,
            'debug_request' => $_REQUEST
        ]);
        exit;
    }
    
    // Check if report already exists for today
    $stmt = $conn->prepare("SELECT id FROM daily_progress_reports WHERE user_id = ? AND report_date = ?");
    $stmt->bind_param("is", $user_id, $report_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $update_stmt = $conn->prepare("UPDATE daily_progress_reports SET tasks_completed = ?, challenges = ?, plan_tomorrow = ? WHERE user_id = ? AND report_date = ?");
        $update_stmt->bind_param("sssis", $tasks_completed, $challenges, $plan_tomorrow, $user_id, $report_date);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Report updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("INSERT INTO daily_progress_reports (user_id, report_date, tasks_completed, challenges, plan_tomorrow) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issss", $user_id, $report_date, $tasks_completed, $challenges, $plan_tomorrow);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Report submitted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.', 'received_post' => $_POST, 'received_action' => $action]);
