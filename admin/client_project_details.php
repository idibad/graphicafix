<?php
include 'dashboard_header.php';

// Verify role access
if ($role !== 'client' && $role !== 'client_sub') {
    header("Location: index.php");
    exit;
}

$project_id = intval($_GET['id'] ?? 0);
$client_id = intval($data['client_id'] ?? 0);

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

if (!$project) {
    echo "<script>alert('Project not found'); window.location='client_projects.php';</script>";
    exit;
}

// Role Badge Helper
function getRoleBadge($role) {
    $r = strtolower($role);
    if ($r === 'admin')      return ['bg' => '#fff4e5', 'text' => '#e17055'];
    if ($r === 'pm')         return ['bg' => '#fce7f3', 'text' => '#db2777'];
    if ($r === 'teacher')    return ['bg' => '#e0f2fe', 'text' => '#0284c7'];
    if ($r === 'student')    return ['bg' => '#d7f8b8', 'text' => '#2b7a2b'];
    if ($r === 'user')       return ['bg' => '#f3e8ff', 'text' => '#7c3aed'];
    if ($r === 'sales')      return ['bg' => '#d1fae5', 'text' => '#065f46'];
    if ($r === 'client')     return ['bg' => '#e0f2fe', 'text' => '#0284c7'];
    if ($r === 'client_sub') return ['bg' => '#f1f5f9', 'text' => '#475569'];
    return ['bg' => '#f3f4f6', 'text' => '#4b5563'];
}

// ── Form Actions ─────────────────────────────────────────────────────────────
// 1. Submit Feedback / File upload by client
if (isset($_POST['upload_file_client'])) {
    if (!empty($_FILES['client_doc']['name'])) {
        $upload_dir = "uploads/projects/" . $project_id . "/client/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = basename($_FILES['client_doc']['name']);
        $file_path = $upload_dir . time() . "_" . $file_name;
        
        if (move_uploaded_file($_FILES['client_doc']['tmp_name'], $file_path)) {
            $title = trim($_POST['file_title'] ?? '') ?: "Client Asset: " . $file_name;
            $file_type = 'document';
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                $file_type = 'image';
            }
            
            $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_type, title, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt_file->bind_param("isssi", $project_id, $file_path, $file_type, $title, $user_id);
            if ($stmt_file->execute()) {
                // Notify project team
                require_once __DIR__ . '/../core/functions.php';
                $t_stmt = $conn->prepare("SELECT u.email FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = ?");
                $t_stmt->bind_param("i", $project_id);
                $t_stmt->execute();
                $team = $t_stmt->get_result();
                
                require_once __DIR__ . '/../core/task_email_helper.php';
                $subject = "Client Uploaded File: " . $project['project_name'];
                $heading = "Client File Submission";
                $body = "<p>Client contact <strong>" . htmlspecialchars($name) . "</strong> has uploaded a new asset/file for project <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
                         <div style='background: #f8fafc; border-radius: 8px; padding: 16px; border:1px solid #cbd5e1; margin: 16px 0;'>
                            <strong>File Name:</strong> " . htmlspecialchars($file_name) . "<br>
                            <strong>Title:</strong> " . htmlspecialchars($title) . "
                         </div>";
                
                while ($member = $team->fetch_assoc()) {
                    if (!empty($member['email'])) {
                        sendTaskEmail($member['email'], $subject, $heading, $body, 0);
                    }
                }
                echo "<script>alert('File uploaded successfully!'); window.location='client_project_details.php?id={$project_id}';</script>";
                exit;
            }
        }
    }
}

