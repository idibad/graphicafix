<?php
include 'dashboard_header.php';

// Check permissions
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'teacher', 'user', 'manager'])) {
    header('location:index.php'); exit;
}

$project_id = $_GET['id'] ?? 0;

// Fetch project details
$project_query = "SELECT p.*, c.client_name 
                  FROM projects p 
                  LEFT JOIN clients c ON p.client_id = c.id 
                  WHERE p.id = ?";
$stmt = $conn->prepare($project_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<script>alert('Project not found');window.location='manage_projects.php';</script>";
    exit;
}

// Fetch team members
$team_query = "SELECT u.user_id, u.name, u.email, u.role 
               FROM project_team pt 
               JOIN users u ON pt.user_id = u.user_id 
               WHERE pt.project_id = ?";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$team_members = $stmt->get_result();

// Fetch gallery images
$gallery_query = "SELECT * FROM project_gallery WHERE project_id = ? ORDER BY sort_order";
$stmt = $conn->prepare($gallery_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$gallery = $stmt->get_result();

// Fetch project files
$files_query = "SELECT * FROM project_files WHERE project_id = ? AND file_type != 'thumbnail' ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($files_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$files = $stmt->get_result();

// Fetch project statistics for quick actions report
$stmt_task_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM tasks 
    WHERE project_id = ?
");
$stmt_task_stats->bind_param("i", $project_id);
$stmt_task_stats->execute();
$task_stats = $stmt_task_stats->get_result()->fetch_assoc();
$total_tasks_count = intval($task_stats['total_tasks']);
$completed_tasks_count = intval($task_stats['completed_tasks']);
$task_completion_rate = $total_tasks_count > 0 ? round(($completed_tasks_count / $total_tasks_count) * 100) : 0;

$stmt_inv_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_invoices,
        COALESCE(SUM(total_amount), 0) as total_billed,
        COALESCE(SUM(amount_paid), 0) as total_paid,
        COALESCE(SUM(amount_due), 0) as total_due
    FROM invoices 
    WHERE project_id = ?
");
$stmt_inv_stats->bind_param("i", $project_id);
$stmt_inv_stats->execute();
$inv_stats = $stmt_inv_stats->get_result()->fetch_assoc();
$total_invoices_count = intval($inv_stats['total_invoices']);
$total_billed_amount = floatval($inv_stats['total_billed']);
$total_paid_amount = floatval($inv_stats['total_paid']);
$total_due_amount = floatval($inv_stats['total_due']);

$stmt_curr = $conn->prepare("SELECT currency FROM invoices WHERE project_id = ? LIMIT 1");
$stmt_curr->bind_param("i", $project_id);
$stmt_curr->execute();
$curr_res = $stmt_curr->get_result()->fetch_assoc();
$primary_currency = $curr_res ? $curr_res['currency'] : 'USD';

$stmt_comm_count = $conn->prepare("SELECT COUNT(*) as total_comments FROM project_comments WHERE project_id = ?");
$stmt_comm_count->bind_param("i", $project_id);
$stmt_comm_count->execute();
$total_comments_count = intval($stmt_comm_count->get_result()->fetch_assoc()['total_comments']);

$stmt_team_count = $conn->prepare("SELECT COUNT(*) as total_team FROM project_team WHERE project_id = ?");
$stmt_team_count->bind_param("i", $project_id);
$stmt_team_count->execute();
$total_team_count = intval($stmt_team_count->get_result()->fetch_assoc()['total_team']);

// Role Badge Helper
function getRoleBadge($role) {
    $r = strtolower($role);
    if ($r === 'admin')      return ['bg' => '#fff4e5', 'text' => '#e17055'];
    if ($r === 'pm')         return ['bg' => '#fce7f3', 'text' => '#db2777'];
    if ($r === 'teacher')    return ['bg' => '#e0f2fe', 'text' => '#0284c7'];
    if ($r === 'student')    return ['bg' => '#d7f8b8', 'text' => '#2b7a2b'];
    if ($r === 'user')       return ['bg' => '#f3e8ff', 'text' => '#7c3aed'];
    if ($r === 'client')     return ['bg' => '#e0f2fe', 'text' => '#0284c7'];
    if ($r === 'client_sub') return ['bg' => '#f1f5f9', 'text' => '#475569'];
    return ['bg' => '#f3f4f6', 'text' => '#4b5563'];
}

// Status and Priority styling
function getStatusClass($status) {
    $classes = [
        'Not Started' => 'status-not-started',
        'In Progress' => 'status-in-progress',
        'Completed' => 'status-completed',
        'On Hold' => 'status-on-hold'
    ];
    return $classes[$status] ?? 'status-default';
}

function getPriorityClass($priority) {
    $classes = [
        'Low' => 'priority-low',
        'Medium' => 'priority-medium',
        'High' => 'priority-high'
    ];
    return $classes[$priority] ?? 'priority-medium';
}

// High-visibility task status style helper
function getTaskStatusStyle($status) {
    $s = trim($status);
    switch ($s) {
        case 'Completed':
            return [
                'bg' => '#ecfdf5',
                'text' => '#047857',
                'border' => '#a7f3d0',
                'icon' => 'fas fa-check-circle',
                'label' => 'Completed',
                'dot' => '#10b981'
            ];
        case 'In Progress':
            return [
                'bg' => '#eff6ff',
                'text' => '#1d4ed8',
                'border' => '#93c5fd',
                'icon' => 'fas fa-spinner fa-spin',
                'label' => 'In Progress',
                'dot' => '#3b82f6'
            ];
        case 'In Review':
            return [
                'bg' => '#faf5ff',
                'text' => '#7e22ce',
                'border' => '#d8b4fe',
                'icon' => 'fas fa-eye',
                'label' => 'In Review',
                'dot' => '#8b5cf6'
            ];
        case 'Cancelled':
            return [
                'bg' => '#fef2f2',
                'text' => '#b91c1c',
                'border' => '#fca5a5',
                'icon' => 'fas fa-times-circle',
                'label' => 'Cancelled',
                'dot' => '#ef4444'
            ];
        case 'Pending':
        case 'Not Started':
        default:
            return [
                'bg' => '#fffbeb',
                'text' => '#b45309',
                'border' => '#fde68a',
                'icon' => 'far fa-clock',
                'label' => 'Pending',
                'dot' => '#f59e0b'
            ];
    }
}

// Fetch ONLY active users for the task filter selector
$active_users_stmt = $conn->prepare("
    SELECT user_id, name, role 
    FROM users 
    WHERE (status = 'active' OR status IS NULL OR status = '') 
      AND role NOT IN ('client', 'client_sub', 'student') 
    ORDER BY name ASC
");
$active_users_stmt->execute();
$active_task_users = $active_users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Form Processing Actions ──────────────────────────────────────────────────
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT user_id, name, role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$current_user_data = $user_stmt->get_result()->fetch_assoc();
$user_id = $current_user_data['user_id'];
$role = strtolower($current_user_data['role']);
$name = $current_user_data['name'];
$can_edit = (in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager']));

// 1. Manual Progress Override
if (isset($_POST['manual_progress_override']) && $can_edit) {
    $new_progress = intval($_POST['progress_override']);
    if ($new_progress >= 0 && $new_progress <= 100) {
        $stmt_upd = $conn->prepare("UPDATE projects SET progress = ?, updated_at = NOW() WHERE id = ?");
        $stmt_upd->bind_param("ii", $new_progress, $project_id);
        if ($stmt_upd->execute()) {
            require_once __DIR__ . '/../core/functions.php';
            notifyClientOnProjectProgress($conn, $project_id, $new_progress);
            echo "<script>alert('Progress updated and client notified!'); window.location='project_details.php?id={$project_id}';</script>";
            exit;
        }
    }
}

// 2. Add Project Comment
if (isset($_POST['add_project_comment'])) {
    $comment_text = trim($_POST['comment_text'] ?? '');
    if (!empty($comment_text)) {
        $file_path = null;
        if (!empty($_FILES['comment_file']['name'])) {
            $upload_dir = "uploads/projects/" . $project_id . "/comments/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_path = $upload_dir . time() . "_" . basename($_FILES['comment_file']['name']);
            move_uploaded_file($_FILES['comment_file']['tmp_name'], $file_path);
            
            // Also log it in project_files
            $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_type, title, uploaded_by) VALUES (?, ?, 'document', ?, ?)");
            $title = "Comment Attachment - " . basename($_FILES['comment_file']['name']);
            $stmt_file->bind_param("issi", $project_id, $file_path, $title, $user_id);
            $stmt_file->execute();
        }
        
        $stmt_comm = $conn->prepare("INSERT INTO project_comments (project_id, user_id, comment_text, file_path) VALUES (?, ?, ?, ?)");
        $stmt_comm->bind_param("iiss", $project_id, $user_id, $comment_text, $file_path);
        if ($stmt_comm->execute()) {
            // Notify client ONLY if comment is posted by admin
            if (in_array($role, ['super_admin', 'superadmin', 'admin'])) {
                require_once __DIR__ . '/../core/functions.php';
                $stmt_proj = $conn->prepare("SELECT p.project_name, c.email as client_email, p.client_id FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
                $stmt_proj->bind_param("i", $project_id);
                $stmt_proj->execute();
                $proj = $stmt_proj->get_result()->fetch_assoc();
                if ($proj) {
                    $recipients = [];
                    if (!empty($proj['client_email'])) $recipients[] = $proj['client_email'];
                    if (!empty($proj['client_id'])) {
                        $u_stmt = $conn->prepare("SELECT email FROM users WHERE client_id = ? AND role = 'client'");
                        $u_stmt->bind_param("i", $proj['client_id']);
                        $u_stmt->execute();
                        $u_res = $u_stmt->get_result();
                        while ($u_row = $u_res->fetch_assoc()) {
                            if (!empty($u_row['email']) && !in_array($u_row['email'], $recipients)) {
                                $recipients[] = $u_row['email'];
                            }
                        }
                    }
                    if (!empty($recipients)) {
                        require_once __DIR__ . '/../core/task_email_helper.php';
                        $subject = "New Comment on Project: " . $proj['project_name'];
                        $heading = "New Project Comment";
                        $body = "<p>A new comment was posted on your project <strong>" . htmlspecialchars($proj['project_name']) . "</strong> by Admin (" . htmlspecialchars($name) . "):</p>
                                 <blockquote style='background: #f8fafc; border-left: 4px solid #024442; padding: 12px; margin: 16px 0;'>" . nl2br(htmlspecialchars($comment_text)) . "</blockquote>";
                        foreach ($recipients as $to_email) {
                            sendClientEmail($to_email, $subject, $heading, $body, $project_id);
                        }
                    }
                }
            }
            echo "<script>alert('Comment added!'); window.location='project_details.php?id={$project_id}';</script>";
            exit;
        }
    }
}

// 3. Add Project Task
if (isset($_POST['create_project_task']) && ($can_edit || $role === 'user')) {
    $task_title = trim($_POST['task_title'] ?? '');
    $task_desc = trim($_POST['task_desc'] ?? '');
    $priority = $_POST['task_priority'] ?? 'Medium';
    $due_date = !empty($_POST['task_due_date']) ? $_POST['task_due_date'] : null;
    $assigned_to = !empty($_POST['task_assigned_to']) ? intval($_POST['task_assigned_to']) : null;
    
    if (!empty($task_title)) {
        $stmt_task = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, created_by, project_id) VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?)");
        $stmt_task->bind_param("ssssiii", $task_title, $task_desc, $priority, $due_date, $assigned_to, $user_id, $project_id);
        if ($stmt_task->execute()) {
            // Task added without overwriting project progress (progress is set manually by admin)
            if ($assigned_to > 0) {
                $u_stmt = $conn->query("SELECT name, email FROM users WHERE user_id = $assigned_to");
                if ($u_row = $u_stmt->fetch_assoc()) {
                    if (!empty($u_row['email'])) {
                        require_once __DIR__ . '/../core/task_email_helper.php';
                        $task_id_new = $conn->insert_id;
                        $heading = "New Task Assigned: " . $task_title;
                        $subject = $heading;
                        $priorityBadgeColor = ($priority === 'High' ? '#ef4444' : ($priority === 'Medium' ? '#f59e0b' : '#3b82f6'));
                        $body = "<p>Hello " . htmlspecialchars($u_row['name']) . ",</p>
                                 <p>You have been assigned a new task under project <strong>" . htmlspecialchars($project['project_name']) . "</strong>.</p>
                                 <div style='background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px;'>
                                     <strong>Title:</strong> " . htmlspecialchars($task_title) . "<br>
                                     <strong>Priority:</strong> <span style='background: {$priorityBadgeColor}; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px;'>$priority</span><br>
                                     <strong>Due Date:</strong> " . ($due_date ?: 'N/A') . "
                                 </div>";
                        sendTaskEmail($u_row['email'], $subject, $heading, $body, $task_id_new);
                    }
                }
            }
            echo "<script>alert('Task added successfully!'); window.location='project_details.php?id={$project_id}';</script>";
            exit;
        }
    }
}

// 4. Approve/Reject Manual Payment screenshot
if (isset($_GET['invoice_action'], $_GET['inv_id']) && $can_edit) {
    $inv_id = intval($_GET['inv_id']);
    $inv_action = $_GET['invoice_action'];
    
    if ($inv_action === 'approve') {
        $conn->query("UPDATE invoices SET status='paid', amount_paid=total_amount, amount_due=0, paid_at=NOW() WHERE id={$inv_id}");
        echo "<script>alert('Payment approved and marked as Paid!'); window.location='project_details.php?id={$project_id}';</script>";
        exit;
    } elseif ($inv_action === 'reject') {
        $conn->query("UPDATE invoices SET status='sent', payment_screenshot=NULL, payment_uploaded_at=NULL WHERE id={$inv_id}");
        echo "<script>alert('Payment proof rejected.'); window.location='project_details.php?id={$project_id}';</script>";
        exit;
    }
}
?>


<div class="height-100" >
    <!-- Project Header -->
    <div class="project-details-header" style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div class="header-details-top" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom: 20px;">
            <div class="header-content">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#024442;"><?= htmlspecialchars($project['project_name']) ?></h1>
                <div class="client-name" style="margin-top:6px; color:#64748b; font-size:14.5px; font-weight:500;">
                    <i class="fas fa-building" style="color: #024442;"></i> <?= htmlspecialchars($project['client_name']) ?>
                </div>
            </div>
            <div class="header-actions" style="display:flex; gap:10px;">
                <button class="btn btn-secondary" onclick="window.location='manage_projects.php'" style="padding: 8px 16px; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    ← Back
                </button>
                <button class="btn btn-secondary" onclick="window.location='edit_project.php?id=<?= $project_id ?>'" style="padding: 8px 16px; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-pencil-alt"></i> Edit
                </button>
                <button class="btn btn-primary" style="padding: 8px 16px; font-size: 13.5px; font-weight: 600; background: #024442; border: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-upload"></i> Share
                </button>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Status</span>
                <span class="badge <?= getStatusClass($project['status']) ?>">
                    <?= htmlspecialchars($project['status']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Priority</span>
                <span class="badge <?= getPriorityClass($project['priority']) ?>">
                    <?= htmlspecialchars($project['priority']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Start Date</span>
                <span class="meta-value">
                    <?= !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">End Date</span>
                <span class="meta-value">
                    <?= !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Visibility</span>
                <span class="meta-value" style="text-transform: capitalize;">
                    <?= htmlspecialchars($project['visibility']) ?>
                </span>
            </div>
        </div>

        <?php if (!empty($project['start_date']) && !empty($project['end_date'])): 
            $start = strtotime($project['start_date']);
            $end = strtotime($project['end_date']);
            $now = time();
            $total_days = max(1, ($end - $start) / 86400);
            $elapsed_days = max(0, min($total_days, ($now - $start) / 86400));
            $progress = min(100, ($elapsed_days / $total_days) * 100);
        ?>
        <div class="progress-section">
            <div class="progress-header">
                <span class="progress-label">Project Timeline</span>
                <span class="progress-percent"><?= round($progress) ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Description -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-edit"></i> Project Description</h2>
                </div>
                <?php if (!empty($project['description'])): ?>
                    <div class="description-text">
                        <?= nl2br(htmlspecialchars($project['description'])) ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
                        <div>No description provided</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($project['public_description'])): ?>
            <!-- Public Description -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-eye"></i>️ Public Description</h2>
                </div>
                <div class="description-text">
                    <?= strip_tags($project['public_description']) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($project['internal_notes'])): ?>
            <!-- Internal Notes -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-lock"></i> Internal Notes</h2>
                </div>
                <div class="description-text" style="background: var(--warning-light); padding: 16px; border-radius: var(--radius);">
                    <?= nl2br(htmlspecialchars($project['internal_notes'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gallery -->
            <?php if ($gallery->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-image"></i>️ Project Gallery</h2>
                    <button class="card-action">⋮</button>
                </div>
                <div class="gallery-grid">
                    <?php while ($img = $gallery->fetch_assoc()): ?>
                        <div class="gallery-item">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Gallery image">
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Files -->
            <?php if ($files->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-paperclip"></i> Project Files</h2>
                    <button class="card-action">⋮</button>
                </div>
                <div class="file-list">
                    <?php while ($file = $files->fetch_assoc()): 
                        $file_ext = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                        $file_name = basename($file['file_path']);
                    ?>
                        <div class="file-item">
                            <div class="file-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="file-info">
                                <div class="file-name"><?= htmlspecialchars($file_name) ?></div>
                                <div class="file-meta">
                                    <?= strtoupper($file_ext) ?> • 
                                    <?= !empty($file['uploaded_at']) ? date('M d, Y', strtotime($file['uploaded_at'])) : 'Unknown' ?>
                                </div>
                            </div>
                            <button class="file-download" onclick="window.open('<?= htmlspecialchars($file['file_path']) ?>', '_blank')">
                                ⬇️
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Project Tasks Card -->
            <?php
            $stmt_tasks = $conn->prepare("
                SELECT t.*, u.name as assigned_name, u.role as assigned_role 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_to = u.user_id 
                WHERE t.project_id = ? 
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
            ");
            $stmt_tasks->bind_param("i", $project_id);
            $stmt_tasks->execute();
            $proj_tasks_res = $stmt_tasks->get_result();
            $all_project_tasks = $proj_tasks_res->fetch_all(MYSQLI_ASSOC);

            // Compute status count breakdown
            $status_counts = [
                'all' => count($all_project_tasks),
                'Pending' => 0,
                'In Progress' => 0,
                'In Review' => 0,
                'Completed' => 0,
                'Cancelled' => 0
            ];
            foreach ($all_project_tasks as $tk_item) {
                $s_norm = $tk_item['status'];
                if ($s_norm === 'Not Started') $s_norm = 'Pending';
                if (isset($status_counts[$s_norm])) {
                    $status_counts[$s_norm]++;
                } else {
                    $status_counts['Pending']++;
                }
            }
            ?>
            <div class="card" style="margin-top: 20px;">
                <!-- Card Header with Actions -->
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <h2 class="card-title" style="margin:0; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-clipboard-list" style="color:#024442;"></i> Project Tasks
                        </h2>
                        <span class="badge" id="taskTotalCountBadge" style="background:#e0f2fe; color:#0284c7; font-weight:800; font-size:12px; padding:3px 10px; border-radius:12px;">
                            <?= $status_counts['all'] ?>
                        </span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <!-- PDF Export Button -->
                        <a href="export_project_tasks_pdf.php?id=<?= $project_id ?>" id="btnExportTaskPdf" target="_blank" class="btn btn-sm" style="background:#fef2f2; color:#dc2626; border:1.5px solid #fca5a5; font-weight:700; padding:6px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:13px; box-shadow:0 1px 2px rgba(0,0,0,0.03); transition:all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'" title="Download project tasks list as a PDF report">
                            <i class="fas fa-file-pdf"></i> Export Tasks PDF
                        </a>
                        <?php if ($can_edit || $role === 'user'): ?>
                            <button class="btn btn-primary btn-sm" onclick="document.getElementById('taskFormModal').style.display='block'" style="background:#024442; border:none; padding:6px 14px; font-weight:700; font-size:13px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                <i class="fas fa-plus"></i> Add Task
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Quick Pills Bar (High Visibility Overview) -->
                <div style="padding: 14px 16px 4px 16px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; background: #fafbfc;">
                    <span style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 4px;">Status:</span>
                    <button type="button" class="status-quick-pill active" onclick="quickFilterStatus('all', this)" id="pillStatusAll" style="border: 1px solid #cbd5e1; background: #024442; color: #ffffff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        All <span class="badge" style="background: rgba(255,255,255,0.25); color: #fff; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillAll"><?= $status_counts['all'] ?></span>
                    </button>
                    <button type="button" class="status-quick-pill" onclick="quickFilterStatus('Pending', this)" style="border: 1px solid #fcd34d; background: #fffbeb; color: #b45309; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        ⏳ Pending <span class="badge" style="background: #fde68a; color: #92400e; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillPending"><?= $status_counts['Pending'] ?></span>
                    </button>
                    <button type="button" class="status-quick-pill" onclick="quickFilterStatus('In Progress', this)" style="border: 1px solid #93c5fd; background: #eff6ff; color: #1d4ed8; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        ⚡ In Progress <span class="badge" style="background: #bfdbfe; color: #1e40af; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillInProgress"><?= $status_counts['In Progress'] ?></span>
                    </button>
                    <button type="button" class="status-quick-pill" onclick="quickFilterStatus('In Review', this)" style="border: 1px solid #d8b4fe; background: #faf5ff; color: #7e22ce; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        🔍 In Review <span class="badge" style="background: #e9d5ff; color: #6b21a8; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillInReview"><?= $status_counts['In Review'] ?></span>
                    </button>
                    <button type="button" class="status-quick-pill" onclick="quickFilterStatus('Completed', this)" style="border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        ✓ Completed <span class="badge" style="background: #bbf7d0; color: #065f46; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillCompleted"><?= $status_counts['Completed'] ?></span>
                    </button>
                    <?php if ($status_counts['Cancelled'] > 0): ?>
                    <button type="button" class="status-quick-pill" onclick="quickFilterStatus('Cancelled', this)" style="border: 1px solid #fca5a5; background: #fef2f2; color: #b91c1c; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        ✕ Cancelled <span class="badge" style="background: #fecdd3; color: #991b1b; font-size: 10.5px; padding: 1px 6px; border-radius: 10px;" id="countPillCancelled"><?= $status_counts['Cancelled'] ?></span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Filter Toolbar Controls -->
                <div style="padding: 14px 16px; background: #ffffff; border-bottom: 1px solid #e2e8f0;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <!-- Filter 1: Active Users Selector (MUST) -->
                        <div style="min-width: 190px; flex: 1;">
                            <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">
                                <i class="fas fa-user-check" style="color: #024442;"></i> Assignee (Active):
                            </label>
                            <select id="taskFilterUser" class="form-select form-select-sm" onchange="applyTaskFilters()" style="font-size: 12.5px; padding: 6px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; width: 100%;">
                                <option value="all">👥 All Assignees</option>
                                <option value="unassigned">👤 Unassigned Tasks</option>
                                <optgroup label="Active Users">
                                    <?php foreach ($active_task_users as $usr): ?>
                                        <option value="<?= $usr['user_id'] ?>">
                                            <?= htmlspecialchars($usr['name']) ?> (<?= ucfirst($usr['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>

                        <!-- Filter 2: Status Selector -->
                        <div style="min-width: 145px; flex: 1;">
                            <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">
                                <i class="fas fa-tasks" style="color: #024442;"></i> Status:
                            </label>
                            <select id="taskFilterStatus" class="form-select form-select-sm" onchange="applyTaskFilters()" style="font-size: 12.5px; padding: 6px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; width: 100%;">
                                <option value="all">🎯 All Statuses</option>
                                <option value="Pending">⏳ Pending</option>
                                <option value="In Progress">⚡ In Progress</option>
                                <option value="In Review">🔍 In Review</option>
                                <option value="Completed">✓ Completed</option>
                                <option value="Cancelled">✕ Cancelled</option>
                            </select>
                        </div>

                        <!-- Filter 3: Priority Selector -->
                        <div style="min-width: 130px; flex: 1;">
                            <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">
                                <i class="fas fa-flag" style="color: #024442;"></i> Priority:
                            </label>
                            <select id="taskFilterPriority" class="form-select form-select-sm" onchange="applyTaskFilters()" style="font-size: 12.5px; padding: 6px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; width: 100%;">
                                <option value="all">🔥 All Priorities</option>
                                <option value="High">High Priority</option>
                                <option value="Medium">Medium Priority</option>
                                <option value="Low">Low Priority</option>
                            </select>
                        </div>

                        <!-- Filter 4: Keyword Search -->
                        <div style="min-width: 200px; flex: 1.5;">
                            <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">
                                <i class="fas fa-search" style="color: #024442;"></i> Search:
                            </label>
                            <div style="position: relative;">
                                <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px;"></i>
                                <input type="text" id="taskSearchInput" placeholder="Search title or description..." class="form-control form-control-sm" oninput="applyTaskFilters()" style="font-size: 12.5px; padding: 6px 10px 6px 30px; border: 1.5px solid #cbd5e1; border-radius: 6px; width: 100%;">
                            </div>
                        </div>

                        <!-- Filter Reset Button -->
                        <div style="align-self: flex-end; padding-bottom: 1px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="resetTaskFilters()" title="Reset all task filters" style="background: #f1f5f9; border: 1.5px solid #cbd5e1; color: #475569; padding: 6px 14px; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px;">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Filter Counter -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 12px; color: #64748b;">
                        <div>
                            Showing <strong id="visibleTaskCount" style="color: #0f172a;"><?= count($all_project_tasks) ?></strong> of <strong id="totalTaskCount" style="color: #0f172a;"><?= count($all_project_tasks) ?></strong> tasks
                        </div>
                        <div id="filterFeedbackText" style="font-style: italic; color: #024442; font-weight: 600;"></div>
                    </div>
                </div>

                <!-- Tasks Table -->
                <div class="table-responsive" style="padding: 0;">
                    <?php if (count($all_project_tasks) > 0): ?>
                    <table class="table" id="projectTasksTable" style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 0;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc; color: #475569;">
                                <th style="padding: 12px 14px; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px;">Task Title</th>
                                <th style="padding: 12px 14px; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px;">Assigned To</th>
                                <th style="padding: 12px 14px; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px;">Priority</th>
                                <th style="padding: 12px 14px; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px;">Due Date</th>
                                <th style="padding: 12px 14px; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="projectTasksTableBody">
                            <?php 
                            foreach ($all_project_tasks as $tk): 
                                $priColor = ($tk['priority'] === 'High' ? '#ef4444' : ($tk['priority'] === 'Medium' ? '#f59e0b' : '#3b82f6'));
                                $priBg = ($tk['priority'] === 'High' ? '#fef2f2' : ($tk['priority'] === 'Medium' ? '#fffbeb' : '#eff6ff'));
                                $priBorder = ($tk['priority'] === 'High' ? '#fca5a5' : ($tk['priority'] === 'Medium' ? '#fde68a' : '#bfdbfe'));
                                
                                $statusStyle = getTaskStatusStyle($tk['status']);
                                $assigneeId = intval($tk['assigned_to'] ?? 0);
                                $assigneeName = !empty($tk['assigned_name']) ? $tk['assigned_name'] : 'Unassigned';
                                $assigneeRole = !empty($tk['assigned_role']) ? ucfirst($tk['assigned_role']) : '';
                                
                                $isOverdue = (!empty($tk['due_date']) && strtotime($tk['due_date']) < strtotime(date('Y-m-d')) && $tk['status'] !== 'Completed');
                                $searchData = strtolower($tk['title'] . ' ' . ($tk['description'] ?? '') . ' ' . $assigneeName);
                            ?>
                            <tr class="task-table-row" 
                                id="task-row-<?= $tk['id'] ?>"
                                data-task-id="<?= $tk['id'] ?>"
                                data-user-id="<?= $assigneeId ?>"
                                data-status="<?= htmlspecialchars($tk['status']) ?>"
                                data-priority="<?= htmlspecialchars($tk['priority']) ?>"
                                data-search="<?= htmlspecialchars($searchData) ?>"
                                style="border-bottom: 1px solid #f1f5f9; border-left: 4px solid <?= $statusStyle['border'] ?>; transition: all 0.15s ease;">
                                
                                <!-- Task Details -->
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($tk['title']) ?></div>
                                    <?php if (!empty($tk['description'])): ?>
                                        <div style="font-size: 12px; color: #64748b; margin-top: 3px; max-width: 320px; line-height: 1.4;">
                                            <?= htmlspecialchars(mb_strimwidth($tk['description'], 0, 90, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Assigned To -->
                                <td style="padding: 12px 14px;">
                                    <?php if ($assigneeId > 0): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 30px; height: 30px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <?= strtoupper(substr($assigneeName, 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #1e293b; font-size: 13.5px;"><?= htmlspecialchars($assigneeName) ?></div>
                                                <?php if (!empty($assigneeRole)): ?>
                                                    <div style="font-size: 11px; color: #94a3b8;"><?= htmlspecialchars($assigneeRole) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="far fa-user"></i> Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Priority -->
                                <td style="padding: 12px 14px;">
                                    <span class="badge" style="color: <?= $priColor ?>; background: <?= $priBg ?>; border: 1.5px solid <?= $priBorder ?>; padding: 4px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-flag" style="font-size: 10px;"></i> <?= htmlspecialchars($tk['priority']) ?>
                                    </span>
                                </td>

                                <!-- Due Date -->
                                <td style="padding: 12px 14px; white-space: nowrap;">
                                    <?php if (!empty($tk['due_date'])): ?>
                                        <div style="font-size: 13px; font-weight: 600; color: <?= $isOverdue ? '#dc2626' : '#334155' ?>; display: flex; align-items: center; gap: 6px;">
                                            <i class="far fa-calendar-alt" style="color: <?= $isOverdue ? '#dc2626' : '#94a3b8' ?>;"></i>
                                            <?= date('M d, Y', strtotime($tk['due_date'])) ?>
                                        </div>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 10px; padding: 1px 6px; border-radius: 8px; margin-top: 3px; display: inline-block;">
                                                Overdue
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 13px;">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status (High Visibility) -->
                                <td style="padding: 12px 14px;">
                                    <?php if ($can_edit || $role === 'user' || $tk['assigned_to'] == $user_id): ?>
                                        <!-- Interactive High-Visibility Status Dropdown -->
                                        <div class="task-status-dropdown-wrap" style="position: relative; display: inline-block;">
                                            <select class="form-select form-select-sm task-status-select" 
                                                    id="task-status-select-<?= $tk['id'] ?>"
                                                    data-task-id="<?= $tk['id'] ?>" 
                                                    onchange="onTaskStatusDropdownChange(this, <?= $tk['id'] ?>)" 
                                                    style="font-size: 12.5px; font-weight: 800; padding: 6px 28px 6px 12px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease; outline: none; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-color: <?= $statusStyle['bg'] ?>; color: <?= $statusStyle['text'] ?>; border: 1.5px solid <?= $statusStyle['border'] ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                                <option value="Pending" <?= ($tk['status'] === 'Pending' || $tk['status'] === 'Not Started') ? 'selected' : '' ?> style="background:#fff; color:#b45309; font-weight:700;">⏳ Pending</option>
                                                <option value="In Progress" <?= $tk['status'] === 'In Progress' ? 'selected' : '' ?> style="background:#fff; color:#1d4ed8; font-weight:700;">⚡ In Progress</option>
                                                <option value="In Review" <?= $tk['status'] === 'In Review' ? 'selected' : '' ?> style="background:#fff; color:#7e22ce; font-weight:700;">🔍 In Review</option>
                                                <option value="Completed" <?= $tk['status'] === 'Completed' ? 'selected' : '' ?> style="background:#fff; color:#047857; font-weight:700;">✓ Completed</option>
                                                <option value="Cancelled" <?= $tk['status'] === 'Cancelled' ? 'selected' : '' ?> style="background:#fff; color:#b91c1c; font-weight:700;">✕ Cancelled</option>
                                            </select>
                                            <i class="fas fa-chevron-down" id="task-status-icon-<?= $tk['id'] ?>" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 9px; pointer-events: none; color: <?= $statusStyle['text'] ?>; opacity: 0.85;"></i>
                                        </div>
                                    <?php else: ?>
                                        <!-- Read-Only High-Visibility Status Pill -->
                                        <span class="badge" style="background: <?= $statusStyle['bg'] ?>; color: <?= $statusStyle['text'] ?>; border: 1.5px solid <?= $statusStyle['border'] ?>; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="<?= $statusStyle['icon'] ?>"></i> <?= htmlspecialchars($statusStyle['label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- Empty Search/Filter Row -->
                            <tr id="taskFilterEmptyRow" style="display: none;">
                                <td colspan="5" style="text-align: center; padding: 36px 16px; color: #94a3b8; background: #fff;">
                                    <div style="font-size: 26px; margin-bottom: 8px;">🔍</div>
                                    <div style="font-weight: 700; color: #334155; font-size: 15px;">No tasks match the selected filters.</div>
                                    <div style="font-size: 13px; color: #64748b; margin-top: 4px;">Try changing the assignee, status, priority, or clearing search keywords.</div>
                                    <button type="button" class="btn btn-sm" onclick="resetTaskFilters()" style="margin-top: 14px; background: #024442; color: #ffffff; border: none; padding: 6px 18px; border-radius: 6px; font-weight: 700; font-size: 12.5px;">
                                        Clear All Filters
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; padding: 36px 20px;">
                            <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
                            <div style="font-weight: 700; color: #475569; font-size: 15px;">No tasks created for this project yet.</div>
                            <?php if ($can_edit || $role === 'user'): ?>
                                <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Click "+ Add Task" above to create your first task for this project.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if(in_array($role, ['super_admin', 'superadmin', 'admin'])): ?>
            <!-- Project Invoices & Payments Card -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Project Invoices & Receipts</h2>
                </div>
                <div class="table-responsive" style="padding: 16px;">
                    <?php
                    $stmt_invs = $conn->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY created_at DESC");
                    $stmt_invs->bind_param("i", $project_id);
                    $stmt_invs->execute();
                    $proj_invs = $stmt_invs->get_result();
                    if ($proj_invs->num_rows > 0):
                    ?>
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                <th style="padding: 10px;">Invoice #</th>
                                <th style="padding: 10px;">Due Date</th>
                                <th style="padding: 10px;">Total Amount</th>
                                <th style="padding: 10px;">Status</th>
                                <th style="padding: 10px;">Payment Proof</th>
                                <th style="padding: 10px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($inv = $proj_invs->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px; font-weight: 600;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                <td style="padding: 10px;"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                                <td style="padding: 10px;"><?= number_format($inv['total_amount'], 2) ?> <?= $inv['currency'] ?></td>
                                <td style="padding: 10px;">
                                    <span class="badge" style="text-transform: capitalize; background: <?= $inv['status'] === 'paid' ? '#ecfdf5;color:#10b981;' : ($inv['status'] === 'overdue' ? '#fef2f2;color:#ef4444;' : '#fef3c7;color:#d97706;') ?>"><?= htmlspecialchars($inv['status']) ?></span>
                                </td>
                                <td style="padding: 10px;">
                                    <?php if ($inv['payment_screenshot']): ?>
                                        <a href="javascript:void(0)" onclick="showScreenshot('<?= htmlspecialchars($inv['payment_screenshot']) ?>')" style="color: #024442; text-decoration: underline; font-weight: 600;">
                                            <i class="fas fa-image"></i> View Screenshot
                                        </a>
                                        <br><small style="color: #64748b;">Uploaded: <?= date('M d, Y H:i', strtotime($inv['payment_uploaded_at'])) ?></small>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">No payment proof</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px;">
                                    <div style="display: flex; gap: 8px;">
                                        <a href="generate_invoice_pdf.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm" target="_blank" title="Download Invoice/Receipt">
                                            ⬇️ PDF
                                        </a>
                                        <?php if ($inv['payment_screenshot'] && $inv['status'] !== 'paid' && $can_edit): ?>
                                            <a href="project_details.php?id=<?= $project_id ?>&inv_id=<?= $inv['id'] ?>&invoice_action=approve" class="btn btn-success btn-sm" onclick="return confirm('Approve payment for this invoice?')" style="background: #10b981; color: white; border: none; padding: 2px 8px; border-radius: 4px; text-decoration: none; font-size:12px;">Approve</a>
                                            <a href="project_details.php?id=<?= $project_id ?>&inv_id=<?= $inv['id'] ?>&invoice_action=reject" class="btn btn-danger btn-sm" onclick="return confirm('Reject this screenshot proof?')" style="background: #ef4444; color: white; border: none; padding: 2px 8px; border-radius: 4px; text-decoration: none; font-size:12px;">Reject</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; padding: 20px;">No invoices generated for this project yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif;?>

            <!-- Project Comments / Discussion Card -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-comments"></i> Project Discussion</h2>
                </div>
                <div style="padding: 16px;">
                    <!-- Add Comment Form -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <input type="hidden" name="add_project_comment" value="1">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-weight: 600; font-size: 13.5px; display: block; margin-bottom: 6px;">Post an update or feedback:</label>
                            <textarea name="comment_text" rows="3" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-family: inherit; font-size: 14px;" placeholder="Type your comment here..." required></textarea>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div class="form-group" style="margin: 0; display:flex; align-items:center; gap:8px;">
                                <label style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fas fa-paperclip"></i> Attach File: </label>
                                <input type="file" name="comment_file" style="font-size: 12.5px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 8px 20px; background:#024442; border:none;">Submit Comment</button>
                        </div>
                    </form>

                    <!-- Comments List -->
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php
                        $stmt_comments = $conn->prepare("
                            SELECT pc.*, u.name, u.role 
                            FROM project_comments pc 
                            JOIN users u ON pc.user_id = u.user_id 
                            WHERE pc.project_id = ? 
                            ORDER BY pc.created_at DESC
                        ");
                        $stmt_comments->bind_param("i", $project_id);
                        $stmt_comments->execute();
                        $proj_comms = $stmt_comments->get_result();
                        if ($proj_comms->num_rows > 0):
                            while ($comm = $proj_comms->fetch_assoc()):
                                $initial = strtoupper(substr($comm['name'], 0, 1));
                                $roleBadge = getRoleBadge($comm['role']);
                        ?>
                                <?php
                                $isAgency = in_array(strtolower($comm['role']), ['super_admin', 'superadmin', 'admin', 'pm', 'teacher', 'user']);
                                $cardBg = $isAgency ? '#f0f5f4' : '#f8fafc';
                                $borderColor = $isAgency ? '#024442' : '#0284c7';
                                ?>
                                <div style="display: flex; gap: 16px; margin-bottom: 20px; align-items: flex-start;">
                                    <!-- Avatar -->
                                    <div style="width: 42px; height: 42px; background: <?= $isAgency ? '#024442' : '#0284c7' ?>; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <?= $initial ?>
                                    </div>
                                    <!-- Bubble -->
                                    <div style="flex-grow: 1; background: <?= $cardBg ?>; border-left: 4px solid <?= $borderColor ?>; border-radius: 0 12px 12px 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative;">
                                        <!-- Header info -->
                                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; border-bottom: 1px dashed rgba(0,0,0,0.08); padding-bottom: 6px;">
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <span style="font-weight: 700; color: #1e293b; font-size: 14px;"><?= htmlspecialchars($comm['name']) ?></span>
                                                <span style="background: <?= $roleBadge['bg'] ?>; color: <?= $roleBadge['text'] ?>; font-size: 9px; font-weight: 800; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;"><?= $comm['role'] ?></span>
                                            </div>
                                            <span style="color: #64748b; font-size: 11px; font-weight: 500;"><i class="far fa-clock"></i> <?= date('M d, Y H:i', strtotime($comm['created_at'])) ?></span>
                                        </div>
                                        <!-- Comment text -->
                                        <div style="font-size: 14px; color: #334155; line-height: 1.6; white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($comm['comment_text']) ?></div>
                                        <!-- Attachment -->
                                        <?php if ($comm['file_path']): ?>
                                            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.05);">
                                                <a href="<?= htmlspecialchars($comm['file_path']) ?>" target="_blank" style="background: white; border-radius: 8px; padding: 8px 16px; font-size: 12.5px; color: #024442; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #cbd5e1; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                                    <i class="fas fa-paperclip" style="color: <?= $borderColor ?>;"></i> <?= htmlspecialchars(basename($comm['file_path'])) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <div style="text-align: center; color: #94a3b8; padding: 20px; font-style: italic;">No comments posted yet. Start the conversation!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($can_edit || $role === 'user'): ?>
                <!-- Add Task Modal -->
                <div id="taskFormModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); padding-top: 60px;">
                    <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 24px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;">
                            <h3 style="margin: 0; color: #1e293b;"><i class="fas fa-plus"></i> Add Project Task</h3>
                            <span style="font-size: 24px; font-weight: bold; cursor: pointer;" onclick="document.getElementById('taskFormModal').style.display='none'">&times;</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="create_project_task" value="1">
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13.5px;">Task Title <span style="color:#ef4444;">*</span></label>
                                <input type="text" name="task_title" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" required placeholder="e.g. Design Logo Concepts">
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13.5px;">Description</label>
                                <textarea name="task_desc" rows="3" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" placeholder="Details about this task..."></textarea>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13.5px;">Priority</label>
                                    <select name="task_priority" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13.5px;">Due Date</label>
                                    <input type="date" name="task_due_date" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13.5px;">Assign To</label>
                                <select name="task_assigned_to" style="width: 100%; padding: 8px 12px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                                    <option value="">Unassigned</option>
                                    <?php
                                    // 1. Active project team members
                                    $res_assign = $conn->prepare("
                                        SELECT u.user_id, u.name, u.role
                                        FROM project_team pt 
                                        JOIN users u ON pt.user_id = u.user_id 
                                        WHERE pt.project_id = ? AND u.role != 'student' AND (u.status = 'active' OR u.status IS NULL OR u.status = '')
                                        ORDER BY u.name ASC
                                    ");
                                    $res_assign->bind_param("i", $project_id);
                                    $res_assign->execute();
                                    $users_assign = $res_assign->get_result();
                                    $assigned_team_ids = [];
                                    if ($users_assign->num_rows > 0) {
                                        echo "<optgroup label='Project Team (Active)'>";
                                        while ($usr = $users_assign->fetch_assoc()) {
                                            $assigned_team_ids[] = $usr['user_id'];
                                            echo "<option value='{$usr['user_id']}'>{$usr['name']} (" . ucfirst($usr['role']) . ")</option>";
                                        }
                                        echo "</optgroup>";
                                    }

                                    // 2. Other Active Agency Staff
                                    $other_active = array_filter($active_task_users, function($au) use ($assigned_team_ids) {
                                        return !in_array($au['user_id'], $assigned_team_ids);
                                    });
                                    if (!empty($other_active)) {
                                        echo "<optgroup label='Other Active Staff'>";
                                        foreach ($other_active as $o_usr) {
                                            echo "<option value='{$o_usr['user_id']}'>{$o_usr['name']} (" . ucfirst($o_usr['role']) . ")</option>";
                                        }
                                        echo "</optgroup>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('taskFormModal').style.display='none'">Cancel</button>
                                <button type="submit" class="btn btn-primary" style="background: #024442; border: none; padding: 8px 20px;">Save Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Team Members -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-users"></i> Team Members</h2>
                    <button class="card-action">+</button>
                </div>
                <?php if ($team_members->num_rows > 0): ?>
                    <div class="team-list">
                        <?php while ($member = $team_members->fetch_assoc()): 
                            $initial = strtoupper(substr($member['name'], 0, 1));
                        ?>
                            <div class="team-member">
                                <div class="team-avatar"><?= $initial ?></div>
                                <div class="team-info">
                                    <div class="team-name"><?= htmlspecialchars($member['name']) ?></div>
                                    <div class="team-role"><?= htmlspecialchars($member['role']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user"></i></div>
                        <div>No team members assigned</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Project Timeline -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Timeline</h2>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--success);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Project Created</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($project['start_date'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--info);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Start Date</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['start_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($project['end_date'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--warning);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Target End Date</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['end_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Project Progress Override (For Admin/PM) -->
            <?php if ($can_edit): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-line"></i> Actual Progress</h2>
                </div>
                <div style="padding: 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-weight: 600; color:#334155;">Current Progress:</span>
                        <span style="font-size: 20px; font-weight: 800; color: #024442;"><?= intval($project['progress']) ?>%</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="manual_progress_override" value="1">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label style="font-size: 13px; color:#64748b; display:block; margin-bottom:4px;">Set Progress manually (%):</label>
                            <input type="number" name="progress_override" min="0" max="100" value="<?= intval($project['progress']) ?>" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center; background:#024442; border:none; padding:8px 0; font-weight:700;">Update Progress</button>
                    </form>
                    <small style="color:#94a3b8; display:block; margin-top:8px; line-height:1.4;">Updating manually will send a branded progress email to the client.</small>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-chart-line"></i> Actual Progress</h2>
                </div>
                <div style="padding: 16px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight: 600; color:#334155;">Current Progress:</span>
                    <span style="font-size: 20px; font-weight: 800; color: #024442;"><?= intval($project['progress']) ?>%</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="document.querySelector('input[name=\'comment_file\']').scrollIntoView({ behavior: 'smooth', block: 'center' }); document.querySelector('input[name=\'comment_file\']').focus();">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="document.querySelector('textarea[name=\'comment_text\']').scrollIntoView({ behavior: 'smooth', block: 'center' }); document.querySelector('textarea[name=\'comment_text\']').focus();">
                        <i class="fas fa-comment"></i> Add Comment
                    </button>
                    <a href="export_project_tasks_pdf.php?id=<?= $project_id ?>" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-pdf" style="color: #ef4444;"></i> Export Tasks PDF
                    </a>
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;" onclick="showProjectReport()">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </button>
                    <button class="btn btn-danger" style="width: 100%; justify-content: center;" onclick="deleteProject(<?= $project_id ?>)">
                        <i class="fas fa-trash"></i>️ Delete Project
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Toast Notification Container -->
<div id="taskToastContainer" style="position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;"></div>

<script>
const TASK_BASE_PDF_URL = 'export_project_tasks_pdf.php?id=<?= $project_id ?>';

const TASK_STATUS_STYLES = {
    'Completed':   { bg: '#ecfdf5', text: '#047857', border: '#a7f3d0' },
    'In Progress': { bg: '#eff6ff', text: '#1d4ed8', border: '#93c5fd' },
    'In Review':   { bg: '#faf5ff', text: '#7e22ce', border: '#d8b4fe' },
    'Cancelled':   { bg: '#fef2f2', text: '#b91c1c', border: '#fca5a5' },
    'Pending':     { bg: '#fffbeb', text: '#b45309', border: '#fde68a' },
    'Not Started': { bg: '#fffbeb', text: '#b45309', border: '#fde68a' }
};

function deleteProject(id) {
    if (!confirm('Are you sure? This will permanently delete the project and all files.')) return;
    window.location = 'delete_project.php?id=' + id;
}

// ── Tasks Multi-Criteria Filter Logic ──────────────────────────────────────────
function applyTaskFilters() {
    const userFilter = document.getElementById('taskFilterUser') ? document.getElementById('taskFilterUser').value : 'all';
    const statusFilter = document.getElementById('taskFilterStatus') ? document.getElementById('taskFilterStatus').value : 'all';
    const priorityFilter = document.getElementById('taskFilterPriority') ? document.getElementById('taskFilterPriority').value : 'all';
    const searchKeyword = document.getElementById('taskSearchInput') ? document.getElementById('taskSearchInput').value.trim().toLowerCase() : '';

    const rows = document.querySelectorAll('.task-table-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowUserId = parseInt(row.getAttribute('data-user-id')) || 0;
        const rowStatus = (row.getAttribute('data-status') || '').trim();
        const rowPriority = (row.getAttribute('data-priority') || '').trim();
        const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();

        // 1. User / Assignee match
        let userMatch = false;
        if (userFilter === 'all') {
            userMatch = true;
        } else if (userFilter === 'unassigned') {
            userMatch = (rowUserId === 0);
        } else {
            userMatch = (String(rowUserId) === String(userFilter));
        }

        // 2. Status match
        let statusMatch = false;
        if (statusFilter === 'all') {
            statusMatch = true;
        } else if (statusFilter === 'Pending') {
            statusMatch = (rowStatus === 'Pending' || rowStatus === 'Not Started');
        } else {
            statusMatch = (rowStatus.toLowerCase() === statusFilter.toLowerCase());
        }

        // 3. Priority match
        let priorityMatch = false;
        if (priorityFilter === 'all') {
            priorityMatch = true;
        } else {
            priorityMatch = (rowPriority.toLowerCase() === priorityFilter.toLowerCase());
        }

        // 4. Keyword search match
        let searchMatch = false;
        if (!searchKeyword) {
            searchMatch = true;
        } else {
            searchMatch = rowSearch.includes(searchKeyword);
        }

        if (userMatch && statusMatch && priorityMatch && searchMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Update Counter
    const counterEl = document.getElementById('visibleTaskCount');
    if (counterEl) counterEl.textContent = visibleCount;

    // Show/Hide Empty Filter State Row
    const emptyRow = document.getElementById('taskFilterEmptyRow');
    if (emptyRow) {
        emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }

    // Sync Quick-Filter Pills Active State
    document.querySelectorAll('.status-quick-pill').forEach(pill => {
        pill.classList.remove('active');
        pill.style.boxShadow = 'none';
        pill.style.opacity = '0.85';
    });
    
    if (statusFilter === 'all') {
        const pAll = document.getElementById('pillStatusAll');
        if (pAll) { pAll.classList.add('active'); pAll.style.opacity = '1'; pAll.style.boxShadow = '0 2px 6px rgba(2,68,66,0.3)'; }
    } else {
        document.querySelectorAll('.status-quick-pill').forEach(pill => {
            if (pill.textContent.includes(statusFilter)) {
                pill.classList.add('active');
                pill.style.opacity = '1';
                pill.style.boxShadow = '0 2px 6px rgba(0,0,0,0.12)';
            }
        });
    }

    // Dynamically update PDF export button URL with current filters
    updatePdfExportUrl(userFilter, statusFilter, priorityFilter, searchKeyword);
}

function updatePdfExportUrl(userFilter, statusFilter, priorityFilter, searchKeyword) {
    const pdfBtn = document.getElementById('btnExportTaskPdf');
    if (!pdfBtn) return;

    let url = TASK_BASE_PDF_URL;
    if (userFilter && userFilter !== 'all') {
        url += '&user_id=' + encodeURIComponent(userFilter);
    }
    if (statusFilter && statusFilter !== 'all') {
        url += '&status=' + encodeURIComponent(statusFilter);
    }
    if (priorityFilter && priorityFilter !== 'all') {
        url += '&priority=' + encodeURIComponent(priorityFilter);
    }
    if (searchKeyword && searchKeyword.trim() !== '') {
        url += '&search=' + encodeURIComponent(searchKeyword);
    }
    pdfBtn.href = url;
}

function quickFilterStatus(status, btnElement) {
    const statusSelect = document.getElementById('taskFilterStatus');
    if (statusSelect) {
        statusSelect.value = status;
    }
    applyTaskFilters();
}

function resetTaskFilters() {
    if (document.getElementById('taskFilterUser')) document.getElementById('taskFilterUser').value = 'all';
    if (document.getElementById('taskFilterStatus')) document.getElementById('taskFilterStatus').value = 'all';
    if (document.getElementById('taskFilterPriority')) document.getElementById('taskFilterPriority').value = 'all';
    if (document.getElementById('taskSearchInput')) document.getElementById('taskSearchInput').value = '';
    applyTaskFilters();
    showTaskToast('Filters have been reset', 'info');
}

// ── Interactive High-Visibility Status Dropdown Change ───────────────────────
function onTaskStatusDropdownChange(selectEl, taskId) {
    const newStatus = selectEl.value;
    const style = TASK_STATUS_STYLES[newStatus] || TASK_STATUS_STYLES['Pending'];
    const prevStatus = selectEl.getAttribute('data-prev-status') || '';

    // 1. Immediately update select element styling
    selectEl.style.backgroundColor = style.bg;
    selectEl.style.color = style.text;
    selectEl.style.borderColor = style.border;

    const iconEl = document.getElementById('task-status-icon-' + taskId);
    if (iconEl) iconEl.style.color = style.text;

    // 2. Immediately update table row border and attributes
    const rowEl = document.getElementById('task-row-' + taskId);
    if (rowEl) {
        rowEl.style.borderLeftColor = style.border;
        rowEl.setAttribute('data-status', newStatus);
        
        // Update search data attribute
        const currentSearch = rowEl.getAttribute('data-search') || '';
        rowEl.setAttribute('data-search', currentSearch + ' ' + newStatus.toLowerCase());
    }

    // 3. Update quick filter count chips dynamically
    refreshStatusPillCounts();

    // 4. Send AJAX request to persist update
    selectEl.disabled = true;
    selectEl.style.opacity = '0.7';

    const formData = new FormData();
    formData.append('id', taskId);
    formData.append('status', newStatus);

    fetch('update_task_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        selectEl.disabled = false;
        selectEl.style.opacity = '1';
        selectEl.setAttribute('data-prev-status', newStatus);

        if (data.success) {
            showTaskToast('✓ Task status updated to "' + newStatus + '"', 'success');
        } else {
            showTaskToast('Failed: ' + (data.message || 'Error updating status'), 'error');
            if (prevStatus) {
                selectEl.value = prevStatus;
                onTaskStatusDropdownChange(selectEl, taskId);
            }
        }
    })
    .catch(error => {
        selectEl.disabled = false;
        selectEl.style.opacity = '1';
        console.error('Error updating task status:', error);
        showTaskToast('Network or server error updating task status', 'error');
    });
}

function refreshStatusPillCounts() {
    const rows = document.querySelectorAll('.task-table-row');
    const counts = {
        'all': rows.length,
        'Pending': 0,
        'In Progress': 0,
        'In Review': 0,
        'Completed': 0,
        'Cancelled': 0
    };

    rows.forEach(r => {
        let s = (r.getAttribute('data-status') || '').trim();
        if (s === 'Not Started') s = 'Pending';
        if (counts.hasOwnProperty(s)) {
            counts[s]++;
        } else {
            counts['Pending']++;
        }
    });

    if (document.getElementById('countPillAll')) document.getElementById('countPillAll').textContent = counts['all'];
    if (document.getElementById('countPillPending')) document.getElementById('countPillPending').textContent = counts['Pending'];
    if (document.getElementById('countPillInProgress')) document.getElementById('countPillInProgress').textContent = counts['In Progress'];
    if (document.getElementById('countPillInReview')) document.getElementById('countPillInReview').textContent = counts['In Review'];
    if (document.getElementById('countPillCompleted')) document.getElementById('countPillCompleted').textContent = counts['Completed'];
    if (document.getElementById('countPillCancelled')) document.getElementById('countPillCancelled').textContent = counts['Cancelled'];
    if (document.getElementById('taskTotalCountBadge')) document.getElementById('taskTotalCountBadge').textContent = counts['all'];
}

function showTaskToast(message, type = 'success') {
    const container = document.getElementById('taskToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.style.pointerEvents = 'auto';
    toast.style.padding = '10px 18px';
    toast.style.borderRadius = '8px';
    toast.style.fontSize = '13.5px';
    toast.style.fontWeight = '700';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '10px';
    toast.style.boxShadow = '0 6px 20px rgba(0,0,0,0.15)';
    toast.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
    toast.style.transform = 'translateY(-10px)';
    toast.style.opacity = '0';

    if (type === 'success') {
        toast.style.background = '#024442';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #047857';
    } else if (type === 'error') {
        toast.style.background = '#ef4444';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #dc2626';
    } else {
        toast.style.background = '#1e293b';
        toast.style.color = '#ffffff';
    }

    toast.innerHTML = '<span>' + message + '</span>';
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(-10px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function showScreenshot(src) {
    document.getElementById('screenshotImg').style.display = 'none';
    document.getElementById('screenshotSpinner').style.display = 'flex';
    document.getElementById('screenshotImg').src = src;
    const modal = document.getElementById('screenshotModal');
    modal.style.display = 'flex';
}
function closeScreenshotModal() {
    document.getElementById('screenshotModal').style.display = 'none';
}
function showProjectReport() {
    document.getElementById('reportModal').style.display = 'flex';
}
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}
</script>

<!-- Screenshot Preview Modal -->
<div id="screenshotModal" class="modal-overlay" onclick="if(event.target===this)closeScreenshotModal()" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:16px; max-width:600px; width:90%; position:relative; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:12px;">
            <h4 style="margin:0; color:#1e293b;"><i class="fas fa-image"></i> Payment Proof Screenshot</h4>
            <span style="font-size:24px; font-weight:bold; cursor:pointer;" onclick="closeScreenshotModal()">&times;</span>
        </div>
        <div style="text-align:center; position:relative; min-height:150px; display:flex; align-items:center; justify-content:center;">
            <div id="screenshotSpinner" style="display:flex; align-items:center; justify-content:center; position:absolute; left:0; right:0; top:0; bottom:0;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #024442;"></i>
            </div>
            <img id="screenshotImg" src="" alt="Screenshot Proof" style="max-width:100%; max-height:450px; object-fit:contain; border-radius:6px; border:1px solid #cbd5e1; display:none;" onload="document.getElementById('screenshotSpinner').style.display='none'; this.style.display='inline-block';">
        </div>
    </div>
</div>

<!-- Project Report Modal -->
<div id="reportModal" class="modal-overlay" onclick="if(event.target===this)closeReportModal()" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:24px; max-width:550px; width:90%; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.25); border: 1px solid #cbd5e1;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:20px;">
            <h4 style="margin:0; color:#024442; font-weight:800; display:flex; align-items:center; gap:8px;"><i class="fas fa-chart-pie"></i> Project Analytics Report</h4>
            <span style="font-size:24px; font-weight:bold; cursor:pointer; color:#64748b;" onclick="closeReportModal()">&times;</span>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Tasks Report -->
            <div style="background:#f8fafc; border-radius:10px; padding:16px; border:1px solid #e2e8f0;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-weight:600; color:#1e293b;">
                    <span>Tasks Completed</span>
                    <span style="color:#024442;"><?= $completed_tasks_count ?> / <?= $total_tasks_count ?> (<?= $task_completion_rate ?>%)</span>
                </div>
                <div style="width:100%; height:8px; background:#cbd5e1; border-radius:10px; overflow:hidden;">
                    <div style="width:<?= $task_completion_rate ?>%; height:100%; background:#024442; border-radius:10px;"></div>
                </div>
            </div>
            
            <!-- Invoices / Finances -->
            <div style="background:#f8fafc; border-radius:10px; padding:16px; border:1px solid #e2e8f0; display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; text-align:center;">
                <div>
                    <div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase;">Total Billed</div>
                    <div style="font-size:15px; font-weight:700; color:#0f172a; margin-top:4px;"><?= number_format($total_billed_amount, 2) ?> <small style="font-size:10px;"><?= htmlspecialchars($primary_currency) ?></small></div>
                </div>
                <div>
                    <div style="font-size:15px; font-weight:700; color:#10b981; margin-top:4px;"><?= number_format($total_paid_amount, 2) ?> <small style="font-size:10px;"><?= htmlspecialchars($primary_currency) ?></small></div>
                    <div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase;">Total Paid</div>
                </div>
                <div>
                    <div style="font-size:15px; font-weight:700; color:#ef4444; margin-top:4px;"><?= number_format($total_due_amount, 2) ?> <small style="font-size:10px;"><?= htmlspecialchars($primary_currency) ?></small></div>
                    <div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase;">Outstanding</div>
                </div>
            </div>
            
            <!-- Other Stats -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px;">
                    <div style="width:36px; height:36px; background:#e0f2fe; color:#0284c7; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px;"><i class="fas fa-users"></i></div>
                    <div>
                        <div style="font-size:18px; font-weight:800; color:#0f172a;"><?= $total_team_count ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:500;">Team Assigned</div>
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:12px;">
                    <div style="width:36px; height:36px; background:#f3e8ff; color:#7c3aed; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px;"><i class="fas fa-comments"></i></div>
                    <div>
                        <div style="font-size:18px; font-weight:800; color:#0f172a;"><?= $total_comments_count ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:500;">Discussion Posts</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="margin-top:24px; text-align:right;">
            <button onclick="closeReportModal()" style="background:#024442; border:none; color:#fff; padding:8px 24px; font-weight:700; border-radius:8px; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<?php 
include('dashboard_footer.php');
?>