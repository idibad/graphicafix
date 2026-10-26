<?php
include 'dashboard_header.php';
require_once __DIR__ . '/../core/project_pm_schema.php';
require_once __DIR__ . '/../core/project_pm_helper.php';

// Verify role access
if ($role !== 'client' && $role !== 'client_sub') {
    header("Location: index.php");
    exit;
}

$project_id = intval($_GET['id'] ?? 0);
$client_id = intval($data['client_id'] ?? 0);

// Auto-migrate PM tables if not present
ensure_project_pm_schema($conn);

// Fetch project details and verify ownership
$stmt = $conn->prepare("
    SELECT p.*, c.client_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.id = ? AND p.client_id = ? AND p.visibility IN ('client', 'public')
");
$stmt->bind_param("ii", $project_id, $client_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    echo "<script>alert('Project not found or unauthorized access.'); window.location='client_projects.php';</script>";
    exit;
}

// ── Form Processing Actions ──────────────────────────────────────────────────

// 1. Submit pending asset / information requested by Agency
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_client_request'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    $submitted_text = trim($_POST['submitted_text'] ?? '');
    $submitted_file = null;

    if (!empty($_FILES['request_file']['name'])) {
        $upload_dir = "uploads/projects/" . $project_id . "/client_requests/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $fname = basename($_FILES['request_file']['name']);
        $submitted_file = $upload_dir . time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fname);
        move_uploaded_file($_FILES['request_file']['tmp_name'], $submitted_file);

        // Also log in project_files
        $stmt_pf = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_type, title, uploaded_by) VALUES (?, ?, 'document', ?, ?)");
        $pftitle = "Client Asset: " . $fname;
        $stmt_pf->bind_param("issi", $project_id, $submitted_file, $pftitle, $user_id);
        $stmt_pf->execute();
        $stmt_pf->close();
    }

    $stmt_upd = $conn->prepare("
        UPDATE project_client_requests
        SET status = 'submitted',
            submitted_file = COALESCE(?, submitted_file),
            submitted_text = ?,
            submitted_at = NOW(),
            rejection_reason = NULL
        WHERE id = ? AND project_id = ?
    ");
    $stmt_upd->bind_param("ssii", $submitted_file, $submitted_text, $request_id, $project_id);
    if ($stmt_upd->execute()) {
        $stmt_upd->close();

        // Notify agency
        require_once __DIR__ . '/../core/task_email_helper.php';
        $subject = "Client Submitted Asset: " . $project['project_name'];
        $heading = "Client Requirement Submitted";
        $bodyHtml = "<p>Client contact <strong>" . htmlspecialchars($name) . "</strong> has submitted requested assets for project <strong>" . htmlspecialchars($project['project_name']) . "</strong>.</p>";

        $t_res = $conn->query("SELECT u.email FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = {$project_id}");
        if ($t_res) {
            while ($tm = $t_res->fetch_assoc()) {
                if (!empty($tm['email'])) sendTaskEmail($tm['email'], $subject, $heading, $bodyHtml, 0);
            }
        }
        $admin_res = $conn->query("SELECT email FROM users WHERE role IN ('super_admin', 'superadmin', 'admin')");
        if ($admin_res) {
            while ($adm = $admin_res->fetch_assoc()) {
                if (!empty($adm['email'])) sendTaskEmail($adm['email'], $subject, $heading, $bodyHtml, 0);
            }
        }

        echo "<script>alert('Thank you! Information submitted successfully.'); window.location='client_project_details.php?id={$project_id}&tab=requests';</script>";
        exit;
    }
}

