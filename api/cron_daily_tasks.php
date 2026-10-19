<?php
/**
 * CRON JOB: Daily Task Reminder for Staff (Admin, User, HRM)
 * 
 * Recommended Setup in Hostinger hPanel:
 * Command: /usr/bin/php /path/to/public_html/graphicafix/api/cron_daily_tasks.php
 * Or via wget: wget -qO- https://graphicafix.com/api/cron_daily_tasks.php?token=graphicafixtasksecret
 * Set schedule to daily at your preferred morning time (e.g., 09:00 AM).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Protect the script from arbitrary public execution
$expected_token = "graphicafixtasksecret";
$is_cli = php_sapi_name() === 'cli';
$provided_token = $_GET['token'] ?? '';

if (!$is_cli && $provided_token !== $expected_token) {
    http_response_code(403);
    die("Forbidden: Invalid or missing token.");
}

// Adjust this path if this script is moved
require_once __DIR__ . '/../core/config.php'; 

// Fetch tasks for staff members
$sql = "SELECT u.user_id, u.name, u.email, t.title, t.priority, t.status, t.due_date 
        FROM users u
        JOIN tasks t ON u.user_id = t.assigned_to
        WHERE u.role IN ('admin', 'user', 'hrm') 
        AND t.status IN ('Pending', 'In Progress')
        AND u.email IS NOT NULL AND u.email != ''
        ORDER BY u.user_id, t.due_date ASC";

$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "No pending or in-progress tasks found for staff members today.\n";
    exit;
}

$userTasks = [];
while ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    if (!isset($userTasks[$userId])) {
        $userTasks[$userId] = [
            'name' => $row['name'],
            'email' => $row['email'],
            'tasks' => []
        ];
    }
    $userTasks[$userId]['tasks'][] = [
        'title' => $row['title'],
        'priority' => $row['priority'],
        'status' => $row['status'],
        'due_date' => $row['due_date']
    ];
}

$siteUrl = 'https://graphicafix.com';
$emailsSent = 0;

foreach ($userTasks as $userId => $data) {
    $name = htmlspecialchars($data['name']);
    $email = $data['email'];
    $taskCount = count($data['tasks']);
    
    $subject = "Good Morning $name! You have $taskCount active tasks today.";
    
    // Build HTML Email
    $html = "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
    <body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
    <div style='max-width:600px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.05);'>
        <div style='background:#024442;padding:25px;text-align:center;'>
            <h1 style='margin:0;color:#fff;font-size:22px;'>Good Morning, $name! ☕</h1>
            <p style='margin:10px 0 0 0;color:#e2e8f0;font-size:15px;'>Here is your daily task roundup for Graphicafix.</p>
        </div>
        <div style='padding:30px;'>
            <p style='font-size:15px;color:#333;margin-top:0;'>You currently have <strong>$taskCount</strong> active tasks that require your attention.</p>
            
            <table style='width:100%;border-collapse:collapse;margin-top:20px;'>
                <thead>
                    <tr style='background:#f8fafc;border-bottom:2px solid #e2e8f0;'>
                        <th style='padding:12px;text-align:left;font-size:13px;color:#64748b;'>Task</th>
                        <th style='padding:12px;text-align:center;font-size:13px;color:#64748b;'>Priority</th>
                        <th style='padding:12px;text-align:center;font-size:13px;color:#64748b;'>Status</th>
                        <th style='padding:12px;text-align:right;font-size:13px;color:#64748b;'>Due Date</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($data['tasks'] as $task) {
        $title = htmlspecialchars($task['title']);
        $priority = $task['priority'];
        $status = $task['status'];
        $dueDate = $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No Due Date';
        
        // Priority colors
        $pColor = '#64748b';
        if ($priority === 'High') $pColor = '#ef4444';
        if ($priority === 'Medium') $pColor = '#f59e0b';
        if ($priority === 'Low') $pColor = '#10b981';
        
        // Status colors
        $sColor = '#3b82f6'; // Pending
        if ($status === 'In Progress') $sColor = '#8b5cf6';
        
        $html .= "
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:12px;font-size:14px;color:#0f172a;'>$title</td>
                        <td style='padding:12px;text-align:center;'><span style='font-size:12px;font-weight:bold;color:$pColor;'>$priority</span></td>
                        <td style='padding:12px;text-align:center;'><span style='font-size:12px;background:$sColor;color:#fff;padding:3px 8px;border-radius:12px;'>$status</span></td>
                        <td style='padding:12px;text-align:right;font-size:13px;color:#64748b;'>$dueDate</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
            
            <div style='margin-top:30px;text-align:center;'>
                <a href='$siteUrl/admin/tasks.php' style='display:inline-block;background:#024442;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;font-size:14px;'>Go to Dashboard</a>
            </div>
        </div>
        <div style='background:#f8fafc;padding:20px;text-align:center;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;'>
            <p style='margin:0;'>This is an automated daily reminder from the Graphicafix platform.</p>
        </div>
    </div>
    </body></html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Graphicafix Tasks <noreply@graphicafix.com>\r\n";
    
    if (mail($email, $subject, $html, $headers)) {
        $emailsSent++;
        echo "Sent daily task reminder to $email ($name)<br>\n";
    } else {
        echo "Failed to send to $email<br>\n";
    }
}

echo "Process completed. Total emails sent: $emailsSent<br>\n";
