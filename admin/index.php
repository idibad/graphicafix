<?php
include 'dashboard_header.php';
require_once __DIR__ . '/../core/task_assignees_helper.php';
ensureTaskUserSchema($conn);

// Fetch active Team Member of the Month for the Graphicafix Family overview
$member_of_month = null;
if (!in_array($role, ['client', 'client_sub', 'student']) && file_exists(__DIR__ . '/../core/team_schema.php')) {
    require_once __DIR__ . '/../core/team_schema.php';
    if (function_exists('getActiveMemberOfMonth')) {
        $member_of_month = getActiveMemberOfMonth($conn);
    }
}

// ── 1. Ensure Teacher/Student/Blogs Tables Exist ──
$conn->query("CREATE TABLE IF NOT EXISTS live_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    teacher_id INT,
    topic VARCHAR(255),
    meeting_link VARCHAR(500),
    recording_link VARCHAR(500) DEFAULT NULL,
    start_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100),
    excerpt TEXT,
    content TEXT,
    image VARCHAR(255),
    author_id INT,
    status VARCHAR(50) DEFAULT 'draft',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS course_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    student_id INT,
    query_text TEXT,
    answer_text TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    teacher_id INT,
    title VARCHAR(255),
    description TEXT,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT,
    student_id INT,
    file_path VARCHAR(500),
    notes TEXT,
    grade VARCHAR(10) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS teacher_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    title VARCHAR(255),
    description TEXT,
    file_path VARCHAR(500),
    status VARCHAR(20) DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS recorded_lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    teacher_id INT,
    title VARCHAR(255),
    description TEXT,
    file_path VARCHAR(500),
    status VARCHAR(20) DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL
)");

// ==========================================;

$conn->query("CREATE TABLE IF NOT EXISTS daily_progress_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_date DATE NOT NULL,
    tasks_completed TEXT NOT NULL,
    challenges TEXT,
    plan_tomorrow TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, report_date)
)");

// ── 2. Handle POST Requests (Teacher Actions) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'teacher') {
    if (isset($_POST['post_meeting'])) {
        $cid = intval($_POST['course_id']);
        $topic = trim($_POST['topic']);
        $link = trim($_POST['meeting_link']);
        $time = $_POST['start_time'];
        
        $s = $conn->prepare("INSERT INTO live_sessions (course_id, teacher_id, topic, meeting_link, start_time) VALUES (?, ?, ?, ?, ?)");
        $s->bind_param("iisss", $cid, $user_id, $topic, $link, $time);
        $s->execute();
        header("Location: index.php?meeting_posted=1"); exit;
    }
    
    if (isset($_POST['update_recording'])) {
        $sid = intval($_POST['session_id']);
        $rec_link = trim($_POST['recording_link']);
        
        $s = $conn->prepare("UPDATE live_sessions SET recording_link = ? WHERE id = ? AND teacher_id = ?");
        $s->bind_param("sii", $rec_link, $sid, $user_id);
        $s->execute();
        header("Location: index.php?recording_added=1"); exit;
    }
}

// ── 3. Base Queries (Admin & Staff) ──
$notice_query = "SELECT * FROM notices ORDER BY `date` DESC";
$notice_result = mysqli_query($conn, $notice_query);
$notice_cout = mysqli_num_rows($notice_result);
$notice_count = $notice_cout;

// Fetch tasks based on role (includes grouped task assignments)
if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin') {
    $task_query = "SELECT * FROM tasks WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
} else {
    $access_sql = userTaskAccessSql($user_id, '');
    $task_query = "SELECT * FROM tasks WHERE {$access_sql} AND status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
}
$task_result = mysqli_query($conn, $task_query);
$dashboard_tasks = [];
if ($task_result) {
    while ($tr = mysqli_fetch_assoc($task_result)) {
        $dashboard_tasks[] = $tr;
    }
    if (mysqli_num_rows($task_result) > 0) {
        mysqli_data_seek($task_result, 0);
    }
}
$task_count = count($dashboard_tasks);
$dashboard_assignees_map = getAssigneesMapForTasks($conn, array_column($dashboard_tasks, 'id'));

// Fetch resources
$resource_query  = "SELECT * FROM resources ORDER BY created_at DESC";
$resource_result = mysqli_query($conn, $resource_query);
$resource_count  = mysqli_num_rows($resource_result);

