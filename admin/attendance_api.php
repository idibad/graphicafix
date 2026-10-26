<?php
/**
 * admin/attendance_api.php
 * AJAX API for Daily Attendance Check-In, Geolocation, Checkout, Heartbeat, and Deduction Tickets
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'check_in':
        $lat = isset($_POST['lat']) && is_numeric($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) && is_numeric($_POST['lng']) ? floatval($_POST['lng']) : null;
        $accuracy = isset($_POST['accuracy']) && is_numeric($_POST['accuracy']) ? floatval($_POST['accuracy']) : null;
        $location_denied = !empty($_POST['denied']);
        $notes = trim($_POST['notes'] ?? '');

        $result = recordDailyCheckIn($conn, $user_id, $lat, $lng, $accuracy, $location_denied, $notes);
        echo json_encode($result);
        exit;

    case 'check_out':
        $result = recordDailyCheckOut($conn, $user_id);
        echo json_encode($result);
        exit;

    case 'heartbeat':
        $res = recordAttendanceHeartbeat($conn, $user_id);
        echo json_encode(['success' => $res]);
        exit;

    case 'get_today_status':
        ensure_attendance_schema($conn);
        $record = getTodayAttendanceRecord($conn, $user_id);
        $user_meta = getUserAttendanceMeta($conn, $user_id);
        $grace_count = countGracePeriodArrivalsInMonth($conn, $user_id, date('Y-m'));
        $medical_count = countMedicalLeavesInMonth($conn, $user_id, date('Y-m'));

        echo json_encode([
            'success'        => true,
            'is_checked_in'  => (!empty($record) && !empty($record['check_in_time'])),
            'is_checked_out' => (!empty($record) && !empty($record['check_out_time'])),
            'record'         => $record,
            'user'           => $user_meta,
            'grace_count'    => $grace_count,
            'medical_count'  => $medical_count
        ]);
        exit;

    case 'raise_ticket':
        $deduction_id = intval($_POST['deduction_id'] ?? 0);
        $subject      = trim($_POST['subject'] ?? '');
        $reason       = trim($_POST['reason'] ?? '');

        if (!$deduction_id || empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid deduction ID and reason.']);
            exit;
        }

        // Verify deduction belongs to user
        $stmt = $conn->prepare("SELECT id, user_id, amount, deduction_type, date_incurred FROM salary_deductions WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $deduction_id, $user_id);
        $stmt->execute();
        $ded = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ded) {
            echo json_encode(['success' => false, 'message' => 'Deduction record not found or does not belong to you.']);
            exit;
        }

        // Check if ticket already open
        $stmt_check = $conn->prepare("SELECT id, ticket_number FROM deduction_tickets WHERE deduction_id = ? AND user_id = ? LIMIT 1");
        $stmt_check->bind_param("ii", $deduction_id, $user_id);
        $stmt_check->execute();
        $existing_tkt = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($existing_tkt) {
            echo json_encode(['success' => false, 'message' => 'A dispute ticket (' . $existing_tkt['ticket_number'] . ') is already submitted for this deduction.']);
            exit;
        }

        // Handle file upload if provided
        $attachment_path = null;
        if (!empty($_FILES['attachment']['name'])) {
            $upload_dir = __DIR__ . '/uploads/tickets/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            if (in_array($ext, $allowed_exts)) {
                $filename = 'tkt_' . $user_id . '_' . time() . '.' . $ext;
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                    $attachment_path = 'uploads/tickets/' . $filename;
                }
            }
        }

        if (empty($subject)) {
            $subject = "Dispute: " . $ded['deduction_type'] . " on " . $ded['date_incurred'];
        }

        $ticket_number = 'TKT-' . date('Ym') . '-' . strtoupper(substr(uniqid(), -5));

        $stmt_ins = $conn->prepare("INSERT INTO deduction_tickets 
            (ticket_number, deduction_id, user_id, subject, reason, attachment, status)
            VALUES (?, ?, ?, ?, ?, ?, 'open')");
        if ($stmt_ins) {
            $stmt_ins->bind_param("siisss", $ticket_number, $deduction_id, $user_id, $subject, $reason, $attachment_path);
            $stmt_ins->execute();
            $stmt_ins->close();

            // Mark deduction status as 'disputed'
            $conn->query("UPDATE salary_deductions SET status = 'disputed' WHERE id = " . intval($deduction_id));

            echo json_encode([
                'success'       => true,
                'message'       => 'Dispute ticket submitted successfully! Reference: ' . $ticket_number,
                'ticket_number' => $ticket_number
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Failed to create dispute ticket.']);
        exit;

    case 'review_ticket':
        $role = strtolower(trim($_SESSION['role'] ?? ''));
        $is_admin = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'hrm' || $role === 'manager');
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit;
        }

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $decision  = $_POST['decision'] ?? ''; // 'approved_waived' or 'rejected'
        $remarks   = trim($_POST['remarks'] ?? '');

        if (!$ticket_id || !in_array($decision, ['approved_waived', 'rejected'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid ticket ID or decision.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT t.*, d.user_id, d.salary_month, d.amount FROM deduction_tickets t 
            INNER JOIN salary_deductions d ON t.deduction_id = d.id 
            WHERE t.id = ? LIMIT 1");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Ticket record not found.']);
            exit;
        }

        // Update ticket
        $stmt_upd = $conn->prepare("UPDATE deduction_tickets 
            SET status = ?, admin_remarks = ?, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?");
        $stmt_upd->bind_param("ssii", $decision, $remarks, $user_id, $ticket_id);
        $stmt_upd->execute();
        $stmt_upd->close();

        if ($decision === 'approved_waived') {
            // Mark deduction as waived
            $conn->query("UPDATE salary_deductions SET status = 'waived' WHERE id = " . intval($ticket['deduction_id']));
        } else {
            // Revert deduction to applied
            $conn->query("UPDATE salary_deductions SET status = 'applied' WHERE id = " . intval($ticket['deduction_id']));
        }

        // Recalculate salary for this employee's month
        syncUserMonthlySalary($conn, $ticket['user_id'], $ticket['salary_month']);

        echo json_encode([
            'success'  => true,
            'message'  => ($decision === 'approved_waived') ? 'Deduction waived and employee payroll recalculated successfully!' : 'Ticket rejected successfully.',
            'decision' => $decision
        ]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
}
