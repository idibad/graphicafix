<?php
/**
 * export_project_tasks_pdf.php
 * Generates an executive PDF report of tasks for a specific project.
 * Supports optional filtering by assignee, status, priority, and search keyword.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress error display to prevent PDF stream corruption
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

// Auth check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    die('Access Denied. Please log in to view this report.');
}

$username = $_SESSION['username'] ?? '';
$user_id = intval($_SESSION['user_id'] ?? 0);

// Fetch user role
$user_stmt = $conn->prepare("SELECT user_id, name, role, client_id FROM users WHERE username = ? OR user_id = ?");
$user_stmt->bind_param("si", $username, $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

if (!$current_user) {
    die('Access Denied. User record not found.');
}

$role = strtolower($current_user['role']);
$current_client_id = intval($current_user['client_id'] ?? 0);

// Check permission
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'teacher', 'user', 'manager', 'hrm', 'client', 'client_sub'])) {
    die('Access Denied. Insufficient privileges.');
}

$project_id = intval($_GET['id'] ?? ($_GET['project_id'] ?? 0));
if ($project_id <= 0) {
    die('Invalid project ID specified.');
}

// Fetch project details
$proj_stmt = $conn->prepare("
    SELECT p.*, c.client_name, c.company, c.email as client_email, c.phone as client_phone
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.id = ?
");
$proj_stmt->bind_param("i", $project_id);
$proj_stmt->execute();
$project = $proj_stmt->get_result()->fetch_assoc();

if (!$project) {
    die('Project not found.');
}

// Client visibility check
if (in_array($role, ['client', 'client_sub'])) {
    if (intval($project['client_id']) !== $current_client_id || $current_client_id <= 0) {
        die('Access Denied. You do not have permission to access tasks for this project.');
    }
}

// ── Build Filter Criteria ──
$filter_user = $_GET['user_id'] ?? ($_GET['assigned_to'] ?? 'all');
$filter_status = trim($_GET['status'] ?? 'all');
$filter_priority = trim($_GET['priority'] ?? 'all');
$filter_search = trim($_GET['search'] ?? '');

$where_clauses = ["t.project_id = ?"];
$params = [$project_id];
$types = "i";

$active_filters_text = [];

// Filter by User / Assignee
if ($filter_user === 'unassigned') {
    $where_clauses[] = "(t.assigned_to IS NULL OR t.assigned_to = 0)";
    $active_filters_text[] = "Assignee: Unassigned";
} elseif ($filter_user !== 'all' && intval($filter_user) > 0) {
    $user_filter_id = intval($filter_user);
    $where_clauses[] = "t.assigned_to = ?";
    $params[] = $user_filter_id;
    $types .= "i";
    
    $u_chk = $conn->query("SELECT name FROM users WHERE user_id = {$user_filter_id}");
    if ($u_chk && $u_row = $u_chk->fetch_assoc()) {
        $active_filters_text[] = "Assignee: " . $u_row['name'];
    }
}

// Filter by Status
if (!empty($filter_status) && strtolower($filter_status) !== 'all') {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
    $types .= "s";
    $active_filters_text[] = "Status: " . $filter_status;
}

// Filter by Priority
if (!empty($filter_priority) && strtolower($filter_priority) !== 'all') {
    $where_clauses[] = "t.priority = ?";
    $params[] = $filter_priority;
    $types .= "s";
    $active_filters_text[] = "Priority: " . $filter_priority;
}

// Filter by Search Query
if (!empty($filter_search)) {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $search_param = "%{$filter_search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
    $active_filters_text[] = "Keyword: \"{$filter_search}\"";
}

$where_sql = implode(" AND ", $where_clauses);

// ── Overall Project Tasks Stats ──
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'In Review' THEN 1 ELSE 0 END) as in_review,
        SUM(CASE WHEN status = 'Pending' OR status = 'Not Started' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM tasks 
    WHERE project_id = ?
");
$stats_query->bind_param("i", $project_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();

$total_tasks = intval($stats['total'] ?? 0);
$completed_tasks = intval($stats['completed'] ?? 0);
$inprogress_tasks = intval($stats['in_progress'] ?? 0);
$inreview_tasks = intval($stats['in_review'] ?? 0);
$pending_tasks = intval($stats['pending'] ?? 0);
$cancelled_tasks = intval($stats['cancelled'] ?? 0);
$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

// ── Fetch Task Rows ──
$tasks_sql = "
    SELECT t.*, u.name as assigned_name, u.role as assigned_role
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    WHERE {$where_sql}
    ORDER BY 
        CASE 
            WHEN t.status = 'In Progress' THEN 1
            WHEN t.status = 'In Review' THEN 2
            WHEN t.status = 'Pending' THEN 3
            WHEN t.status = 'Not Started' THEN 4
            WHEN t.status = 'Completed' THEN 5
            WHEN t.status = 'Cancelled' THEN 6
            ELSE 7
        END,
        t.due_date ASC,
        t.id DESC
";

$stmt_tasks = $conn->prepare($tasks_sql);
$stmt_tasks->bind_param($types, ...$params);
$stmt_tasks->execute();
$tasks_res = $stmt_tasks->get_result();
$tasks_list = $tasks_res->fetch_all(MYSQLI_ASSOC);
$filtered_count = count($tasks_list);

// Helpers for Status & Priority Styling in PDF
function getPdfStatusStyle($status) {
    $s = trim($status);
    switch ($s) {
        case 'Completed':
            return ['bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0', 'icon' => '✓ Completed'];
        case 'In Progress':
            return ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#93c5fd', 'icon' => '⚡ In Progress'];
        case 'In Review':
            return ['bg' => '#faf5ff', 'color' => '#7e22ce', 'border' => '#d8b4fe', 'icon' => '🔍 In Review'];
        case 'Cancelled':
            return ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fca5a5', 'icon' => '✕ Cancelled'];
        case 'Pending':
        case 'Not Started':
        default:
            return ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fcd34d', 'icon' => '⏳ Pending'];
    }
}

function getPdfPriorityStyle($priority) {
    $p = trim($priority);
    switch ($p) {
        case 'High':
            return ['bg' => '#fee2e2', 'color' => '#dc2626', 'border' => '#f87171'];
        case 'Medium':
            return ['bg' => '#fef3c7', 'color' => '#d97706', 'border' => '#fbbf24'];
        case 'Low':
        default:
            return ['bg' => '#e0f2fe', 'color' => '#0284c7', 'border' => '#7dd3fc'];
    }
}

$headerImg = __DIR__ . '/../assets/images/letter_header.png';
$footerImg = __DIR__ . '/../assets/images/letter_footer.png';
$hasHeader = file_exists($headerImg);
$hasFooter = file_exists($footerImg);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: 'DejaVu Sans', 'Segoe UI', Helvetica, Arial, sans-serif;
        color: #1e293b;
        font-size: 9.5pt;
        line-height: 1.45;
    }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    
    .text-primary { color: #024442; }
    .text-muted { color: #64748b; }
    
    /* Header Card */
    .header-box {
        border-bottom: 2px solid #024442;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .report-title {
        font-size: 20pt;
        font-weight: bold;
        color: #024442;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }
    .report-subtitle {
        font-size: 9pt;
        color: #64748b;
        margin-top: 4px;
    }
    
    /* Project Summary Banner */
    .summary-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }
    .proj-name {
        font-size: 13.5pt;
        font-weight: bold;
        color: #024442;
        margin-bottom: 3px;
    }
    .proj-client {
        font-size: 9pt;
        color: #475569;
        font-weight: 500;
    }
    
    /* KPI Stats Grid */
    .kpi-table {
        margin-bottom: 18px;
    }
    .kpi-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 8px;
        text-align: center;
    }
    .kpi-val {
        font-size: 15pt;
        font-weight: bold;
        color: #0f172a;
    }
    .kpi-label {
        font-size: 7.5pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }
    
    /* Progress bar */
    .progress-bar-bg {
        background: #e2e8f0;
        height: 7px;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 4px;
    }
    .progress-bar-fill {
        background: #024442;
        height: 7px;
        border-radius: 4px;
    }
    
    /* Filter Badge */
    .filter-notice {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        color: #b45309;
        font-size: 8pt;
        padding: 6px 12px;
        border-radius: 4px;
        margin-bottom: 14px;
    }
    
    /* Tasks Table */
    .tasks-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .tasks-table th {
        background-color: #024442;
        color: #ffffff;
        font-size: 8.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 10px;
        text-align: left;
        border: 1px solid #024442;
    }
    .tasks-table td {
        padding: 8px 10px;
        font-size: 8.5pt;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .tasks-table tr:nth-child(even) {
        background-color: #f8fafc;
    }
    
    /* Pill Badges */
    .badge {
        font-size: 7.5pt;
        font-weight: bold;
        padding: 3px 8px;
        border-radius: 12px;
        display: inline-block;
        white-space: nowrap;
        text-align: center;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-style: italic;
        font-size: 10pt;
    }
    
    .footer-note {
        margin-top: 20px;
        font-size: 7.5pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
        text-align: right;
    }
</style>
</head>
<body>

    <?php if ($hasHeader): ?>
    <htmlpageheader name="pageHeader">
        <div style="width: 100%;">
            <img src="<?php echo $headerImg; ?>" style="width: 100%; margin: 0; padding: 0;" />
        </div>
    </htmlpageheader>
    <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />
    <?php endif; ?>

    <?php if ($hasFooter): ?>
    <htmlpagefooter name="pageFooter">
        <div style="width: 100%;">
            <img src="<?php echo $footerImg; ?>" style="width: 100%; margin: 0; padding: 0;" />
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />
    <?php endif; ?>

    <!-- Document Content Wrapper -->
    <div style="padding: <?php echo $hasHeader ? '5px 18mm 15mm 18mm' : '15mm'; ?>;">
        
        <!-- Header Section -->
        <table class="header-box">
            <tr>
                <td width="55%">
                    <h1 class="report-title">Tasks Report</h1>
                    <div class="report-subtitle">
                        Generated on <strong><?php echo date('M d, Y, h:i A'); ?></strong> by <?php echo htmlspecialchars($current_user['name']); ?> (<?php echo strtoupper($role); ?>)
                    </div>
                </td>
                <td width="45%" style="text-align: right; vertical-align: middle;">
                    <div style="font-size: 8.5pt; color: #64748b;">PROJECT STATUS:</div>
                    <div style="font-size: 12pt; font-weight: bold; color: #024442; text-transform: uppercase;">
                        <?php echo htmlspecialchars($project['status']); ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Project Overview Box -->
        <div class="summary-card">
            <table width="100%">
                <tr>
                    <td width="60%">
                        <div class="proj-name"><?php echo htmlspecialchars($project['project_name']); ?></div>
                        <div class="proj-client">
                            <strong>Client / Brand:</strong> <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?>
                            <?php if (!empty($project['company'])): ?> (<?php echo htmlspecialchars($project['company']); ?>)<?php endif; ?>
                        </div>
                    </td>
                    <td width="40%" style="text-align: right;">
                        <div style="font-size: 8.5pt; color: #475569;">
                            <strong>Timeline:</strong> 
                            <?php echo !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'TBD'; ?> - 
                            <?php echo !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'TBD'; ?>
                        </div>
                        <div style="font-size: 8.5pt; color: #475569; margin-top: 3px;">
                            <strong>Project Completion:</strong> <?php echo intval($project['progress']); ?>%
                        </div>
                        <div class="progress-bar-bg" style="width: 150px; float: right;">
                            <div class="progress-bar-fill" style="width: <?php echo intval($project['progress']); ?>%;"></div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- KPI Metric Cards -->
        <table class="kpi-table" width="100%">
            <tr>
                <td width="20%" style="padding-right: 6px;">
                    <div class="kpi-box">
                        <div class="kpi-val"><?php echo $total_tasks; ?></div>
                        <div class="kpi-label">Total Tasks</div>
                    </div>
                </td>
                <td width="20%" style="padding-right: 6px;">
                    <div class="kpi-box" style="border-top: 3px solid #10b981;">
                        <div class="kpi-val" style="color: #047857;"><?php echo $completed_tasks; ?></div>
                        <div class="kpi-label">Completed (<?php echo $completion_rate; ?>%)</div>
                    </div>
                </td>
                <td width="20%" style="padding-right: 6px;">
                    <div class="kpi-box" style="border-top: 3px solid #3b82f6;">
                        <div class="kpi-val" style="color: #1d4ed8;"><?php echo $inprogress_tasks; ?></div>
                        <div class="kpi-label">In Progress</div>
                    </div>
                </td>
                <td width="20%" style="padding-right: 6px;">
                    <div class="kpi-box" style="border-top: 3px solid #8b5cf6;">
                        <div class="kpi-val" style="color: #7e22ce;"><?php echo $inreview_tasks; ?></div>
                        <div class="kpi-label">In Review</div>
                    </div>
                </td>
                <td width="20%">
                    <div class="kpi-box" style="border-top: 3px solid #f59e0b;">
                        <div class="kpi-val" style="color: #b45309;"><?php echo $pending_tasks; ?></div>
                        <div class="kpi-label">Pending</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Filter Context Notice (if filtered) -->
        <?php if (!empty($active_filters_text)): ?>
        <div class="filter-notice">
            <strong>Filtered Report:</strong> Showing <?php echo $filtered_count; ?> task(s) matching criteria &mdash; <em><?php echo htmlspecialchars(implode(" • ", $active_filters_text)); ?></em>
        </div>
        <?php endif; ?>

        <!-- Task Items Table -->
        <table class="tasks-table">
            <thead>
                <tr>
                    <th width="5%" style="text-align: center;">#</th>
                    <th width="38%">Task Description</th>
                    <th width="20%">Assigned To</th>
                    <th width="11%" style="text-align: center;">Priority</th>
                    <th width="12%" style="text-align: center;">Due Date</th>
                    <th width="14%" style="text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($filtered_count > 0): ?>
                    <?php 
                    $counter = 1;
                    foreach ($tasks_list as $task): 
                        $statStyle = getPdfStatusStyle($task['status']);
                        $priStyle = getPdfPriorityStyle($task['priority']);
                        $assigneeName = !empty($task['assigned_name']) ? $task['assigned_name'] : 'Unassigned';
                        $assigneeRole = !empty($task['assigned_role']) ? ucfirst($task['assigned_role']) : '';
                    ?>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-weight: bold;"><?php echo $counter++; ?></td>
                        <td>
                            <div style="font-weight: bold; color: #0f172a; font-size: 9pt;"><?php echo htmlspecialchars($task['title']); ?></div>
                            <?php if (!empty($task['description'])): ?>
                                <div style="font-size: 7.5pt; color: #64748b; margin-top: 2px; line-height: 1.3;">
                                    <?php echo htmlspecialchars(mb_strimwidth($task['description'], 0, 110, '...')); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($assigneeName); ?></div>
                            <?php if (!empty($assigneeRole)): ?>
                                <div style="font-size: 7.5pt; color: #64748b;"><?php echo htmlspecialchars($assigneeRole); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: <?php echo $priStyle['bg']; ?>; color: <?php echo $priStyle['color']; ?>; border: 1px solid <?php echo $priStyle['border']; ?>;">
                                <?php echo htmlspecialchars($task['priority']); ?>
                            </span>
                        </td>
                        <td style="text-align: center; color: #475569; font-size: 8pt; white-space: nowrap;">
                            <?php echo !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : '<span style="color:#94a3b8;">N/A</span>'; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: <?php echo $statStyle['bg']; ?>; color: <?php echo $statStyle['color']; ?>; border: 1px solid <?php echo $statStyle['border']; ?>;">
                                <?php echo htmlspecialchars($statStyle['icon']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            No tasks found matching the requested criteria for this project.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-note">
            Graphica Fix Task Management System &bull; Project ID: #<?php echo $project_id; ?> &bull; Page {PAGENO} of {nbpg}
        </div>

    </div>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'margin_top' => $hasHeader ? 40 : 15,
    'margin_bottom' => $hasFooter ? 30 : 15,
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
    'format' => 'A4',
    'default_font' => 'dejavusans'
]);

$mpdf->SetTitle('Tasks Report - ' . $project['project_name']);
$mpdf->SetAuthor('Graphica Fix');
$mpdf->WriteHTML($html);

$clean_filename = 'Tasks-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $project['project_name']) . '-' . date('Ymd') . '.pdf';
$dest = (isset($_GET['dl']) && $_GET['dl'] == '1') ? 'D' : 'I';
$mpdf->Output($clean_filename, $dest);
exit;