// Admin/PM/Manager/Sales/Staff stats
if (in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'sales', 'user', 'hrm'])) {
    $stats_filter = "1=1";
    if (!in_array($role, ['super_admin', 'superadmin', 'admin'])) {
        $stats_filter = userTaskAccessSql($user_id, '');
    }

    if ($role === 'super_admin' || $role === 'superadmin') {
        $total_users_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
        $total_users            = mysqli_fetch_assoc($total_users_result)['cnt'];
        // Recent users
        $recent_users_result = mysqli_query($conn, "SELECT * FROM users WHERE role != 'student' ORDER BY created_at DESC LIMIT 5");

        // ── Executive Business KPIs for Super Admin ──
        // A. Projects
        $exec_proj_res = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('In Progress', 'active') OR (progress > 0 AND progress < 100) THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'Completed' OR progress = 100 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN status = 'Not Started' THEN 1 ELSE 0 END) as not_started
            FROM projects
        ");
        $exec_proj = $exec_proj_res ? $exec_proj_res->fetch_assoc() : ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'on_hold' => 0, 'not_started' => 0];
        $active_projects_count = intval($exec_proj['in_progress'] ?? 0);

        // B. Clients
        $exec_clients_res = $conn->query("SELECT COUNT(*) as cnt FROM clients");
        $total_clients = $exec_clients_res ? intval($exec_clients_res->fetch_assoc()['cnt']) : 0;

        // C. Invoices & Revenue Snapshot
        $exec_inv_res = $conn->query("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(IF(status = 'paid', amount_paid, 0)) as total_paid,
                SUM(IF(status IN ('sent', 'overdue'), amount_due, 0)) as total_pending_due,
                COUNT(IF(status = 'paid', 1, NULL)) as paid_count,
                COUNT(IF(status = 'overdue', 1, NULL)) as overdue_count,
                COUNT(IF(status = 'sent', 1, NULL)) as sent_count
            FROM invoices
        ");
        $exec_inv = $exec_inv_res ? $exec_inv_res->fetch_assoc() : ['total_invoices' => 0, 'total_paid' => 0, 'total_pending_due' => 0, 'paid_count' => 0, 'overdue_count' => 0, 'sent_count' => 0];

        // D. Quotations & Pipeline
        $exec_quot_res = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(IF(status = 'accepted', 1, NULL)) as accepted_count,
                COUNT(IF(status = 'sent', 1, NULL)) as pending_count,
                SUM(IF(status = 'accepted', total_amount, 0)) as accepted_val
            FROM quotations
        ");
        $exec_quot = $exec_quot_res ? $exec_quot_res->fetch_assoc() : ['total' => 0, 'accepted_count' => 0, 'pending_count' => 0, 'accepted_val' => 0];

        // E. Daily Progress Reports & Team Attendance Today
        $today_str = date('Y-m-d');
        $exec_dpr_res = $conn->query("SELECT COUNT(DISTINCT user_id) as cnt FROM daily_progress_reports WHERE report_date = '$today_str'");
        $dpr_today_count = $exec_dpr_res ? intval($exec_dpr_res->fetch_assoc()['cnt']) : 0;
        
        $exec_team_res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE (status = 'active' OR status IS NULL) AND role NOT IN ('client', 'client_sub', 'student')");
        $active_team_count = $exec_team_res ? intval($exec_team_res->fetch_assoc()['cnt']) : 0;
        $dpr_compliance_rate = $active_team_count > 0 ? round(($dpr_today_count / $active_team_count) * 100) : 0;

        // F. Recent Active Projects for Executive View
        $recent_projects_res = $conn->query("
            SELECT p.id, p.project_name, p.status, p.progress, p.start_date, p.end_date, c.client_name
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
            ORDER BY p.id DESC LIMIT 5
        ");

        // G. Recent Invoices for Executive Financial Snapshot
        $recent_invoices_res = $conn->query("
            SELECT i.id, i.invoice_number, i.total_amount, i.amount_paid, i.amount_due, i.status, i.currency, i.due_date, c.client_name
            FROM invoices i
            LEFT JOIN clients c ON i.client_id = c.id
            ORDER BY i.created_at DESC LIMIT 5
        ");
    }

    $total_tasks_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE $stats_filter");
    $total_tasks            = mysqli_fetch_assoc($total_tasks_result)['cnt'];

    $completed_tasks_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE status = 'Completed' AND $stats_filter");
    $completed_tasks        = mysqli_fetch_assoc($completed_tasks_result)['cnt'];

    $pending_tasks_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE status NOT IN ('Completed','Cancelled') AND $stats_filter");
    $pending_tasks          = mysqli_fetch_assoc($pending_tasks_result)['cnt'];

    $overdue_tasks_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('Completed','Cancelled') AND $stats_filter");
    $overdue_tasks          = mysqli_fetch_assoc($overdue_tasks_result)['cnt'];

    $high_priority_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE priority = 'High' AND status NOT IN ('Completed','Cancelled') AND $stats_filter");
    $high_priority_tasks    = mysqli_fetch_assoc($high_priority_result)['cnt'];

    // Tasks by priority
    $priority_result = mysqli_query($conn, "SELECT priority, COUNT(*) as cnt FROM tasks WHERE status NOT IN ('Completed','Cancelled') AND $stats_filter GROUP BY priority");
    $priority_data   = [];
    while ($row = mysqli_fetch_assoc($priority_result)) {
        $priority_data[$row['priority']] = $row['cnt'];
    }

    // Personal Project & DPR KPIs for Staff / Employees
    if ($role === 'user' || $role === 'hrm') {
        $today_str = date('Y-m-d');
        $user_proj_res = $conn->query("
            SELECT COUNT(DISTINCT pt.project_id) as cnt 
            FROM project_team pt
            WHERE pt.user_id = $user_id
        ");
        $user_projects_count = $user_proj_res ? intval($user_proj_res->fetch_assoc()['cnt']) : 0;

        $user_dpr_res = $conn->query("SELECT id FROM daily_progress_reports WHERE user_id = $user_id AND report_date = '$today_str' LIMIT 1");
        $has_submitted_dpr = ($user_dpr_res && $user_dpr_res->num_rows > 0);
    }
}
elseif ($role === 'teacher') {
    // Teacher Data
    $t_courses = $conn->query("SELECT * FROM courses WHERE instructor_id = $user_id");
    $stats['my_courses'] = $t_courses->num_rows;
    $stats['total_students'] = $conn->query("SELECT COUNT(*) as c FROM course_enrollments e JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = $user_id AND e.status = 'active'")->fetch_assoc()['c'];
    $stats['pending_queries'] = $conn->query("SELECT COUNT(*) as c FROM course_queries q JOIN courses c ON q.course_id = c.id WHERE c.instructor_id = $user_id AND q.status = 'pending'")->fetch_assoc()['c'];
    
    $my_sessions = $conn->query("SELECT ls.*, c.title as course_title FROM live_sessions ls JOIN courses c ON ls.course_id = c.id WHERE ls.teacher_id = $user_id ORDER BY ls.start_time DESC LIMIT 10");
    
    $stats['total_assignments'] = $conn->query("SELECT COUNT(*) as c FROM assignments WHERE teacher_id = $user_id")->fetch_assoc()['c'] ?? 0;
    $stats['pending_docs'] = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE teacher_id = $user_id AND status = 'pending'")->fetch_assoc()['c'] ?? 0;
    
    // Recent assignments
    $recent_assignments = $conn->query("SELECT a.*, c.title as course_title, 
        (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as submission_count
        FROM assignments a JOIN courses c ON a.course_id = c.id 
        WHERE a.teacher_id = $user_id ORDER BY a.created_at DESC LIMIT 5");
}
elseif ($role === 'student') {
    // Student Data
    $my_enrollments = $conn->query("
        SELECT e.*, c.title, c.thumbnail 
        FROM course_enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.student_email = '$email'
    ");
    
    $stats['active_courses'] = 0;
    $stats['completed'] = 0;
    $course_ids = [];
    $active_courses_list = [];
    
    if ($my_enrollments) {
        while($row = $my_enrollments->fetch_assoc()) {
            if ($row['status'] === 'active' || $row['status'] === 'completed') {
                $course_ids[] = $row['course_id'];
            }
            if ($row['status'] === 'active') {
                $stats['active_courses']++;
                $active_courses_list[] = $row;
            }
            if ($row['status'] === 'completed') $stats['completed']++;
        }
    }
    
    $upcoming_meetings = [];
    if (!empty($course_ids)) {
        $ids_str = implode(',', $course_ids);
        $um_res = $conn->query("
            SELECT ls.*, c.title as course_title, u.name as teacher_name 
            FROM live_sessions ls 
            JOIN courses c ON ls.course_id = c.id 
            LEFT JOIN users u ON ls.teacher_id = u.user_id
            WHERE ls.course_id IN ($ids_str) 
            ORDER BY ls.start_time DESC LIMIT 5
        ");
        if($um_res) { while($m = $um_res->fetch_assoc()) $upcoming_meetings[] = $m; }
    }
    
    // Student additional stats
    $stats['pending_assignments'] = 0;
    $stats['pending_queries'] = $conn->query("SELECT COUNT(*) as c FROM course_queries WHERE student_id = $user_id AND status = 'pending'")->fetch_assoc()['c'] ?? 0;
    $stats['answered_queries'] = $conn->query("SELECT COUNT(*) as c FROM course_queries WHERE student_id = $user_id AND status = 'answered'")->fetch_assoc()['c'] ?? 0;
    
    $upcoming_assignments = [];
    if (!empty($course_ids)) {
        $ids_str2 = implode(',', $course_ids);
        $stats['pending_assignments'] = $conn->query("SELECT COUNT(*) as c FROM assignments WHERE course_id IN ($ids_str2) AND due_date >= CURDATE()")->fetch_assoc()['c'] ?? 0;
        $ua_res = $conn->query("
            SELECT a.*, c.title as course_title,
                (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = $user_id) as submitted
            FROM assignments a 
            JOIN courses c ON a.course_id = c.id 
            WHERE a.course_id IN ($ids_str2)
            ORDER BY a.due_date ASC LIMIT 5
        ");
        if($ua_res) { while($ua = $ua_res->fetch_assoc()) $upcoming_assignments[] = $ua; }
    }
}
?>

<style>
/* ── Overview Tab Unified Modern Design System (Clean White + Accent Green #b8f35a) ── */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 28px 48px !important;
}

/* Override legacy dashboard.css high-clutter / low-contrast rules on Overview */
.dashboard-container .tasks-card,
.dashboard-container .resources-card,
.dashboard-container .main-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 16px !important;
    padding: 22px 24px !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
}
.dashboard-container .card-header-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px !important;
    padding-bottom: 14px !important;
    border-bottom: 1px solid #f1f5f9 !important;
}
.dashboard-container .card-header-custom h3 {
    font-size: 0.98rem !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin: 0 !important;
    display: flex;
    align-items: center;
    gap: 9px;
    letter-spacing: -0.01em;
}
.dashboard-container .card-header-custom h3 i {
    font-size: 0.92rem;
}
.dashboard-container .badge-count {
    background: #ecfccb !important;
    color: #365314 !important;
    border: 1px solid #d9f99d !important;
    padding: 3px 10px !important;
    border-radius: 999px !important;
    font-size: 0.72rem !important;
    font-weight: 800 !important;
}
.dashboard-container .view-all-link {
    font-size: 0.78rem !important;
    color: #475569 !important;
    font-weight: 700 !important;
    text-decoration: none !important;
    padding: 4px 10px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
}
.dashboard-container .view-all-link:hover {
    background: #b8f35a !important;
    color: #142600 !important;
    border-color: #a3e635 !important;
    text-decoration: none !important;
}
.dashboard-container .notices-body {
    padding: 0 !important;
    height: auto !important;
    overflow: visible !important;
}
.dashboard-container .notice-item {
    display: block;
    padding: 12px 14px !important;
    margin-bottom: 8px !important;
    border-radius: 10px !important;
    background: #f8fafc !important;
    border: 1px solid #f1f5f9 !important;
    border-left: 3px solid #b8f35a !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}
.dashboard-container .notice-item:last-child { margin-bottom: 0 !important; }
.dashboard-container .notice-item:hover {
    background: #f7fee7 !important;
    border-color: #d9f99d !important;
    border-left-color: #84cc16 !important;
    transform: translateX(2px) !important;
}
.dashboard-container .notice-title {
    font-size: 0.86rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin-bottom: 2px !important;
    line-height: 1.35;
}
.dashboard-container .resources-list {
    padding: 0 !important;
    max-height: 320px;
}
.dashboard-container .resource-item {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 10px 12px !important;
    margin-bottom: 8px !important;
    background: #f8fafc !important;
    border: 1px solid #f1f5f9 !important;
    border-radius: 10px !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}
.dashboard-container .resource-item:last-child { margin-bottom: 0 !important; }
.dashboard-container .resource-item:hover {
    background: #f7fee7 !important;
    border-color: #d9f99d !important;
    transform: translateX(2px) !important;
    box-shadow: none !important;
}
.dashboard-container .resource-icon {
    width: 36px !important;
    height: 36px !important;
    margin-right: 0 !important;
    border-radius: 10px !important;
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #365314 !important;
    font-size: 0.88rem !important;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.dashboard-container .resource-icon i {
    color: #365314 !important;
}
.dashboard-container .resource-item:hover .resource-icon {
    background: #b8f35a !important;
    border-color: #a3e635 !important;
}
.dashboard-container .resource-title {
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin-bottom: 0 !important;
}
.dashboard-container .resource-link-icon {
    color: #94a3b8 !important;
    font-size: 0.85rem !important;
}

/* ── Top Welcome Bar ── */
.ov-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}
.ov-topbar-title h1 {
    font-size: 1.6rem !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    letter-spacing: -0.025em;
    margin: 0 0 4px 0 !important;
    line-height: 1.2;
}
.ov-topbar-title p {
    margin: 0 !important;
    font-size: 0.88rem !important;
    color: #64748b !important;
    font-weight: 500;
}
.ov-topbar-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.ov-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
}
.ov-date-pill i {
    color: #65a30d !important;
    font-size: 0.82rem;
}
.ov-primary-cta {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 10px;
    background: #b8f35a;
    border: 1px solid #a3e635;
    color: #142600 !important;
    font-size: 0.82rem;
    font-weight: 800;
    text-decoration: none !important;
    transition: all 0.15s ease;
    box-shadow: 0 2px 8px rgba(184, 243, 90, 0.35);
}
.ov-primary-cta:hover {
    background: #a8e846;
    transform: translateY(-1px);
    color: #142600 !important;
}
.ov-primary-cta i {
    color: #142600 !important;
    font-size: 0.8rem;
}

/* Admin stat cards — extended from .stat-card */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

/* Balanced 3-column horizontal KPI grid for Staff/User Overview */
.ov-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.ov-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    text-decoration: none !important;
    color: inherit !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 0.18s ease;
}
.ov-kpi-card:hover {
    border-color: #b8f35a;
    background: #fcfef8;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -6px rgba(15, 23, 42, 0.07);
}
.ov-kpi-card.is-accent {
    border-color: #d9f99d;
    background: linear-gradient(135deg, #ffffff 0%, #f9fef0 100%);
}
.ov-kpi-left {
    min-width: 0;
}
.ov-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.55px;
    color: #64748b;
    margin-bottom: 6px;
}
.ov-kpi-value {
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    letter-spacing: -0.025em;
    font-variant-numeric: tabular-nums;
}
.ov-kpi-sub {
    font-size: 0.74rem;
    color: #94a3b8;
    font-weight: 600;
    margin-top: 4px;
}
.ov-kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}
.ov-kpi-icon.accent { background: #ecfccb; border-color: #d9f99d; }
.ov-kpi-icon.accent i { color: #3f6212 !important; }
.ov-kpi-icon.blue   { background: #eff6ff; border-color: #dbeafe; }
.ov-kpi-icon.blue i { color: #2563eb !important; }
.ov-kpi-icon.red    { background: #fef2f2; border-color: #fee2e2; }
.ov-kpi-icon.red i  { color: #dc2626 !important; }
.ov-kpi-icon.green  { background: #ecfdf5; border-color: #d1fae5; }
.ov-kpi-icon.green i { color: #059669 !important; }
.ov-kpi-icon.purple { background: #f5f3ff; border-color: #ede9fe; }
.ov-kpi-icon.purple i { color: #7c3aed !important; }
.ov-kpi-icon.amber  { background: #fffbeb; border-color: #fef3c7; }
.ov-kpi-icon.amber i { color: #d97706 !important; }

/* Compact Quick Actions Strip */
.ov-actions-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 22px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.ov-actions-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.ov-actions-header span {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.65px;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.ov-actions-strip {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(125px, 1fr));
    gap: 10px;
}
.ov-action-tile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 13px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 11px;
    text-decoration: none !important;
    color: #1e293b !important;
    font-size: 0.81rem;
    font-weight: 700;
    transition: all 0.16s ease;
}
.ov-action-tile:hover {
    background: #f7fee7;
    border-color: #b8f35a;
    color: #142600 !important;
    transform: translateY(-1px);
}
.ov-action-ico {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    flex-shrink: 0;
    transition: all 0.16s ease;
}
.ov-action-tile:hover .ov-action-ico {
    background: #b8f35a;
    border-color: #a3e635;
}
.ov-action-tile:hover .ov-action-ico i {
    color: #142600 !important;
}

/* Asymmetric Clean Workspace Split (Tasks left, Notices/Resources right) */
.ov-workspace-grid {
    display: grid;
    grid-template-columns: 1.45fr 1fr;
    gap: 20px;
    align-items: start;
}
.ov-task-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 12px;
    border-radius: 11px;
    border: 1px solid transparent;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.15s ease;
}
.ov-task-row:last-child { border-bottom-color: transparent; }
.ov-task-row:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.ov-task-check {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    cursor: pointer;
    accent-color: #65a30d;
    flex-shrink: 0;
}

/* Interactive clickable stat cards matching modern clean aesthetics */
.stat-card { 
    position: relative; 
    background: #ffffff; 
    padding: 24px 20px 22px; 
    border-radius: 14px; 
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), 
                box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), 
                border-color 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.stat-card::before { display: none !important; }

/* Elegant Soft-Tinted Icon Badges */
.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin: 0 auto 12px auto;
    background: #f1f5f9;
    color: var(--primary, #024442) !important;
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.22s ease;
    flex-shrink: 0;
}

.stat-card.green .stat-icon,
.stat-card.emerald .stat-icon {
    background: #ecfdf5 !important;
    color: #059669 !important;
}
.stat-card.blue .stat-icon {
    background: #eff6ff !important;
    color: #2563eb !important;
}
.stat-card.red .stat-icon {
    background: #fef2f2 !important;
    color: #dc2626 !important;
}
.stat-card.orange .stat-icon {
    background: #fffbeb !important;
    color: #d97706 !important;
}
.stat-card.purple .stat-icon {
    background: #f5f3ff !important;
    color: #7c3aed !important;
}
.stat-card.teal .stat-icon {
    background: #f0fdfa !important;
    color: #0d9488 !important;
}

/* Card Interactive Links & Subtle Micro-interactions */
a.stat-card {
    text-decoration: none !important;
    color: inherit !important;
    cursor: pointer;
}
a.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.03);
    border-color: #cbd5e1;
    color: inherit !important;
}
a.stat-card:hover .stat-icon {
    transform: scale(1.08);
}
a.stat-card.green:hover, a.stat-card.emerald:hover { border-color: #a7f3d0; }
a.stat-card.blue:hover   { border-color: #bfdbfe; }
a.stat-card.red:hover    { border-color: #fecaca; }
a.stat-card.orange:hover { border-color: #fde68a; }
a.stat-card.purple:hover { border-color: #ddd6fe; }
a.stat-card.teal:hover   { border-color: #99f6e4; }
a.stat-card:active {
    transform: translateY(-1px);
}
/* Clean executive stat cards */
.section-kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 22px 0 14px 0;
}
.section-kpi-header h4 {
    margin: 0;
    font-size: 12.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-pill {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    display: inline-block;
}
.status-pill.paid, .status-pill.completed { background: #dcfce7; color: #15803d; }
.status-pill.sent, .status-pill.in-progress, .status-pill.in_progress { background: #e0f2fe; color: #0284c7; }
.status-pill.overdue, .status-pill.rejected, .status-pill.high { background: #fee2e2; color: #b91c1c; }
.status-pill.draft, .status-pill.on-hold, .status-pill.on_hold { background: #fef3c7; color: #b45309; }
.status-pill.not-started, .status-pill.not_started { background: #f1f5f9; color: #64748b; }
.mini-progress {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}
.mini-progress-fill {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width 0.4s ease;
}
.stat-value { 
    font-size: 1.95rem; 
    font-weight: 800; 
    color: #0f172a; 
    line-height: 1.15; 
    margin-bottom: 4px;
    letter-spacing: -0.025em;
    font-feature-settings: "tnum";
    font-variant-numeric: tabular-nums;
}
.stat-label { 
    font-size: 0.72rem; 
    color: #64748b; 
    font-weight: 700; 
    text-transform: uppercase; 
    letter-spacing: 0.65px; 
    line-height: 1.3;
}

/* Quick actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
    gap: 12px;
}
.quick-action-btn {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 16px 12px; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 12px; text-decoration: none; color: #1e293b;
    font-weight: 700; font-size: 12.5px; text-align: center; transition: all 0.18s ease;
}
.quick-action-btn:hover { background: #f7fee7; border-color: #b8f35a; color: #142600; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(15,23,42,0.05); }
.quick-action-btn .qa-icon { font-size: 1.35rem; color: #365314; }

/* Two-column admin panels */
.admin-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
@media (max-width: 900px) { .admin-panels { grid-template-columns: 1fr; } }

/* Role badges */
.role-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.role-badge.admin { background: #fff4e5; color: #e17055; }
.role-badge.user  { background: #e5f5ff; color: #0984e3; }

/* Priority bars */
.priority-bars { display: flex; flex-direction: column; gap: 18px; }
.pbar-row { display: flex; align-items: center; gap: 12px; }
.pbar-label { width: 75px; font-size: 13px; font-weight: 600; color: #555; }
.pbar-track { flex: 1; height: 10px; background: #f0f0f0; border-radius: 99px; overflow: hidden; }
.pbar-fill { height: 100%; border-radius: 99px; transition: width 0.6s ease; }
.pbar-fill.high   { background: linear-gradient(135deg, #d63031, #ff7675); }
.pbar-fill.medium { background: linear-gradient(135deg, #e17055, #fdcb6e); }
.pbar-fill.low    { background: linear-gradient(135deg, var(--accent), #87bd0aff); }
.pbar-count { font-size: 13px; font-weight: 700; color: #333; width: 24px; text-align: right; }

/* Assignee chip */
.assignee-chip { background: #fff4e5; color: #e17055; border-radius: 12px; padding: 3px 10px; font-size: 11px; font-weight: 600; }

/* View all link */
.view-all-link { font-size: 13px; color: var(--accent); text-decoration: none; font-weight: 600; }
.view-all-link:hover { text-decoration: underline; }

/* ── Teacher & Student Shared Styles ── */
.grid-2-col { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; margin-bottom:30px;}
@media (max-width: 991px) { .grid-2-col { grid-template-columns: 1fr; } }
.d-card { background: #fff; border-radius: 16px; border: 1px solid #ebebeb; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); height: 100%; }
.d-card-title { font-size: 1.1rem; font-weight: 800; color: #1a1a1a; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom:2px solid #f0f0f0; padding-bottom:15px;}

.meeting-item { padding: 18px; border: 1px solid #eee; border-radius: 12px; margin-bottom: 15px; transition: 0.2s; background:#fff;}
.meeting-item:hover { border-color: var(--primary); box-shadow: 0 4px 15px rgba(2,68,66,0.05); }
.m-date { font-size: .75rem; color: #ef4444; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.m-title { font-size: 1.05rem; font-weight: 800; color: #333; margin-bottom: 4px; }
.m-course { font-size: .85rem; color: #666; margin-bottom: 12px; }
.m-actions { display: flex; gap: 10px; flex-wrap:wrap;}
.btn-meet { background: #e0f2fe; color: #0284c7; padding: 8px 16px; border-radius: 50px; font-size: .8rem; font-weight: 700; text-decoration: none; transition: 0.2s; display:inline-flex; align-items:center; gap:6px;}
.btn-meet:hover { background: #0284c7; color: #fff; }
.btn-rec { background: #f3e8ff; color: #7c3aed; padding: 8px 16px; border-radius: 50px; font-size: .8rem; font-weight: 700; text-decoration: none; transition: 0.2s; display:inline-flex; align-items:center; gap:6px;}
.btn-rec:hover { background: #7c3aed; color: #fff; }

.sc-card { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: 12px; background: #f9fbfc; margin-bottom: 15px; border: 1px solid #eef2eb; transition:0.2s;}
.sc-card:hover { border-color:var(--primary); background:#fff; box-shadow: 0 4px 12px rgba(0,0,0,0.04);}
.sc-img { width: 65px; height: 65px; border-radius: 10px; object-fit: cover; background: var(--primary); flex-shrink:0;}
.sc-info h4 { margin: 0 0 6px; font-size: .95rem; font-weight: 800; color: #333; line-height:1.3;}
.sc-info span { font-size: .7rem; background: #d7f8b8; color: #2b7a2b; padding: 3px 10px; border-radius: 20px; font-weight: 700; text-transform:uppercase; letter-spacing:0.5px;}

.form-label-custom { display:block; font-size:.85rem; font-weight:700; color:#555; margin-bottom:6px; }
.form-input-custom { width:100%; padding:12px 14px; border:1.5px solid #e0e0e0; border-radius:10px; margin-bottom:18px; font-size:.9rem; background:#f9fbfc; transition:all 0.2s; outline:none;}
.form-input-custom:focus { border-color:var(--primary); background:#fff; box-shadow:0 0 0 3px rgba(2,68,66,0.1);}

/* ── Full-Width Team Member of the Month Card (Minimal White + Accent Green #b8f35a + Pop Animations) ── */
@keyframes motmCardPop {
    0%   { opacity: 0; transform: translateY(10px) scale(0.985); }
    65%  { opacity: 1; transform: translateY(-2px) scale(1.005); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes motmRingPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(184, 243, 90, 0.55); }
    60%      { box-shadow: 0 0 0 9px rgba(184, 243, 90, 0); }
}
@keyframes motmFloatA {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50%      { transform: translateY(-7px) rotate(12deg); }
}
@keyframes motmFloatB {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50%      { transform: translateY(6px) rotate(-14deg); }
}
@keyframes motmCrownWiggle {
    0%, 82%, 100% { transform: rotate(0deg) scale(1); }
    86%           { transform: rotate(-12deg) scale(1.12); }
    91%           { transform: rotate(10deg) scale(1.12); }
    95%           { transform: rotate(-6deg) scale(1.05); }
}
@keyframes motmPillShimmer {
    0%   { left: -75%; }
    40%  { left: 135%; }
    100% { left: 135%; }
}
@keyframes motmCheerFloat {
    0%   { opacity: 0; transform: translate(-50%, 6px) scale(0.88); }
    18%  { opacity: 1; transform: translate(-50%, -6px) scale(1.03); }
    80%  { opacity: 1; transform: translate(-50%, -18px) scale(1); }
    100% { opacity: 0; transform: translate(-50%, -28px) scale(0.96); }
}
@keyframes motmBtnPop {
    0%   { transform: scale(1); }
    35%  { transform: scale(0.92); }
    70%  { transform: scale(1.08); }
    100% { transform: scale(1); }
}

.motm-hero-card {
    width: 100%;
    position: relative;
    overflow: hidden;
    border-radius: 16px;
    padding: 20px 24px;
    margin: 0 0 22px 0;
    background: #ffffff;
    border: 1px solid #d9f2a6;
    border-left: 4px solid #b8f35a;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.045);
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    animation: motmCardPop 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}
.motm-hero-card::after {
    content: "";
    position: absolute;
    right: -40px;
    top: -60px;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(184, 243, 90, 0.26) 0%, transparent 70%);
    pointer-events: none;
}
.motm-sparkles {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: 0;
}
.motm-sp {
    position: absolute;
    opacity: 0.72;
}
.motm-sp.s1 { right: 24%; top: 14px; color: #84cc16; font-size: 11px; animation: motmFloatA 3.4s ease-in-out infinite; }
.motm-sp.s2 { right: 16%; bottom: 14px; color: #f59e0b; font-size: 10px; animation: motmFloatB 3.9s ease-in-out infinite; }
.motm-sp.s3 { right: 31%; bottom: 18px; width: 7px; height: 7px; border-radius: 50%; background: #b8f35a; animation: motmFloatA 4.2s ease-in-out infinite; }
.motm-sp.s4 { right: 9%; top: 12px; width: 6px; height: 12px; border-radius: 3px; background: #fbbf24; transform: rotate(24deg); animation: motmFloatB 3.6s ease-in-out infinite; }
.motm-sp.s5 { left: 104px; top: 10px; width: 6px; height: 6px; border-radius: 2px; background: #b8f35a; transform: rotate(45deg); animation: motmFloatA 4.5s ease-in-out infinite; }
.motm-sp.s6 { right: 28%; top: 48%; width: 5px; height: 5px; border-radius: 50%; background: #38bdf8; animation: motmFloatB 3.2s ease-in-out infinite; }
.motm-sp i  { color: inherit !important; }

.motm-hero-main {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 1;
    min-width: 0;
    position: relative;
    z-index: 1;
}
.motm-avatar-frame {
    position: relative;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(135deg, #b8f35a 0%, #84cc16 100%);
    flex-shrink: 0;
    animation: motmRingPulse 2.8s ease-out infinite;
}
.motm-avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    background: #f7fee7;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
}
.motm-avatar-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.motm-avatar-initials {
    font-size: 1.45rem;
    font-weight: 800;
    color: #365314;
    letter-spacing: 0.5px;
}
.motm-avatar-crown {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #b8f35a;
    border: 2px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: motmCrownWiggle 4.2s ease-in-out infinite;
}
.motm-avatar-crown i {
    color: #142600 !important;
    font-size: 0.65rem;
}
.motm-hero-body {
    flex: 1;
    min-width: 0;
}
.motm-badges-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 5px;
}
.motm-pill-primary {
    position: relative;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #b8f35a;
    color: #142600;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 3px 10px;
    border-radius: 999px;
}
.motm-pill-primary::after {
    content: "";
    position: absolute;
    top: 0;
    left: -75%;
    width: 45%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
    transform: skewX(-22deg);
    animation: motmPillShimmer 4.5s ease-in-out infinite;
    pointer-events: none;
}
.motm-pill-primary i {
    color: #142600 !important;
    font-size: 0.66rem;
}
.motm-meta-sep {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
}
.motm-name-row {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 10px;
}
.motm-member-name {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.025em;
    line-height: 1.2;
}
.motm-member-meta {
    margin: 0;
    font-size: 0.84rem;
    color: #64748b;
    font-weight: 600;
}
.motm-quote-line {
    margin-top: 6px;
    font-size: 0.85rem;
    line-height: 1.45;
    color: #475569;
}
.motm-hero-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
.motm-celebrate-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 15px;
    border-radius: 11px;
    background: #b8f35a;
    border: 1px solid #a3e635;
    color: #142600;
    font-size: 0.8rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 3px 12px rgba(184, 243, 90, 0.42);
    transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
    font-family: inherit;
}
.motm-celebrate-btn:hover {
    background: #a8e846;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(184, 243, 90, 0.55);
}
.motm-celebrate-btn.is-popping {
    animation: motmBtnPop 0.36s cubic-bezier(0.22, 1, 0.36, 1);
}
.motm-celebrate-btn i {
    color: #142600 !important;
    font-size: 0.84rem;
}
.motm-cheer-pill {
    position: fixed;
    z-index: 10050;
    pointer-events: none;
    background: #0f172a;
    color: #b8f35a;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 7px 14px;
    border-radius: 999px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.22);
    white-space: nowrap;
    animation: motmCheerFloat 1.75s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
.motm-trophy-badge {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 14px;
    border-radius: 12px;
    background: #f7fee7;
    border: 1px solid #d9f99d;
}
.motm-trophy-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #b8f35a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.motm-trophy-icon i {
    font-size: 0.9rem;
    color: #142600 !important;
}
.motm-manage-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    text-decoration: none;
    padding: 9px 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
}
.motm-manage-link:hover {
    background: #f7fee7;
    color: #142600;
    border-color: #b8f35a;
}
.motm-manage-link i {
    color: inherit !important;
    font-size: 0.72rem;
}
#motmConfettiCanvas {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 10040;
}
@media (prefers-reduced-motion: reduce) {
    .motm-hero-card, .motm-avatar-frame, .motm-avatar-crown, .motm-sp, .motm-pill-primary::after {
        animation: none !important;
    }
}

@media (max-width: 992px) {
    .ov-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ov-workspace-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .dashboard-container { padding: 16px 14px 36px !important; }
    .admin-stats-grid   { grid-template-columns: repeat(2, 1fr); }
    .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    .ov-actions-strip   { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .motm-hero-card {
        flex-direction: column;
        align-items: flex-start;
        padding: 18px;
    }
    .motm-hero-main {
        align-items: flex-start;
        gap: 14px;
    }
    .motm-hero-right {
        width: 100%;
        justify-content: space-between;
    }
    .motm-member-name { font-size: 1.2rem; }
}
</style>

<div class="dashboard-container">

    <div class="ov-topbar">
        <div class="ov-topbar-title">
            <h1>Welcome back, <?= htmlspecialchars($name) ?></h1>
            <p><?php
                if ($role === 'super_admin' || $role === 'superadmin') echo "Executive dashboard &amp; agency operations overview.";
                elseif ($role === 'admin') echo "Administrative and operational overview for today.";
                elseif ($role === 'client' || $role === 'client_sub') echo "Welcome to your client workspace — track your project deliverables and invoices.";
                elseif ($role === 'teacher') echo "Manage your assigned courses, students, and live teaching sessions.";
                elseif ($role === 'student') echo "Continue learning and track your course progress.";
                else echo "Your daily Graphicafix Family workspace — tasks, attendance &amp; updates.";
            ?></p>
        </div>
        <div class="ov-topbar-meta">
            <span class="ov-date-pill">
                <i class="far fa-calendar-alt"></i> <?= date('l, M j, Y') ?>
            </span>
            <?php if (in_array($role, ['user', 'hrm'])): ?>
            <a href="dpr.php" class="ov-primary-cta">
                <i class="fas fa-plus-circle"></i> <?= !empty($has_submitted_dpr) ? 'View Today\'s DPR' : 'Submit DPR' ?>
            </a>
            <?php elseif (in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager'])): ?>
            <a href="tasks.php" class="ov-primary-cta">
                <i class="fas fa-columns"></i> Task Board
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($member_of_month)):
        $motm_disp_name  = trim($member_of_month['display_name'] ?? $member_of_month['member_name'] ?? '');
        $motm_disp_role  = trim($member_of_month['display_role'] ?? $member_of_month['member_role'] ?? '');
        $motm_disp_dept  = trim($member_of_month['display_department'] ?? $member_of_month['member_department'] ?? '');
        $motm_disp_photo = !empty($member_of_month['display_photo']) ? BASE_URL . ltrim($member_of_month['display_photo'], '/') : '';
        $motm_disp_email = strtolower(trim($member_of_month['display_email'] ?? $member_of_month['member_email'] ?? ''));
        $motm_month_lbl  = $member_of_month['month_display'] ?? date('F Y');
        $motm_badge_lbl  = trim($member_of_month['badge_title'] ?? '') ?: 'Team Member of the Month';
        $motm_note_txt   = trim($member_of_month['appreciation_note'] ?? '');
        if ($motm_note_txt === '') {
            $motm_note_txt = 'Celebrating outstanding dedication, creativity, and the team spirit that makes the Graphicafix Family shine.';
        }
        $motm_parts = preg_split('/\s+/', $motm_disp_name);
        $motm_initials = strtoupper(substr($motm_parts[0] ?? 'G', 0, 1) . (isset($motm_parts[1]) ? substr($motm_parts[1], 0, 1) : ''));
        $is_current_user_honoree = (
            ($motm_disp_email !== '' && isset($email) && strtolower(trim($email)) === $motm_disp_email)
            || ($motm_disp_name !== '' && strtolower(trim($name ?? '')) === strtolower($motm_disp_name))
        );
        $can_manage_spotlight = in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'hrm', 'pm']);
        $meta_bits = array_filter([$motm_disp_role, $motm_disp_dept]);
    ?>
    <!-- Full-Width Graphicafix Family Spotlight Card -->
    <div class="motm-hero-card" id="motmSpotlightCard" data-honoree="<?= htmlspecialchars($motm_disp_name) ?>" aria-label="Team Member of the Month">
        <!-- Floating ambient pop accents -->
        <div class="motm-sparkles" aria-hidden="true">
            <span class="motm-sp s1"><i class="fas fa-star"></i></span>
            <span class="motm-sp s2"><i class="fas fa-star"></i></span>
            <span class="motm-sp s3"></span>
            <span class="motm-sp s4"></span>
            <span class="motm-sp s5"></span>
            <span class="motm-sp s6"></span>
        </div>

        <div class="motm-hero-main">
            <div class="motm-avatar-frame">
                <div class="motm-avatar-inner">
                    <?php if (!empty($motm_disp_photo)): ?>
                        <img src="<?= htmlspecialchars($motm_disp_photo) ?>" alt="<?= htmlspecialchars($motm_disp_name) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span class="motm-avatar-initials" style="display:none;"><?= htmlspecialchars($motm_initials) ?></span>
                    <?php else: ?>
                        <span class="motm-avatar-initials"><?= htmlspecialchars($motm_initials) ?></span>
                    <?php endif; ?>
                </div>
                <span class="motm-avatar-crown" title="Team Member of the Month">
                    <i class="fas fa-trophy"></i>
                </span>
            </div>

            <div class="motm-hero-body">
                <div class="motm-badges-row">
                    <span class="motm-pill-primary">
                        <i class="fas fa-star"></i> <?= htmlspecialchars($motm_badge_lbl) ?>
                    </span>
                    <span class="motm-meta-sep">
                        <?= htmlspecialchars($motm_month_lbl) ?> &middot; Graphicafix Family
                    </span>
                    <?php if ($is_current_user_honoree): ?>
                    <span class="motm-pill-primary" style="background:#ecfccb; border:1px solid #bef264;">
                        <i class="fas fa-award"></i> That&rsquo;s You!
                    </span>
                    <?php endif; ?>
                </div>

                <div class="motm-name-row">
                    <h2 class="motm-member-name"><?= htmlspecialchars($motm_disp_name) ?></h2>
                    <?php if (!empty($meta_bits)): ?>
                    <span class="motm-member-meta"><?= htmlspecialchars(implode(' · ', $meta_bits)) ?></span>
                    <?php endif; ?>
                </div>

                <div class="motm-quote-line">
                    &ldquo;<?= htmlspecialchars($motm_note_txt) ?>&rdquo;
                </div>
            </div>
        </div>

        <div class="motm-hero-right">
            <button type="button" class="motm-celebrate-btn" onclick="triggerMotmCelebrate(this)" title="Pop confetti to celebrate <?= htmlspecialchars($motm_disp_name) ?>!">
                <i class="fas fa-trophy"></i>
                <span>Celebrate</span>
            </button>
            <?php if ($can_manage_spotlight): ?>
            <a href="manage_team.php" class="motm-manage-link" title="Update Team Member of the Month">
                <i class="fas fa-sliders-h"></i> Manage
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'pm' || $role === 'manager' || $role === 'sales'): ?>

    <?php if ($role === 'super_admin' || $role === 'superadmin'): ?>
    
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- SUPER ADMIN EXECUTIVE COMMAND CENTER                                      -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->

    <!-- 1. Executive Business & Financial Overview -->
    <div class="section-kpi-header">
        <h4><i class="fas fa-chart-line" style="color:var(--primary);"></i> Business &amp; Financial Overview</h4>
    </div>

    <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); margin-bottom: 24px;">
        <!-- Active Projects -->
        <a href="manage_projects.php" class="stat-card green" title="View active projects">
            <div class="stat-icon" style="color:#00b894;"><i class="fas fa-project-diagram"></i></div>
            <div class="stat-value"><?= number_format($active_projects_count) ?></div>
            <div class="stat-label">Active Projects</div>
        </a>

        <!-- Total Clients -->
        <a href="manage_clients.php" class="stat-card blue" title="View client directory">
            <div class="stat-icon" style="color:#0984e3;"><i class="fas fa-handshake"></i></div>
            <div class="stat-value"><?= number_format($total_clients) ?></div>
            <div class="stat-label">Client Accounts</div>
        </a>

        <!-- Revenue Collected -->
        <a href="manage_invoices.php?filter=paid" class="stat-card emerald" title="View paid billing & invoices">
            <div class="stat-icon" style="color:#10b981;"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-value" style="font-size:1.75rem;">PKR <?= number_format($exec_inv['total_paid'] ?? 0) ?></div>
            <div class="stat-label">Revenue Collected</div>
        </a>

        <!-- Receivables & Pending Due -->
        <a href="manage_invoices.php?filter=sent" class="stat-card <?= (intval($exec_inv['overdue_count'] ?? 0) > 0) ? 'red' : 'orange' ?>" title="View outstanding due">
            <div class="stat-icon" style="color:<?= (intval($exec_inv['overdue_count'] ?? 0) > 0) ? '#ef4444' : '#f59e0b' ?>;"><i class="fas fa-receipt"></i></div>
            <div class="stat-value" style="font-size:1.75rem;">PKR <?= number_format($exec_inv['total_pending_due'] ?? 0) ?></div>
            <div class="stat-label">Outstanding Due</div>
        </a>

        <!-- Proposals & Quotations Pipeline -->
        <a href="manage_invoices.php?tab=quotations" class="stat-card purple" title="View quotations & proposals">
            <div class="stat-icon" style="color:#8b5cf6;"><i class="fas fa-file-contract"></i></div>
            <div class="stat-value"><?= number_format($exec_quot['total']) ?></div>
            <div class="stat-label">Quotations</div>
        </a>

        <!-- Today's DPR Compliance -->
        <a href="dpr.php" class="stat-card orange" title="View team DPR logs">
            <div class="stat-icon" style="color:#e17055;"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value"><?= $dpr_today_count ?> <span style="font-size:1.1rem; color:#94a3b8; font-weight:600;">/ <?= $active_team_count ?></span></div>
            <div class="stat-label">Today's DPR Logs</div>
        </a>
    </div>

    <!-- 2. Operations & Execution Velocity KPIs -->
    <div class="section-kpi-header">
        <h4><i class="fas fa-tasks" style="color:var(--primary);"></i> Tasks &amp; Operations</h4>
        <a href="tasks.php" class="view-all-link">Full Task Board &rarr;</a>
    </div>

    <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); margin-bottom: 26px;">
        <a href="tasks.php" class="stat-card" title="View all tasks">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-value"><?= number_format($total_tasks) ?></div>
            <div class="stat-label">Total Tasks</div>
        </a>
        <a href="tasks.php" class="stat-card orange" title="View pending tasks">
            <div class="stat-icon" style="color:#e17055;"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-value"><?= number_format($pending_tasks) ?></div>
            <div class="stat-label">Pending / Active</div>
        </a>
        <a href="tasks.php" class="stat-card blue" title="View high priority tasks">
            <div class="stat-icon" style="color:#0984e3;"><i class="fas fa-fire"></i></div>
            <div class="stat-value"><?= number_format($high_priority_tasks) ?></div>
            <div class="stat-label">High Priority</div>
        </a>
        <a href="tasks.php" class="stat-card red" title="View overdue tasks">
            <div class="stat-icon" style="color:#d63031;"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value"><?= number_format($overdue_tasks) ?></div>
            <div class="stat-label">Overdue Tasks</div>
        </a>
        <a href="tasks.php" class="stat-card green" title="View completed tasks">
            <div class="stat-icon" style="color:#00b894;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= number_format($completed_tasks) ?></div>
            <div class="stat-label">Completed</div>
        </a>
    </div>

    <!-- 3. Super Admin Quick Actions -->
    <div class="tasks-card" style="margin-bottom:30px;">
        <div class="card-header-custom">
            <h3><i class="fas fa-bolt" style="color:#f59e0b;"></i> Quick Actions</h3>
        </div>
        <div class="quick-actions-grid">
            <a href="create_quotation.php" class="quick-action-btn"><span class="qa-icon" style="color:#8b5cf6;"><i class="fas fa-file-contract"></i></span> New Quotation</a>
            <a href="create_invoice.php"   class="quick-action-btn"><span class="qa-icon" style="color:#10b981;"><i class="fas fa-file-invoice-dollar"></i></span> New Invoice</a>
            <a href="project_add.php"      class="quick-action-btn"><span class="qa-icon" style="color:#0284c7;"><i class="fas fa-folder-plus"></i></span> New Project</a>
            <a href="manage_users.php?add_user=1" class="quick-action-btn"><span class="qa-icon" style="color:#f59e0b;"><i class="fas fa-user-plus"></i></span> Add User</a>
            <a href="create_task.php"      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-edit"></i></span> New Task</a>
            <a href="manage_clients.php"   class="quick-action-btn"><span class="qa-icon" style="color:#0984e3;"><i class="fas fa-handshake"></i></span> Clients</a>
            <a href="dpr.php"              class="quick-action-btn"><span class="qa-icon" style="color:#e17055;"><i class="fas fa-calendar-check"></i></span> DPR Desk</a>
            <a href="attendance.php"       class="quick-action-btn"><span class="qa-icon" style="color:#0f766e;"><i class="fas fa-clock"></i></span> Attendance</a>
            <a href="tasks.php"            class="quick-action-btn"><span class="qa-icon"><i class="fas fa-clipboard"></i></span> Task Board</a>
            <a href="price_calculator.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-calculator"></i></span> Price Calc</a>
            <a href="manage_notices.php?add_notice=1" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-bullhorn"></i></span> Post Notice</a>
            <a href="profile.php"          class="quick-action-btn"><span class="qa-icon">⚙️</span> Settings</a>
        </div>
    </div>

    <!-- 4. Executive Row 1: Active Projects Pulse & Recent Invoices -->
    <div class="admin-panels">
        <!-- Active Projects Pulse -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-project-diagram" style="color:var(--primary);"></i> Active Projects Pulse</h3>
                <a href="manage_projects.php" class="view-all-link">Manage Projects &rarr;</a>
            </div>
            <?php if ($recent_projects_res && $recent_projects_res->num_rows > 0): ?>
            <div class="main-table">
                <div class="table-head" style="grid-template-columns: 2.2fr 1.3fr 1fr 0.6fr;">
                    <span>Project &amp; Client</span>
                    <span>Progress</span>
                    <span>Status</span>
                    <span>Action</span>
                </div>
                <?php while($p = $recent_projects_res->fetch_assoc()): 
                    $pProg = intval($p['progress'] ?? 0);
                    $pStatus = strtolower(str_replace(' ', '-', $p['status'] ?? 'not-started'));
                ?>
                <div class="table-row" style="grid-template-columns: 2.2fr 1.3fr 1fr 0.6fr; align-items:center;">
                    <div style="overflow:hidden;">
                        <a href="project_details.php?id=<?= $p['id'] ?>" style="font-size:13.5px; font-weight:700; color:#0f172a; text-decoration:none; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($p['project_name']) ?>
                        </a>
                        <small style="font-size:12px; color:#64748b;"><?= htmlspecialchars($p['client_name'] ?: 'Internal / Direct') ?></small>
                    </div>
                    <div style="padding-right:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; color:#475569;">
                            <span><?= $pProg ?>%</span>
                        </div>
                        <div class="mini-progress">
                            <div class="mini-progress-fill" style="width:<?= $pProg ?>%;"></div>
                        </div>
                    </div>
                    <span>
                        <span class="status-pill <?= $pStatus ?>"><?= htmlspecialchars($p['status'] ?? 'Pending') ?></span>
                    </span>
                    <span>
                        <a href="project_details.php?id=<?= $p['id'] ?>" class="btn-meet" style="padding:4px 10px; font-size:11.5px; border-radius:6px;" title="Open Project Details">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center; padding:35px 20px; color:#94a3b8;">
                <i class="fas fa-folder-open" style="font-size:2rem; margin-bottom:8px; display:block;"></i>
                No projects found. <a href="project_add.php" style="color:var(--primary); font-weight:600;">Create your first project</a>.
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Billing & Invoices Pulse -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-receipt" style="color:#10b981;"></i> Financial &amp; Billing Pulse</h3>
                <a href="manage_invoices.php" class="view-all-link">Billing Desk &rarr;</a>
            </div>
            <?php if ($recent_invoices_res && $recent_invoices_res->num_rows > 0): ?>
            <div class="main-table">
                <div class="table-head" style="grid-template-columns: 1.6fr 1.6fr 1fr 0.6fr;">
                    <span>Invoice #</span>
                    <span>Client / Total</span>
                    <span>Status</span>
                    <span>Action</span>
                </div>
                <?php while($inv = $recent_invoices_res->fetch_assoc()): 
                    $invStatus = strtolower($inv['status'] ?? 'draft');
                    $curr = htmlspecialchars($inv['currency'] ?? 'PKR');
                ?>
                <div class="table-row" style="grid-template-columns: 1.6fr 1.6fr 1fr 0.6fr; align-items:center;">
                    <div>
                        <a href="invoice_details.php?id=<?= $inv['id'] ?>" style="font-size:13.5px; font-weight:700; color:var(--primary); text-decoration:none;">
                            <?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                        <div style="font-size:11px; color:#94a3b8;">Due <?= date('M d, Y', strtotime($inv['due_date'])) ?></div>
                    </div>
                    <div>
                        <strong style="font-size:13px; color:#0f172a;"><?= $curr ?> <?= number_format($inv['total_amount'], 0) ?></strong>
                        <div style="font-size:11.5px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($inv['client_name'] ?: 'Direct Client') ?></div>
                    </div>
                    <span>
                        <span class="status-pill <?= $invStatus ?>"><?= ucfirst($invStatus) ?></span>
                    </span>
                    <span>
                        <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="btn-meet" style="padding:4px 10px; font-size:11.5px; border-radius:6px;" title="View Invoice">
                            <i class="fas fa-eye"></i>
                        </a>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center; padding:35px 20px; color:#94a3b8;">
                <i class="fas fa-file-invoice" style="font-size:2rem; margin-bottom:8px; display:block;"></i>
                No invoices recorded yet. <a href="create_invoice.php" style="color:var(--primary); font-weight:600;">Create an invoice</a>.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Executive Row 2: Recent Users & Tasks by Priority -->
    <div class="admin-panels">
        <!-- Recent Users Card -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-users" style="color:var(--primary);"></i> Recent Users &amp; Role Access</h3>
                <a href="manage_users.php" class="view-all-link">Directory &rarr;</a>
            </div>
            <div class="main-table">
                <div class="table-head" style="grid-template-columns:1.5fr 2fr 0.8fr;">
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                </div>
                <?php while($u = mysqli_fetch_assoc($recent_users_result)): ?>
                <div class="table-row" style="grid-template-columns:1.5fr 2fr 0.8fr; align-items:center;">
                    <div class="client">
                        <div class="avatar <?= in_array($u['role'],['pink','blue','green','orange','purple']) ? $u['role'] : 'blue' ?>"
                             style="width:32px;height:32px;font-size:13px;">
                            <?= strtoupper(substr($u['name'],0,1)) ?>
                        </div>
                        <strong style="font-size:13.5px;"><?= htmlspecialchars($u['name']) ?></strong>
                    </div>
                    <span data-label="Email" style="font-size:13px;color:#666;"><?= htmlspecialchars($u['email']) ?></span>
                    <span data-label="Role"><span class="role-badge <?= strtolower($u['role']) ?>"><?= ucfirst($u['role']) ?></span></span>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Priority Breakdown & Notices -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-chart-bar" style="color:#0984e3;"></i> Tasks by Priority</h3>
                <a href="tasks.php" class="view-all-link">Task Board &rarr;</a>
            </div>
            <?php
                $maxP   = max(array_values($priority_data) ?: [1]);
                $high   = $priority_data['High']   ?? 0;
                $medium = $priority_data['Medium']  ?? 0;
                $low    = $priority_data['Low']     ?? 0;
            ?>
            <div class="priority-bars" style="margin-bottom:25px;">
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-danger"></i> High</span>
                    <div class="pbar-track"><div class="pbar-fill high"   style="width:<?= $maxP ? round($high/$maxP*100)   : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $high ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-warning"></i> Medium</span>
                    <div class="pbar-track"><div class="pbar-fill medium" style="width:<?= $maxP ? round($medium/$maxP*100) : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $medium ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-success"></i> Low</span>
                    <div class="pbar-track"><div class="pbar-fill low"    style="width:<?= $maxP ? round($low/$maxP*100)    : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $low ?></span>
                </div>
            </div>

            <div class="card-header-custom" style="margin-top:10px;">
                <h3><i class="fas fa-bullhorn" style="color:#e17055;"></i> Latest Notices</h3>
                <a href="manage_notices.php" class="view-all-link">Manage &rarr;</a>
            </div>
            <?php
                mysqli_data_seek($notice_result, 0);
                $nc = 0;
                while($nd = mysqli_fetch_assoc($notice_result)):
                    if ($nc >= 3) break; $nc++;
            ?>
            <a href="notice_details.php?id=<?= $nd['notice_id'] ?>" class="notice-item" style="display:block; text-decoration:none;">
                <div class="notice-title"><?= htmlspecialchars($nd['notice_title']) ?></div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

    <?php else: // Admin, PM, Manager, Sales ?>

    <!-- Standard Staff & Ops Overview -->
    <div class="admin-stats-grid">
        <a href="tasks.php" class="stat-card" title="View all tasks">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-value"><?= $total_tasks ?></div>
            <div class="stat-label">Total Tasks</div>
        </a>
        <a href="tasks.php" class="stat-card green" title="View completed tasks">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $completed_tasks ?></div>
            <div class="stat-label">Completed</div>
        </a>
        <a href="tasks.php" class="stat-card orange" title="View pending tasks">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-value"><?= $pending_tasks ?></div>
            <div class="stat-label">Pending Tasks</div>
        </a>
        <a href="tasks.php" class="stat-card red" title="View overdue tasks">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value"><?= $overdue_tasks ?></div>
            <div class="stat-label">Overdue</div>
        </a>
        <a href="tasks.php" class="stat-card blue" title="View high priority tasks">
            <div class="stat-icon"><i class="fas fa-fire"></i></div>
            <div class="stat-value"><?= $high_priority_tasks ?></div>
            <div class="stat-label">High Priority</div>
        </a>
    </div>

    <div class="tasks-card" style="margin-bottom:30px;">
        <div class="card-header-custom"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
        <div class="quick-actions-grid">
            <?php if (function_exists('canUserCreateTask') && canUserCreateTask($role)): ?>
            <a href="create_task.php"                      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-edit"></i></span> New Task</a>
            <?php endif; ?>
            <a href="tasks.php"                            class="quick-action-btn"><span class="qa-icon"><i class="fas fa-clipboard"></i></span> All Tasks</a>
            <a href="price_calculator.php"                 class="quick-action-btn"><span class="qa-icon"><i class="fas fa-calculator"></i></span> Price Calc</a>
            <?php if ($role === 'admin' || $role === 'manager'): ?>
            <a href="manage_notices.php?add_notice=1"      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-bullhorn"></i></span> Post Notice</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
            <a href="manage_resources.php?add_resource=1"  class="quick-action-btn"><span class="qa-icon"><i class="fas fa-book"></i></span> Add Resource</a>
            <a href="correspondence.php"                   class="quick-action-btn"><span class="qa-icon"><i class="fas fa-envelope"></i></span> Letters / Docs</a>
            <a href="attendance.php"                       class="quick-action-btn"><span class="qa-icon"><i class="fas fa-clock"></i></span> Attendance</a>
            <?php endif; ?>
            <a href="profile.php"                          class="quick-action-btn"><span class="qa-icon"><i class="fas fa-cog"></i></span> Settings</a>
        </div>
    </div>

    <div class="admin-panels">
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-chart-bar"></i> Tasks by Priority</h3>
                <a href="tasks.php" class="view-all-link">View All →</a>
            </div>
            <?php
                $maxP   = max(array_values($priority_data) ?: [1]);
                $high   = $priority_data['High']   ?? 0;
                $medium = $priority_data['Medium']  ?? 0;
                $low    = $priority_data['Low']     ?? 0;
            ?>
            <div class="priority-bars" style="margin-bottom:25px;">
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-danger"></i> High</span>
                    <div class="pbar-track"><div class="pbar-fill high"   style="width:<?= $maxP ? round($high/$maxP*100)   : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $high ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-warning"></i> Medium</span>
                    <div class="pbar-track"><div class="pbar-fill medium" style="width:<?= $maxP ? round($medium/$maxP*100) : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $medium ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label"><i class="fas fa-circle text-success"></i> Low</span>
                    <div class="pbar-track"><div class="pbar-fill low"    style="width:<?= $maxP ? round($low/$maxP*100)    : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $low ?></span>
                </div>
            </div>

            <div class="card-header-custom" style="margin-top:10px;">
                <h3><i class="fas fa-bullhorn"></i> Latest Notices</h3>
                <a href="manage_notices.php" class="view-all-link">Manage →</a>
            </div>
            <?php
                mysqli_data_seek($notice_result, 0);
                $nc = 0;
                while($nd = mysqli_fetch_assoc($notice_result)):
                    if ($nc >= 3) break; $nc++;
            ?>
            <a href="notice_details.php?id=<?= $nd['notice_id'] ?>" class="notice-item" style="display:block; text-decoration:none;">
                <div class="notice-title"><?= htmlspecialchars($nd['notice_title']) ?></div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-card" style="margin-bottom:30px;">
        <div class="main-header">
            <h3><i class="fas fa-check-circle"></i> All Active Tasks</h3>
            <span class="badge-count"><?= $task_count ?> Active</span>
        </div>
        <div class="main-table">
            <div class="table-head" style="grid-template-columns:2.5fr 1fr 1fr 1.2fr 0.5fr;">
                <span>Task</span>
                <span>Priority</span>
                <span>Assignee</span>
                <span>Due Date</span>
                <span>Done</span>
            </div>
            <?php foreach($dashboard_tasks as $task):
                $t_members = $dashboard_assignees_map[intval($task['id'])] ?? [];
                $t_is_group = count($t_members) > 1;
                $t_names_title = implode(', ', array_map(function($m) { return $m['name']; }, $t_members));
            ?>
            <div class="table-row" style="grid-template-columns:2.5fr 1fr 1fr 1.2fr 0.5fr;" id="admin-task-<?= $task['id'] ?>">
                <div>
                    <a href="task_details.php?id=<?= $task['id'] ?>" class="task-title" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;color:inherit;text-decoration:none;margin-bottom:3px;">
                        <?= htmlspecialchars($task['title']) ?>
                        <?php if ($t_is_group): ?>
                        <span title="Grouped Task (<?= count($t_members) ?> members): <?= htmlspecialchars($t_names_title) ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;">
                            <i class="fas fa-users" style="font-size:9px;color:#0369a1 !important;"></i> Group (<?= count($t_members) ?>)
                        </span>
                        <?php endif; ?>
                    </a>
                    <div style="font-size:12.5px;color:#888;"><?= htmlspecialchars(substr($task['description'] ?? '',0,60)) ?><?= strlen($task['description'] ?? '')>60?'…':'' ?></div>
                </div>
                <span data-label="Priority">
                    <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?></span>
                </span>
                <span data-label="Assignee" title="<?= htmlspecialchars($t_names_title ?: 'Unassigned') ?>">
                    <?php if (!empty($t_members)): ?>
                    <div style="display:inline-flex;align-items:center;gap:6px;">
                        <?= renderTaskAvatarStackHtml($t_members, 3, 24) ?>
                        <span style="font-size:12px;font-weight:600;color:#334155;">
                            <?= htmlspecialchars($t_members[0]['name']) ?><?= $t_is_group ? ' <strong style="color:#0369a1;">+' . (count($t_members) - 1) . '</strong>' : '' ?>
                        </span>
                    </div>
                    <?php elseif (!empty($task['assigned_to'])): ?>
                    <span class="assignee-chip"><i class="fas fa-user"></i> User #<?= $task['assigned_to'] ?></span>
                    <?php else: ?>
                    <span style="color:#aaa;font-size:13px;">—</span>
                    <?php endif; ?>
                </span>
                <span data-label="Due" style="font-size:12.5px;color:#64748b;font-weight:600;"><i class="far fa-calendar-alt" style="margin-right:4px;color:#94a3b8 !important;"></i><?= !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'No date' ?></span>
                <span>
                    <input type="checkbox" class="task-status"
                        data-task-id="<?= $task['id'] ?>"
                        <?= $task['status'] === 'Completed' ? 'checked' : '' ?>
                        style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent);">
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin'): ?>
    <?php include('visitor_widget.php'); ?>

    <div class="resources-card">
        <div class="card-header-custom">
            <h3><i class="fas fa-book"></i> Resources & Links</h3>
            <a href="manage_resources.php?add_resource=1" class="view-all-link">+ Add New</a>
        </div>
        <div class="resources-list">
            <?php mysqli_data_seek($resource_result, 0); while($resource = mysqli_fetch_assoc($resource_result)): ?>
            <a href="<?= htmlspecialchars($resource['link']) ?>" class="resource-item" target="_blank">
                <div class="resource-icon"><i class="fas fa-file-alt"></i></div>
                <div class="resource-info">
                    <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                    <div class="resource-description"><?= htmlspecialchars($resource['description']) ?></div>
                </div>
                <span class="resource-link-icon">→</span>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>


    <?php elseif ($role === 'teacher'): ?>
        
        <div class="admin-stats-grid" style="margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-value" style="color:var(--primary);"><?= $stats['my_courses'] ?></div>
                <div class="stat-label">My Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-value" style="color:#0284c7;"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-comment-dots"></i></div>
                <div class="stat-value" style="color:#e17055;"><?= $stats['pending_queries'] ?></div>
                <div class="stat-label">Pending Queries</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-value" style="color:#0984e3;"><?= $stats['total_assignments'] ?></div>
                <div class="stat-label">Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stat-value" style="color:#7c3aed;"><?= $stats['pending_docs'] ?></div>
                <div class="stat-label">Pending Docs</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="tasks-card" style="margin-bottom:24px;">
            <div class="card-header-custom"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
            <div class="quick-actions-grid">
                <a href="teacher_assignments.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-plus"></i></span> New Assignment</a>
                <a href="student_queries.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-comment"></i></span> Answer Queries</a>
                <a href="teacher_documents.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-upload"></i></span> Upload Document</a>
                <a href="teacher_notices.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-bullhorn"></i></span> View Notices</a>
                <a href="teacher_courses.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-book-open"></i></span> My Courses</a>
            </div>
        </div>

        <div class="grid-2-col">
            <div class="d-card">
                <h3 class="d-card-title"><i class="fas fa-video" style="color:var(--primary);"></i> Manage Live Sessions</h3>
                
                <?php if($my_sessions && $my_sessions->num_rows > 0): while($sess = $my_sessions->fetch_assoc()): ?>
                    <div class="meeting-item">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <div class="m-date"><?= date('l, M j, Y - g:i A', strtotime($sess['start_time'])) ?></div>
                                <div class="m-title"><?= htmlspecialchars($sess['topic']) ?></div>
                                <div class="m-course"><i class="fas fa-book" style="color:#aaa;"></i> <?= htmlspecialchars($sess['course_title']) ?></div>
                            </div>
                            <div class="m-actions">
                                <a href="<?= htmlspecialchars($sess['meeting_link']) ?>" target="_blank" class="btn-meet"><i class="fas fa-play"></i> Host Now</a>
                            </div>
                        </div>
                        
                        <form method="POST" style="margin-top: 15px; background:#f9fbfc; padding:12px; border-radius:10px; display:flex; gap:10px; border:1px dashed #d0d0d0;">
                            <input type="hidden" name="update_recording" value="1">
                            <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                            <input type="url" name="recording_link" placeholder="Paste Recording URL here..." value="<?= htmlspecialchars($sess['recording_link'] ?? '') ?>" style="flex:1; padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-size:.85rem; outline:none;">
                            <button type="submit" class="btn btn-primary-custom" style="padding:8px 16px; border-radius:6px; font-size:.85rem;">Save Link</button>
                        </form>
                    </div>
                <?php endwhile; else: ?>
                    <div style="text-align:center; padding:40px 20px; color:#888;">
                        <i class="fas fa-calendar-times" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                        <p>You haven't scheduled any live sessions yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-plus-circle" style="color:var(--accent);"></i> Post Meeting</h3>
                    <form method="POST">
                        <input type="hidden" name="post_meeting" value="1">
                        
                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Select Course</label>
                            <select name="course_id" required>
                                <?php 
                                if ($t_courses && $t_courses->num_rows > 0) {
                                    $t_courses->data_seek(0);
                                    while($c = $t_courses->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                                <?php endwhile; } else { echo "<option value=''>No courses assigned</option>"; } ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Topic / Title</label>
                            <input type="text" name="topic" required placeholder="e.g. Chapter 1 Q&A">
                        </div>

                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Zoom / Meet Link</label>
                            <input type="url" name="meeting_link" required placeholder="https://zoom.us/j/...">
                        </div>

                        <div class="form-group" style="margin-bottom:20px;">
                            <label>Date & Time</label>
                            <input type="datetime-local" name="start_time" required>
                        </div>

                        <button type="submit" class="btn btn-primary-custom" style="width:100%;"><i class="fas fa-paper-plane"></i> Publish to Students</button>
                    </form>
                </div>

                <!-- Recent Notices -->
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-bullhorn" style="color:#f59e0b;"></i> Latest Notices</h3>
                    <?php 
                    mysqli_data_seek($notice_result, 0);
                    $nc = 0;
                    while($nd = mysqli_fetch_assoc($notice_result)):
                        if ($nc >= 3) break; $nc++;
                    ?>
                    <div style="padding:12px 15px; border-left:3px solid var(--accent); background:#f9fbfc; border-radius:0 8px 8px 0; margin-bottom:10px;">
                        <div style="font-weight:700; font-size:14px; color:#333; margin-bottom:3px;"><?= htmlspecialchars($nd['notice_title']) ?></div>
                        <div style="font-size:12px; color:#888;"><?= date('M d, Y', strtotime($nd['date'])) ?></div>
                    </div>
                    <?php endwhile; ?>
                    <a href="teacher_notices.php" style="display:block; text-align:center; color:var(--primary); font-weight:600; font-size:13px; text-decoration:none; margin-top:10px;">View All Notices →</a>
                </div>
                
                <!-- Student Queries Summary -->
                <div class="d-card">
                    <h3 class="d-card-title"><i class="fas fa-question-circle" style="color:#0284c7;"></i> Student Queries</h3>
                    <p style="font-size:13px; color:#666; margin-bottom:15px;">You have <strong><?= $stats['pending_queries'] ?></strong> unanswered questions from your students.</p>
                    <a href="student_queries.php" class="btn btn-secondary-custom" style="width:100%; text-align:center;">View Queries →</a>
                </div>
            </div>
        </div>

        <!-- Recent Assignments -->
        <?php if($recent_assignments && $recent_assignments->num_rows > 0): ?>
        <div class="d-card" style="margin-top:24px;">
            <h3 class="d-card-title"><i class="fas fa-tasks" style="color:var(--primary);"></i> Recent Assignments</h3>
            <div class="main-table">
                <div class="table-head" style="grid-template-columns:2.5fr 1.5fr 1fr 1fr;">
                    <span>Assignment</span>
                    <span>Course</span>
                    <span>Due Date</span>
                    <span>Submissions</span>
                </div>
                <?php while($asn = $recent_assignments->fetch_assoc()): ?>
                <div class="table-row" style="grid-template-columns:2.5fr 1.5fr 1fr 1fr;">
                    <div><strong style="font-size:14px;"><?= htmlspecialchars($asn['title']) ?></strong></div>
                    <span data-label="Course" style="font-size:13px; color:#666;"><?= htmlspecialchars($asn['course_title']) ?></span>
                    <span data-label="Due" style="font-size:13px; color:<?= strtotime($asn['due_date']) < time() ? '#ef4444' : '#666' ?>;"><?= date('M d, Y', strtotime($asn['due_date'])) ?></span>
                    <span data-label="Submissions"><span style="background:#e0f2fe; color:#0284c7; padding:4px 12px; border-radius:20px; font-weight:700; font-size:13px;"><?= $asn['submission_count'] ?></span></span>
                </div>
                <?php endwhile; ?>
            </div>
            <a href="teacher_assignments.php" style="display:block; text-align:center; color:var(--primary); font-weight:600; font-size:13px; text-decoration:none; margin-top:15px;">Manage All Assignments →</a>
        </div>
        <?php endif; ?>


    <?php elseif ($role === 'student'): ?>
        <div class="admin-stats-grid" style="margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-value" style="color:var(--primary);"><?= $stats['active_courses'] ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div class="stat-value" style="color:#00b894;"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-value" style="color:#0984e3;"><?= $stats['pending_assignments'] ?></div>
                <div class="stat-label">Upcoming Tasks</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-scroll"></i></div>
                <div class="stat-value" style="color:#e17055;"><?= $stats['completed'] ?></div>
                <div class="stat-label">Certificates</div>
            </div>
        </div>

        <!-- Quick Actions for Students -->
        <div class="tasks-card" style="margin-bottom:24px;">
            <div class="card-header-custom"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
            <div class="quick-actions-grid">
                <a href="student_courses.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-book-open"></i></span> My Courses</a>
                <a href="student_assignments.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-tasks"></i></span> Assignments</a>
                <a href="student_live.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-video"></i></span> Live Classes</a>
                <a href="student_my_queries.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-question-circle"></i></span> My Queries</a>
                <a href="student_certificates.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-certificate"></i></span> Certificates</a>
                <a href="teacher_notices.php" class="quick-action-btn"><span class="qa-icon"><i class="fas fa-bullhorn"></i></span> Notices</a>
            </div>
        </div>

        <div class="grid-2-col">
            <div>
                <!-- Live Classes -->
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-broadcast-tower" style="color:#ef4444;"></i> Live Classes & Recordings</h3>
                    
                    <?php if(!empty($upcoming_meetings)): ?>
                        <?php foreach($upcoming_meetings as $m): ?>
                            <div class="meeting-item">
                                <div class="m-date"><?= date('l, M j, Y - g:i A', strtotime($m['start_time'])) ?></div>
                                <div class="m-title"><?= htmlspecialchars($m['topic']) ?></div>
                                <div class="m-course">
                                    <strong>Course:</strong> <?= htmlspecialchars($m['course_title']) ?><br>
                                    <span style="color:#888; font-size:12px;">Instructor: <?= htmlspecialchars($m['teacher_name'] ?? 'Instructor') ?></span>
                                </div>
                                <div class="m-actions">
                                    <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" class="btn-meet"><i class="fas fa-video"></i> Join Live Class</a>
                                    
                                    <?php if(!empty($m['recording_link'])): ?>
                                        <a href="<?= htmlspecialchars($m['recording_link']) ?>" target="_blank" class="btn-rec"><i class="fas fa-play-circle"></i> Watch Recording</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px 10px; color:#888;">
                            <i class="fas fa-mug-hot" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                            <p style="font-size:15px; font-weight:600;">No live classes scheduled right now.</p>
                            <p style="font-size:13px;">Check back later or explore your course materials.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Assignments -->
                <?php if(!empty($upcoming_assignments)): ?>
                <div class="d-card">
                    <h3 class="d-card-title"><i class="fas fa-tasks" style="color:#0984e3;"></i> Upcoming Assignments</h3>
                    <?php foreach($upcoming_assignments as $ua): 
                        $days_left = (strtotime($ua['due_date']) - time()) / 86400;
                        $urgency_color = $days_left < 0 ? '#ef4444' : ($days_left <= 3 ? '#f59e0b' : '#10b981');
                        $urgency_label = $days_left < 0 ? 'Overdue' : ($days_left <= 1 ? 'Due Today' : ($days_left <= 3 ? 'Due Soon' : date('M d', strtotime($ua['due_date']))));
                    ?>
                    <div style="padding:15px; border:1px solid #eee; border-radius:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:14px; color:#333; margin-bottom:4px;"><?= htmlspecialchars($ua['title']) ?></div>
                            <div style="font-size:12px; color:#888;"><i class="fas fa-book" style="color:#ccc;"></i> <?= htmlspecialchars($ua['course_title']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <span style="background:<?= $urgency_color ?>15; color:<?= $urgency_color ?>; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700;"><?= $urgency_label ?></span>
                            <?php if($ua['submitted'] > 0): ?>
                                <div style="margin-top:5px; font-size:11px; color:#10b981; font-weight:600;"><i class="fas fa-check-circle"></i> Submitted</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="student_assignments.php" style="display:block; text-align:center; color:var(--primary); font-weight:600; font-size:13px; text-decoration:none; margin-top:10px;">View All Assignments →</a>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <!-- Active Courses -->
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-book-open" style="color:var(--primary);"></i> My Active Courses</h3>
                    
                    <?php if(!empty($active_courses_list)): ?>
                        <?php foreach($active_courses_list as $ac): ?>
                            <div class="sc-card">
                                <?php if(!empty($ac['thumbnail'])): ?>
                                    <img src="<?= $ac['thumbnail'] ?>" class="sc-img">
                                <?php else: ?>
                               <div class="sc-img" style="display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;"><i class="fas fa-graduation-cap"></i></div>
                                <?php endif; ?>
                                <div class="sc-info">
                                    <h4><?= htmlspecialchars($ac['title']) ?></h4>
                                    <span>In Progress</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="margin-top:20px;">
                            <a href="student_courses.php" class="btn btn-primary-custom" style="width:100%; text-align:center;">Go to My Learning →</a>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:20px 0;">
                            <p style="color:#888; font-size:.9rem; margin-bottom:15px;">You aren't actively enrolled in any courses right now.</p>
                            <a href="courses.php" class="btn btn-primary-custom" style="width:100%; text-align:center;">Browse Courses</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Latest Notices -->
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-bullhorn" style="color:#f59e0b;"></i> Latest Notices</h3>
                    <?php 
                    mysqli_data_seek($notice_result, 0);
                    $nc2 = 0;
                    while($nd2 = mysqli_fetch_assoc($notice_result)):
                        if ($nc2 >= 3) break; $nc2++;
                    ?>
                    <div style="padding:12px 15px; border-left:3px solid var(--accent); background:#f9fbfc; border-radius:0 8px 8px 0; margin-bottom:10px;">
                        <div style="font-weight:700; font-size:14px; color:#333; margin-bottom:3px;"><?= htmlspecialchars($nd2['notice_title']) ?></div>
                        <div style="font-size:12px; color:#888;"><?= date('M d, Y', strtotime($nd2['date'])) ?></div>
                    </div>
                    <?php endwhile; ?>
                    <a href="teacher_notices.php" style="display:block; text-align:center; color:var(--primary); font-weight:600; font-size:13px; text-decoration:none; margin-top:10px;">View All Notices →</a>
                </div>

                <!-- My Queries Summary -->
                <div class="d-card" style="margin-bottom:20px;">
                    <h3 class="d-card-title"><i class="fas fa-question-circle" style="color:#0284c7;"></i> My Queries</h3>
                    <div style="display:flex; gap:15px; margin-bottom:15px;">
                        <div style="flex:1; text-align:center; padding:15px; background:#fff7ed; border-radius:10px; border:1px solid #fed7aa;">
                            <div style="font-size:1.5rem; font-weight:800; color:#f59e0b;"><?= $stats['pending_queries'] ?></div>
                            <div style="font-size:11px; font-weight:600; color:#92400e; text-transform:uppercase;">Pending</div>
                        </div>
                        <div style="flex:1; text-align:center; padding:15px; background:#ecfdf5; border-radius:10px; border:1px solid #a7f3d0;">
                            <div style="font-size:1.5rem; font-weight:800; color:#10b981;"><?= $stats['answered_queries'] ?></div>
                            <div style="font-size:11px; font-weight:600; color:#065f46; text-transform:uppercase;">Answered</div>
                        </div>
                    </div>
                    <a href="student_my_queries.php" class="btn btn-secondary-custom" style="width:100%; text-align:center;">View All Queries →</a>
                </div>

                <!-- Achievements -->
                <div class="d-card">
                    <h3 class="d-card-title"><i class="fas fa-certificate" style="color:#f59e0b;"></i> Achievements</h3>
                    <p style="font-size:13px; color:#666; margin-bottom:15px;">You have unlocked <strong><?= $stats['completed'] ?></strong> certificates.</p>
                    <a href="student_certificates.php" class="btn btn-secondary-custom" style="width:100%; text-align:center;">View Certificates →</a>
                </div>
            </div>
    <?php elseif ($role === 'client' || $role === 'client_sub'): 
        $client_id = intval($data['client_id'] ?? 0);
        
        $proj_stats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
            FROM projects 
            WHERE client_id = {$client_id} AND visibility IN ('client', 'public')
        ")->fetch_assoc();
        
        $inv_stats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'sent' OR status = 'overdue' THEN 1 ELSE 0 END) as pending,
                SUM(amount_due) as total_due
            FROM invoices 
            WHERE client_id = {$client_id} AND status != 'draft'
        ")->fetch_assoc();
    ?>
        <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); margin-bottom: 24px;">
            <a href="client_projects.php" class="stat-card" title="View all my projects">
                <div class="stat-icon" style="color:var(--primary);"><i class="fas fa-folder-open"></i></div>
                <div class="stat-value"><?= intval($proj_stats['total']) ?></div>
                <div class="stat-label">Total Projects</div>
            </a>
            <a href="client_projects.php?filter=active" class="stat-card blue" title="View active projects in progress">
                <div class="stat-icon" style="color:#0984e3;"><i class="fas fa-spinner"></i></div>
                <div class="stat-value"><?= intval($proj_stats['active']) ?></div>
                <div class="stat-label">In Progress</div>
            </a>
            <a href="client_projects.php?filter=completed" class="stat-card green" title="View delivered projects">
                <div class="stat-icon" style="color:#00b894;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?= intval($proj_stats['completed']) ?></div>
                <div class="stat-label">Completed</div>
            </a>
            <?php if ($role === 'client'): ?>
            <a href="client_invoices.php" class="stat-card orange" title="View pending invoices">
                <div class="stat-icon" style="color:#e17055;"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-value"><?= intval($inv_stats['pending']) ?></div>
                <div class="stat-label">Pending Invoices</div>
            </a>
            <a href="client_invoices.php" class="stat-card red" title="View outstanding balance">
                <div class="stat-icon" style="color:#d63031;"><i class="fas fa-receipt"></i></div>
                <div class="stat-value" style="font-size:1.65rem;">PKR <?= number_format($inv_stats['total_due'] ?? 0, 0) ?></div>
                <div class="stat-label">Outstanding Due</div>
            </a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Projects List -->
            <div class="col-lg-8">
                <!-- How to Use Video Guide -->
                <div class="card mb-4" style="padding:24px; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">
                            <i class="fab fa-youtube" style="color:#ef4444; margin-right:8px;"></i> How to Use the Client Portal
                        </h3>
                        <span style="background:#e0f2fe; color:#0284c7; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:20px;">
                            Video Tutorial
                        </span>
                    </div>
                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 10px; background: #000; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                        <iframe src="https://www.youtube.com/embed/r6KaII-FkR4?rel=0" 
                                title="Client Portal Tutorial" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                allowfullscreen 
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border:0;">
                        </iframe>
                    </div>
                    <div style="margin-top: 12px; font-size: 13px; color: #64748b; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <span><i class="fas fa-info-circle" style="color:#024442;"></i> Watch this quick video guide to learn how to track deliverables, review milestones, and collaborate with us.</span>
                        <a href="https://youtu.be/r6KaII-FkR4?si=Bav6a-KWEmLB0sha" target="_blank" style="color: #024442; font-weight: 700; text-decoration: none; font-size: 12.5px; display:inline-flex; align-items:center; gap:5px;">
                            Watch on YouTube <i class="fas fa-external-link-alt" style="font-size:11px;"></i>
                        </a>
                    </div>
                </div>

                <div class="card" style="padding:24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-folder-open" style="color:#024442;"></i> My Projects</h3>
                    <?php
                    $proj_query = $conn->query("SELECT * FROM projects WHERE client_id = {$client_id} AND visibility IN ('client', 'public') ORDER BY created_at DESC LIMIT 5");
                    if ($proj_query && $proj_query->num_rows > 0):
                    ?>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php while ($proj = $proj_query->fetch_assoc()): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                                    <div>
                                        <a href="client_projects.php?id=<?= $proj['id'] ?>" style="font-weight:700; color:#1e293b; text-decoration:none; font-size:14.5px;"><?= htmlspecialchars($proj['project_name']) ?></a>
                                        <div style="font-size:12px; color:#64748b; margin-top:2px;">Status: <span style="font-weight:600; color:#334155;"><?= $proj['status'] ?></span></div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="font-weight:700; color:#024442; font-size:14px;"><?= $proj['progress'] ?>%</div>
                                        <div style="width:100px; background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                                            <div style="background:#024442; width:<?= $proj['progress'] ?>%; height:100%;"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="client_projects.php" class="btn btn-secondary-custom" style="display:block; width:100%; text-align:center; margin-top:12px; text-decoration:none;">View All Projects →</a>
                    <?php else: ?>
                        <div style="text-align:center; color:#94a3b8; padding:30px;">No projects associated with your account yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoices / Quick Links Sidebar -->
            <div class="col-lg-4">
                <?php if ($role === 'client'): ?>
                <div class="card" style="padding:24px; margin-bottom:20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-file-invoice-dollar" style="color:#d97706;"></i> Billing Summary</h3>
                    <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:14px;">
                        <span style="color:#64748b; font-weight:500;">Pending Invoices:</span>
                        <strong style="color:#d97706;"><?= intval($inv_stats['pending']) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-size:14px;">
                        <span style="color:#64748b; font-weight:500;">Total Due:</span>
                        <strong style="color:#ef4444;">PKR <?= number_format($inv_stats['total_due'] ?? 0, 2) ?></strong>
                    </div>
                    <a href="client_invoices.php" class="btn btn-primary" style="display:block; width:100%; text-align:center; background:#024442; border:none; text-decoration:none; padding:8px 0; border-radius:6px; color:white; font-weight:700;">Manage Invoices</a>
                </div>
                <?php endif; ?>

                <div class="card" style="padding:24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-info-circle" style="color:#0284c7;"></i> Account Info</h3>
                    <div style="font-size:13.5px; color:#475569; line-height:1.6;">
                        <strong>User:</strong> <?= htmlspecialchars($name) ?><br>
                        <strong>Role:</strong> <span style="text-transform: capitalize;"><?= $role === 'client' ? 'Primary Contact' : 'Client Participant' ?></span><br>
                        <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
                    </div>
                </div>
            </div>
        </div>

    <?php else: // Standard Graphicafix Family Member Overview (role === 'user' or 'hrm') ?>

    <!-- 1. Balanced 3x2 Personal Work KPIs -->
    <div class="ov-kpi-grid">
        <a href="tasks.php" class="ov-kpi-card is-accent" title="View my active tasks">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">Active Tasks</div>
                <div class="ov-kpi-value"><?= number_format($pending_tasks ?? 0) ?></div>
                <div class="ov-kpi-sub">Currently on your plate</div>
            </div>
            <div class="ov-kpi-icon accent"><i class="fas fa-clipboard-list"></i></div>
        </a>

        <a href="tasks.php" class="ov-kpi-card" title="View my high priority tasks">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">High Priority</div>
                <div class="ov-kpi-value"><?= number_format($high_priority_tasks ?? 0) ?></div>
                <div class="ov-kpi-sub">Needs focus first</div>
            </div>
            <div class="ov-kpi-icon blue"><i class="fas fa-fire"></i></div>
        </a>

        <a href="tasks.php" class="ov-kpi-card" title="View my overdue tasks">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">Overdue Tasks</div>
                <div class="ov-kpi-value" style="<?= intval($overdue_tasks ?? 0) > 0 ? 'color:#dc2626;' : '' ?>"><?= number_format($overdue_tasks ?? 0) ?></div>
                <div class="ov-kpi-sub"><?= intval($overdue_tasks ?? 0) > 0 ? 'Action needed' : 'All on schedule' ?></div>
            </div>
            <div class="ov-kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        </a>

        <a href="tasks.php" class="ov-kpi-card" title="View my completed tasks">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">Completed</div>
                <div class="ov-kpi-value"><?= number_format($completed_tasks ?? 0) ?></div>
                <div class="ov-kpi-sub">Tasks delivered</div>
            </div>
            <div class="ov-kpi-icon green"><i class="fas fa-check-circle"></i></div>
        </a>

        <a href="manage_projects.php" class="ov-kpi-card" title="View my assigned projects">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">My Projects</div>
                <div class="ov-kpi-value"><?= number_format($user_projects_count ?? 0) ?></div>
                <div class="ov-kpi-sub">Active collaborations</div>
            </div>
            <div class="ov-kpi-icon purple"><i class="fas fa-folder-open"></i></div>
        </a>

        <a href="dpr.php" class="ov-kpi-card <?= !empty($has_submitted_dpr) ? 'is-accent' : '' ?>" title="Submit or review today's Daily Progress Report">
            <div class="ov-kpi-left">
                <div class="ov-kpi-label">Today&rsquo;s DPR</div>
                <div class="ov-kpi-value" style="font-size:1.35rem; color:<?= !empty($has_submitted_dpr) ? '#365314' : '#d97706' ?>;"><?= !empty($has_submitted_dpr) ? 'Submitted' : 'Pending' ?></div>
                <div class="ov-kpi-sub"><?= !empty($has_submitted_dpr) ? 'Logged for today' : 'Click to log update' ?></div>
            </div>
            <div class="ov-kpi-icon <?= !empty($has_submitted_dpr) ? 'accent' : 'amber' ?>"><i class="fas fa-calendar-check"></i></div>
        </a>
    </div>

    <!-- 2. Compact Quick Actions Strip -->
    <div class="ov-actions-card">
        <div class="ov-actions-header">
            <span><i class="fas fa-bolt" style="color:#65a30d !important;"></i> Quick Shortcuts</span>
        </div>
        <div class="ov-actions-strip">
            <a href="dpr.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-calendar-check" style="color:#d97706 !important;"></i></span>
                <span>Submit DPR</span>
            </a>
            <a href="attendance.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-clock" style="color:#0d9488 !important;"></i></span>
                <span>Attendance</span>
            </a>
            <a href="tasks.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-tasks" style="color:#365314 !important;"></i></span>
                <span>My Tasks</span>
            </a>
            <a href="manage_projects.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-folder-open" style="color:#7c3aed !important;"></i></span>
                <span>My Projects</span>
            </a>
            <a href="my_salary.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-receipt" style="color:#059669 !important;"></i></span>
                <span>Salary Slips</span>
            </a>
            <a href="deduction_tickets.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-ticket-alt" style="color:#dc2626 !important;"></i></span>
                <span>Dispute Ticket</span>
            </a>
            <a href="price_calculator.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-calculator" style="color:#0284c7 !important;"></i></span>
                <span>Price Calc</span>
            </a>
            <a href="entertainment.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-gamepad" style="color:#db2777 !important;"></i></span>
                <span>Entertainment</span>
            </a>
            <a href="profile.php" class="ov-action-tile">
                <span class="ov-action-ico"><i class="fas fa-cog" style="color:#475569 !important;"></i></span>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- 3. Asymmetric Two-Column Workspace: Active Tasks & Notices/Resources -->
    <div class="ov-workspace-grid">
        <!-- Left: My Active Tasks -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-check-circle" style="color:#65a30d !important;"></i> My Active Tasks</h3>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="badge-count"><?= $task_count ?></span>
                    <a href="tasks.php" class="view-all-link">Task Board &rarr;</a>
                </div>
            </div>
            <?php if ($task_count > 0): ?>
                <div>
                <?php foreach($dashboard_tasks as $task):
                    $t_members = $dashboard_assignees_map[intval($task['id'])] ?? [];
                    $t_is_group = count($t_members) > 1;
                    $t_names_title = implode(', ', array_map(function($m) { return $m['name']; }, $t_members));
                    $t_is_overdue = (!empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] !== 'Completed');
                ?>
                <div class="ov-task-row">
                    <input type="checkbox" class="task-status ov-task-check" data-task-id="<?= $task['id'] ?>" <?= $task['status'] === 'Completed' ? 'checked' : '' ?>>
                    <div style="flex:1; min-width:0;">
                        <div class="task-title" style="font-weight:700; color:#0f172a; font-size:0.9rem; display:flex; align-items:center; gap:7px; flex-wrap:wrap; margin-bottom:2px;">
                            <a href="task_details.php?id=<?= $task['id'] ?>" style="color:inherit; text-decoration:none;"><?= htmlspecialchars($task['title']) ?></a>
                            <?php if ($t_is_group): ?>
                            <span title="Grouped Task (<?= count($t_members) ?> members): <?= htmlspecialchars($t_names_title) ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:#ecfccb;color:#365314;border:1px solid #d9f99d;">
                                <i class="fas fa-users" style="font-size:9px;color:#365314 !important;"></i> Group (<?= count($t_members) ?>)
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($task['description'])): ?>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:2px; line-height:1.4;"><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 95, '...')) ?></div>
                        <?php endif; ?>
                        <div style="margin-top:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <span class="status-pill <?= strtolower($task['priority'] ?? 'low') ?>"><?= htmlspecialchars($task['priority'] ?? 'Normal') ?></span>
                            <span style="font-size:0.75rem; font-weight:600; color:<?= $t_is_overdue ? '#dc2626' : '#64748b' ?>;">
                                <i class="far fa-calendar-alt" style="margin-right:3px; color:<?= $t_is_overdue ? '#dc2626' : '#94a3b8' ?> !important;"></i>
                                <?= !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'No due date' ?>
                            </span>
                            <?php if ($t_is_group): ?>
                            <span title="<?= htmlspecialchars($t_names_title) ?>" style="display:inline-flex;align-items:center;gap:4px;">
                                <?= renderTaskAvatarStackHtml($t_members, 3, 20) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:42px 20px; color:#64748b;">
                    <div style="width:48px; height:48px; border-radius:14px; background:#ecfccb; display:inline-flex; align-items:center; justify-content:center; margin-bottom:10px;">
                        <i class="fas fa-check-double" style="font-size:1.2rem; color:#365314 !important;"></i>
                    </div>
                    <div style="font-weight:700; color:#0f172a; font-size:0.92rem;">All caught up!</div>
                    <div style="font-size:0.82rem; color:#94a3b8; margin-top:3px;">You have no pending tasks assigned right now.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Notices & Resources -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Company Notices -->
            <div class="tasks-card">
                <div class="card-header-custom">
                    <h3><i class="fas fa-bullhorn" style="color:#d97706 !important;"></i> Company Notices</h3>
                    <span class="badge-count"><?= $notice_count ?></span>
                </div>
                <div class="notices-body">
                    <?php
                    mysqli_data_seek($notice_result, 0);
                    $nc = 0;
                    while($notice = mysqli_fetch_assoc($notice_result)):
                        if ($nc >= 4) break; $nc++;
                        $notice_time = isset($notice['created_at']) ? date('M d, g:i A', strtotime($notice['created_at'])) : '';
                    ?>
                    <a href="notice_details.php?id=<?= $notice['notice_id'] ?>" class="notice-item">
                        <div class="notice-title"><?= htmlspecialchars($notice['notice_title']) ?></div>
                        <?php if ($notice_time): ?>
                        <div style="font-size:0.74rem; color:#64748b; margin-top:3px;">
                            <i class="far fa-clock" style="margin-right:3px; color:#94a3b8 !important;"></i><?= $notice_time ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endwhile; ?>
                    <?php if ($nc === 0): ?>
                    <div style="text-align:center; padding:20px 10px; font-size:0.84rem; color:#94a3b8;">No company notices yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resources & Guidelines -->
            <div class="resources-card">
                <div class="card-header-custom">
                    <h3><i class="fas fa-book-open" style="color:#365314 !important;"></i> Resources &amp; Guides</h3>
                    <span class="badge-count"><?= $resource_count ?></span>
                </div>
                <div class="resources-list">
                    <?php
                    mysqli_data_seek($resource_result, 0);
                    $rc = 0;
                    while($resource = mysqli_fetch_assoc($resource_result)):
                        if ($rc >= 4) break; $rc++;
                    ?>
                    <a href="<?= htmlspecialchars($resource['link']) ?>" class="resource-item" target="_blank">
                        <div class="resource-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="resource-info">
                            <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                        </div>
                        <i class="fas fa-external-link-alt resource-link-icon"></i>
                    </a>
                    <?php endwhile; ?>
                    <?php if ($rc === 0): ?>
                    <div style="text-align:center; padding:20px 10px; font-size:0.84rem; color:#94a3b8;">No resources shared yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

        <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    $('.task-status').on('change', function () {
        var $checkbox = $(this);
        var taskId    = $checkbox.data('task-id');
        var isChecked = $checkbox.is(':checked');
        var status    = isChecked ? 'Completed' : 'Pending';
        var $taskItem = $checkbox.closest('.ov-task-row, .task-item, .table-row');

        if (isChecked) {
            $taskItem.css('opacity', '0.5');
            $taskItem.find('.task-title').css('text-decoration', 'line-through');
        } else {
            $taskItem.css('opacity', '1');
            $taskItem.find('.task-title').css('text-decoration', 'none');
        }

        $.ajax({
            url: 'update_task_status.php',
            type: 'POST',
            dataType: 'json',
            data: { id: taskId, status: status },
            success: function (response) {
                if (!response.success) {
                    $checkbox.prop('checked', !isChecked);
                    $taskItem.css('opacity', '1');
                    $taskItem.find('.task-title').css('text-decoration', 'none');
                    alert('Failed to update: ' + response.message);
                }
            },
            error: function (xhr) {
                $checkbox.prop('checked', !isChecked);
                $taskItem.css('opacity', '1');
                $taskItem.find('.task-title').css('text-decoration', 'none');
                console.error('AJAX Error:', xhr.responseText);
                alert('Server error. Please try again.');
            }
        });
    });
});

// ── Team Member of the Month Confetti Pop & Cheer Micro-Interaction ──
(function () {
    const COLORS = ['#b8f35a', '#84cc16', '#fbbf24', '#34d399', '#38bdf8', '#fb7185', '#a78bfa'];
    let particles = [];
    let rafId = null;
    let canvas = null;
    let ctx = null;
    let cheerIdx = 0;

    function ensureCanvas() {
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = 'motmConfettiCanvas';
            document.body.appendChild(canvas);
            ctx = canvas.getContext('2d');
        }
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function spawnBurst(originX, originY, count, spread) {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        ensureCanvas();
        for (let i = 0; i < count; i++) {
            const angle = -Math.PI / 2 + (Math.random() - 0.5) * spread;
            const speed = 4.5 + Math.random() * 8.5;
            const shape = Math.random() < 0.35 ? 'circle' : (Math.random() < 0.7 ? 'rect' : 'star');
            particles.push({
                x: originX + (Math.random() - 0.5) * 18,
                y: originY + (Math.random() - 0.5) * 10,
                vx: Math.cos(angle) * speed + (Math.random() - 0.5) * 2.2,
                vy: Math.sin(angle) * speed - Math.random() * 2.5,
                size: 5 + Math.random() * 5,
                color: COLORS[Math.floor(Math.random() * COLORS.length)],
                rot: Math.random() * Math.PI * 2,
                vrot: (Math.random() - 0.5) * 0.25,
                shape: shape,
                alpha: 1,
                decay: 0.011 + Math.random() * 0.008
            });
        }
        if (!rafId) tick();
    }

    function drawStar(c, x, y, r) {
        c.beginPath();
        for (let i = 0; i < 5; i++) {
            const a1 = (i * 2 * Math.PI) / 5 - Math.PI / 2;
            const a2 = a1 + Math.PI / 5;
            if (i === 0) c.moveTo(x + Math.cos(a1) * r, y + Math.sin(a1) * r);
            else c.lineTo(x + Math.cos(a1) * r, y + Math.sin(a1) * r);
            c.lineTo(x + Math.cos(a2) * (r * 0.45), y + Math.sin(a2) * (r * 0.45));
        }
        c.closePath();
        c.fill();
    }

    function tick() {
        if (!ctx || !canvas) return;
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.24; // smooth gravity
            p.vx *= 0.985; // air drag
            p.rot += p.vrot;
            p.alpha -= p.decay;
            if (p.alpha <= 0 || p.y > window.innerHeight + 30) {
                particles.splice(i, 1);
                continue;
            }
            ctx.save();
            ctx.globalAlpha = Math.max(0, p.alpha);
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = p.color;
            if (p.shape === 'circle') {
                ctx.beginPath();
                ctx.arc(0, 0, p.size * 0.48, 0, Math.PI * 2);
                ctx.fill();
            } else if (p.shape === 'star') {
                drawStar(ctx, 0, 0, p.size * 0.65);
            } else {
                ctx.fillRect(-p.size * 0.5, -p.size * 0.28, p.size, p.size * 0.56);
            }
            ctx.restore();
        }
        if (particles.length > 0) {
            rafId = requestAnimationFrame(tick);
        } else {
            rafId = null;
            ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
        }
    }

    window.triggerMotmCelebrate = function (btn) {
        const card = document.getElementById('motmSpotlightCard');
        const honoree = (card && card.getAttribute('data-honoree')) || 'our star';
        if (btn) {
            btn.classList.remove('is-popping');
            void btn.offsetWidth;
            btn.classList.add('is-popping');
            const rect = btn.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            spawnBurst(cx, cy, 46, Math.PI * 1.15);

            // Also pop a secondary burst from the portrait
            const avatar = card ? card.querySelector('.motm-avatar-frame') : null;
            if (avatar) {
                const ar = avatar.getBoundingClientRect();
                spawnBurst(ar.left + ar.width / 2, ar.top + ar.height / 2, 24, Math.PI * 1.35);
            }

            // Floating Graphicafix Family cheer pill
            const cheers = [
                `Cheers to ${honoree}!`,
                `Graphicafix Family pride!`,
                `Way to shine, ${honoree}!`,
                `Well deserved, ${honoree}!`
            ];
            const pill = document.createElement('div');
            pill.className = 'motm-cheer-pill';
            pill.textContent = cheers[cheerIdx % cheers.length];
            cheerIdx++;
            pill.style.left = Math.min(Math.max(cx, 110), window.innerWidth - 110) + 'px';
            pill.style.top = Math.max(rect.top - 12, 24) + 'px';
            document.body.appendChild(pill);
            setTimeout(() => pill.remove(), 1800);
        }
    };

    // Subtle welcome pop burst when the spotlight card loads
    window.addEventListener('load', function () {
        const card = document.getElementById('motmSpotlightCard');
        if (!card) return;
        setTimeout(function () {
            const rect = card.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                spawnBurst(rect.left + 70, rect.top + rect.height * 0.45, 28, Math.PI * 1.1);
                spawnBurst(rect.right - 90, rect.top + rect.height * 0.45, 28, Math.PI * 1.1);
            }
        }, 380);
    });
})();
</script>

<?php include('dashboard_footer.php'); ?>