// 2. Deliverable Sign-Off / Revision Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_deliverable_action'])) {
    $deliverable_id = intval($_POST['deliverable_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $revision_notes = trim($_POST['revision_notes'] ?? '');

    if ($decision === 'approve') {
        $stmt_app = $conn->prepare("
            UPDATE project_deliverables
            SET signoff_status = 'approved',
                signed_off_at = NOW(),
                signed_off_by = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt_app->bind_param("iii", $user_id, $deliverable_id, $project_id);
        $stmt_app->execute();
        $stmt_app->close();

        // Notify agency team of formal client sign-off
        require_once __DIR__ . '/../core/notification_mailer.php';
        notifyDeliverableApproved($conn, $project_id, $deliverable_id, $user_id);

        echo "<script>alert('Deliverable approved successfully!'); window.location='client_project_details.php?id={$project_id}&tab=deliverables';</script>";
        exit;
    } elseif ($decision === 'request_revision') {
        $stmt_rev = $conn->prepare("
            UPDATE project_deliverables
            SET signoff_status = 'revision_requested',
                revision_notes = ?,
                revision_count = revision_count + 1
            WHERE id = ? AND project_id = ?
        ");
        $stmt_rev->bind_param("sii", $revision_notes, $deliverable_id, $project_id);
        $stmt_rev->execute();
        $stmt_rev->close();

        // Notify agency
        require_once __DIR__ . '/../core/task_email_helper.php';
        $subject = "Revision Requested on Deliverable - " . $project['project_name'];
        $heading = "Client Requested Deliverable Revision";
        $bodyHtml = "<p>Client contact <strong>" . htmlspecialchars($name) . "</strong> has requested revisions on a deliverable for project <strong>" . htmlspecialchars($project['project_name']) . "</strong>.</p>
                     <div style='background:#f8fafc; border-left:4px solid #f59e0b; padding:12px; margin:16px 0;'>" . nl2br(htmlspecialchars($revision_notes)) . "</div>";

        $t_res = $conn->query("SELECT u.email FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = {$project_id}");
        if ($t_res) {
            while ($tm = $t_res->fetch_assoc()) {
                if (!empty($tm['email'])) sendTaskEmail($tm['email'], $subject, $heading, $bodyHtml, 0);
            }
        }

        echo "<script>alert('Revision request submitted to the team.'); window.location='client_project_details.php?id={$project_id}&tab=deliverables';</script>";
        exit;
    }
}

// 3. Submit New Client Query / Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_client_query'])) {
    $subject = trim($_POST['query_subject'] ?? '');
    $category = $_POST['query_category'] ?? 'general';
    $priority = $_POST['query_priority'] ?? 'normal';
    $message = trim($_POST['query_message'] ?? '');

    if (!empty($subject) && !empty($message)) {
        $stmt_q = $conn->prepare("
            INSERT INTO project_client_queries (project_id, client_user_id, subject, category, priority, status)
            VALUES (?, ?, ?, ?, ?, 'open')
        ");
        $stmt_q->bind_param("iisss", $project_id, $user_id, $subject, $category, $priority);
        if ($stmt_q->execute()) {
            $query_id = $conn->insert_id;
            $stmt_q->close();

            $attachment_path = null;
            if (!empty($_FILES['query_file']['name'])) {
                $upload_dir = "uploads/projects/" . $project_id . "/queries/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $fname = basename($_FILES['query_file']['name']);
                $attachment_path = $upload_dir . time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fname);
                move_uploaded_file($_FILES['query_file']['tmp_name'], $attachment_path);
            }

            $stmt_rep = $conn->prepare("
                INSERT INTO project_query_replies (query_id, user_id, is_internal, message, attachment_path)
                VALUES (?, ?, 0, ?, ?)
            ");
            $stmt_rep->bind_param("iiss", $query_id, $user_id, $message, $attachment_path);
            $stmt_rep->execute();
            $stmt_rep->close();

            // Notify agency
            require_once __DIR__ . '/../core/task_email_helper.php';
            $mailSubject = "New Client Query: " . $subject . " [" . $project['project_name'] . "]";
            $heading = "New Project Query Received";
            $bodyHtml = "<p>Client contact <strong>" . htmlspecialchars($name) . "</strong> submitted a query regarding <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
                         <p><strong>Category:</strong> " . htmlspecialchars($category) . "<br><strong>Priority:</strong> " . htmlspecialchars($priority) . "</p>
                         <blockquote style='background:#f8fafc; border-left:4px solid #024442; padding:12px; margin:16px 0;'>" . nl2br(htmlspecialchars($message)) . "</blockquote>";

            $admin_res = $conn->query("SELECT email FROM users WHERE role IN ('super_admin', 'superadmin', 'admin', 'pm')");
            if ($admin_res) {
                while ($adm = $admin_res->fetch_assoc()) {
                    if (!empty($adm['email'])) sendTaskEmail($adm['email'], $mailSubject, $heading, $bodyHtml, 0);
                }
            }

            echo "<script>alert('Your question has been sent to the team.'); window.location='client_project_details.php?id={$project_id}&tab=queries';</script>";
            exit;
        }
    }
}

// 4. Add Comment in Discussion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client_comment'])) {
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
            echo "<script>alert('Message posted successfully!'); window.location='client_project_details.php?id={$project_id}&tab=discussion';</script>";
            exit;
        }
    }
}

