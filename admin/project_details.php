<?php
include 'dashboard_header.php';
require_once __DIR__ . '/../core/project_pm_schema.php';
require_once __DIR__ . '/../core/project_pm_helper.php';

// Check permissions
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'teacher', 'user', 'manager', 'sales'])) {
    header('location:index.php');
    exit;
}

$project_id = intval($_GET['id'] ?? 0);

// Auto-migrate PM tables
ensure_project_pm_schema($conn);

// Fetch project details
$project_query = "SELECT p.*, c.client_name, c.email as client_email
                  FROM projects p
                  LEFT JOIN clients c ON p.client_id = c.id
                  WHERE p.id = ?";
$stmt = $conn->prepare($project_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    echo "<script>alert('Project not found'); window.location='manage_projects.php';</script>";
    exit;
}

// Current user details
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT user_id, name, role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$current_user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$user_id = $current_user_data['user_id'];
$role = strtolower($current_user_data['role']);
$name = $current_user_data['name'];
$can_edit = (in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager']));

// Active staff for task assignments
$active_users_stmt = $conn->prepare("
    SELECT user_id, name, role
    FROM users
    WHERE (status = 'active' OR status IS NULL OR status = '')
      AND role NOT IN ('client', 'client_sub', 'student')
    ORDER BY name ASC
");
$active_users_stmt->execute();
$active_task_users = $active_users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$active_users_stmt->close();

// ── Form Processing Actions ──────────────────────────────────────────────────

// 1. Manual Progress Override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_progress_override']) && $can_edit) {
    $new_progress = intval($_POST['progress_override']);
    if ($new_progress >= 0 && $new_progress <= 100) {
        $stmt_upd = $conn->prepare("UPDATE projects SET progress = ?, updated_at = NOW() WHERE id = ?");
        $stmt_upd->bind_param("ii", $new_progress, $project_id);
        if ($stmt_upd->execute()) {
            $stmt_upd->close();
            require_once __DIR__ . '/../core/functions.php';
            notifyClientOnProjectProgress($conn, $project_id, $new_progress);
            echo "<script>alert('Progress updated and client notified.'); window.location='project_details.php?id={$project_id}&tab=overview';</script>";
            exit;
        }
    }
}

// 2. Create / Delete Milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_milestone']) && $can_edit) {
    $m_title = trim($_POST['milestone_title'] ?? '');
    $m_desc = trim($_POST['milestone_desc'] ?? '');
    $m_due = !empty($_POST['milestone_due_date']) ? $_POST['milestone_due_date'] : null;
    $m_order = intval($_POST['milestone_order'] ?? 1);

    if (!empty($m_title)) {
        $stmt_m = $conn->prepare("INSERT INTO project_milestones (project_id, title, description, due_date, order_num, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt_m->bind_param("isssi", $project_id, $m_title, $m_desc, $m_due, $m_order);
        $stmt_m->execute();
        $stmt_m->close();
        echo "<script>alert('Milestone created.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_milestone' && $can_edit && isset($_GET['milestone_id'])) {
    $m_id = intval($_GET['milestone_id']);
    $conn->query("DELETE FROM project_milestones WHERE id = {$m_id} AND project_id = {$project_id}");
    echo "<script>alert('Milestone deleted.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
    exit;
}

if (isset($_POST['update_milestone_status']) && $can_edit) {
    $m_id = intval($_POST['milestone_id']);
    $m_status = $_POST['status'] ?? 'pending';
    $stmt_ms = $conn->prepare("UPDATE project_milestones SET status = ? WHERE id = ? AND project_id = ?");
    $stmt_ms->bind_param("sii", $m_status, $m_id, $project_id);
    $stmt_ms->execute();
    $stmt_ms->close();
    echo "<script>alert('Milestone status updated.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
    exit;
}

// 3. Create / Submit / Delete Deliverable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_deliverable']) && $can_edit) {
    $milestone_id = intval($_POST['deliverable_milestone_id'] ?? 0);
    $d_title = trim($_POST['deliverable_title'] ?? '');
    $d_specs = trim($_POST['deliverable_specs'] ?? '');
    $d_version = trim($_POST['deliverable_version'] ?? '1.0');
    $d_preview = trim($_POST['deliverable_preview_url'] ?? '');
    $d_file = null;

    if (!empty($_FILES['deliverable_file']['name'])) {
        $upload_dir = "uploads/projects/" . $project_id . "/deliverables/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $fname = basename($_FILES['deliverable_file']['name']);
        $d_file = $upload_dir . time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fname);
        move_uploaded_file($_FILES['deliverable_file']['tmp_name'], $d_file);
    }

    if (!empty($d_title) && $milestone_id > 0) {
        $stmt_d = $conn->prepare("INSERT INTO project_deliverables (milestone_id, project_id, title, specs, deliverable_file, live_preview_url, version, signoff_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')");
        $stmt_d->bind_param("iisssss", $milestone_id, $project_id, $d_title, $d_specs, $d_file, $d_preview, $d_version);
        $stmt_d->execute();
        $stmt_d->close();
        echo "<script>alert('Deliverable added.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'submit_deliverable_review' && $can_edit && isset($_GET['deliv_id'])) {
    $d_id = intval($_GET['deliv_id']);
    $conn->query("UPDATE project_deliverables SET signoff_status = 'pending_review' WHERE id = {$d_id} AND project_id = {$project_id}");

    // Notify client
    $sub = "Deliverable Ready for Review: " . $project['project_name'];
    $head = "Project Deliverable Submitted for Review";
    $msg = "<p>The agency team has published a deliverable for your review and sign-off on project <strong>" . htmlspecialchars($project['project_name']) . "</strong>.</p>
            <p>Please log in to your client dashboard to inspect the deliverable and sign off.</p>";
    notifyProjectClient($conn, $project_id, $sub, $head, $msg);

    echo "<script>alert('Deliverable submitted for client review and client notified.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_deliverable' && $can_edit && isset($_GET['deliv_id'])) {
    $d_id = intval($_GET['deliv_id']);
    $conn->query("DELETE FROM project_deliverables WHERE id = {$d_id} AND project_id = {$project_id}");
    echo "<script>alert('Deliverable removed.'); window.location='project_details.php?id={$project_id}&tab=deliverables';</script>";
    exit;
}

// 4. Create / Verify Client Asset & Information Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_client_request']) && $can_edit) {
    $req_title = trim($_POST['request_title'] ?? '');
    $req_type = $_POST['request_type'] ?? 'file';
    $req_priority = $_POST['request_priority'] ?? 'normal';
    $req_inst = trim($_POST['request_instructions'] ?? '');
    $req_due = !empty($_POST['request_due_date']) ? $_POST['request_due_date'] : null;

    if (!empty($req_title)) {
        $stmt_req = $conn->prepare("INSERT INTO project_client_requests (project_id, request_title, request_type, instructions, priority, status, due_date, created_by) VALUES (?, ?, ?, ?, ?, 'requested', ?, ?)");
        $stmt_req->bind_param("isssssi", $project_id, $req_title, $req_type, $req_inst, $req_priority, $req_due, $user_id);
        $stmt_req->execute();
        $stmt_req->close();

        // Notify client
        $sub = "Action Required: Information/Asset Needed for " . $project['project_name'];
        $head = "Information Request from Project Team";
        $msg = "<p>The project team requires information/assets from you to proceed with project <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
                <div style='background:#f8fafc; border-left:4px solid #024442; padding:12px; margin:16px 0;'>
                    <strong>Item:</strong> " . htmlspecialchars($req_title) . "<br>
                    <strong>Instructions:</strong> " . nl2br(htmlspecialchars($req_inst)) . "
                </div>
                <p>Please log in to your client dashboard to submit this item.</p>";
        notifyProjectClient($conn, $project_id, $sub, $head, $msg);

        echo "<script>alert('Request created and client notified.'); window.location='project_details.php?id={$project_id}&tab=client-requests';</script>";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_client_request']) && $can_edit) {
    $req_id = intval($_POST['request_id']);
    $decision = $_POST['decision'] ?? '';
    $rej_reason = trim($_POST['rejection_reason'] ?? '');

    if ($decision === 'accept') {
        $conn->query("UPDATE project_client_requests SET status = 'accepted', verified_at = NOW(), rejection_reason = NULL WHERE id = {$req_id} AND project_id = {$project_id}");
        echo "<script>alert('Asset accepted and unblocked.'); window.location='project_details.php?id={$project_id}&tab=client-requests';</script>";
        exit;
    } elseif ($decision === 'reject') {
        $stmt_rej = $conn->prepare("UPDATE project_client_requests SET status = 'rejected', rejection_reason = ? WHERE id = ? AND project_id = ?");
        $stmt_rej->bind_param("sii", $rej_reason, $req_id, $project_id);
        $stmt_rej->execute();
        $stmt_rej->close();

        // Notify client
        $sub = "Re-upload Needed: " . $project['project_name'];
        $head = "Asset Feedback - Adjustment Required";
        $msg = "<p>The team reviewed your submitted item for project <strong>" . htmlspecialchars($project['project_name']) . "</strong> but requires an adjustment:</p>
                <div style='background:#fee2e2; border-left:4px solid #ef4444; padding:12px; margin:16px 0; color:#991b1b;'>" . nl2br(htmlspecialchars($rej_reason)) . "</div>";
        notifyProjectClient($conn, $project_id, $sub, $head, $msg);

        echo "<script>alert('Client notified to re-upload.'); window.location='project_details.php?id={$project_id}&tab=client-requests';</script>";
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_client_request' && $can_edit && isset($_GET['req_id'])) {
    $req_id = intval($_GET['req_id']);
    $conn->query("DELETE FROM project_client_requests WHERE id = {$req_id} AND project_id = {$project_id}");
    echo "<script>alert('Request removed.'); window.location='project_details.php?id={$project_id}&tab=client-requests';</script>";
    exit;
}

// 5. Reply to Client Query & Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_client_query'])) {
    $query_id = intval($_POST['query_id'] ?? 0);
    $q_message = trim($_POST['reply_message'] ?? '');
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    $set_status = $_POST['set_query_status'] ?? '';

    if (!empty($q_message) && $query_id > 0) {
        $stmt_rep = $conn->prepare("INSERT INTO project_query_replies (query_id, user_id, is_internal, message) VALUES (?, ?, ?, ?)");
        $stmt_rep->bind_param("iiis", $query_id, $user_id, $is_internal, $q_message);
        $stmt_rep->execute();
        $stmt_rep->close();

        if (!empty($set_status)) {
            $stmt_sq = $conn->prepare("UPDATE project_client_queries SET status = ?, resolved_at = (CASE WHEN ? IN ('resolved', 'closed') THEN NOW() ELSE NULL END) WHERE id = ? AND project_id = ?");
            $stmt_sq->bind_param("ssii", $set_status, $set_status, $query_id, $project_id);
            $stmt_sq->execute();
            $stmt_sq->close();
        }

        // Notify client if public reply
        if (!$is_internal) {
            $sub = "Team Replied to Your Inquiry - " . $project['project_name'];
            $head = "Update on Your Project Query";
            $msg = "<p>The agency team responded to your inquiry on project <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
                    <blockquote style='background:#f8fafc; border-left:4px solid #024442; padding:12px; margin:16px 0;'>" . nl2br(htmlspecialchars($q_message)) . "</blockquote>";
            notifyProjectClient($conn, $project_id, $sub, $head, $msg);
        }

        echo "<script>alert('Response posted.'); window.location='project_details.php?id={$project_id}&tab=client-queries';</script>";
        exit;
    }
}

// 6. Create Project Broadcast (Weekly Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project_broadcast']) && $can_edit) {
    $b_title = trim($_POST['broadcast_title'] ?? 'Weekly Progress Update');
    $b_done = trim($_POST['broadcast_completed'] ?? '');
    $b_prog = trim($_POST['broadcast_in_progress'] ?? '');
    $b_next = trim($_POST['broadcast_next'] ?? '');
    $b_block = trim($_POST['broadcast_blockers'] ?? '');
    $send_mail = isset($_POST['send_email_notification']) ? 1 : 0;

    $stmt_bc = $conn->prepare("INSERT INTO project_broadcasts (project_id, title, completed_text, in_progress_text, next_up_text, blockers_text, created_by, sent_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_bc->bind_param("isssssii", $project_id, $b_title, $b_done, $b_prog, $b_next, $b_block, $user_id, $send_mail);
    if ($stmt_bc->execute()) {
        $stmt_bc->close();

        if ($send_mail) {
            $sub = "Project Progress Update: " . $project['project_name'];
            $head = htmlspecialchars($b_title);
            $body = "<p>Here is the latest progress snapshot for <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>";
            if (!empty($b_done)) $body .= "<div style='margin-bottom:12px;'><strong>Completed:</strong><br>" . nl2br(htmlspecialchars($b_done)) . "</div>";
            if (!empty($b_prog)) $body .= "<div style='margin-bottom:12px;'><strong>In Progress:</strong><br>" . nl2br(htmlspecialchars($b_prog)) . "</div>";
            if (!empty($b_next)) $body .= "<div style='margin-bottom:12px;'><strong>Coming Up:</strong><br>" . nl2br(htmlspecialchars($b_next)) . "</div>";
            if (!empty($b_block)) $body .= "<div style='margin-bottom:12px; color:#991b1b;'><strong>Required from Client:</strong><br>" . nl2br(htmlspecialchars($b_block)) . "</div>";
            notifyProjectClient($conn, $project_id, $sub, $head, $body);
        }

        echo "<script>alert('Update published successfully.'); window.location='project_details.php?id={$project_id}&tab=updates';</script>";
        exit;
    }
}

// 7. Add Project Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project_task']) && ($can_edit || $role === 'user')) {
    $task_title = trim($_POST['task_title'] ?? '');
    $task_desc = trim($_POST['task_desc'] ?? '');
    $priority = $_POST['task_priority'] ?? 'Medium';
    $due_date = !empty($_POST['task_due_date']) ? $_POST['task_due_date'] : null;
    $assigned_to = !empty($_POST['task_assigned_to']) ? intval($_POST['task_assigned_to']) : null;
    $milestone_id = !empty($_POST['task_milestone_id']) ? intval($_POST['task_milestone_id']) : null;
    $estimated_hours = floatval($_POST['task_estimated_hours'] ?? 0);

    if (!empty($task_title)) {
        $stmt_task = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, created_by, project_id, milestone_id, estimated_hours) VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?)");
        $stmt_task->bind_param("ssssiiiid", $task_title, $task_desc, $priority, $due_date, $assigned_to, $user_id, $project_id, $milestone_id, $estimated_hours);
        if ($stmt_task->execute()) {
            $stmt_task->close();
            if ($assigned_to > 0) {
                $u_stmt = $conn->query("SELECT name, email FROM users WHERE user_id = $assigned_to");
                if ($u_row = $u_stmt->fetch_assoc()) {
                    if (!empty($u_row['email'])) {
                        require_once __DIR__ . '/../core/task_email_helper.php';
                        $heading = "New Task Assigned: " . $task_title;
                        $body = "<p>Hello " . htmlspecialchars($u_row['name']) . ",</p>
                                 <p>You have been assigned a task under project <strong>" . htmlspecialchars($project['project_name']) . "</strong>.</p>";
                        sendTaskEmail($u_row['email'], $heading, $heading, $body, 0);
                    }
                }
            }
            echo "<script>alert('Task added successfully.'); window.location='project_details.php?id={$project_id}&tab=tasks';</script>";
            exit;
        }
    }
}