// 2. Add Project Comment / Discussion Post
if (isset($_POST['add_client_comment'])) {
    $comment_text = trim($_POST['comment_text'] ?? '');
    if (!empty($comment_text)) {
        $file_path = null;
        if (!empty($_FILES['comment_file']['name'])) {
            $upload_dir = "uploads/projects/" . $project_id . "/comments/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_path = $upload_dir . time() . "_" . basename($_FILES['comment_file']['name']);
            move_uploaded_file($_FILES['comment_file']['tmp_name'], $file_path);
            
            // Also log in project_files
            $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_path, file_type, title, uploaded_by) VALUES (?, ?, 'document', ?, ?)");
            $title = "Comment Attachment - " . basename($_FILES['comment_file']['name']);
            $stmt_file->bind_param("issi", $project_id, $file_path, $title, $user_id);
            $stmt_file->execute();
        }
        
        $stmt_comm = $conn->prepare("INSERT INTO project_comments (project_id, user_id, comment_text, file_path) VALUES (?, ?, ?, ?)");
        $stmt_comm->bind_param("iiss", $project_id, $user_id, $comment_text, $file_path);
        if ($stmt_comm->execute()) {
            // Notify project team and admin
            $t_stmt = $conn->prepare("SELECT u.email FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = ?");
            $t_stmt->bind_param("i", $project_id);
            $t_stmt->execute();
            $team = $t_stmt->get_result();
            
            require_once __DIR__ . '/../core/task_email_helper.php';
            $subject = "New Client Comment on Project: " . $project['project_name'];
            $heading = "New Client Feedback";
            $body = "<p>Client user <strong>" . htmlspecialchars($name) . "</strong> left a comment on project <strong>" . htmlspecialchars($project['project_name']) . "</strong>:</p>
                     <blockquote style='background: #f8fafc; border-left: 4px solid #024442; padding: 12px; margin: 16px 0;'>" . nl2br(htmlspecialchars($comment_text)) . "</blockquote>";
            
            $sent_emails = [];
            while ($member = $team->fetch_assoc()) {
                if (!empty($member['email']) && !in_array($member['email'], $sent_emails)) {
                    sendTaskEmail($member['email'], $subject, $heading, $body, 0);
                    $sent_emails[] = $member['email'];
                }
            }
            // Ensure Admin is notified as well
            $admin_res = $conn->query("SELECT email FROM users WHERE role = 'admin'");
            if ($admin_res) {
                while ($admin_row = $admin_res->fetch_assoc()) {
                    if (!empty($admin_row['email']) && !in_array($admin_row['email'], $sent_emails)) {
                        sendTaskEmail($admin_row['email'], $subject, $heading, $body, 0);
                        $sent_emails[] = $admin_row['email'];
                    }
                }
            }
            echo "<script>alert('Comment posted!'); window.location='client_project_details.php?id={$project_id}';</script>";
            exit;
        }
    }
}

// Fetch team members
$team_query = "SELECT u.name, u.role FROM project_team pt JOIN users u ON pt.user_id = u.user_id WHERE pt.project_id = ?";
$stmt_team = $conn->prepare($team_query);
$stmt_team->bind_param("i", $project_id);
$stmt_team->execute();
$team_members = $stmt_team->get_result();

// Fetch shared project files
$files_query = "SELECT pf.*, u.name as uploader_name, u.role as uploader_role FROM project_files pf LEFT JOIN users u ON pf.uploaded_by = u.user_id WHERE pf.project_id = ? AND pf.file_type != 'thumbnail' ORDER BY pf.uploaded_at DESC";
$stmt_files = $conn->prepare($files_query);
$stmt_files->bind_param("i", $project_id);
$stmt_files->execute();
$files = $stmt_files->get_result();
?>