// ── Data Queries ─────────────────────────────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'overview';
$allowed_tabs = ['overview', 'deliverables', 'requests', 'queries', 'updates', 'discussion', 'files'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'overview';

$kpis = getProjectKPIs($conn, $project_id);

// Pending Client Requests
$res_pending_requests = $conn->query("
    SELECT *
    FROM project_client_requests
    WHERE project_id = {$project_id}
    ORDER BY CASE WHEN status = 'requested' THEN 1 WHEN status = 'rejected' THEN 2 ELSE 3 END, id DESC
");
$client_requests = $res_pending_requests ? $res_pending_requests->fetch_all(MYSQLI_ASSOC) : [];

// Milestones & Deliverables
$res_milestones = $conn->query("
    SELECT *
    FROM project_milestones
    WHERE project_id = {$project_id}
    ORDER BY order_num ASC, id ASC
");
$milestones = $res_milestones ? $res_milestones->fetch_all(MYSQLI_ASSOC) : [];

$res_delivs = $conn->query("
    SELECT d.*, m.title as milestone_title
    FROM project_deliverables d
    LEFT JOIN project_milestones m ON d.milestone_id = m.id
    WHERE d.project_id = {$project_id}
    ORDER BY d.id DESC
");
$deliverables = $res_delivs ? $res_delivs->fetch_all(MYSQLI_ASSOC) : [];

// Client Queries
$res_queries = $conn->query("
    SELECT q.*, u.name as assigned_name
    FROM project_client_queries q
    LEFT JOIN users u ON q.assigned_to = u.user_id
    WHERE q.project_id = {$project_id}
    ORDER BY q.created_at DESC
");
$queries = $res_queries ? $res_queries->fetch_all(MYSQLI_ASSOC) : [];

// Project Broadcasts
$res_broadcasts = $conn->query("
    SELECT b.*, u.name as sender_name
    FROM project_broadcasts b
    JOIN users u ON b.created_by = u.user_id
    WHERE b.project_id = {$project_id}
    ORDER BY b.created_at DESC
");
$broadcasts = $res_broadcasts ? $res_broadcasts->fetch_all(MYSQLI_ASSOC) : [];

// Files
$stmt_files = $conn->prepare("
    SELECT pf.*, u.name as uploader_name, u.role as uploader_role
    FROM project_files pf
    LEFT JOIN users u ON pf.uploaded_by = u.user_id
    WHERE pf.project_id = ? AND pf.file_type != 'thumbnail'
    ORDER BY pf.uploaded_at DESC
");
$stmt_files->bind_param("i", $project_id);
$stmt_files->execute();
$files = $stmt_files->get_result();
$stmt_files->close();
?>

<div class="height-100">
    <!-- Project Header -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h1 style="margin: 0; color:#0f172a; font-size: 26px; font-weight: 700;"><?= htmlspecialchars($project['project_name']) ?></h1>
                <div style="margin-top: 6px; color:#64748b; font-size: 14px;">
                    <i class="fas fa-building text-muted"></i> <?= htmlspecialchars($project['client_name']) ?>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="client_projects.php" class="btn btn-secondary btn-sm" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-weight:600; padding:8px 16px; border-radius:8px; text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
            </div>
        </div>

        <!-- Meta Details -->
        <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:20px; border-top:1px solid #f1f5f9; padding-top:16px; font-size:13.5px; color:#64748b;">
            <div>
                <strong>Status:</strong>
                <span class="badge" style="background: <?= $project['status'] === 'Completed' ? '#ecfdf5;color:#10b981;' : ($project['status'] === 'In Progress' ? '#e0f2fe;color:#0284c7;' : '#fef3c7;color:#d97706;') ?>">
                    <?= htmlspecialchars($project['status']) ?>
                </span>
            </div>
            <div>
                <strong>Project Health:</strong>
                <span class="fw-bold <?= $kpis['health_class'] ?>"><?= $kpis['health_status'] ?></span>
            </div>
            <div>
                <strong>Start Date:</strong> <?= !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'TBD' ?>
            </div>
            <div>
                <strong>Target End Date:</strong> <?= !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'TBD' ?>
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

    <!-- Outstanding Client Blocker Alert (Clean Notification) -->
    <?php if ($kpis['pending_client_requests'] > 0): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-exclamation-triangle" style="color:#d97706; font-size: 20px;"></i>
            <div>
                <strong style="color: #92400e; font-size: 14.5px;">Action Required from You</strong>
                <div style="color: #b45309; font-size: 13px; margin-top: 2px;">
                    The agency team is waiting on <?= $kpis['pending_client_requests'] ?> item(s) to continue delivery without delay.
                </div>
            </div>
        </div>
        <a href="client_project_details.php?id=<?= $project_id ?>&tab=requests" class="btn btn-sm" style="background: #024442; color:#fff; font-weight: 600; padding: 6px 16px; border-radius: 6px; text-decoration:none;">
            View Required Items
        </a>
    </div>
    <?php endif; ?>

    <style>
        .pm-nav-tabs-wrapper {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
            padding-bottom: 2px;
            cursor: grab;
            user-select: none;
            -webkit-user-select: none;
        }
        .pm-nav-tabs-wrapper.is-dragging {
            cursor: grabbing;
        }
        .pm-nav-tabs-wrapper::-webkit-scrollbar {
            height: 6px;
        }
        .pm-nav-tabs-wrapper::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }
        .pm-nav-tabs-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .pm-nav-tabs-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .pm-nav-tabs {
            display: inline-flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            white-space: nowrap !important;
            border-bottom: none !important;
            margin: 0 !important;
            padding: 0 !important;
            gap: 2px;
            list-style: none;
        }
        .pm-nav-tabs .nav-item {
            flex-shrink: 0 !important;
            display: inline-block !important;
        }
        .pm-nav-tabs .nav-link {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            white-space: nowrap !important;
            padding: 12px 18px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            border: none !important;
            border-bottom: 3px solid transparent !important;
            background: transparent !important;
            color: #64748b !important;
            margin-bottom: -2px;
            text-decoration: none;
            transition: color 0.15s ease, border-color 0.15s ease;
        }
        .pm-nav-tabs .nav-link:hover {
            color: #024442 !important;
        }
        .pm-nav-tabs .nav-link.active {
            color: #024442 !important;
            border-bottom-color: #024442 !important;
            font-weight: 700 !important;
            background: transparent !important;
        }
        .pm-nav-tabs .badge {
            font-size: 11px;
            padding: 3px 7px;
            border-radius: 10px;
        }
    </style>

    <!-- Navigation Tabs (Horizontally Scrollable Inline) -->
    <div class="pm-nav-tabs-wrapper">
        <ul class="nav nav-tabs pm-nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'overview' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=overview">
                    <i class="fas fa-info-circle"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'deliverables' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=deliverables">
                    <i class="fas fa-box-open"></i> Deliverables
                    <?php if ($kpis['pending_review_deliverables'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= $kpis['pending_review_deliverables'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'requests' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=requests">
                    <i class="fas fa-clipboard-list"></i> Required from You
                    <?php if ($kpis['pending_client_requests'] > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $kpis['pending_client_requests'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'queries' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=queries">
                    <i class="fas fa-question-circle"></i> Ask Questions
                    <?php if ($kpis['open_client_queries'] > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $kpis['open_client_queries'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'updates' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=updates">
                    <i class="fas fa-bullhorn"></i> Project Updates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'discussion' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=discussion">
                    <i class="fas fa-comments"></i> Discussion
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'files' ? 'active' : '' ?>" href="client_project_details.php?id=<?= $project_id ?>&tab=files">
                    <i class="fas fa-folder"></i> Shared Files
                </a>
            </li>
        </ul>
    </div>

    <!-- Tab 1: Overview -->
    <?php if ($active_tab === 'overview'): ?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
        <div style="display:flex; flex-direction:column; gap:24px;">
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff;">
                <div class="card-header" style="background:#fff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px;">
                    <h2 class="card-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-file-alt text-muted"></i> Project Summary</h2>
                </div>
                <div style="padding: 20px; font-size: 14.5px; color:#334155; line-height: 1.7;">
                    <?= !empty($project['public_description']) ? strip_tags($project['public_description']) : (!empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : 'No project description is currently provided.') ?>
                </div>
            </div>

            <!-- Milestones Progress Timeline -->
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff;">
                <div class="card-header" style="background:#fff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px;">
                    <h2 class="card-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><i class="fas fa-map-marker-alt text-muted"></i> Milestone Roadmap</h2>
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
                                        Target: <?= !empty($m['due_date']) ? date('M d, Y', strtotime($m['due_date'])) : 'TBD' ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="color:#94a3b8; font-size:13.5px; text-align:center; padding:16px;">Milestones for this project are currently being scheduled by the agency lead.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:24px;">
            <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
                <h4 style="margin:0 0 16px; font-size:15px; font-weight:700; color:#0f172a;"><i class="fas fa-chart-pie text-muted"></i> Deliverable Status</h4>
                <div style="display:flex; flex-direction:column; gap:12px; font-size:13.5px;">
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Total Milestones:</span>
                        <strong><?= $kpis['total_milestones'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Deliverables Approved:</span>
                        <strong class="text-success"><?= $kpis['approved_deliverables'] ?> / <?= $kpis['total_deliverables'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Awaiting Your Review:</span>
                        <strong class="text-warning"><?= $kpis['pending_review_deliverables'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#475569;">
                        <span>Items Needed from You:</span>
                        <strong class="text-danger"><?= $kpis['pending_client_requests'] ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 2: Deliverables & Sign-Off -->
    <?php if ($active_tab === 'deliverables'): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
        <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Project Deliverables & Sign-Off</h3>
            <p style="margin:4px 0 0; font-size:13.5px; color:#64748b;">Review delivered packages from the agency. You can inspect assets, approve them, or request revisions.</p>
        </div>

        <?php if (!empty($deliverables)): ?>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($deliverables as $del): ?>
                <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; background: #fafbfc;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                        <div>
                            <span style="font-size:11.5px; text-transform:uppercase; font-weight:700; color:#64748b;">
                                <?= htmlspecialchars($del['milestone_title'] ?? 'Deliverable') ?>
                            </span>
                            <h4 style="margin:4px 0 0; font-size:16px; font-weight:700; color:#0f172a;">
                                <?= htmlspecialchars($del['title']) ?>
                                <small style="font-size:12px; color:#64748b; font-weight:600;">(v<?= htmlspecialchars($del['version']) ?>)</small>
                            </h4>
                        </div>
                        <div>
                            <?php if ($del['signoff_status'] === 'approved'): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                            <?php elseif ($del['signoff_status'] === 'pending_review'): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Awaiting Your Review</span>
                            <?php elseif ($del['signoff_status'] === 'revision_requested'): ?>
                                <span class="badge bg-secondary"><i class="fas fa-redo"></i> Revision Requested</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted">In Preparation</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($del['specs'])): ?>
                        <div style="font-size:13.5px; color:#475569; margin-bottom:16px; line-height:1.6;">
                            <?= nl2br(htmlspecialchars($del['specs'])) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Links & Actions -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-top:1px solid #f1f5f9; padding-top:14px;">
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <?php if (!empty($del['deliverable_file'])): ?>
                                <a href="<?= htmlspecialchars($del['deliverable_file']) ?>" target="_blank" class="btn btn-sm" style="background:#fff; border:1px solid #cbd5e1; color:#024442; font-weight:600; text-decoration:none; padding:6px 14px; border-radius:6px; font-size:12.5px;">
                                    <i class="fas fa-download"></i> Download Package
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($del['live_preview_url'])): ?>
                                <a href="<?= htmlspecialchars($del['live_preview_url']) ?>" target="_blank" class="btn btn-sm" style="background:#fff; border:1px solid #cbd5e1; color:#2563eb; font-weight:600; text-decoration:none; padding:6px 14px; border-radius:6px; font-size:12.5px;">
                                    <i class="fas fa-external-link-alt"></i> Open Live Preview
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Sign-off Controls -->
                        <?php if ($del['signoff_status'] === 'pending_review'): ?>
                        <div style="display:flex; gap:10px;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="client_deliverable_action" value="1">
                                <input type="hidden" name="deliverable_id" value="<?= $del['id'] ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this deliverable?')" style="padding:6px 16px; font-weight:600; font-size:12.5px; border-radius:6px;">
                                    <i class="fas fa-check"></i> Approve Deliverable
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openRevisionModal(<?= $del['id'] ?>)" style="padding:6px 16px; font-weight:600; font-size:12.5px; border-radius:6px;">
                                <i class="fas fa-comment-dots"></i> Request Changes
                            </button>
                        </div>
                        <?php elseif ($del['signoff_status'] === 'approved' && !empty($del['signed_off_at'])): ?>
                            <span style="font-size:12px; color:#059669; font-weight:600;">
                                <i class="fas fa-check-circle"></i> Approved on <?= date('M d, Y', strtotime($del['signed_off_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:36px; color:#94a3b8;">
                <p style="margin:0; font-size:14px;">No deliverables published for review yet. The agency team will package items here as milestones are completed.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tab 3: Required from You (Client Requests / Blockers) -->
    <?php if ($active_tab === 'requests'): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
        <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Action Items Required From You</h3>
            <p style="margin:4px 0 0; font-size:13.5px; color:#64748b;">To prevent delivery delays, please upload or submit the assets, content, or credentials requested below.</p>
        </div>

        <?php if (!empty($client_requests)): ?>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($client_requests as $req):
                    $isPending = ($req['status'] === 'requested' || $req['status'] === 'rejected');
                    $isSubmitted = ($req['status'] === 'submitted');
                    $isAccepted = ($req['status'] === 'accepted');
                ?>
                <div style="border: 1px solid <?= $isPending ? ($req['priority'] === 'blocker' ? '#fecaca' : '#fde68a') : '#e2e8f0' ?>; background: <?= $isPending ? ($req['priority'] === 'blocker' ? '#fffafa' : '#fffdfa') : '#fff' ?>; border-radius: 10px; padding: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
                        <div>
                            <?php if ($req['priority'] === 'blocker'): ?>
                                <span class="badge bg-danger text-uppercase me-2" style="font-size:10.5px;">Blocker</span>
                            <?php endif; ?>
                            <span style="font-weight:700; font-size:15px; color:#0f172a;"><?= htmlspecialchars($req['request_title']) ?></span>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Type: <span class="text-capitalize"><?= htmlspecialchars($req['request_type']) ?></span> • Due Date: <?= !empty($req['due_date']) ? date('M d, Y', strtotime($req['due_date'])) : 'Flexible' ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($isAccepted): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Accepted by Team</span>
                            <?php elseif ($isSubmitted): ?>
                                <span class="badge bg-info text-dark"><i class="fas fa-hourglass-half"></i> Submitted (Under Verification)</span>
                            <?php elseif ($req['status'] === 'rejected'): ?>
                                <span class="badge bg-danger"><i class="fas fa-times"></i> Re-upload Needed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle"></i> Action Needed</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($req['instructions'])): ?>
                        <div style="font-size:13.5px; color:#475569; margin-bottom:14px; line-height:1.6;">
                            <?= nl2br(htmlspecialchars($req['instructions'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($req['rejection_reason'])): ?>
                        <div style="background:#fee2e2; border-left:4px solid #ef4444; padding:10px 14px; font-size:13px; color:#991b1b; margin-bottom:14px; border-radius:4px;">
                            <strong>Note from Team:</strong> <?= htmlspecialchars($req['rejection_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Submission Form if pending -->
                    <?php if ($isPending): ?>
                        <form method="POST" enctype="multipart/form-data" style="margin-top:14px; padding-top:14px; border-top:1px dashed #cbd5e1; display:flex; flex-direction:column; gap:12px;">
                            <input type="hidden" name="submit_client_request" value="1">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">

                            <?php if ($req['request_type'] === 'file'): ?>
                                <div>
                                    <label style="font-size:13px; font-weight:600; color:#334155; margin-bottom:4px; display:block;">Select File to Upload:</label>
                                    <input type="file" name="request_file" class="form-control form-control-sm" required style="font-size:13px;">
                                </div>
                            <?php else: ?>
                                <div>
                                    <label style="font-size:13px; font-weight:600; color:#334155; margin-bottom:4px; display:block;">Provide Requested Information / Credentials:</label>
                                    <textarea name="submitted_text" rows="3" class="form-control form-control-sm" placeholder="Enter details here..." required style="font-size:13px;"></textarea>
                                </div>
                            <?php endif; ?>

                            <div>
                                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:6px 18px; font-weight:600; font-size:13px;">
                                    <i class="fas fa-paper-plane"></i> Submit to Agency
                                </button>
                            </div>
                        </form>
                    <?php elseif ($isSubmitted): ?>
                        <div style="font-size:12.5px; color:#0369a1; background:#f0f9ff; padding:10px 14px; border-radius:6px; border:1px solid #bae6fd;">
                            <i class="fas fa-check-circle"></i> Submitted on <?= date('M d, Y H:i', strtotime($req['submitted_at'])) ?>. The team is currently verifying this item.
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:36px; color:#94a3b8;">
                <p style="margin:0; font-size:14px;">No outstanding information or assets required from you right now. You are all set!</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tab 4: Client Queries (Ask Questions) -->
    <?php if ($active_tab === 'queries'): ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">
        <!-- Left: Query Submission Form -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-question-circle text-muted"></i> Submit a Question or Inquiry</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="submit_client_query" value="1">

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Subject</label>
                    <input type="text" name="query_subject" class="form-control form-control-sm" placeholder="e.g. Question about Phase 2 timeline" required style="font-size:13.5px;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Category</label>
                        <select name="query_category" class="form-select form-select-sm" style="font-size:13px;">
                            <option value="general">General</option>
                            <option value="design">Design</option>
                            <option value="technical">Technical</option>
                            <option value="scope">Scope</option>
                            <option value="timeline">Timeline</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Priority</label>
                        <select name="query_priority" class="form-select form-select-sm" style="font-size:13px;">
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Your Question / Details</label>
                    <textarea name="query_message" rows="4" class="form-control form-control-sm" placeholder="Describe your question or clarification in detail..." required style="font-size:13.5px;"></textarea>
                </div>

                <div class="mb-3">
                    <label style="font-size:13px; font-weight:600; color:#334155; display:block; margin-bottom:4px;">Attachment (Optional)</label>
                    <input type="file" name="query_file" class="form-control form-control-sm" style="font-size:12.5px;">
                </div>

                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:8px 20px; font-weight:600; width:100%;">
                    Submit Question to Team
                </button>
            </form>
        </div>

        <!-- Right: Recent Queries List -->
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
            <h3 style="margin:0 0 16px; font-size:17px; font-weight:700; color:#0f172a;"><i class="fas fa-list text-muted"></i> Your Inquiries</h3>
            <?php if (!empty($queries)): ?>
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <?php foreach ($queries as $q):
                        $statusClass = ($q['status'] === 'resolved' || $q['status'] === 'closed') ? 'bg-success' : 'bg-warning text-dark';
                    ?>
                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #fafbfc;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                            <strong style="font-size:14px; color:#1e293b;"><?= htmlspecialchars($q['subject']) ?></strong>
                            <span class="badge <?= $statusClass ?> text-capitalize"><?= htmlspecialchars($q['status']) ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b; margin-bottom:8px;">
                            Category: <span class="text-capitalize"><?= htmlspecialchars($q['category']) ?></span> • <?= date('M d, Y H:i', strtotime($q['created_at'])) ?>
                        </div>

                        <!-- Thread Replies -->
                        <?php
                        $q_id = intval($q['id']);
                        $rep_res = $conn->query("
                            SELECT qr.*, u.name as responder_name, u.role as responder_role
                            FROM project_query_replies qr
                            JOIN users u ON qr.user_id = u.user_id
                            WHERE qr.query_id = {$q_id} AND qr.is_internal = 0
                            ORDER BY qr.created_at ASC
                        ");
                        if ($rep_res && $rep_res->num_rows > 0):
                        ?>
                        <div style="border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 10px; display:flex; flex-direction:column; gap:8px;">
                            <?php while ($r = $rep_res->fetch_assoc()): ?>
                                <div style="background:#fff; border:1px solid #f1f5f9; padding:8px 12px; border-radius:6px; font-size:13px;">
                                    <div style="display:flex; justify-content:space-between; color:#64748b; font-size:11.5px; margin-bottom:4px;">
                                        <strong><?= htmlspecialchars($r['responder_name']) ?> (<?= htmlspecialchars($r['responder_role']) ?>)</strong>
                                        <span><?= date('M d, H:i', strtotime($r['created_at'])) ?></span>
                                    </div>
                                    <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
                                    <?php if (!empty($r['attachment_path'])): ?>
                                        <div style="margin-top:6px;">
                                            <a href="<?= htmlspecialchars($r['attachment_path']) ?>" target="_blank" style="font-size:11.5px; color:#024442; text-decoration:none;">
                                                <i class="fas fa-paperclip"></i> View Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:24px; color:#94a3b8; font-size:13.5px;">No inquiries submitted yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 5: Project Updates Broadcast Feed -->
    <?php if ($active_tab === 'updates'): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
        <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Progress Updates from Team</h3>
            <p style="margin:4px 0 0; font-size:13.5px; color:#64748b;">Official progress snapshots and milestone digests published by the agency project lead.</p>
        </div>

        <?php if (!empty($broadcasts)): ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($broadcasts as $bc): ?>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:20px; background:#fafbfc;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                        <div>
                            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($bc['title']) ?></h4>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Published by <?= htmlspecialchars($bc['sender_name']) ?> • <?= date('F d, Y \a\t H:i', strtotime($bc['created_at'])) ?>
                            </div>
                        </div>
                        <span class="badge bg-light text-muted border">Official Update</span>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; font-size:13.5px;">
                        <?php if (!empty($bc['completed_text'])): ?>
                        <div style="background:#fff; border:1px solid #ecfdf5; border-left:3px solid #10b981; padding:12px; border-radius:6px;">
                            <strong style="color:#065f46; display:block; margin-bottom:4px;">Completed This Week:</strong>
                            <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($bc['completed_text'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($bc['in_progress_text'])): ?>
                        <div style="background:#fff; border:1px solid #eff6ff; border-left:3px solid #3b82f6; padding:12px; border-radius:6px;">
                            <strong style="color:#1e40af; display:block; margin-bottom:4px;">In Progress:</strong>
                            <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($bc['in_progress_text'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($bc['next_up_text'])): ?>
                        <div style="background:#fff; border:1px solid #f3e8ff; border-left:3px solid #8b5cf6; padding:12px; border-radius:6px;">
                            <strong style="color:#6b21a8; display:block; margin-bottom:4px;">Upcoming Next:</strong>
                            <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($bc['next_up_text'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($bc['blockers_text'])): ?>
                        <div style="background:#fff; border:1px solid #fef3c7; border-left:3px solid #f59e0b; padding:12px; border-radius:6px;">
                            <strong style="color:#92400e; display:block; margin-bottom:4px;">Items Required from Client:</strong>
                            <div style="color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($bc['blockers_text'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:36px; color:#94a3b8; font-size:14px;">No broadcast updates published yet.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tab 6: Discussion -->
    <?php if ($active_tab === 'discussion'): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
        <h3 style="margin:0 0 16px; font-size:18px; font-weight:700; color:#0f172a;"><i class="fas fa-comments text-muted"></i> Direct Discussion with Team</h3>

        <!-- Post Comment Form -->
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <input type="hidden" name="add_client_comment" value="1">
            <div class="mb-3">
                <textarea name="comment_text" rows="3" class="form-control form-control-sm" placeholder="Write feedback, updates, or comments here..." required style="font-size:13.5px;"></textarea>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fas fa-paperclip"></i> Attach File: </label>
                    <input type="file" name="comment_file" style="font-size: 12.5px;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 18px; background:#024442; border:none; font-weight:600;">Post Comment</button>
            </div>
        </form>

        <!-- Comments List -->
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
        $comms = $stmt_comments->get_result();
        $stmt_comments->close();
        ?>
        <div style="display:flex; flex-direction:column; gap:16px;">
            <?php if ($comms && $comms->num_rows > 0): ?>
                <?php while ($comm = $comms->fetch_assoc()):
                    $isAgency = in_array(strtolower($comm['role']), ['super_admin', 'superadmin', 'admin', 'pm', 'teacher', 'user']);
                    $initial = strtoupper(substr($comm['name'], 0, 1));
                ?>
                <div style="display:flex; gap:14px; align-items:flex-start;">
                    <div style="width:36px; height:36px; border-radius:50%; background:<?= $isAgency ? '#024442' : '#0284c7' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0;">
                        <?= $initial ?>
                    </div>
                    <div style="flex:1; background:<?= $isAgency ? '#f0f5f4' : '#f8fafc' ?>; border-left:3px solid <?= $isAgency ? '#024442' : '#0284c7' ?>; border-radius:8px; padding:12px 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong style="font-size:13.5px; color:#1e293b;"><?= htmlspecialchars($comm['name']) ?> <small style="color:#64748b; font-weight:normal;">(<?= ucfirst($comm['role']) ?>)</small></strong>
                            <span style="font-size:11px; color:#94a3b8;"><?= date('M d, Y H:i', strtotime($comm['created_at'])) ?></span>
                        </div>
                        <div style="font-size:13.5px; color:#334155; line-height:1.5;"><?= nl2br(htmlspecialchars($comm['comment_text'])) ?></div>
                        <?php if (!empty($comm['file_path'])): ?>
                            <div style="margin-top:8px;">
                                <a href="<?= htmlspecialchars($comm['file_path']) ?>" target="_blank" style="font-size:12px; color:#024442; font-weight:600; text-decoration:none;">
                                    <i class="fas fa-paperclip"></i> <?= htmlspecialchars(basename($comm['file_path'])) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding:24px; color:#94a3b8; font-size:13.5px;">No comments posted yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab 7: Shared Files -->
    <?php if ($active_tab === 'files'): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 12px; background:#fff; padding:20px;">
        <h3 style="margin:0 0 16px; font-size:18px; font-weight:700; color:#0f172a;"><i class="fas fa-folder-open text-muted"></i> Project Documentation & Shared Assets</h3>

        <?php if ($files && $files->num_rows > 0): ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php while ($f = $files->fetch_assoc()):
                    $fname = basename($f['file_path']);
                ?>
                <div style="display:flex; justify-content:space-between; align-items:center; background:#fafbfc; border:1px solid #e2e8f0; padding:12px 16px; border-radius:8px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fas fa-file-alt" style="color:#64748b; font-size:20px;"></i>
                        <div>
                            <div style="font-weight:600; font-size:14px; color:#1e293b;"><?= htmlspecialchars($f['title'] ?: $fname) ?></div>
                            <div style="font-size:11.5px; color:#94a3b8;">Uploaded on <?= date('M d, Y', strtotime($f['uploaded_at'])) ?></div>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" class="btn btn-sm" style="background:#fff; border:1px solid #cbd5e1; color:#024442; font-weight:600; text-decoration:none; padding:4px 12px; border-radius:6px; font-size:12px;">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:36px; color:#94a3b8; font-size:14px;">No files shared yet.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Request Changes / Revisions on Deliverable -->
<div id="revisionModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; overflow-y:auto; padding:20px;">
    <div style="background:#fff; border-radius:12px; padding:24px; max-width:500px; width:90%; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Request Deliverable Revision</h4>
            <span style="font-size:22px; cursor:pointer; color:#64748b;" onclick="closeRevisionModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="client_deliverable_action" value="1">
            <input type="hidden" name="decision" value="request_revision">
            <input type="hidden" name="deliverable_id" id="modalDeliverableId" value="">

            <div class="mb-3">
                <label style="font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; display:block;">Please specify the requested adjustments or feedback:</label>
                <textarea name="revision_notes" rows="4" class="form-control" placeholder="Describe the changes required..." required style="font-size:13.5px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="closeRevisionModal()">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary" style="background:#024442; border:none; font-weight:600;">Submit Revision Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRevisionModal(id) {
    document.getElementById('modalDeliverableId').value = id;
    document.getElementById('revisionModal').style.display = 'flex';
}
function closeRevisionModal() {
    document.getElementById('revisionModal').style.display = 'none';
}

// ── Horizontal Tabs Scrolling & Active Center ───────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const tabsWrapper = document.querySelector('.pm-nav-tabs-wrapper');
    if (tabsWrapper) {
        // 1. Mouse wheel horizontal scrolling
        tabsWrapper.addEventListener('wheel', function(e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                this.scrollLeft += e.deltaY;
            }
        }, { passive: false });

        // 2. Mouse drag-to-scroll (pan)
        let isDown = false;
        let startX = 0;
        let scrollStart = 0;
        let hasDragged = false;

        tabsWrapper.addEventListener('mousedown', function(e) {
            isDown = true;
            hasDragged = false;
            tabsWrapper.classList.add('is-dragging');
            startX = e.pageX - tabsWrapper.offsetLeft;
            scrollStart = tabsWrapper.scrollLeft;
        });

        window.addEventListener('mouseup', function() {
            if (isDown) {
                isDown = false;
                tabsWrapper.classList.remove('is-dragging');
            }
        });

        tabsWrapper.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            const x = e.pageX - tabsWrapper.offsetLeft;
            const walk = (x - startX) * 1.5;
            if (Math.abs(walk) > 4) {
                hasDragged = true;
                e.preventDefault();
                tabsWrapper.scrollLeft = scrollStart - walk;
            }
        });

        tabsWrapper.querySelectorAll('a.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (hasDragged) {
                    e.preventDefault();
                    hasDragged = false;
                }
            });
        });

        // 3. Center active tab into view on load
        const activeLink = tabsWrapper.querySelector('.nav-link.active');
        if (activeLink) {
            setTimeout(function() {
                activeLink.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }, 100);
        }
    }
});
</script>

<?php include 'dashboard_footer.php'; ?>
