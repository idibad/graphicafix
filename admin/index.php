<?php
include 'dashboard_header.php'; 

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

// Fetch tasks based on role
if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin') {
    $task_query = "SELECT * FROM tasks WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
} else {
    $task_query = "SELECT * FROM tasks WHERE (assigned_to = $user_id OR created_by = $user_id) AND status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
}
$task_result = mysqli_query($conn, $task_query);
$task_count  = mysqli_num_rows($task_result);

// Fetch resources
$resource_query  = "SELECT * FROM resources ORDER BY created_at DESC";
$resource_result = mysqli_query($conn, $resource_query);
$resource_count  = mysqli_num_rows($resource_result);

// Admin/PM/Manager/Sales stats
if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'pm' || $role === 'manager' || $role === 'sales') {
    $stats_filter = "1=1";
    if ($role !== 'super_admin' && $role !== 'superadmin' && $role !== 'admin') {
        $stats_filter = "(assigned_to = $user_id OR created_by = $user_id)";
    }

    if ($role === 'super_admin' || $role === 'superadmin') {
        $total_users_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
        $total_users            = mysqli_fetch_assoc($total_users_result)['cnt'];
        // Recent users
        $recent_users_result = mysqli_query($conn, "SELECT * FROM users WHERE role != 'student' ORDER BY created_at DESC LIMIT 5");
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

    // Sales Visits metrics
    $total_visits_count  = $conn->query("SELECT COUNT(*) AS c FROM sales_visits")->fetch_assoc()['c'] ?? 0;
    $urgent_visits_count = $conn->query("SELECT COUNT(*) AS c FROM sales_visits WHERE is_urgent = 1 AND status != 'closed'")->fetch_assoc()['c'] ?? 0;
    $pending_visits_count = $conn->query("SELECT COUNT(*) AS c FROM sales_visits WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;

    $recent_inbound_visits = [];
    if (in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm'])) {
        $v_res = $conn->query("SELECT * FROM sales_visits ORDER BY is_urgent DESC, id DESC LIMIT 5");
        if ($v_res) while($vr = $v_res->fetch_assoc()) $recent_inbound_visits[] = $vr;
    } elseif ($role === 'sales') {
        $v_res = $conn->query("SELECT * FROM sales_visits WHERE sales_rep_id = $user_id ORDER BY id DESC LIMIT 5");
        if ($v_res) while($vr = $v_res->fetch_assoc()) $recent_inbound_visits[] = $vr;
    }

    if ($role === 'sales') {
        $sales_leads_count   = $conn->query("SELECT COUNT(*) AS c FROM leads")->fetch_assoc()['c'] ?? 0;
        $sales_active_leads  = $conn->query("SELECT COUNT(*) AS c FROM leads WHERE pipeline_stage NOT IN ('won','lost')")->fetch_assoc()['c'] ?? 0;
        $sales_quot_count    = $conn->query("SELECT COUNT(*) AS c FROM quotations")->fetch_assoc()['c'] ?? 0;
        $sales_accepted_quot = $conn->query("SELECT COUNT(*) AS c FROM quotations WHERE status = 'accepted'")->fetch_assoc()['c'] ?? 0;
        $sales_my_visits     = $conn->query("SELECT COUNT(*) AS c FROM sales_visits WHERE sales_rep_id = $user_id")->fetch_assoc()['c'] ?? 0;
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
/* ── Only styles NOT already in dashboard.css ── */

/* Admin stat cards — extended from .stat-card */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card { border-top: 4px solid var(--accent); position: relative; overflow: hidden; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.02);}
.stat-card::before { content: ''; position: absolute; top: -40px; right: -40px; width: 100px; height: 100px; background: rgba(0,0,0,0.03); border-radius: 50%; }
.stat-card.red    { border-top-color: #d63031; }
.stat-card.green  { border-top-color: #00b894; }
.stat-card.orange { border-top-color: #e17055; }
.stat-card.blue   { border-top-color: #0984e3; }
.stat-icon  { font-size: 2rem; margin-bottom:10px;}
.stat-value { font-size: 2.2rem; font-weight: 700; color: #333; line-height: 1; margin-bottom:5px;}
.stat-label { font-size: .85rem; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* Quick actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 15px;
}
.quick-action-btn {
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    padding: 20px 12px; background: #f8f9fa; border: 2px solid transparent;
    border-radius: 12px; text-decoration: none; color: #333;
    font-weight: 600; font-size: 13px; text-align: center; transition: all 0.3s ease;
}
.quick-action-btn:hover { background: #fcfff5ff; border-color: var(--accent); color: var(--accent); transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.quick-action-btn .qa-icon { font-size: 1.6rem; }

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

@media (max-width: 768px) {
    .admin-stats-grid   { grid-template-columns: repeat(2, 1fr); }
    .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>

<div class="dashboard-container">

    <div class="welcome-text">
        <h1>Welcome back, <?php echo htmlspecialchars($name); ?> <?php 
            if ($role === 'super_admin' || $role === 'superadmin') echo '<i class="fas fa-crown" style="color:#f59e0b;"></i>';
            elseif ($role === 'admin') echo '<i class="fas fa-user-shield" style="color:var(--primary);"></i>';
            elseif ($role === 'sales') echo '<i class="fas fa-handshake" style="color:#059669;"></i>';
            else echo '👋'; 
        ?></h1>
        <p><?php 
            if ($role === 'super_admin' || $role === 'superadmin') echo "Here's your comprehensive system overview for today (Super Admin).";
            elseif ($role === 'admin') echo "Here's your administrative and operational overview for today.";
            elseif ($role === 'sales') echo "Here's your sales pipeline, deals, and client activities for today.";
            else echo "Here's what's happening with your projects today."; 
        ?></p>
    </div>

    <?php if ($urgent_visits_count > 0 && in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm'])): ?>
    <div style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border: 1.5px solid #ef4444; border-radius: 12px; padding: 14px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.12);">
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #ef4444; color: #fff; font-size: 1.15rem; flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle"></i>
            </span>
            <div>
                <strong style="color: #991b1b; font-size: 15px; display: block;"><?= $urgent_visits_count ?> Field Visit(s) Need URGENT Contact!</strong>
                <div style="color: #b91c1c; font-size: 13px;">Prospects marked for immediate reach-out are waiting in the Response Unit.</div>
            </div>
        </div>
        <a href="sales_response_unit.php" style="background: #dc2626; color: #fff; font-weight: 700; font-size: 13px; padding: 9px 18px; border-radius: 8px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);">
            <i class="fas fa-bolt"></i> Open Response Unit
        </a>
    </div>
    <?php endif; ?>

    <?php if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'pm' || $role === 'manager' || $role === 'sales'): ?>
    <?php if ($role === 'sales'): ?>
    <div class="admin-stats-grid">
        <div class="stat-card" style="border-top-color:#0284c7;">
            <div class="stat-icon"><i class="fas fa-street-view" style="color:#0284c7;"></i></div>
            <div class="stat-value"><?= $sales_my_visits ?></div>
            <div class="stat-label">Visits Logged</div>
        </div>
        <div class="stat-card" style="border-top-color:#059669;">
            <div class="stat-icon"><i class="fas fa-filter" style="color:#059669;"></i></div>
            <div class="stat-value"><?= $sales_leads_count ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-funnel-dollar"></i></div>
            <div class="stat-value"><?= $sales_active_leads ?></div>
            <div class="stat-label">Active Pipeline</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
            <div class="stat-value"><?= $sales_quot_count ?></div>
            <div class="stat-label">Quotations</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
            <div class="stat-value"><?= $sales_accepted_quot ?></div>
            <div class="stat-label">Deals Won</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-value"><?= $pending_tasks ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?= $overdue_tasks ?></div>
            <div class="stat-label">Overdue Tasks</div>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-stats-grid">
        <?php if ($role === 'super_admin' || $role === 'superadmin'): ?>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <?php endif; ?>
        <div class="stat-card" style="border-top-color:#059669;">
            <div class="stat-icon"><i class="fas fa-street-view" style="color:#059669;"></i></div>
            <div class="stat-value"><?= $total_visits_count ?></div>
            <div class="stat-label">Field Visits</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-value"><?= $total_tasks ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $completed_tasks ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= $pending_tasks ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?= $overdue_tasks ?></div>
            <div class="stat-label">Overdue</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-fire"></i></div>
            <div class="stat-value"><?= $high_priority_tasks ?></div>
            <div class="stat-label">High Priority</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="tasks-card" style="margin-bottom:30px;">
        <div class="card-header-custom"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
        <div class="quick-actions-grid">
            <?php if ($role === 'super_admin' || $role === 'superadmin'): ?>
            <a href="manage_users.php?add_user=1"         class="quick-action-btn"><span class="qa-icon"><i class="fas fa-plus"></i></span> Add User</a>
            <?php endif; ?>
            <?php if ($role === 'sales'): ?>
            <a href="sales_visits.php?log=1"               class="quick-action-btn"><span class="qa-icon"><i class="fas fa-plus-circle" style="color:#059669;"></i></span> Log Visit</a>
            <a href="sales_visits.php"                     class="quick-action-btn"><span class="qa-icon"><i class="fas fa-street-view" style="color:#0284c7;"></i></span> Field Visits</a>
            <a href="manage_leads.php?action=add"          class="quick-action-btn"><span class="qa-icon"><i class="fas fa-user-tag" style="color:#059669;"></i></span> Add Lead</a>
            <a href="manage_leads.php"                     class="quick-action-btn"><span class="qa-icon"><i class="fas fa-filter"></i></span> Pipeline</a>
            <a href="create_quotation.php"                 class="quick-action-btn"><span class="qa-icon"><i class="fas fa-file-signature"></i></span> New Quote</a>
            <a href="manage_invoices.php?tab=quotations"   class="quick-action-btn"><span class="qa-icon"><i class="fas fa-file-alt"></i></span> Quotations</a>
            <a href="price_calculator.php"                 class="quick-action-btn"><span class="qa-icon"><i class="fas fa-calculator"></i></span> Price Calc</a>
            <a href="create_task.php"                      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-edit"></i></span> New Task</a>
            <a href="manage_clients.php"                   class="quick-action-btn"><span class="qa-icon"><i class="fas fa-building"></i></span> Clients</a>
            <a href="dpr.php"                              class="quick-action-btn"><span class="qa-icon"><i class="fas fa-calendar-check"></i></span> My DPR</a>
            <?php else: ?>
            <a href="sales_response_unit.php"              class="quick-action-btn" style="position:relative;"><span class="qa-icon"><i class="fas fa-satellite-dish" style="color:#f59e0b;"></i></span> Response Unit <?= ($urgent_visits_count > 0 ? '<span style="position:absolute;top:8px;right:8px;background:#ef4444;color:#fff;border-radius:999px;font-size:10px;padding:1px 6px;font-weight:800;">' . $urgent_visits_count . '</span>' : '') ?></a>
            <a href="sales_visits.php"                     class="quick-action-btn"><span class="qa-icon"><i class="fas fa-street-view" style="color:#10b981;"></i></span> Field Visits</a>
            <a href="create_task.php"                      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-edit"></i></span> New Task</a>
            <?php if ($role === 'super_admin' || $role === 'superadmin'): ?>
            <a href="manage_users.php"                     class="quick-action-btn"><span class="qa-icon"><i class="fas fa-users"></i></span> Manage Users</a>
            <?php endif; ?>
            <a href="tasks.php"                            class="quick-action-btn"><span class="qa-icon"><i class="fas fa-clipboard"></i></span> All Tasks</a>
            <a href="price_calculator.php"                 class="quick-action-btn"><span class="qa-icon"><i class="fas fa-calculator"></i></span> Price Calc</a>
            <?php if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'manager'): ?>
            <a href="manage_notices.php?add_notice=1"      class="quick-action-btn"><span class="qa-icon"><i class="fas fa-bullhorn"></i></span> Post Notice</a>
            <?php endif; ?>
            <?php if ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin'): ?>
            <a href="manage_resources.php?add_resource=1"  class="quick-action-btn"><span class="qa-icon"><i class="fas fa-book"></i></span> Add Resource</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
            <a href="correspondence.php"                   class="quick-action-btn"><span class="qa-icon"><i class="fas fa-envelope"></i></span> Letters / Docs</a>
            <a href="attendance.php"                       class="quick-action-btn"><span class="qa-icon"><i class="fas fa-clock"></i></span> Attendance</a>
            <?php endif; ?>
            <?php endif; ?>
            <a href="profile.php"                          class="quick-action-btn"><span class="qa-icon">⚙️</span> Settings</a>
        </div>
    </div>

    <div class="admin-panels">

        <?php if ($role === 'super_admin' || $role === 'superadmin'): ?>
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3><i class="fas fa-users"></i> Recent Users</h3>
                <a href="manage_users.php" class="view-all-link">View All →</a>
            </div>
            <div class="main-table">
                <div class="table-head" style="grid-template-columns:1.5fr 2fr 0.8fr;">
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                </div>
                <?php while($u = mysqli_fetch_assoc($recent_users_result)): ?>
                <div class="table-row" style="grid-template-columns:1.5fr 2fr 0.8fr;">
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
        <?php endif; ?>

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

    <!-- ── Inbound Field Visits & Response Unit Live Widget ── -->
    <?php if (!empty($recent_inbound_visits)): ?>
    <div class="main-card" style="margin-bottom:30px; background:#fff; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 4px 15px rgba(0,0,0,0.03); overflow:hidden;">
        <div class="main-header" style="padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:<?= ($role==='sales'?'#ecfdf5':'#e0f2fe') ?>; color:<?= ($role==='sales'?'#059669':'#0284c7') ?>;">
                    <i class="fas <?= ($role==='sales'?'fa-street-view':'fa-satellite-dish') ?>"></i>
                </span>
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">
                    <?= ($role === 'sales' ? 'My Recent Field Visits' : '⚡ Inbound Field Visits (Live Response Radar)') ?>
                </h3>
                <?php if ($urgent_visits_count > 0 && $role !== 'sales'): ?>
                <span style="background:#fee2e2; color:#ef4444; font-size:11px; font-weight:800; padding:3px 9px; border-radius:999px;">
                    🚨 <?= $urgent_visits_count ?> Urgent
                </span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($role === 'sales'): ?>
                <a href="sales_visits.php?log=1" class="btn" style="background:#059669; color:#fff; font-size:12.5px; font-weight:700; padding:6px 14px; border-radius:6px; text-decoration:none; margin-right:8px;">
                    <i class="fas fa-plus"></i> Log Visit
                </a>
                <a href="sales_visits.php" class="view-all-link">View All Logged →</a>
                <?php else: ?>
                <a href="sales_response_unit.php" class="btn" style="background:var(--primary); color:#b8f35a; font-size:12.5px; font-weight:700; padding:6px 14px; border-radius:6px; text-decoration:none;">
                    <i class="fas fa-bolt"></i> Open Full Response Unit →
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                        <th style="padding:12px 18px;">Company / Client</th>
                        <th style="padding:12px 14px;">Contact Window</th>
                        <th style="padding:12px 14px;">Interest Level</th>
                        <th style="padding:12px 14px;">Logged By</th>
                        <th style="padding:12px 14px;">Status</th>
                        <th style="padding:12px 18px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_inbound_visits as $rv): 
                        $is_urg = ($rv['is_urgent'] == 1);
                        $clean_wa = preg_replace('/[^0-9]/', '', $rv['whatsapp'] ?: $rv['phone']);
                        $wa_msg = urlencode("Hello " . $rv['client_name'] . ", this is Graphicafix Management following up on your discussion with our team. Are you available for a quick discussion?");
                    ?>
                    <tr style="border-bottom:1px solid #f1f5f9; <?= $is_urg ? 'background:#fffbfb;' : '' ?>">
                        <td style="padding:14px 18px;">
                            <div style="font-weight:700; color:#0f172a; font-size:13.5px; display:flex; align-items:center; gap:6px;">
                                <?= htmlspecialchars($rv['company_name']) ?>
                                <?php if ($is_urg): ?>
                                <span style="background:#ef4444; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:4px;">URGENT</span>
                                <?php endif; ?>
                            </div>
                            <div style="color:#64748b; font-size:12px;"><?= htmlspecialchars($rv['client_name']) ?> • <?= htmlspecialchars($rv['phone']) ?></div>
                        </td>
                        <td style="padding:14px 14px;">
                            <span style="background:#ecfdf5; color:#065f46; font-size:11.5px; font-weight:600; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                                <i class="far fa-clock"></i> <?= htmlspecialchars($rv['contact_availability']) ?>
                            </span>
                        </td>
                        <td style="padding:14px 14px;">
                            <?php if ($rv['interest_level'] === 'hot'): ?>
                            <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;">🔥 HOT</span>
                            <?php elseif ($rv['interest_level'] === 'warm'): ?>
                            <span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;">WARM</span>
                            <?php else: ?>
                            <span style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;">COLD</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 14px; color:#475569; font-size:12.5px;">
                            <?= htmlspecialchars($rv['sales_rep_name']) ?>
                        </td>
                        <td style="padding:14px 14px;">
                            <span style="font-size:11.5px; font-weight:600; text-transform:capitalize; color:#334155;">
                                <?= htmlspecialchars($rv['status']) ?>
                            </span>
                        </td>
                        <td style="padding:14px 18px; text-align:right;">
                            <div style="display:inline-flex; gap:6px;">
                                <a href="https://wa.me/<?= $clean_wa ?>?text=<?= $wa_msg ?>" target="_blank" 
                                   style="background:#25D366; color:#fff; font-size:12px; font-weight:700; padding:5px 10px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="WhatsApp Reach Out">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </a>
                                <a href="<?= ($role==='sales'?'sales_visits.php':'sales_response_unit.php?highlight='.$rv['id']) ?>" 
                                   style="background:#f1f5f9; color:#334155; font-size:12px; padding:5px 9px; border-radius:6px; text-decoration:none;" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            <?php while($task = mysqli_fetch_assoc($task_result)): ?>
            <div class="table-row" style="grid-template-columns:2.5fr 1fr 1fr 1.2fr 0.5fr;" id="admin-task-<?= $task['id'] ?>">
                <div>
                    <strong class="task-title" style="display:block;font-size:14px;margin-bottom:3px;"><?= htmlspecialchars($task['title']) ?></strong>
                    <span style="font-size:12.5px;color:#888;"><?= htmlspecialchars(substr($task['description'],0,60)) ?><?= strlen($task['description'])>60?'…':'' ?></span>
                </div>
                <span data-label="Priority">
                    <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?></span>
                </span>
                <span data-label="Assignee">
                    <?php if (!empty($task['assigned_to'])): ?>
                    <span class="assignee-chip"><i class="fas fa-user"></i> User #<?= $task['assigned_to'] ?></span>
                    <?php else: ?>
                    <span style="color:#aaa;font-size:13px;">—</span>
                    <?php endif; ?>
                </span>
                <span data-label="Due" style="font-size:13px;color:#888;">⏰ <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                <span>
                    <input type="checkbox" class="task-status"
                        data-task-id="<?= $task['id'] ?>"
                        <?= $task['status'] === 'Completed' ? 'checked' : '' ?>
                        style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent);">
                </span>
            </div>
            <?php endwhile; ?>
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
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card" style="background:#fff; border-radius:12px; padding:20px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; background:#e0f2fe; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#0284c7; font-size:20px;"><i class="fas fa-tasks"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#1e293b;"><?= intval($proj_stats['total']) ?></div>
                        <div style="font-size:13px; color:#64748b; font-weight:600;">Total Projects</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background:#fff; border-radius:12px; padding:20px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; background:#ecfdf5; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#10b981; font-size:20px;"><i class="fas fa-spinner"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#1e293b;"><?= intval($proj_stats['active']) ?></div>
                        <div style="font-size:13px; color:#64748b; font-weight:600;">In Progress</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background:#fff; border-radius:12px; padding:20px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; background:#fef3c7; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:20px;"><i class="fas fa-check-double"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#1e293b;"><?= intval($proj_stats['completed']) ?></div>
                        <div style="font-size:13px; color:#64748b; font-weight:600;">Completed Projects</div>
                    </div>
                </div>
            </div>
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

    <?php else: ?>
    <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="user-card">
                    <div class="user-avatar"><i class="fas fa-user-tie"></i>‍<i class="fas fa-briefcase"></i></div>
                    <div class="user-name"><?php echo htmlspecialchars($name); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($designation ?? ''); ?></div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="notice-board">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-bullhorn"></i> Notice Board</h3>
                        <span class="badge-count"><?php echo $notice_cout; ?></span>
                    </div>
                    <div class="notices-body">
                        <?php mysqli_data_seek($notice_result, 0); while($data = mysqli_fetch_assoc($notice_result)): 
                            $notice_time = isset($data['created_at']) ? date('M d, g:i A', strtotime($data['created_at'])) : 'Today, 2:30 PM';
                        ?>
                        <a href="notice_details.php?id=<?= $data['notice_id'] ?>" class="notice-item" style="display:block; text-decoration:none;">
                            <div class="notice-title"><?= htmlspecialchars($data['notice_title']) ?></div>
                            <div class="notice-time"><?= $notice_time ?></div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="tasks-card">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-check-circle"></i> Your Tasks</h3>
                        <span class="badge-count"><?php echo $task_count; ?> Active</span>
                    </div>
                    <?php while($task = mysqli_fetch_assoc($task_result)): ?>
                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" class="task-status" data-task-id="<?= $task['id'] ?>" <?= $task['status'] === 'Completed' ? 'checked' : '' ?>>
                        </div>
                        <div class="task-content">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                            <div class="task-meta">
                                <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?> Priority</span>
                                <span class="task-deadline">⏰ Due: <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="resources-card">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-book"></i> Resources & Links</h3>
                        <span class="badge-count"><?php echo $resource_count; ?> Items</span>
                    </div>
                    <div class="resources-list">
                        <?php while($resource = mysqli_fetch_assoc($resource_result)): ?>
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
        var $taskItem = $checkbox.closest('.task-item, .table-row');

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
</script>

<?php include('dashboard_footer.php'); ?>); ?>