<div class="height-100">
    <!-- Project Header -->
    <div class="project-details-header" style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px;">
        <div class="header-details-top" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div class="header-content">
                <h1 style="margin: 0; color:#1e293b;"><?= htmlspecialchars($project['project_name']) ?></h1>
                <div class="client-name" style="margin-top: 6px; color:#64748b; font-size: 14.5px;">
                    <span><i class="fas fa-building"></i></span> Company: <?= htmlspecialchars($project['client_name']) ?>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location='client_projects.php'" style="background: #f1f5f9; border: 1px solid #cbd5e1; color:#334155; padding: 8px 16px; border-radius: 8px;">
                    <span>←</span> Back to Projects
                </button>
            </div>
        </div>

        <div class="meta-grid" style="display:flex; gap:24px; flex-wrap:wrap; margin-top:20px; border-top:1px solid #f1f5f9; padding-top:16px; font-size:13.5px; color:#64748b;">
            <div>
                <strong>Status:</strong> 
                <span class="badge" style="background: <?= $project['status'] === 'Completed' ? '#ecfdf5;color:#10b981;' : ($project['status'] === 'In Progress' ? '#e0f2fe;color:#0284c7;' : '#fef3c7;color:#d97706;') ?>"><?= htmlspecialchars($project['status']) ?></span>
            </div>
            <div>
                <strong>Priority:</strong> 
                <span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($project['priority']) ?></span>
            </div>
            <div>
                <strong>Start Date:</strong> <?= !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'TBD' ?>
            </div>
            <div>
                <strong>End Date:</strong> <?= !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'TBD' ?>
            </div>
        </div>

        <div class="progress-section" style="margin-top:20px;">
            <div class="progress-header" style="display:flex; justify-content:space-between; font-weight:600; color:#475569; margin-bottom:6px; font-size:13px;">
                <span class="progress-label">Completion Status</span>
                <span class="progress-percent"><?= intval($project['progress']) ?>%</span>
            </div>
            <div class="progress-bar" style="background:#e2e8f0; height:12px; border-radius:6px; overflow:hidden;">
                <div class="progress-fill" style="background:#024442; width: <?= intval($project['progress']) ?>%; height: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Project Description -->
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-file-alt"></i> Project Overview</h2>
                </div>
                <div class="description-text" style="padding:20px; font-size:14.5px; color:#334155; line-height:1.7;">
                    <?= !empty($project['public_description']) ? strip_tags($project['public_description']) : (!empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : 'No project description is currently provided.') ?>
                </div>
            </div>

            <?php
            // Deliverables Requiring Client Approval (Only show if there are active approval requests)
            $stmt_approvals = $conn->prepare("SELECT * FROM tasks WHERE project_id = ? AND stage = 'Client Approval' AND (client_approval_status != 'Approved' OR client_approval_status IS NULL) ORDER BY due_date ASC");
            $stmt_approvals->bind_param("i", $project_id);
            $stmt_approvals->execute();
            $proj_approvals = $stmt_approvals->get_result();
            if ($proj_approvals->num_rows > 0):
            ?>
            <div class="card" style="margin-bottom:24px; border: 1px solid #bae6fd; background:#f8fafc;">
                <div class="card-header" style="background:#f0f9ff; border-bottom:1px solid #e0f2fe; padding:16px 20px;">
                    <h2 class="card-title" style="color:#0369a1; font-size:16px; margin:0;"><i class="fas fa-clipboard-check"></i> Deliverables Awaiting Your Approval</h2>
                </div>
                <div style="padding:16px 20px;">
                    <p style="font-size:13.5px; color:#475569; margin-bottom:16px;">The following deliverable item(s) have been submitted for your review and approval:</p>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php while ($tk = $proj_approvals->fetch_assoc()): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                                <div>
                                    <div style="font-weight:700; color:#1e293b; font-size:14px;"><?= htmlspecialchars($tk['title']) ?></div>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px;">Revision <?= intval($tk['current_revision']) ?> • Submitted for client review</div>
                                </div>
                                <a href="client_approval.php?id=<?= $tk['id'] ?>" class="btn btn-sm btn-primary" style="background:#024442; border:none; padding:6px 16px; font-weight:700; font-size:12.5px; border-radius:6px; color:#fff; text-decoration:none;">
                                    Review & Decide →
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Client Information Submission / File Sharing -->
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-cloud-upload-alt"></i> Shared Resources & Uploads</h2>
                </div>
                <div style="padding:16px;">
                    <!-- File Upload Form -->
                    <form method="POST" enctype="multipart/form-data" style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:20px; margin-bottom:20px;">
                        <input type="hidden" name="upload_file_client" value="1">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:12px;">
                            <div class="form-group" style="margin:0;">
                                <label style="font-size:13px; font-weight:700; color:#334155;">Title/Label:</label>
                                <input type="text" name="file_title" placeholder="e.g. Requirement Doc, Logo Assets" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:6px 10px; font-size:13.5px;" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="font-size:13px; font-weight:700; color:#334155;">Select File:</label>
                                <input type="file" name="client_doc" style="width:100%; font-size:13.5px;" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="background:#024442; border:none; padding:8px 20px; width:100%; font-weight:700;">Submit Deliverables / Assets</button>
                    </form>

                    <!-- Shared Files list -->
                    <?php if ($files->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php while ($file = $files->fetch_assoc()): 
                                $file_ext = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                                $file_name = basename($file['file_path']);
                            ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #f1f5f9; padding:12px; border-radius:8px;">
                                    <div style="display:flex; gap:12px; align-items:center;">
                                        <div style="width:36px; height:36px; background:#f1f5f9; color:#475569; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:18px;"><i class="fas fa-file"></i></div>
                                        <div>
                                            <div style="font-weight:700; font-size:13.5px; color:#1e293b;"><?= htmlspecialchars($file['title'] ?: $file_name) ?></div>
                                            <div style="font-size:11.5px; color:#94a3b8;">
                                                Uploaded by: <?= htmlspecialchars($file['uploader_name'] ?? 'Team') ?> (<?= htmlspecialchars($file['uploader_role'] ?? 'staff') ?>) • <?= date('M d, Y', strtotime($file['uploaded_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" style="padding:6px 12px; background:#f1f5f9; color:#024442; text-decoration:none; font-weight:700; font-size:12px; border-radius:6px; border:1px solid #e2e8f0;">
                                        ⬇️ Download
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color:#94a3b8; padding:20px; font-style:italic;">No files uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Project Comments / Discussion -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-comments"></i> Admin & Client Discussion</h2>
                </div>
                <div style="padding:16px;">
                    <!-- Add Comment Form -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <input type="hidden" name="add_client_comment" value="1">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-weight: 600; font-size: 13.5px; display: block; margin-bottom: 6px;">Write a message, feedback, or update to the Admin:</label>
                            <textarea name="comment_text" rows="3" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-family: inherit; font-size: 14px;" placeholder="Type your message to Admin here..." required></textarea>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div class="form-group" style="margin: 0; display:flex; align-items:center; gap:8px;">
                                <label style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fas fa-paperclip"></i> Attach File: </label>
                                <input type="file" name="comment_file" style="font-size: 12.5px;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 8px 20px; background:#024442; border:none;">Post Message</button>
                        </div>
                    </form>

                    <!-- Comments list (Only Admin and Client comments) -->
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php
                        $stmt_comments = $conn->prepare("
                            SELECT pc.*, u.name, u.role 
                            FROM project_comments pc 
                            JOIN users u ON pc.user_id = u.user_id 
                            WHERE pc.project_id = ? 
                              AND (LOWER(u.role) = 'admin' OR u.user_id = ? OR (u.client_id = ? AND u.client_id > 0))
                            ORDER BY pc.created_at DESC
                        ");
                        $stmt_comments->bind_param("iii", $project_id, $user_id, $client_id);
                        $stmt_comments->execute();
                        $proj_comms = $stmt_comments->get_result();
                        if ($proj_comms->num_rows > 0):
                            while ($comm = $proj_comms->fetch_assoc()):
                                $initial = strtoupper(substr($comm['name'], 0, 1));
                                $roleBadge = getRoleBadge($comm['role']);
                        ?>
                                <?php
                                $isAgency = (strtolower($comm['role']) === 'admin');
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
                                                <span style="background: <?= $roleBadge['bg'] ?>; color: <?= $roleBadge['text'] ?>; font-size: 9px; font-weight: 800; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;"><?= $isAgency ? 'Admin' : $comm['role'] ?></span>
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
                            <div style="text-align: center; color: #94a3b8; padding: 20px; font-style: italic;">No comments posted yet between you and Admin. Write a message above to contact the Admin!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Project Team -->
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-user-friends"></i> Assigned Team</h2>
                </div>
                <div style="padding:16px;">
                    <?php if ($team_members->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php while ($member = $team_members->fetch_assoc()): 
                                $initial = strtoupper(substr($member['name'], 0, 1));
                            ?>
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <div style="width:32px; height:32px; background:#e0f2fe; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#0369a1; font-weight:bold; font-size:13px;"><?= $initial ?></div>
                                    <div>
                                        <div style="font-weight:700; font-size:13.5px; color:#334155;"><?= htmlspecialchars($member['name']) ?></div>
                                        <div style="font-size:11px; color:#64748b; text-transform:capitalize;"><?= htmlspecialchars($member['role']) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="color:#94a3b8; font-style:italic; font-size:13px;">No direct team members assigned to this project yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Project Dates & Timeline -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Timeline</h2>
                </div>
                <div style="padding:16px;">
                    <div style="display:flex; flex-direction:column; gap:12px; font-size:13px; color:#475569;">
                        <div>
                            <strong>Start Date:</strong> <br><?= !empty($project['start_date']) ? date('F d, Y', strtotime($project['start_date'])) : 'Not Scheduled' ?>
                        </div>
                        <div>
                            <strong>Target End Date:</strong> <br><?= !empty($project['end_date']) ? date('F d, Y', strtotime($project['end_date'])) : 'Not Scheduled' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
