<?php
/**
 * api/cron_task_delays.php
 * Automated Daily Cron: Task Delays Audit, Grace Evaluation & Payroll Deductions
 *
 * Setup in Hostinger hPanel or Server Crontab:
 * Command: /usr/bin/php /path/to/public_html/api/cron_task_delays.php
 * Or via wget: wget -qO- https://graphicafix.com/api/cron_task_delays.php?token=graphicafixtasksecret
 * Recommended Schedule: Daily at 08:30 AM (before office start).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$expected_token = "graphicafixtasksecret";
$is_cli = php_sapi_name() === 'cli';
$provided_token = $_GET['token'] ?? '';

if (!$is_cli && $provided_token !== $expected_token) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Forbidden: Invalid or missing token.']));
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/task_delay_schema.php';
require_once __DIR__ . '/../core/task_delay_helper.php';
require_once __DIR__ . '/../core/notification_mailer.php';

ensure_task_delay_schema($conn);

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$summary = [
    'timestamp'        => date('Y-m-d H:i:s'),
    'evaluated_tasks'  => 0,
    'warnings_sent'    => 0,
    'graces_applied'   => 0,
    'penalties_applied'=> 0,
    'total_pkr'        => 0.00,
    'details'          => []
];

// ── 1. SEND UPCOMING DUE WARNINGS (Tasks due Today or Tomorrow) ───────────────
$warn_sql = "SELECT t.id, t.title, t.due_date, t.assigned_to 
             FROM tasks t
             JOIN users u ON t.assigned_to = u.user_id
             WHERE t.status IN ('Pending', 'In Progress') 
               AND (t.stage IS NULL OR t.stage NOT IN ('Internal Review', 'Client Approval', 'Publication/Delivery', 'Reporting'))
               AND (t.extension_status IS NULL OR t.extension_status != 'pending')
               AND (t.delay_status IS NULL OR t.delay_status != 'exempted')
               AND COALESCE(t.is_delay_exempt, 0) = 0
               AND u.role NOT IN ('student', 'client', 'client_sub')
               AND t.due_date IN (?, ?)
             ORDER BY t.due_date ASC";
$warn_stmt = $conn->prepare($warn_sql);
if ($warn_stmt) {
    $warn_stmt->bind_param("ss", $today, $tomorrow);
    $warn_stmt->execute();
    $warn_res = $warn_stmt->get_result();
    while ($w = $warn_res->fetch_assoc()) {
        if (function_exists('notifyTaskDelayWarning')) {
            @notifyTaskDelayWarning($conn, $w['id']);
            $summary['warnings_sent']++;
        }
    }
    $warn_stmt->close();
}

// ── 2. AUDIT DELAYED TASKS & APPLY PENALTIES ──────────────────────────────────
$del_sql = "SELECT t.*, u.name as assignee_name, u.email as assignee_email 
            FROM tasks t
            JOIN users u ON t.assigned_to = u.user_id
            WHERE t.due_date < ? 
              AND t.status NOT IN ('Completed', 'In Review', 'Cancelled', 'Archived')
              AND (t.stage IS NULL OR t.stage NOT IN ('Internal Review', 'Client Approval', 'Publication/Delivery', 'Reporting'))
              AND (t.delay_status IS NULL OR t.delay_status != 'exempted')
              AND COALESCE(t.is_delay_exempt, 0) = 0
              AND (t.extension_status IS NULL OR t.extension_status != 'pending')
              AND u.role NOT IN ('student', 'client', 'client_sub')
            ORDER BY t.due_date ASC";

$del_stmt = $conn->prepare($del_sql);
if ($del_stmt) {
    $del_stmt->bind_param("s", $today);
    $del_stmt->execute();
    $del_res = $del_stmt->get_result();

    while ($task = $del_res->fetch_assoc()) {
        $task_id = intval($task['id']);
        $summary['evaluated_tasks']++;

        $calc = calculateTaskDelayDeduction($conn, $task, $today);

        if ($calc['status_category'] === 'penalized' && $calc['penalty_amount'] > 0) {
            $applied = applyTaskDelayPenalty($conn, $task_id, $today);
            if ($applied) {
                $summary['penalties_applied']++;
                $summary['total_pkr'] += $calc['penalty_amount'];

                if (function_exists('notifyTaskDelayDeductionApplied')) {
                    @notifyTaskDelayDeductionApplied($conn, $task_id, $calc['penalty_amount'], $calc['delay_days']);
                }

                $summary['details'][] = [
                    'task_id'       => $task_id,
                    'title'         => $task['title'],
                    'assignee'      => $task['assignee_name'],
                    'status'        => 'penalized',
                    'days_overdue'  => $calc['delay_days'],
                    'penalty_pkr'   => $calc['penalty_amount'],
                    'reason'        => $calc['reason']
                ];
            }
        } elseif ($calc['status_category'] === 'grace_period' || $calc['status_category'] === 'monthly_grace') {
            applyTaskDelayPenalty($conn, $task_id, $today);
            $summary['graces_applied']++;
            $summary['details'][] = [
                'task_id'       => $task_id,
                'title'         => $task['title'],
                'assignee'      => $task['assignee_name'],
                'status'        => $calc['status_category'],
                'days_overdue'  => $calc['delay_days'],
                'penalty_pkr'   => 0.00,
                'reason'        => $calc['reason']
            ];
        }
    }
    $del_stmt->close();
}

if (!$is_cli) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'summary' => $summary], JSON_PRETTY_PRINT);
} else {
    echo "=== Task Delays Cron Run Finished ===\n";
    echo "Evaluated: " . $summary['evaluated_tasks'] . " tasks\n";
    echo "Warnings Sent: " . $summary['warnings_sent'] . "\n";
    echo "Graces Applied: " . $summary['graces_applied'] . "\n";
    echo "Penalties Applied: " . $summary['penalties_applied'] . " (PKR " . number_format($summary['total_pkr'], 2) . ")\n";
}