// 8. Add Project Comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project_comment'])) {
    $comment_text = trim($_POST['comment_text'] ?? '');
    if (!empty($comment_text)) {
        $file_path = null;
        if (!empty($_FILES['comment_file']['name'])) {
            $upload_dir = "uploads/projects/" . $project_id . "/comments/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_path = $upload_dir . time() . "_" . basename($_FILES['comment_file']['name']);
            move_uploaded_file($_FILES['comment_file']['tmp_name'], $file_path);

            $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_type, title, uploaded_by) VALUES (?, ?, 'document', ?, ?)");
            $title = "Comment Attachment - " . basename($_FILES['comment_file']['name']);
            $stmt_file->bind_param("issi", $project_id, $file_path, $title, $user_id);
            $stmt_file->execute();
            $stmt_file->close();
        }

        $stmt_comm = $conn->prepare("INSERT INTO project_comments (project_id, user_id, comment_text, file_path) VALUES (?, ?, ?, ?)");
        $stmt_comm->bind_param("iiss", $project_id, $user_id, $comment_text, $file_path);
        if ($stmt_comm->execute()) {
            $stmt_comm->close();
            echo "<script>alert('Comment posted.'); window.location='project_details.php?id={$project_id}&tab=updates';</script>";
            exit;
        }
    }
}

