<?php
include 'dashboard_header.php';

// Check permissions
if (!in_array($role, ['admin', 'pm', 'teacher', 'user', 'manager'])) {
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

// ── Form Processing Actions ──────────────────────────────────────────────────
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT user_id, name, role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$current_user_data = $user_stmt->get_result()->fetch_assoc();
$user_id = $current_user_data['user_id'];
$role = strtolower($current_user_data['role']);
$name = $current_user_data['name'];
$can_edit = ($role === 'admin' || $role === 'pm' || $role === 'manager');

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
            // Notify client if the comment is posted by team/admin
            if (in_array($role, ['admin', 'pm', 'user', 'teacher'])) {
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
                        $body = "<p>A new comment was posted on your project <strong>" . htmlspecialchars($proj['project_name']) . "</strong> by " . htmlspecialchars($name) . " (" . htmlspecialchars($role) . "):</p>
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
            require_once __DIR__ . '/../core/functions.php';
            recalculateProjectProgress($conn, $project_id);
            
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
            <div class="card" style="margin-top: 20px;">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="card-title"><i class="fas fa-clipboard-list"></i> Project Tasks</h2>
                    <?php if ($can_edit || $role === 'user'): ?>
                        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('taskFormModal').style.display='block'">+ Add Task</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive" style="padding: 16px;">
                    <?php
                    $stmt_tasks = $conn->prepare("SELECT t.*, u.name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.user_id WHERE t.project_id = ? ORDER BY t.due_date ASC");
                    $stmt_tasks->bind_param("i", $project_id);
                    $stmt_tasks->execute();
                    $proj_tasks = $stmt_tasks->get_result();
                    if ($proj_tasks->num_rows > 0):
                    ?>
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                <th style="padding: 10px;">Task Title</th>
                                <th style="padding: 10px;">Assigned To</th>
                                <th style="padding: 10px;">Priority</th>
                                <th style="padding: 10px;">Due Date</th>
                                <th style="padding: 10px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tk = $proj_tasks->fetch_assoc()): 
                                $priColor = ($tk['priority'] === 'High' ? '#ef4444' : ($tk['priority'] === 'Medium' ? '#f59e0b' : '#3b82f6'));
                                $statClass = ($tk['status'] === 'Completed' ? 'status-completed' : ($tk['status'] === 'In Progress' ? 'status-in-progress' : 'status-not-started'));
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px; font-weight: 600;"><?= htmlspecialchars($tk['title']) ?></td>
                                <td style="padding: 10px;"><?= htmlspecialchars($tk['assigned_name'] ?? 'Unassigned') ?></td>
                                <td style="padding: 10px;"><span style="color: white; background: <?= $priColor ?>; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;"><?= $tk['priority'] ?></span></td>
                                <td style="padding: 10px;"><?= $tk['due_date'] ? date('M d, Y', strtotime($tk['due_date'])) : 'N/A' ?></td>
                                <td style="padding: 10px;">
                                    <?php if ($can_edit || $role === 'user' || $tk['assigned_to'] == $user_id): ?>
                                        <select class="form-select form-select-sm" style="font-size: 12px; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;" onchange="updateTaskStatus(<?= $tk['id'] ?>, this.value)">
                                            <option value="Pending" <?= $tk['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="In Progress" <?= $tk['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="In Review" <?= $tk['status'] === 'In Review' ? 'selected' : '' ?>>In Review</option>
                                            <option value="Completed" <?= $tk['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="Cancelled" <?= $tk['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge <?= $statClass ?>"><?= $tk['status'] ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; padding: 20px;">No tasks created for this project yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if($role == 'admin'): ?>
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
                                $isAgency = in_array(strtolower($comm['role']), ['admin', 'pm', 'teacher', 'user']);
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
                                <select name="task_assigned_to" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                                    <option value="">Unassigned</option>
                                    <?php
                                    $res_assign = $conn->prepare("
                                        SELECT u.user_id, u.name 
                                        FROM project_team pt 
                                        JOIN users u ON pt.user_id = u.user_id 
                                        WHERE pt.project_id = ? AND u.role != 'student'
                                    ");
                                    $res_assign->bind_param("i", $project_id);
                                    $res_assign->execute();
                                    $users_assign = $res_assign->get_result();
                                    while ($usr = $users_assign->fetch_assoc()) {
                                        echo "<option value='{$usr['user_id']}'>{$usr['name']}</option>";
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

<script>
    function deleteProject(id) {
    if (!confirm('Are you sure? This will permanently delete the project and all files.')) return;
    window.location = 'delete_project.php?id=' + id;
}

function updateTaskStatus(taskId, status) {
    const formData = new FormData();
    formData.append('id', taskId);
    formData.append('status', status);
    
    fetch('update_task_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log(data);
        window.location.reload();
    })
    .catch(error => {
        console.error('Error updating task status:', error);
        alert('Error updating task status');
    });
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