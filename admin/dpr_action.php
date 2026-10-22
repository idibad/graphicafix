<?php
ob_start();
session_start();
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/dpr_schema.php';
ensure_dpr_schema($conn);
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
    $tasks_completed     = trim($_POST['tasks_completed'] ?? '');
    $tasks_in_progress   = trim($_POST['tasks_in_progress'] ?? '');
    $challenges          = trim($_POST['challenges'] ?? '');
    $blocker_severity    = trim($_POST['blocker_severity'] ?? 'none');
    $waiting_on          = trim($_POST['waiting_on'] ?? '');
    $plan_tomorrow       = trim($_POST['plan_tomorrow'] ?? '');
    $deliverable_links   = trim($_POST['deliverable_links'] ?? '');
    $hours_worked        = floatval($_POST['hours_worked'] ?? 8.0);
    $project_id          = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $productivity_rating = max(1, min(5, intval($_POST['productivity_rating'] ?? 5)));
    $report_date         = trim($_POST['report_date'] ?? date('Y-m-d'));
    
    // Normalize blocker severity
    if (!in_array($blocker_severity, ['none', 'minor', 'critical'])) {
        $blocker_severity = !empty($challenges) ? 'minor' : 'none';
    }

    // Validate date format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
        $report_date = date('Y-m-d');
    }
    
    // Ensure date cannot be in the future
    $today = date('Y-m-d');
    if ($report_date > $today) {
        $report_date = $today;
    }

    // Regular users can report for today or past 30 days
    if (!$is_manager) {
        $min_allowed = date('Y-m-d', strtotime('-30 days'));
        if ($report_date < $min_allowed) {
            $report_date = $today;
        }
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
        $update_stmt = $conn->prepare("UPDATE daily_progress_reports SET 
            hours_worked = ?, 
            project_id = ?, 
            tasks_completed = ?, 
            tasks_in_progress = ?, 
            challenges = ?, 
            blocker_severity = ?, 
            waiting_on = ?, 
            plan_tomorrow = ?, 
            deliverable_links = ?, 
            productivity_rating = ? 
            WHERE user_id = ? AND report_date = ?");
        $update_stmt->bind_param("disssssssiis", 
            $hours_worked, 
            $project_id, 
            $tasks_completed, 
            $tasks_in_progress, 
            $challenges, 
            $blocker_severity, 
            $waiting_on, 
            $plan_tomorrow, 
            $deliverable_links, 
            $productivity_rating, 
            $user_id, 
            $report_date
        );
        
        if ($update_stmt->execute()) {
            $updated_id = $result->fetch_assoc()['id'] ?? 0;
            echo json_encode([
                'success' => true, 
                'message' => 'Daily Progress Report for ' . date('M j, Y', strtotime($report_date)) . ' updated successfully!',
                'report_id' => $updated_id,
                'report_date' => $report_date
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    } else {
        // Insert new
        $insert_stmt = $conn->prepare("INSERT INTO daily_progress_reports 
            (user_id, report_date, hours_worked, project_id, tasks_completed, tasks_in_progress, challenges, blocker_severity, waiting_on, plan_tomorrow, deliverable_links, productivity_rating) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isdisssssssi", 
            $user_id, 
            $report_date, 
            $hours_worked, 
            $project_id, 
            $tasks_completed, 
            $tasks_in_progress, 
            $challenges, 
            $blocker_severity, 
            $waiting_on, 
            $plan_tomorrow, 
            $deliverable_links, 
            $productivity_rating
        );
        
        if ($insert_stmt->execute()) {
            $new_id = $insert_stmt->insert_id;
            echo json_encode([
                'success' => true, 
                'message' => 'Daily Progress Report for ' . date('M j, Y', strtotime($report_date)) . ' submitted successfully!',
                'report_id' => $new_id,
                'report_date' => $report_date
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
    exit;
}

// ── Check / Get Single DPR by Date ──────────────────────────────────────────
if ($action === 'get_dpr_by_date') {
    $target_date = trim($_GET['date'] ?? date('Y-m-d'));
    $target_user = $user_id;
    if ($is_manager && isset($_GET['user_id']) && intval($_GET['user_id']) > 0) {
        $target_user = intval($_GET['user_id']);
    }

    $stmt = $conn->prepare("
        SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email, p.project_name
        FROM daily_progress_reports dpr
        JOIN users u ON dpr.user_id = u.user_id
        LEFT JOIN projects p ON p.id = dpr.project_id
        WHERE dpr.user_id = ? AND dpr.report_date = ?
    ");
    $stmt->bind_param("is", $target_user, $target_date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        echo json_encode(['success' => true, 'exists' => true, 'report' => $row]);
    } else {
        echo json_encode(['success' => true, 'exists' => false]);
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
        SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email, p.project_name
        FROM daily_progress_reports dpr
        JOIN users u ON dpr.user_id = u.user_id
        LEFT JOIN projects p ON p.id = dpr.project_id
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