// ── Read Data for Views ──────────────────────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'overview';
$allowed_tabs = ['overview', 'deliverables', 'tasks', 'client-requests', 'client-queries', 'updates', 'invoices'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'overview';

$kpis = getProjectKPIs($conn, $project_id);

// Team members
$team_stmt = $conn->prepare("SELECT u.user_id, u.name, u.email, u.role FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = ?");
$team_stmt->bind_param("i", $project_id);
$team_stmt->execute();
$team_members = $team_stmt->get_result();
$team_stmt->close();

// Milestones
$res_m = $conn->query("SELECT * FROM project_milestones WHERE project_id = {$project_id} ORDER BY order_num ASC, id ASC");
$milestones = $res_m ? $res_m->fetch_all(MYSQLI_ASSOC) : [];

// Deliverables grouped by milestone
$res_d = $conn->query("SELECT d.*, m.title as milestone_title FROM project_deliverables d LEFT JOIN project_milestones m ON d.milestone_id = m.id WHERE d.project_id = {$project_id} ORDER BY d.id DESC");
$deliverables = $res_d ? $res_d->fetch_all(MYSQLI_ASSOC) : [];

// Client Requests (Pending Assets / Info)
$res_reqs = $conn->query("SELECT * FROM project_client_requests WHERE project_id = {$project_id} ORDER BY CASE WHEN status = 'requested' THEN 1 WHEN status = 'submitted' THEN 2 WHEN status = 'rejected' THEN 3 ELSE 4 END, id DESC");
$client_requests = $res_reqs ? $res_reqs->fetch_all(MYSQLI_ASSOC) : [];

// Client Queries
$res_q = $conn->query("SELECT q.*, u.name as client_name, a.name as assignee_name FROM project_client_queries q JOIN users u ON q.client_user_id = u.user_id LEFT JOIN users a ON q.assigned_to = a.user_id WHERE q.project_id = {$project_id} ORDER BY q.created_at DESC");
$client_queries = $res_q ? $res_q->fetch_all(MYSQLI_ASSOC) : [];

// Broadcasts
$res_bc = $conn->query("SELECT b.*, u.name as author_name FROM project_broadcasts b JOIN users u ON b.created_by = u.user_id WHERE b.project_id = {$project_id} ORDER BY b.created_at DESC");
$broadcasts = $res_bc ? $res_bc->fetch_all(MYSQLI_ASSOC) : [];

// Invoices
$stmt_invs = $conn->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY created_at DESC");
$stmt_invs->bind_param("i", $project_id);
$stmt_invs->execute();
$invoices = $stmt_invs->get_result();
$stmt_invs->close();

// Files
$stmt_files = $conn->prepare("SELECT * FROM project_files WHERE project_id = ? AND file_type != 'thumbnail' ORDER BY uploaded_at DESC");
$stmt_files->bind_param("i", $project_id);
$stmt_files->execute();
$files = $stmt_files->get_result();
$stmt_files->close();

// Tasks Query
$stmt_tasks = $conn->prepare("
    SELECT t.*, u.name as assigned_name, u.role as assigned_role, m.title as milestone_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    LEFT JOIN project_milestones m ON t.milestone_id = m.id
    WHERE t.project_id = ?
    ORDER BY
        CASE
            WHEN t.status = 'In Progress' THEN 1
            WHEN t.status = 'In Review' THEN 2
            WHEN t.status = 'Pending' THEN 3
            WHEN t.status = 'Not Started' THEN 4
            WHEN t.status = 'Completed' THEN 5
            ELSE 6
        END,
        t.due_date ASC,
        t.id DESC
");
$stmt_tasks->bind_param("i", $project_id);
$stmt_tasks->execute();
$all_project_tasks = $stmt_tasks->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tasks->close();

// Tasks grouped for Kanban board
$kanban_tasks = [
    'Pending' => [],
    'In Progress' => [],
    'In Review' => [],
    'Completed' => []
];
foreach ($all_project_tasks as $tk) {
    $s = $tk['status'];
    if ($s === 'Not Started') $s = 'Pending';
    if (isset($kanban_tasks[$s])) {
        $kanban_tasks[$s][] = $tk;
    } else {
        $kanban_tasks['Pending'][] = $tk;
    }
}
?>

<div class="height-100">
    <!-- Project Master Header (Clean, Zero Emojis) -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #024442;"><?= htmlspecialchars($project['project_name']) ?></h1>
                <div style="margin-top: 6px; color: #64748b; font-size: 14.5px;">
                    <i class="fas fa-building text-muted"></i> <?= htmlspecialchars($project['client_name']) ?>
                </div>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="manage_projects.php" class="btn btn-secondary btn-sm" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-weight:600; padding:8px 16px; border-radius:8px; text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
                <?php if ($can_edit): ?>
                    <a href="edit_project.php?id=<?= $project_id ?>" class="btn btn-secondary btn-sm" style="background:#fff; border:1px solid #cbd5e1; color:#024442; font-weight:600; padding:8px 16px; border-radius:8px; text-decoration:none;">
                        <i class="fas fa-pencil-alt"></i> Edit Project
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta Details Bar -->
        <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:20px; border-top:1px solid #f1f5f9; padding-top:16px; font-size:13.5px; color:#64748b;">
            <div>
                <strong>Status:</strong>
                <span class="badge" style="background:<?= $project['status'] === 'Completed' ? '#ecfdf5;color:#10b981;' : ($project['status'] === 'In Progress' ? '#e0f2fe;color:#0284c7;' : '#fef3c7;color:#d97706;') ?>">
                    <?= htmlspecialchars($project['status']) ?>
                </span>
            </div>
            <div>
                <strong>Project Health:</strong>
                <span class="fw-bold <?= $kpis['health_class'] ?>"><?= $kpis['health_status'] ?></span>
            </div>
            <div>
                <strong>Priority:</strong>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($project['priority']) ?></span>
            </div>
            <div>
                <strong>Start Date:</strong> <?= !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'Not set' ?>
            </div>
            <div>
                <strong>End Date:</strong> <?= !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'Not set' ?>
            </div>
            <div>
                <strong>Visibility:</strong> <span class="text-capitalize"><?= htmlspecialchars($project['visibility']) ?></span>
            </div>
        </div>

        <!-- Progress Bar -->
        <div style="margin-top:20px;">
            <div style="display:flex; justify-content:space-between; font-weight:600; color:#475569; margin-bottom:6px; font-size:13px;">
                <span>Overall Progress</span>
                <span style="color:#024442; font-weight:700;"><?= intval($project['progress']) ?>%</span>
            </div>
            <div style="background:#e2e8f0; height:10px; border-radius:6px; overflow:hidden;">
                <div style="background:#024442; width: <?= intval($project['progress']) ?>%; height: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Clean Navigation Tabs (No Emojis, No Extra Pills) -->
    <ul class="nav nav-tabs" style="border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; background: transparent; padding: 0; display:flex; flex-wrap:wrap; gap:4px;">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'overview' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=overview" style="color: <?= $active_tab === 'overview' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'overview' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-chart-pie"></i> Overview & KPIs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'deliverables' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=deliverables" style="color: <?= $active_tab === 'deliverables' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'deliverables' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-box-open"></i> Milestones & Deliverables
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'tasks' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=tasks" style="color: <?= $active_tab === 'tasks' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'tasks' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-tasks"></i> Tasks (<?= count($all_project_tasks) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'client-requests' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=client-requests" style="color: <?= $active_tab === 'client-requests' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'client-requests' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-clipboard-list"></i> Pending from Client
                <?php if ($kpis['pending_client_requests'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $kpis['pending_client_requests'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'client-queries' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=client-queries" style="color: <?= $active_tab === 'client-queries' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'client-queries' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-question-circle"></i> Client Queries
                <?php if ($kpis['open_client_queries'] > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= $kpis['open_client_queries'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'updates' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=updates" style="color: <?= $active_tab === 'updates' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'updates' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-bullhorn"></i> Updates & Discussion
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'invoices' ? 'active' : '' ?>" href="project_details.php?id=<?= $project_id ?>&tab=invoices" style="color: <?= $active_tab === 'invoices' ? '#024442' : '#64748b' ?>; font-weight: 600; border: none; border-bottom: <?= $active_tab === 'invoices' ? '3px solid #024442' : 'none' ?>; background: transparent; padding: 10px 18px;">
                <i class="fas fa-file-invoice-dollar"></i> Invoices & Files
            </a>
        </li>
    </ul>

    <!-- ======================================================================= -->
    <!-- TAB 1: OVERVIEW & KPIS                                                  -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'overview'): ?>
    <div style="display:flex; flex-direction:column; gap:24px;">
        <!-- Clean KPI Metric Cards (Zero Emojis, High Readability) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Milestone Progress</div>
                <div style="font-size: 26px; font-weight: 800; color: #024442; margin-top: 6px;">
                    <?= $kpis['completed_milestones'] ?> / <?= $kpis['total_milestones'] ?>
                    <small style="font-size: 14px; font-weight: 600; color: #64748b;">(<?= $kpis['milestone_progress'] ?>%)</small>
                </div>
            </div>

            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Tasks Velocity</div>
                <div style="font-size: 26px; font-weight: 800; color: #0284c7; margin-top: 6px;">
                    <?= $kpis['completed_tasks'] ?> / <?= $kpis['total_tasks'] ?>
                    <small style="font-size: 14px; font-weight: 600; color: #64748b;">(<?= $kpis['task_completion_rate'] ?>%)</small>
                </div>
            </div>

            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Overdue Tasks</div>
                <div style="font-size: 26px; font-weight: 800; color: <?= $kpis['overdue_tasks'] > 0 ? '#ef4444' : '#10b981' ?>; margin-top: 6px;">
                    <?= $kpis['overdue_tasks'] ?>
                </div>
            </div>

            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Pending from Client</div>
                <div style="font-size: 26px; font-weight: 800; color: <?= $kpis['blocker_client_requests'] > 0 ? '#ef4444' : ($kpis['pending_client_requests'] > 0 ? '#f59e0b' : '#10b981') ?>; margin-top: 6px;">
                    <?= $kpis['pending_client_requests'] ?>
                    <?php if ($kpis['blocker_client_requests'] > 0): ?>
                        <small style="font-size: 12px; color: #ef4444; font-weight: 700;">(<?= $kpis['blocker_client_requests'] ?> Blockers)</small>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Open Client Queries</div>
                <div style="font-size: 26px; font-weight: 800; color: <?= $kpis['open_client_queries'] > 0 ? '#f59e0b' : '#64748b' ?>; margin-top: 6px;">
                    <?= $kpis['open_client_queries'] ?>
                </div>
            </div>
        </div>

        <!-- 2-Column Content Layout -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Description -->
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff;">
                    <div class="card-header" style="background:#fff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px;">
                        <h2 class="card-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-file-alt text-muted"></i> Project Scope & Description</h2>
                    </div>
                    <div style="padding: 20px; font-size: 14.5px; color:#334155; line-height: 1.7;">
                        <?= !empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : 'No internal description provided.' ?>
                    </div>
                </div>

                <!-- Internal Notes -->
                <?php if (!empty($project['internal_notes']) && $can_edit): ?>
                <div class="card" style="border: 1px solid #fef3c7; border-radius: 12px; background:#fffdf5;">
                    <div class="card-header" style="background:#fffbeb; border-bottom: 1px solid #fef3c7; padding: 14px 20px;">
                        <h2 class="card-title" style="margin:0; font-size:15px; font-weight:700; color:#92400e;"><i class="fas fa-lock"></i> Confidential Internal Notes</h2>
                    </div>
                    <div style="padding: 18px; font-size: 14px; color:#78350f; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($project['internal_notes'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Milestone Roadmap Timeline -->
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff;">
                    <div class="card-header" style="background:#fff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px; display:flex; justify-content:space-between; align-items:center;">
                        <h2 class="card-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-map-marker-alt text-muted"></i> Milestones Roadmap</h2>
                        <a href="project_details.php?id=<?= $project_id ?>&tab=deliverables" style="font-size:12.5px; color:#024442; font-weight:600; text-decoration:none;">Manage Milestones →</a>
                    </div>
                    <div style="padding: 20px;">
                        <?php if (!empty($milestones)): ?>
                            <div style="display:flex; flex-direction:column; gap:16px;">
                                <?php foreach ($milestones as $m):
                                    $isCompleted = ($m['status'] === 'completed');
                                    $isInProgress = ($m['status'] === 'in_progress');
                                ?>
                                <div style="display:flex; gap:16px; align-items:flex-start;">
                                    <div style="width:28px; height:28px; border-radius:50%; background:<?= $isCompleted ? '#ecfdf5' : ($isInProgress ? '#eff6ff' : '#f1f5f9') ?>; color:<?= $isCompleted ? '#059669' : ($isInProgress ? '#2563eb' : '#94a3b8') ?>; border:1px solid <?= $isCompleted ? '#a7f3d0' : ($isInProgress ? '#bfdbfe' : '#e2e8f0') ?>; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; margin-top:2px;">
                                        <i class="fas <?= $isCompleted ? 'fa-check' : ($isInProgress ? 'fa-circle-notch fa-spin' : 'fa-circle') ?>"></i>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                            <span style="font-weight:700; font-size:14.5px; color:#1e293b;"><?= htmlspecialchars($m['title']) ?></span>
                                            <span class="badge" style="background:<?= $isCompleted ? '#ecfdf5;color:#059669;' : ($isInProgress ? '#eff6ff;color:#2563eb;' : '#f1f5f9;color:#64748b;') ?>">
                                                <?= ucfirst($m['status']) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($m['description'])): ?>
                                            <p style="margin:4px 0 0; font-size:13px; color:#64748b;"><?= htmlspecialchars($m['description']) ?></p>
                                        <?php endif; ?>
                                        <div style="font-size:12px; color:#94a3b8; margin-top:4px;">
                                            Due: <?= !empty($m['due_date']) ? date('M d, Y', strtotime($m['due_date'])) : 'Flexible' ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="color:#94a3b8; font-size:13.5px; text-align:center; padding:16px;">
                                No milestones created yet. Go to <a href="project_details.php?id=<?= $project_id ?>&tab=deliverables" style="color:#024442; font-weight:600;">Milestones & Deliverables</a> to set up the project roadmap.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                <!-- Team Members Card -->
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
                    <h3 style="margin:0 0 16px; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-users text-muted"></i> Assigned Team</h3>
                    <?php if ($team_members && $team_members->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php while ($tm = $team_members->fetch_assoc()):
                                $init = strtoupper(substr($tm['name'], 0, 1));
                            ?>
                            <div style="display:flex; gap:12px; align-items:center;">
                                <div style="width:34px; height:34px; border-radius:50%; background:#e0f2fe; color:#0369a1; font-weight:700; display:flex; align-items:center; justify-content:center; font-size:13px;">
                                    <?= $init ?>
                                </div>
                                <div>
                                    <div style="font-weight:700; font-size:13.5px; color:#1e293b;"><?= htmlspecialchars($tm['name']) ?></div>
                                    <div style="font-size:11.5px; color:#64748b; text-transform:capitalize;"><?= htmlspecialchars($tm['role']) ?></div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="color:#94a3b8; font-size:13px;">No team members assigned yet.</div>
                    <?php endif; ?>
                </div>

                <!-- Progress Control -->
                <?php if ($can_edit): ?>
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
                    <h3 style="margin:0 0 12px; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-sliders-h text-muted"></i> Progress Override</h3>
                    <form method="POST">
                        <input type="hidden" name="manual_progress_override" value="1">
                        <div class="mb-3">
                            <label style="font-size:13px; color:#64748b; display:block; margin-bottom:4px;">Adjust Completed %:</label>
                            <input type="number" name="progress_override" min="0" max="100" value="<?= intval($project['progress']) ?>" class="form-control form-control-sm" required style="font-size:13.5px;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width:100%; background:#024442; border:none; padding:7px 0; font-weight:600;">Update & Notify Client</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 2: MILESTONES & DELIVERABLES                                        -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'deliverables'): ?>
    <div style="display:flex; flex-direction:column; gap:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Project Milestones & Deliverables</h3>
                <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Package deliverables under milestones for client sign-off and review tracking.</p>
            </div>
            <?php if ($can_edit): ?>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-sm" onclick="document.getElementById('modalAddMilestone').style.display='flex'" style="background:#fff; border:1px solid #cbd5e1; color:#024442; font-weight:600; padding:6px 14px; border-radius:6px;">
                    <i class="fas fa-plus"></i> Add Milestone
                </button>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('modalAddDeliverable').style.display='flex'" style="background:#024442; border:none; font-weight:600; padding:6px 14px; border-radius:6px;">
                    <i class="fas fa-box-open"></i> Package Deliverable
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($milestones)): ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($milestones as $m):
                    $m_id = intval($m['id']);
                    $m_delivs = array_filter($deliverables, function($d) use ($m_id) {
                        return intval($d['milestone_id']) === $m_id;
                    });
                ?>
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; overflow:hidden;">
                    <div style="background:#fafbfc; border-bottom:1px solid #e2e8f0; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <span style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Phase <?= $m['order_num'] ?></span>
                            <h4 style="margin:2px 0 0; font-size:16px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($m['title']) ?></h4>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Due Date: <?= !empty($m['due_date']) ? date('M d, Y', strtotime($m['due_date'])) : 'Flexible' ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <form method="POST" style="margin:0; display:inline-block;">
                                <input type="hidden" name="update_milestone_status" value="1">
                                <input type="hidden" name="milestone_id" value="<?= $m['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm" style="font-size:12px; padding:4px 8px; width:auto; display:inline-block;">
                                    <option value="pending" <?= $m['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $m['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $m['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </form>
                            <?php if ($can_edit): ?>
                                <a href="project_details.php?id=<?= $project_id ?>&tab=deliverables&action=delete_milestone&milestone_id=<?= $m['id'] ?>" onclick="return confirm('Delete this milestone?')" class="text-danger" style="font-size:13px; text-decoration:none;" title="Delete Milestone">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="padding:20px;">
                        <?php if (!empty($m['description'])): ?>
                            <p style="font-size:13.5px; color:#475569; margin-bottom:16px; line-height:1.6;"><?= nl2br(htmlspecialchars($m['description'])) ?></p>
                        <?php endif; ?>

                        <!-- Deliverables in Milestone -->
                        <h5 style="font-size:13px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:12px;">Deliverable Items</h5>
                        <?php if (!empty($m_delivs)): ?>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                                <?php foreach ($m_delivs as $del): ?>
                                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px; background:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <div style="font-weight:700; font-size:14px; color:#1e293b;">
                                            <?= htmlspecialchars($del['title']) ?>
                                            <span style="font-size:12px; font-weight:600; color:#64748b;">(v<?= htmlspecialchars($del['version']) ?>)</span>
                                        </div>
                                        <?php if (!empty($del['specs'])): ?>
                                            <div style="font-size:12.5px; color:#64748b; margin-top:2px;"><?= htmlspecialchars($del['specs']) ?></div>
                                        <?php endif; ?>
                                        <div style="display:flex; gap:12px; margin-top:6px; font-size:12px;">
                                            <?php if (!empty($del['deliverable_file'])): ?>
                                                <a href="<?= htmlspecialchars($del['deliverable_file']) ?>" target="_blank" style="color:#024442; text-decoration:none; font-weight:600;">
                                                    <i class="fas fa-file-download"></i> Download File
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($del['live_preview_url'])): ?>
                                                <a href="<?= htmlspecialchars($del['live_preview_url']) ?>" target="_blank" style="color:#2563eb; text-decoration:none; font-weight:600;">
                                                    <i class="fas fa-external-link-alt"></i> Preview Link
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <?php if ($del['signoff_status'] === 'approved'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                        <?php elseif ($del['signoff_status'] === 'pending_review'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Client Reviewing</span>
                                        <?php elseif ($del['signoff_status'] === 'revision_requested'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-redo"></i> Revisions Requested</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Draft</span>
                                        <?php endif; ?>

                                        <?php if ($can_edit): ?>
                                            <?php if ($del['signoff_status'] === 'draft' || $del['signoff_status'] === 'revision_requested'): ?>
                                                <a href="project_details.php?id=<?= $project_id ?>&tab=deliverables&action=submit_deliverable_review&deliv_id=<?= $del['id'] ?>" class="btn btn-sm btn-outline-primary" style="font-size:12px; padding:3px 10px; border-radius:5px;">
                                                    Submit to Client
                                                </a>
                                            <?php endif; ?>
                                            <a href="project_details.php?id=<?= $project_id ?>&tab=deliverables&action=delete_deliverable&deliv_id=<?= $del['id'] ?>" onclick="return confirm('Remove deliverable?')" class="text-muted" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:13px; color:#94a3b8; font-style:italic;">No deliverable items packaged for this phase yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:36px; text-align:center; color:#94a3b8;">
                <p style="margin:0; font-size:14px;">No milestones created yet. Add your first milestone phase above.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 3: TASKS & KANBAN BOARD                                             -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'tasks'):
        $view_mode = $_GET['view'] ?? 'table';
    ?>
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Project Tasks</h3>
                <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Manage production backlog, task execution, and assignments.</p>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <!-- View Switcher -->
                <div class="btn-group" role="group">
                    <a href="project_details.php?id=<?= $project_id ?>&tab=tasks&view=table" class="btn btn-sm <?= $view_mode === 'table' ? 'btn-primary' : 'btn-outline-secondary' ?>" style="<?= $view_mode === 'table' ? 'background:#024442; border-color:#024442;' : '' ?>">
                        <i class="fas fa-list"></i> Table View
                    </a>
                    <a href="project_details.php?id=<?= $project_id ?>&tab=tasks&view=kanban" class="btn btn-sm <?= $view_mode === 'kanban' ? 'btn-primary' : 'btn-outline-secondary' ?>" style="<?= $view_mode === 'kanban' ? 'background:#024442; border-color:#024442;' : '' ?>">
                        <i class="fas fa-columns"></i> Kanban Board
                    </a>
                </div>
                <a href="export_project_tasks_pdf.php?id=<?= $project_id ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <?php if ($can_edit || $role === 'user'): ?>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('taskFormModal').style.display='flex'" style="background:#024442; border:none;">
                    <i class="fas fa-plus"></i> Add Task
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($view_mode === 'kanban'): ?>
            <!-- Kanban View (Clean 4 Columns) -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: start;">
                <?php
                $col_titles = [
                    'Pending' => 'Pending',
                    'In Progress' => 'In Progress',
                    'In Review' => 'In Review',
                    'Completed' => 'Completed'
                ];
                foreach ($col_titles as $status_key => $col_label):
                    $col_items = $kanban_tasks[$status_key] ?? [];
                ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; min-height: 480px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                        <span style="font-weight:700; font-size:13.5px; color:#334155; text-transform:uppercase;"><?= $col_label ?></span>
                        <span class="badge bg-secondary" style="font-size:11px;"><?= count($col_items) ?></span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php if (!empty($col_items)): ?>
                            <?php foreach ($col_items as $tk):
                                $prioColor = ($tk['priority'] === 'High' ? '#ef4444' : ($tk['priority'] === 'Medium' ? '#f59e0b' : '#3b82f6'));
                            ?>
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                                    <span style="font-weight:700; font-size:13.5px; color:#1e293b;"><?= htmlspecialchars($tk['title']) ?></span>
                                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $prioColor ?>;" title="<?= htmlspecialchars($tk['priority']) ?> Priority"></span>
                                </div>
                                <?php if (!empty($tk['milestone_name'])): ?>
                                    <div style="font-size:11px; color:#0284c7; font-weight:600; margin-bottom:4px;">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($tk['milestone_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:#64748b; margin-top:8px; border-top:1px solid #f1f5f9; padding-top:6px;">
                                    <span><?= !empty($tk['assigned_name']) ? htmlspecialchars($tk['assigned_name']) : 'Unassigned' ?></span>
                                    <span><?= !empty($tk['due_date']) ? date('M d', strtotime($tk['due_date'])) : 'No date' ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:20px; font-size:12px; color:#94a3b8;">No tasks</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Table View -->
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; overflow:hidden;">
                <div class="table-responsive">
                    <table class="table" style="width:100%; margin:0; font-size:13.5px; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#fafbfc; border-bottom:2px solid #e2e8f0; color:#475569; text-align:left;">
                                <th style="padding:12px 16px;">Task Title</th>
                                <th style="padding:12px 16px;">Milestone</th>
                                <th style="padding:12px 16px;">Assignee</th>
                                <th style="padding:12px 16px;">Priority</th>
                                <th style="padding:12px 16px;">Due Date</th>
                                <th style="padding:12px 16px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_project_tasks)): ?>
                                <?php foreach ($all_project_tasks as $tk):
                                    $pClass = ($tk['priority'] === 'High' ? 'text-danger' : ($tk['priority'] === 'Medium' ? 'text-warning' : 'text-info'));
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 16px; font-weight:600; color:#1e293b;">
                                        <?= htmlspecialchars($tk['title']) ?>
                                    </td>
                                    <td style="padding:12px 16px; color:#64748b;">
                                        <?= htmlspecialchars($tk['milestone_name'] ?? 'General') ?>
                                    </td>
                                    <td style="padding:12px 16px; color:#334155;">
                                        <?= htmlspecialchars($tk['assigned_name'] ?? 'Unassigned') ?>
                                    </td>
                                    <td style="padding:12px 16px; font-weight:600;" class="<?= $pClass ?>">
                                        <?= htmlspecialchars($tk['priority']) ?>
                                    </td>
                                    <td style="padding:12px 16px; color:#64748b;">
                                        <?= !empty($tk['due_date']) ? date('M d, Y', strtotime($tk['due_date'])) : 'None' ?>
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <select onchange="onTaskStatusDropdownChange(this, <?= $tk['id'] ?>)" class="form-select form-select-sm" style="font-size:12.5px; padding:3px 8px; width:auto;">
                                            <option value="Pending" <?= $tk['status'] === 'Pending' || $tk['status'] === 'Not Started' ? 'selected' : '' ?>>Pending</option>
                                            <option value="In Progress" <?= $tk['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="In Review" <?= $tk['status'] === 'In Review' ? 'selected' : '' ?>>In Review</option>
                                            <option value="Completed" <?= $tk['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:32px; color:#94a3b8;">No tasks created for this project yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 4: PENDING FROM CLIENT (ASSET & INFORMATION REQUESTS)              -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'client-requests'): ?>
    <div style="display:flex; flex-direction:column; gap:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Client Information & Asset Requests</h3>
                <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Track missing client assets and credentials that are blocking milestone delivery.</p>
            </div>
            <?php if ($can_edit): ?>
            <button class="btn btn-sm btn-primary" onclick="document.getElementById('modalAddClientRequest').style.display='flex'" style="background:#024442; border:none; font-weight:600; padding:6px 16px; border-radius:6px;">
                <i class="fas fa-plus"></i> Request Item from Client
            </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($client_requests)): ?>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($client_requests as $req):
                    $isPending = ($req['status'] === 'requested' || $req['status'] === 'rejected');
                    $isSubmitted = ($req['status'] === 'submitted');
                    $isAccepted = ($req['status'] === 'accepted');
                ?>
                <div class="card" style="border: 1px solid <?= $isPending && $req['priority'] === 'blocker' ? '#fecaca' : '#e2e8f0' ?>; border-radius: 10px; padding: 20px; background:#fff;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:10px;">
                        <div>
                            <?php if ($req['priority'] === 'blocker'): ?>
                                <span class="badge bg-danger text-uppercase me-2" style="font-size:10.5px;">Blocker</span>
                            <?php endif; ?>
                            <strong style="font-size:15px; color:#0f172a;"><?= htmlspecialchars($req['request_title']) ?></strong>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Type: <span class="text-capitalize"><?= htmlspecialchars($req['request_type']) ?></span> • Due: <?= !empty($req['due_date']) ? date('M d, Y', strtotime($req['due_date'])) : 'Flexible' ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($isAccepted): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Accepted & Verified</span>
                            <?php elseif ($isSubmitted): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Client Submitted - Needs Verification</span>
                            <?php elseif ($req['status'] === 'rejected'): ?>
                                <span class="badge bg-danger"><i class="fas fa-redo"></i> Re-upload Requested</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Waiting for Client</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($req['instructions'])): ?>
                        <div style="font-size:13px; color:#475569; margin-bottom:12px; line-height:1.5;">
                            <strong>Instructions:</strong> <?= nl2br(htmlspecialchars($req['instructions'])) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Submitted Data Preview & Actions -->
                    <?php if ($isSubmitted || $isAccepted): ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-top:10px;">
                        <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">Client Submission:</div>
                        <?php if (!empty($req['submitted_file'])): ?>
                            <div style="margin-bottom:6px;">
                                <a href="<?= htmlspecialchars($req['submitted_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:12px;">
                                    <i class="fas fa-download"></i> Download Submitted Asset (<?= basename($req['submitted_file']) ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($req['submitted_text'])): ?>
                            <div style="font-size:13px; color:#334155; background:#fff; border:1px solid #e2e8f0; padding:8px 12px; border-radius:4px; white-space:pre-wrap;">
                                <?= htmlspecialchars($req['submitted_text']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Verification Controls -->
                        <?php if ($isSubmitted && $can_edit): ?>
                        <div style="margin-top:12px; display:flex; gap:10px; align-items:center;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="verify_client_request" value="1">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <input type="hidden" name="decision" value="accept">
                                <button type="submit" class="btn btn-sm btn-success" style="font-size:12px; padding:4px 14px;">
                                    <i class="fas fa-check"></i> Accept Asset
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="openRejectModal(<?= $req['id'] ?>)" style="font-size:12px; padding:4px 14px;">
                                <i class="fas fa-times"></i> Request Re-upload
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:36px; text-align:center; color:#94a3b8;">
                <p style="margin:0; font-size:14px;">No asset or information requests created yet.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 5: CLIENT QUERIES (HELPDESK TICKETS)                                -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'client-queries'): ?>
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div>
            <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Client Inquiries & Clarifications</h3>
            <p style="margin:2px 0 0; font-size:13px; color:#64748b;">Direct questions from client contacts with threaded team responses and internal notes.</p>
        </div>

        <?php if (!empty($client_queries)): ?>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($client_queries as $q):
                    $q_id = intval($q['id']);
                ?>
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background:#fff;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:12px;">
                        <div>
                            <span class="badge bg-light text-dark border text-capitalize me-2"><?= htmlspecialchars($q['category']) ?></span>
                            <strong style="font-size:15px; color:#0f172a;"><?= htmlspecialchars($q['subject']) ?></strong>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Asked by <?= htmlspecialchars($q['client_name']) ?> • <?= date('M d, Y H:i', strtotime($q['created_at'])) ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="badge <?= $q['status'] === 'resolved' || $q['status'] === 'closed' ? 'bg-success' : 'bg-warning text-dark' ?> text-capitalize">
                                <?= htmlspecialchars($q['status']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Replies Thread -->
                    <?php
                    $reps = $conn->query("
                        SELECT qr.*, u.name as user_name, u.role as user_role
                        FROM project_query_replies qr
                        JOIN users u ON qr.user_id = u.user_id
                        WHERE qr.query_id = {$q_id}
                        ORDER BY qr.created_at ASC
                    ");
                    if ($reps && $reps->num_rows > 0):
                    ?>
                    <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
                        <?php while ($r = $reps->fetch_assoc()):
                            $isInternal = intval($r['is_internal']) === 1;
                        ?>
                        <div style="background:<?= $isInternal ? '#fffdf5' : '#f8fafc' ?>; border:1px solid <?= $isInternal ? '#fef3c7' : '#e2e8f0' ?>; border-left:3px solid <?= $isInternal ? '#f59e0b' : '#024442' ?>; padding:10px 14px; border-radius:6px;">
                            <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b; margin-bottom:4px;">
                                <strong>
                                    <?= htmlspecialchars($r['user_name']) ?> (<?= ucfirst($r['user_role']) ?>)
                                    <?php if ($isInternal): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Internal Note (Hidden from client)</span>
                                    <?php endif; ?>
                                </strong>
                                <span><?= date('M d, H:i', strtotime($r['created_at'])) ?></span>
                            </div>
                            <div style="font-size:13.5px; color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Reply Form -->
                    <form method="POST" style="background:#fafbfc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                        <input type="hidden" name="reply_client_query" value="1">
                        <input type="hidden" name="query_id" value="<?= $q['id'] ?>">

                        <div class="mb-2">
                            <textarea name="reply_message" rows="2" class="form-control form-control-sm" placeholder="Write response to client (or check Internal Note below)..." required style="font-size:13px;"></textarea>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:16px;">
                                <label style="font-size:12.5px; color:#475569; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                    <input type="checkbox" name="is_internal" value="1"> Internal Note (Team Only)
                                </label>
                                <select name="set_query_status" class="form-select form-select-sm" style="font-size:12px; width:auto;">
                                    <option value="">Keep Status (<?= ucfirst($q['status']) ?>)</option>
                                    <option value="in_progress">Set In Progress</option>
                                    <option value="resolved">Mark Resolved</option>
                                    <option value="closed">Close Ticket</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:5px 16px; font-weight:600; font-size:12.5px;">
                                Send Reply
                            </button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:36px; text-align:center; color:#94a3b8;">
                <p style="margin:0; font-size:14px;">No client inquiries on this project yet.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 6: UPDATES & DISCUSSION                                             -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'updates'): ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">
        <!-- Left: Compose & Broadcast Updates -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-bullhorn text-muted"></i> Broadcast Progress Update</h3>
            <form method="POST">
                <input type="hidden" name="create_project_broadcast" value="1">

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Update Title</label>
                    <input type="text" name="broadcast_title" class="form-control form-control-sm" value="Weekly Progress Update - <?= date('M d') ?>" required style="font-size:13.5px;">
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#065f46; display:block; margin-bottom:4px;">Completed This Week</label>
                    <textarea name="broadcast_completed" rows="3" class="form-control form-control-sm" placeholder="List completed tasks, features, or assets..." style="font-size:13px;"></textarea>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#1e40af; display:block; margin-bottom:4px;">Currently In Progress</label>
                    <textarea name="broadcast_in_progress" rows="3" class="form-control form-control-sm" placeholder="List tasks currently active..." style="font-size:13px;"></textarea>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#6b21a8; display:block; margin-bottom:4px;">Coming Up Next</label>
                    <textarea name="broadcast_next" rows="2" class="form-control form-control-sm" placeholder="Next scheduled milestone deliverables..." style="font-size:13px;"></textarea>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#92400e; display:block; margin-bottom:4px;">Items Required from Client</label>
                    <textarea name="broadcast_blockers" rows="2" class="form-control form-control-sm" placeholder="Any pending feedback or assets..." style="font-size:13px;"></textarea>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; color:#475569; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="send_email_notification" value="1" checked> Send formatted HTML update email to client
                    </label>
                </div>

                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:8px 20px; font-weight:600; width:100%;">
                    Publish Update
                </button>
            </form>
        </div>

        <!-- Right: Comments & Discussion -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-comments text-muted"></i> Project Discussion</h3>

            <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px; background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:8px;">
                <input type="hidden" name="add_project_comment" value="1">
                <div class="mb-2">
                    <textarea name="comment_text" rows="3" class="form-control form-control-sm" placeholder="Post a comment or discussion message..." required style="font-size:13px;"></textarea>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <input type="file" name="comment_file" style="font-size:12px;">
                    <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:4px 14px; font-size:12px; font-weight:600;">Post</button>
                </div>
            </form>

            <?php
            $stmt_comms = $conn->prepare("SELECT pc.*, u.name, u.role FROM project_comments pc JOIN users u ON pc.user_id = u.user_id WHERE pc.project_id = ? ORDER BY pc.created_at DESC");
            $stmt_comms->bind_param("i", $project_id);
            $stmt_comms->execute();
            $comms_res = $stmt_comms->get_result();
            $stmt_comms->close();
            ?>
            <div style="display:flex; flex-direction:column; gap:12px; max-height:500px; overflow-y:auto;">
                <?php if ($comms_res && $comms_res->num_rows > 0): ?>
                    <?php while ($c = $comms_res->fetch_assoc()):
                        $init = strtoupper(substr($c['name'], 0, 1));
                    ?>
                    <div style="background:#fafbfc; border:1px solid #e2e8f0; padding:10px 12px; border-radius:6px; font-size:13px;">
                        <div style="display:flex; justify-content:space-between; color:#64748b; font-size:11.5px; margin-bottom:4px;">
                            <strong><?= htmlspecialchars($c['name']) ?> (<?= ucfirst($c['role']) ?>)</strong>
                            <span><?= date('M d, H:i', strtotime($c['created_at'])) ?></span>
                        </div>
                        <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($c['comment_text'])) ?></div>
                        <?php if (!empty($c['file_path'])): ?>
                            <div style="margin-top:6px;">
                                <a href="<?= htmlspecialchars($c['file_path']) ?>" target="_blank" style="font-size:11.5px; color:#024442; text-decoration:none;">
                                    <i class="fas fa-paperclip"></i> <?= htmlspecialchars(basename($c['file_path'])) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">No comments posted yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================================= -->
    <!-- TAB 7: INVOICES & FILES                                                 -->
    <!-- ======================================================================= -->
    <?php if ($active_tab === 'invoices'): ?>
    <div style="display:flex; flex-direction:column; gap:24px;">
        <!-- Invoices Card -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-file-invoice-dollar text-muted"></i> Project Invoices</h3>
            <div class="table-responsive">
                <table class="table" style="width:100%; margin:0; font-size:13.5px;">
                    <thead>
                        <tr style="background:#fafbfc; border-bottom:2px solid #e2e8f0; color:#475569;">
                            <th style="padding:10px;">Invoice #</th>
                            <th style="padding:10px;">Due Date</th>
                            <th style="padding:10px;">Amount</th>
                            <th style="padding:10px;">Status</th>
                            <th style="padding:10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invoices && $invoices->num_rows > 0): ?>
                            <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px; font-weight:600;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                <td style="padding:10px;"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                                <td style="padding:10px; font-weight:600;"><?= number_format($inv['total_amount'], 2) ?> <?= $inv['currency'] ?></td>
                                <td style="padding:10px;">
                                    <span class="badge" style="background:<?= $inv['status'] === 'paid' ? '#ecfdf5;color:#10b981;' : '#fef3c7;color:#d97706;' ?> text-capitalize">
                                        <?= htmlspecialchars($inv['status']) ?>
                                    </span>
                                </td>
                                <td style="padding:10px;">
                                    <a href="generate_invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:12px; padding:3px 10px;">
                                        PDF
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">No invoices generated for this project.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Files Card -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-folder-open text-muted"></i> Project Files</h3>
            <?php if ($files && $files->num_rows > 0): ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php while ($f = $files->fetch_assoc()): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#fafbfc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:6px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-file-alt text-muted"></i>
                            <div>
                                <strong style="font-size:13.5px;"><?= htmlspecialchars($f['title'] ?: basename($f['file_path'])) ?></strong>
                                <div style="font-size:11.5px; color:#94a3b8;"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></div>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:12px;">Download</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">No files uploaded.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================================= -->
<!-- MODALS                                                                  -->
<!-- ======================================================================= -->

<!-- Modal: Add Milestone -->
<div id="modalAddMilestone" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:500px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Add Project Milestone</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="document.getElementById('modalAddMilestone').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="create_milestone" value="1">
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Milestone Title</label>
                <input type="text" name="milestone_title" placeholder="e.g. Discovery & Wireframing" class="form-control form-control-sm" required style="font-size:13.5px;">
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Description / Scope</label>
                <textarea name="milestone_desc" rows="3" class="form-control form-control-sm" placeholder="Brief outline of milestone objectives..." style="font-size:13px;"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Due Date</label>
                    <input type="date" name="milestone_due_date" class="form-control form-control-sm" style="font-size:13px;">
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Phase Order #</label>
                    <input type="number" name="milestone_order" value="<?= count($milestones) + 1 ?>" min="1" class="form-control form-control-sm" style="font-size:13px;">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('modalAddMilestone').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; font-weight:600;">Save Milestone</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Deliverable -->
<div id="modalAddDeliverable" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:550px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Package Deliverable for Client</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="document.getElementById('modalAddDeliverable').style.display='none'">&times;</span>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="create_deliverable" value="1">
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Select Milestone</label>
                <select name="deliverable_milestone_id" class="form-select form-select-sm" required style="font-size:13px;">
                    <?php foreach ($milestones as $m): ?>
                        <option value="<?= $m['id'] ?>">Phase <?= $m['order_num'] ?>: <?= htmlspecialchars($m['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Deliverable Title</label>
                    <input type="text" name="deliverable_title" placeholder="e.g. Brand Identity Guidelines & Vector Pack" class="form-control form-control-sm" required style="font-size:13px;">
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Version</label>
                    <input type="text" name="deliverable_version" value="1.0" class="form-control form-control-sm" style="font-size:13px;">
                </div>
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Specifications / Notes</label>
                <textarea name="deliverable_specs" rows="2" class="form-control form-control-sm" placeholder="Included formats, dimensions, instructions..." style="font-size:13px;"></textarea>
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Attach Deliverable File (ZIP, PDF, etc.)</label>
                <input type="file" name="deliverable_file" class="form-control form-control-sm" style="font-size:12.5px;">
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Live Preview URL (Optional)</label>
                <input type="url" name="deliverable_preview_url" placeholder="https://figma.com/... or staging link" class="form-control form-control-sm" style="font-size:13px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('modalAddDeliverable').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; font-weight:600;">Save Deliverable</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Client Request (Missing Asset / Info) -->
<div id="modalAddClientRequest" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:500px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Request Asset or Info from Client</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="document.getElementById('modalAddClientRequest').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="create_client_request" value="1">
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Item Title</label>
                <input type="text" name="request_title" placeholder="e.g. High-res Vector Logo (SVG/EPS)" class="form-control form-control-sm" required style="font-size:13.5px;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Request Type</label>
                    <select name="request_type" class="form-select form-select-sm" style="font-size:13px;">
                        <option value="file">File Upload</option>
                        <option value="credentials">Credentials / Passwords</option>
                        <option value="text">Content / Copy</option>
                        <option value="feedback">Approval / Feedback</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Priority</label>
                    <select name="request_priority" class="form-select form-select-sm" style="font-size:13px;">
                        <option value="normal">Normal</option>
                        <option value="blocker">Blocker (Halts Delivery)</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Instructions for Client</label>
                <textarea name="request_instructions" rows="3" class="form-control form-control-sm" placeholder="Specify format, requirements, or what is needed..." style="font-size:13px;"></textarea>
            </div>
            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Due Date</label>
                <input type="date" name="request_due_date" class="form-control form-control-sm" style="font-size:13px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('modalAddClientRequest').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; font-weight:600;">Send Request to Client</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Re-upload / Reject Request -->
<div id="modalRejectRequest" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:480px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Request Re-upload from Client</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="document.getElementById('modalRejectRequest').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="verify_client_request" value="1">
            <input type="hidden" name="decision" value="reject">
            <input type="hidden" name="request_id" id="rejectRequestId" value="">

            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Feedback / Rejection Reason</label>
                <textarea name="rejection_reason" rows="3" class="form-control form-control-sm" placeholder="e.g. The PNG is too low resolution, please provide the original vector EPS/SVG..." required style="font-size:13px;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('modalRejectRequest').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger" style="font-weight:600;">Request Re-upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Task -->
<div id="taskFormModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:550px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Add Project Task</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="document.getElementById('taskFormModal').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="create_project_task" value="1">

            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Task Title</label>
                <input type="text" name="task_title" placeholder="e.g. Design responsive navbar component" class="form-control form-control-sm" required style="font-size:13.5px;">
            </div>

            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Milestone Phase</label>
                <select name="task_milestone_id" class="form-select form-select-sm" style="font-size:13px;">
                    <option value="">General (No Milestone)</option>
                    <?php foreach ($milestones as $m): ?>
                        <option value="<?= $m['id'] ?>">Phase <?= $m['order_num'] ?>: <?= htmlspecialchars($m['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Priority</label>
                    <select name="task_priority" class="form-select form-select-sm" style="font-size:13px;">
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Due Date</label>
                    <input type="date" name="task_due_date" class="form-control form-control-sm" style="font-size:13px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:16px;">
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Assignee</label>
                    <select name="task_assigned_to" class="form-select form-select-sm" style="font-size:13px;">
                        <option value="">Unassigned</option>
                        <?php foreach ($active_task_users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Est. Hours</label>
                    <input type="number" step="0.5" name="task_estimated_hours" value="0" class="form-control form-control-sm" style="font-size:13px;">
                </div>
            </div>

            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;">Description / Details</label>
                <textarea name="task_desc" rows="3" class="form-control form-control-sm" placeholder="Task requirements..." style="font-size:13px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('taskFormModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; font-weight:600;">Save Task</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('modalRejectRequest').style.display = 'flex';
}

function onTaskStatusDropdownChange(selectEl, taskId) {
    const newStatus = selectEl.value;
    selectEl.disabled = true;

    const formData = new FormData();
    formData.append('id', taskId);
    formData.append('status', newStatus);

    fetch('update_task_status.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        selectEl.disabled = false;
        if (!data.success) {
            alert('Failed to update task status: ' + (data.message || 'Error'));
        }
    })
    .catch(err => {
        selectEl.disabled = false;
        console.error(err);
    });
}
</script>

<?php include 'dashboard_footer.php'; ?>
