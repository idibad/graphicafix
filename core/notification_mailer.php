<?php
// core/notification_mailer.php — Centralized, targeted email notification service for Graphicafix
// Strictly dispatches notifications ONLY to relevant personals (never broadcasts to unrelated users)

require_once __DIR__ . '/config.php';

if (!function_exists('sendNotificationEmail')) {
    /**
     * Base email sender with clean Graphicafix brand styling (zero emojis, clean cards).
     */
    function sendNotificationEmail($to, $subject, $heading, $bodyHtml, $ctaText = '', $ctaUrl = '') {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $logoUrl = $siteUrl . '/assets/images/logo.png';

        $btnHtml = '';
        if (!empty($ctaText) && !empty($ctaUrl)) {
            $btnHtml = "
            <div style='margin: 30px 0 16px 0; text-align: center;'>
                <a href='" . htmlspecialchars($ctaUrl) . "' style='background: #024442; color: #ffffff; text-decoration: none; padding: 13px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; display: inline-block; letter-spacing: 0.2px;'>
                    " . htmlspecialchars($ctaText) . " &rarr;
                </a>
            </div>";
        }

        $fullHtml = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($heading) . "</title>
</head>
<body style='margin:0; padding:0; background-color:#f4f6f8; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
    <div style='max-width:600px; margin:40px auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 24px rgba(0,0,0,0.06); border:1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background:#024442; padding:32px 36px; text-align:center;'>
            <div style='margin-bottom:12px;'>
                <img src='{$logoUrl}' alt='Graphicafix' style='max-height:48px; border:0;'>
            </div>
            <h1 style='margin:0; color:#ffffff; font-size:20px; font-weight:700; letter-spacing:-0.2px;'>" . htmlspecialchars($heading) . "</h1>
        </div>
        
        <!-- Body Content -->
        <div style='padding:36px 36px 24px 36px; font-size:14.5px; color:#334155; line-height:1.7;'>
            {$bodyHtml}
            {$btnHtml}
        </div>
        
        <!-- Footer -->
        <div style='background-color:#f8fafc; padding:20px 36px; text-align:center; border-top:1px solid #e2e8f0;'>
            <p style='margin:0 0 4px 0; font-size:12px; color:#475569; font-weight:700;'>Graphicafix Management System</p>
            <p style='margin:0; font-size:11.5px; color:#94a3b8;'>This is an automated operational notification delivered strictly to the intended recipient on <a href='{$siteUrl}' style='color:#024442; text-decoration:none; font-weight:600;'>Graphicafix</a>.</p>
        </div>
    </div>
</body>
</html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Graphicafix Notifications <noreply@graphicafix.com>\r\n";
        $headers .= "Reply-To: info@graphicafix.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $fullHtml, $headers);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. TASK NOTIFICATIONS (Sent strictly to Assignee or Creator)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyTaskAssigned')) {
    function notifyTaskAssigned($conn, $task_id) {
        $task_id = intval($task_id);
        if ($task_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT t.title, t.priority, t.due_date, t.description, t.assigned_to,
                   u.name as assignee_name, u.email as assignee_email,
                   c.name as creator_name, p.project_name
            FROM tasks t
            JOIN users u ON t.assigned_to = u.user_id
            LEFT JOIN users c ON t.created_by = c.user_id
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.id = ? AND (u.status = 'active' OR u.status IS NULL)
        ");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task || empty($task['assignee_email'])) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/task_details.php?id=' . $task_id;

        $subject = "Task Assigned: " . $task['title'];
        $heading = "New Task Assigned to You";

        $dueDate = !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'No due date';
        $projName = !empty($task['project_name']) ? htmlspecialchars($task['project_name']) : 'General Task';
        $creatorName = !empty($task['creator_name']) ? htmlspecialchars($task['creator_name']) : 'Management';

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($task['assignee_name']) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'>You have been assigned a new task by <strong>{$creatorName}</strong>:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Task Title:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($task['title']) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Project:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$projName}</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Priority:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($task['priority']) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600;'>Due Date:</td>
                <td style='padding:8px 0; color:#024442; font-weight:700;'>{$dueDate}</td>
            </tr>
        </table>";

        if (!empty($task['description'])) {
            $body .= "
            <div style='background:#f8fafc; border-left:3px solid #024442; padding:12px 16px; margin-bottom:20px; color:#475569;'>
                " . nl2br(htmlspecialchars($task['description'])) . "
            </div>";
        }

        return sendNotificationEmail($task['assignee_email'], $subject, $heading, $body, "Open Task Details", $ctaUrl);
    }
}

if (!function_exists('notifyTaskStatusChanged')) {
    function notifyTaskStatusChanged($conn, $task_id, $new_status, $changed_by_user_id) {
        $task_id = intval($task_id);
        $changed_by_user_id = intval($changed_by_user_id);
        if ($task_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT t.title, t.created_by, t.assigned_to, t.project_id,
                   c.name as creator_name, c.email as creator_email, c.status as creator_status,
                   u.name as updater_name, p.project_name
            FROM tasks t
            LEFT JOIN users c ON t.created_by = c.user_id
            LEFT JOIN users u ON u.user_id = ?
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.id = ? AND (c.status = 'active' OR c.status IS NULL)
        ");
        $stmt->bind_param("ii", $changed_by_user_id, $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task) return false;

        // Notify the task creator if different from the updater
        $targetEmail = '';
        $targetName  = '';
        if ($task['created_by'] != $changed_by_user_id && !empty($task['creator_email'])) {
            $targetEmail = $task['creator_email'];
            $targetName  = $task['creator_name'];
        }

        if (empty($targetEmail)) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/task_details.php?id=' . $task_id;

        $updaterName = !empty($task['updater_name']) ? htmlspecialchars($task['updater_name']) : 'A team member';
        $subject = "Task Updated: " . $task['title'] . " is now " . $new_status;
        $heading = "Task Status Updated";

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($targetName) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'><strong>{$updaterName}</strong> updated the status of a task you created:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Task Title:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($task['title']) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>New Status:</td>
                <td style='padding:8px 0; color:#024442; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($new_status) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600;'>Updated By:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600;'>{$updaterName}</td>
            </tr>
        </table>";

        return sendNotificationEmail($targetEmail, $subject, $heading, $body, "Review Task", $ctaUrl);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. PROJECT TEAM ASSIGNMENT (Sent ONLY to assigned team members)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyProjectTeamAssigned')) {
    function notifyProjectTeamAssigned($conn, $project_id, array $team_user_ids) {
        $project_id = intval($project_id);
        if ($project_id <= 0 || empty($team_user_ids) || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT p.project_name, p.start_date, p.end_date, p.description, c.client_name
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$project) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/project_details.php?id=' . $project_id;

        $subject = "Assigned to Project: " . $project['project_name'];
        $heading = "New Project Assignment";
        $clientName = !empty($project['client_name']) ? htmlspecialchars($project['client_name']) : 'Client';
        $startDate = !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'Immediate';
        $endDate   = !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'TBD';

        foreach ($team_user_ids as $uid) {
            $uid = intval($uid);
            if ($uid <= 0) continue;

            $u_stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ? AND (status = 'active' OR status IS NULL)");
            $u_stmt->bind_param("i", $uid);
            $u_stmt->execute();
            $user = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();

            if (!$user || empty($user['email'])) continue;

            $body = "
            <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
            <p style='margin:0 0 20px 0;'>You have been assigned to the delivery team for project <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
            
            <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Project:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($project['project_name']) . "</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Client:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$clientName}</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Timeline:</td>
                    <td style='padding:8px 0; color:#024442; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$startDate} &mdash; {$endDate}</td>
                </tr>
            </table>";

            sendNotificationEmail($user['email'], $subject, $heading, $body, "Open Project Workspace", $ctaUrl);
        }

        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. DELIVERABLE SIGN-OFF (Sent to Project Team and PM)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyDeliverableApproved')) {
    function notifyDeliverableApproved($conn, $project_id, $deliverable_id, $client_user_id) {
        $project_id = intval($project_id);
        $deliverable_id = intval($deliverable_id);
        if ($project_id <= 0 || $deliverable_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT d.title, d.version, p.project_name, u.name as client_name
            FROM project_deliverables d
            JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON u.user_id = ?
            WHERE d.id = ? AND d.project_id = ?
        ");
        $stmt->bind_param("iii", $client_user_id, $deliverable_id, $project_id);
        $stmt->execute();
        $del = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$del) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/project_details.php?id=' . $project_id . '&tab=deliverables';

        $subject = "Deliverable Approved: " . $del['title'] . " [" . $del['project_name'] . "]";
        $heading = "Client Sign-Off Approved";
        $clientName = !empty($del['client_name']) ? htmlspecialchars($del['client_name']) : 'The Client';

        $body = "
        <p style='margin:0 0 16px 0;'>Great news!</p>
        <p style='margin:0 0 20px 0;'><strong>{$clientName}</strong> has formally approved and signed off on deliverable <strong>" . htmlspecialchars($del['title']) . " (v" . htmlspecialchars($del['version']) . ")</strong> for project <strong>" . htmlspecialchars($del['project_name']) . "</strong>.</p>
        
        <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:20px;'>
            <strong style='color:#166534; font-size:14.5px;'>Status: Officially Approved</strong>
            <div style='color:#15803d; font-size:13px; margin-top:4px;'>Timestamp: " . date('M d, Y g:i A') . "</div>
        </div>";

        // Query team members assigned to this project
        $t_res = $conn->query("
            SELECT DISTINCT u.email 
            FROM project_team pt 
            JOIN users u ON pt.user_id = u.user_id 
            WHERE pt.project_id = {$project_id} 
              AND (u.status = 'active' OR u.status IS NULL)
              AND u.email IS NOT NULL AND u.email != ''
        ");
        if ($t_res) {
            while ($tm = $t_res->fetch_assoc()) {
                sendNotificationEmail($tm['email'], $subject, $heading, $body, "View in Project Workspace", $ctaUrl);
            }
        }

        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. CRITICAL DPR BLOCKER (Sent ONLY to Super Admin and Project Manager)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyCriticalDPRBlocker')) {
    function notifyCriticalDPRBlocker($conn, $dpr_id, $employee_id, $project_id, $challenges, $waiting_on) {
        $dpr_id = intval($dpr_id);
        $employee_id = intval($employee_id);
        if ($dpr_id <= 0 || $employee_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT u.name as employee_name, u.role as employee_role, p.project_name
            FROM users u
            LEFT JOIN projects p ON p.id = ?
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("ii", $project_id, $employee_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$info) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/dpr.php';

        $employeeName = htmlspecialchars($info['employee_name']);
        $projName = !empty($info['project_name']) ? htmlspecialchars($info['project_name']) : 'General / Unassigned';
        $subject = "CRITICAL BLOCKER: " . $employeeName . " on " . $projName;
        $heading = "Critical Blocker Reported in DPR";

        $body = "
        <p style='margin:0 0 16px 0; color:#b91c1c; font-weight:700;'>Attention Management,</p>
        <p style='margin:0 0 20px 0;'><strong>{$employeeName}</strong> flagged a <strong>Critical Blocker</strong> in today's Daily Progress Report that requires executive or PM intervention:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Employee:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>{$employeeName} (" . htmlspecialchars($info['employee_role']) . ")</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Project:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$projName}</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Waiting On:</td>
                <td style='padding:8px 0; color:#dc2626; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($waiting_on ?: 'Not specified') . "</td>
            </tr>
        </table>
        
        <div style='background:#fef2f2; border-left:4px solid #ef4444; padding:14px 18px; margin-bottom:20px; color:#991b1b; font-size:14px;'>
            <strong>Challenges & Blocker Details:</strong><br>
            " . nl2br(htmlspecialchars($challenges)) . "
        </div>";

        // Query only super_admin, admin, and pm
        $m_res = $conn->query("SELECT email FROM users WHERE role IN ('super_admin', 'superadmin', 'admin', 'pm') AND (status = 'active' OR status IS NULL) AND email IS NOT NULL AND email != ''");
        if ($m_res) {
            while ($mgr = $m_res->fetch_assoc()) {
                sendNotificationEmail($mgr['email'], $subject, $heading, $body, "Review Report in DPR Desk", $ctaUrl);
            }
        }

        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. CLIENT USER WELCOME / ONBOARDING (Sent ONLY to the new client user)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyClientUserWelcome')) {
    function notifyClientUserWelcome($email, $name, $username, $password, $client_company = '') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $loginUrl = $siteUrl . '/admin/login.php';

        $subject = "Your Graphicafix Client Portal Account";
        $heading = "Welcome to Graphicafix Client Portal";
        $companyLine = !empty($client_company) ? " for <strong>" . htmlspecialchars($client_company) . "</strong>" : "";

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'>An authorized account{$companyLine} has been created for you on the Graphicafix Client Portal. You can track deliverables, review milestones, and submit asset requests directly.</p>
        
        <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px 24px; margin-bottom:24px;'>
            <div style='font-size:13px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:12px; letter-spacing:0.5px;'>Your Login Credentials</div>
            <table style='width:100%; border-collapse:collapse; font-size:14px;'>
                <tr>
                    <td style='padding:6px 0; color:#64748b; width:120px;'>Username:</td>
                    <td style='padding:6px 0; color:#0f172a; font-weight:700;'>" . htmlspecialchars($username) . "</td>
                </tr>
                <tr>
                    <td style='padding:6px 0; color:#64748b;'>Password:</td>
                    <td style='padding:6px 0; color:#0f172a; font-weight:700; font-family:monospace;'>" . htmlspecialchars($password) . "</td>
                </tr>
            </table>
        </div>
        <p style='margin:0; font-size:13px; color:#64748b;'>Please log in and update your password under your profile settings.</p>";

        return sendNotificationEmail($email, $subject, $heading, $body, "Log in to Client Portal", $loginUrl);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ATTENDANCE / LEAVE OVERRIDE APPROVAL (Sent ONLY to the employee)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyAttendanceApproved')) {
    function notifyAttendanceApproved($conn, $user_id, $attendance_date, $type, $reason, $approved_by_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT u.name, u.email, a.name as approver_name
            FROM users u
            LEFT JOIN users a ON a.user_id = ?
            WHERE u.user_id = ? AND (u.status = 'active' OR u.status IS NULL)
        ");
        $stmt->bind_param("ii", $approved_by_id, $user_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$info || empty($info['email'])) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/attendance.php';

        $statusLabel = match($type) {
            'leave'   => 'Approved Casual / Official Leave',
            'present' => 'Attendance Override (Marked Present)',
            'remote'  => 'Remote Work Authorization',
            default   => ucfirst($type)
        };

        $subject = "Attendance Update: " . $statusLabel . " (" . date('M d, Y', strtotime($attendance_date)) . ")";
        $heading = "Attendance Status Approved";
        $approverName = !empty($info['approver_name']) ? htmlspecialchars($info['approver_name']) : 'HR / Management';

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($info['name']) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'>Your attendance status record for <strong>" . date('l, M d, Y', strtotime($attendance_date)) . "</strong> has been verified and recorded:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Date:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . date('M d, Y', strtotime($attendance_date)) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Status:</td>
                <td style='padding:8px 0; color:#024442; font-weight:700; border-bottom:1px solid #f1f5f9;'>{$statusLabel}</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Approved By:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$approverName}</td>
            </tr>
        </table>";

        if (!empty($reason)) {
            $body .= "
            <div style='background:#f8fafc; border-left:3px solid #024442; padding:12px 16px; margin-bottom:20px; color:#475569;'>
                <strong>Remarks / Reason:</strong><br>
                " . nl2br(htmlspecialchars($reason)) . "
            </div>";
        }

        return sendNotificationEmail($info['email'], $subject, $heading, $body, "View Attendance Record", $ctaUrl);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. INBOUND PROJECT REQUEST (Lead confirmation + Management Alert)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyInboundProjectLead')) {
    function notifyInboundProjectLead($conn, $name, $email, $phone, $project_type, $budget, $description) {
        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';

        // A. Confirmation email to the lead
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $subjectClient = "We Received Your Project Inquiry - Graphicafix";
            $headingClient = "Thank You for Contacting Graphicafix";
            $bodyClient = "
            <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p style='margin:0 0 20px 0;'>Thank you for reaching out to Graphicafix! We have received your inquiry for <strong>" . htmlspecialchars($project_type) . "</strong> and our project team is reviewing your requirements.</p>
            <p style='margin:0 0 20px 0;'>One of our project leads will get in touch with you within 24 business hours to discuss your scope and next steps.</p>";

            sendNotificationEmail($email, $subjectClient, $headingClient, $bodyClient);
        }

        // B. Alert email to internal sales / management
        if ($conn) {
            $subjectInternal = "New Inbound Project Request: " . $name . " [" . $project_type . "]";
            $headingInternal = "New Inbound Project Request";
            $ctaUrl = $siteUrl . '/admin/project_requests.php';

            $bodyInternal = "
            <p style='margin:0 0 16px 0;'>A new project inquiry was submitted via the website:</p>
            <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Name:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($name) . "</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Email:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($email) . "</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Phone:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($phone) . "</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Project Type:</td>
                    <td style='padding:8px 0; color:#024442; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($project_type) . "</td>
                </tr>
                <tr>
                    <td style='padding:8px 0; color:#64748b; font-weight:600;'>Budget:</td>
                    <td style='padding:8px 0; color:#0f172a; font-weight:600;'>" . htmlspecialchars($budget ?: 'Not specified') . "</td>
                </tr>
            </table>
            <div style='background:#f8fafc; border-left:3px solid #024442; padding:12px 16px; margin-bottom:20px;'>
                " . nl2br(htmlspecialchars($description)) . "
            </div>";

            $res_adm = $conn->query("SELECT email FROM users WHERE role IN ('super_admin', 'superadmin', 'admin', 'sales') AND (status = 'active' OR status IS NULL) AND email IS NOT NULL AND email != ''");
            if ($res_adm) {
                while ($adm = $res_adm->fetch_assoc()) {
                    sendNotificationEmail($adm['email'], $subjectInternal, $headingInternal, $bodyInternal, "View in Inbound Requests", $ctaUrl);
                }
            }
        }

        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. LMS ASSIGNMENT ANNOUNCEMENT (Sent ONLY to students in this course)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyCourseAssignmentPublished')) {
    function notifyCourseAssignmentPublished($conn, $course_id, $assignment_title, $due_date, $description) {
        $course_id = intval($course_id);
        if ($course_id <= 0 || !$conn) return false;

        $c_res = $conn->query("SELECT title FROM courses WHERE id = {$course_id}");
        $course = $c_res ? $c_res->fetch_assoc() : null;
        $courseTitle = $course ? $course['title'] : 'Course';

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/student_assignments.php';

        $dueDateStr = !empty($due_date) ? date('M d, Y', strtotime($due_date)) : 'No deadline';
        $subject = "New Assignment: " . $assignment_title . " — " . $courseTitle;
        $heading = "New Course Assignment Announced";

        $body = "
        <p style='margin:0 0 16px 0;'>A new assignment has been posted in <strong>" . htmlspecialchars($courseTitle) . "</strong>:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Assignment:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($assignment_title) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Due Date:</td>
                <td style='padding:8px 0; color:#024442; font-weight:700; border-bottom:1px solid #f1f5f9;'>{$dueDateStr}</td>
            </tr>
        </table>";

        if (!empty($description)) {
            $body .= "
            <div style='background:#f8fafc; border-left:3px solid #024442; padding:12px 16px; margin-bottom:20px; color:#475569;'>
                " . nl2br(htmlspecialchars($description)) . "
            </div>";
        }

        // Query ONLY active students enrolled in this course
        $s_res = $conn->query("
            SELECT student_email, student_name 
            FROM course_enrollments 
            WHERE course_id = {$course_id} AND status = 'active' AND student_email IS NOT NULL AND student_email != ''
        ");
        if ($s_res) {
            while ($st = $s_res->fetch_assoc()) {
                sendNotificationEmail($st['student_email'], $subject, $heading, $body, "View & Submit Assignment", $ctaUrl);
            }
        }

        return true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. LMS ASSIGNMENT GRADED (Sent ONLY to the specific student)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyAssignmentGraded')) {
    function notifyAssignmentGraded($conn, $submission_id, $grade, $feedback) {
        $submission_id = intval($submission_id);
        if ($submission_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT s.student_id, a.title as assignment_title, c.title as course_title,
                   u.name as student_name, u.email as student_email
            FROM assignment_submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN courses c ON a.course_id = c.id
            JOIN users u ON s.student_id = u.user_id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $sub = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sub || empty($sub['student_email'])) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/student_assignments.php';

        $subject = "Assignment Graded: " . $sub['assignment_title'];
        $heading = "Your Assignment Has Been Graded";

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($sub['student_name']) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'>Your submission for <strong>" . htmlspecialchars($sub['assignment_title']) . "</strong> in <strong>" . htmlspecialchars($sub['course_title']) . "</strong> has been evaluated:</p>
        
        <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px; margin-bottom:20px;'>
            <div style='font-size:13px; font-weight:700; color:#64748b; text-transform:uppercase;'>Grade Awarded</div>
            <div style='font-size:24px; font-weight:800; color:#024442; margin-top:4px;'>" . htmlspecialchars($grade) . "</div>
        </div>";

        if (!empty($feedback)) {
            $body .= "
            <div style='background:#fff; border:1px solid #cbd5e1; border-left:4px solid #024442; border-radius:6px; padding:14px; margin-bottom:20px;'>
                <strong style='color:#0f172a;'>Instructor Feedback:</strong><br>
                " . nl2br(htmlspecialchars($feedback)) . "
            </div>";
        }

        return sendNotificationEmail($sub['student_email'], $subject, $heading, $body, "View in Student Dashboard", $ctaUrl);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. LMS QUERY ANSWERED (Sent ONLY to the student who asked)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyStudentQueryAnswered')) {
    function notifyStudentQueryAnswered($conn, $query_id, $answer_text, $teacher_id) {
        $query_id = intval($query_id);
        if ($query_id <= 0 || !$conn) return false;

        $stmt = $conn->prepare("
            SELECT q.query_text, c.title as course_title,
                   u.name as student_name, u.email as student_email,
                   t.name as teacher_name
            FROM course_queries q
            JOIN courses c ON q.course_id = c.id
            JOIN users u ON q.student_id = u.user_id
            LEFT JOIN users t ON t.user_id = ?
            WHERE q.id = ?
        ");
        $stmt->bind_param("ii", $teacher_id, $query_id);
        $stmt->execute();
        $q = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$q || empty($q['student_email'])) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/student_my_queries.php';

        $teacherName = !empty($q['teacher_name']) ? htmlspecialchars($q['teacher_name']) : 'Your Instructor';
        $subject = "Instructor Replied to Your Question — " . $q['course_title'];
        $heading = "Answer to Your Course Question";

        $body = "
        <p style='margin:0 0 16px 0;'>Hello <strong>" . htmlspecialchars($q['student_name']) . "</strong>,</p>
        <p style='margin:0 0 20px 0;'><strong>{$teacherName}</strong> replied to your question in <strong>" . htmlspecialchars($q['course_title']) . "</strong>:</p>
        
        <div style='background:#f8fafc; border-left:3px solid #cbd5e1; padding:12px 16px; margin-bottom:16px; color:#64748b; font-size:13.5px;'>
            <strong>Your Question:</strong><br>
            <em>\"" . nl2br(htmlspecialchars($q['query_text'])) . "\"</em>
        </div>
        
        <div style='background:#f0fdf4; border-left:4px solid #024442; padding:14px 18px; margin-bottom:20px; color:#14532d; font-size:14px;'>
            <strong>Instructor's Response:</strong><br>
            " . nl2br(htmlspecialchars($answer_text)) . "
        </div>";

        return sendNotificationEmail($q['student_email'], $subject, $heading, $body, "Open Course Discussions", $ctaUrl);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 12. NOTICE PUBLISHED NOTIFICATION
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('notifyNoticePublished')) {
    /**
     * Sends an email notification to active team members / staff when a new notice is published.
     */
    function notifyNoticePublished($conn, $notice_id, $title, $content, $date, $posted_by = 'Management') {
        $notice_id = intval($notice_id);
        if (!$conn || empty($title)) return false;

        $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
        $ctaUrl  = $siteUrl . '/admin/notice_details.php?id=' . $notice_id;

        $subject = "Official Notice: " . $title;
        $heading = "New Official Notice Published";

        $formattedDate = !empty($date) ? date('M d, Y', strtotime($date)) : date('M d, Y');
        $postedByClean = !empty($posted_by) ? htmlspecialchars($posted_by) : 'Management';

        $body = "
        <p style='margin:0 0 16px 0;'>Dear Team Member,</p>
        <p style='margin:0 0 20px 0;'>A new official notice has been published on the Graphicafix portal:</p>
        
        <table style='width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;'>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; width:130px; border-bottom:1px solid #f1f5f9;'>Subject:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:700; border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($title) . "</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600; border-bottom:1px solid #f1f5f9;'>Date:</td>
                <td style='padding:8px 0; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;'>{$formattedDate}</td>
            </tr>
            <tr>
                <td style='padding:8px 0; color:#64748b; font-weight:600;'>Posted By:</td>
                <td style='padding:8px 0; color:#024442; font-weight:700;'>{$postedByClean}</td>
            </tr>
        </table>";

        if (!empty($content)) {
            $body .= "
            <div style='background:#f8fafc; border-left:3px solid #024442; padding:14px 18px; margin-bottom:20px; color:#334155; line-height:1.7; font-size:14px;'>
                " . nl2br(htmlspecialchars($content)) . "
            </div>";
        }

        // Fetch all active internal staff/users with valid emails
        $usersRes = $conn->query("
            SELECT DISTINCT email, name 
            FROM users 
            WHERE email IS NOT NULL 
              AND email != '' 
              AND email LIKE '%@%'
              AND (status = 'active' OR status IS NULL)
              AND role IN ('super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user', 'teacher')
        ");

        $sentCount = 0;
        if ($usersRes && $usersRes->num_rows > 0) {
            while ($u = $usersRes->fetch_assoc()) {
                if (!empty($u['email'])) {
                    @sendNotificationEmail($u['email'], $subject, $heading, $body, "View Notice & Print PDF", $ctaUrl);
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}
