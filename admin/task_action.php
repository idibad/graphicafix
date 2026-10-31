<?php
// task_action.php — pure AJAX endpoint, zero includes, zero HTML possible
// Place this file in the same /admin/ folder as tasks.php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/task_delay_helper.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$user_id   = intval($_SESSION['user_id'] ?? 0);
$user_role = strtolower($_SESSION['role'] ?? '');
$role      = $user_role;
$is_admin  = in_array($user_role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm']);
$action    = $_POST['ajax_action'] ?? $_POST['action'] ?? '';
$task_id   = intval($_POST['task_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Bad request']); exit;
}

// ── Verify ownership if task_id provided ──────────────────────────────────────
$t = null;
if ($task_id > 0) {
    $check = $conn->prepare("SELECT id, created_by, assigned_to, stage, status FROM tasks WHERE id=?");
    $check->bind_param("i", $task_id);
    $check->execute();
    $t = $check->get_result()->fetch_assoc();

    if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to modify this task']); exit;
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($action === 'create_task') {
    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $priority        = $_POST['priority'] ?? 'Medium';
    $status          = $_POST['status'] ?? 'Pending';
    $stage           = trim($_POST['stage'] ?? 'Brief');
    $due_date        = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+3 days'));
    $assigned_to     = intval($_POST['assigned_to'] ?? 0) ?: $user_id;
    $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
    $milestone_id    = intval($_POST['milestone_id'] ?? 0) ?: null;
    $task_type       = trim($_POST['task_type'] ?? 'Design');
    $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
    $logged_hours    = floatval($_POST['logged_hours'] ?? 0);

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Task title is required']); exit;
    }

    $allowed_status = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed_status)) $status = 'Pending';

    // Strict rule: only Super Admins, Admins, or PMs can create tasks as Completed
    if ($status === 'Completed' && function_exists('canUserCompleteTask') && !canUserCompleteTask($user_role)) {
        $status = 'In Review';
    }

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, status, stage, due_date, assigned_to, project_id, milestone_id, task_type, estimated_hours, logged_hours, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssssssiiisddi", $title, $description, $priority, $status, $stage, $due_date, $assigned_to, $project_id, $milestone_id, $task_type, $estimated_hours, $logged_hours, $user_id);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        if ($assigned_to && $assigned_to != $user_id) {
            if (file_exists(__DIR__ . '/../core/notification_mailer.php')) {
                require_once __DIR__ . '/../core/notification_mailer.php';
                if (function_exists('notifyTaskAssigned')) notifyTaskAssigned($conn, $new_id);
            }
        }
        echo json_encode(['success' => true, 'task_id' => $new_id, 'message' => 'Task created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;

} elseif ($action === 'edit_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $title           = trim($_POST['title'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $priority        = $_POST['priority'] ?? 'Medium';
    $status          = $_POST['status'] ?? 'Pending';
    $due_date        = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d');
    $assigned_to     = intval($_POST['assigned_to'] ?? 0) ?: $user_id;
    $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
    $milestone_id    = intval($_POST['milestone_id'] ?? 0) ?: null;
    $task_type       = trim($_POST['task_type'] ?? 'Design');
    $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
    $logged_hours    = floatval($_POST['logged_hours'] ?? 0);

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Task title is required']); exit;
    }

    $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed)) $status = 'Pending';

    // Strict rule: only Super Admins, Admins, or PMs can mark task as Completed
    if ($status === 'Completed' && function_exists('canUserCompleteTask') && !canUserCompleteTask($user_role)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Restricted: Only Super Admins, Admins, or Project Managers can mark tasks as Completed.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, priority=?, status=?, due_date=?, assigned_to=?, project_id=?, milestone_id=?, task_type=?, estimated_hours=?, logged_hours=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssiiisddi", $title, $description, $priority, $status, $due_date, $assigned_to, $project_id, $milestone_id, $task_type, $estimated_hours, $logged_hours, $task_id);

    if ($stmt->execute()) {
        if (function_exists('applyTaskDelayPenalty')) {
            applyTaskDelayPenalty($conn, $task_id);
        }
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    exit;

} elseif ($action === 'get_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task_data = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'task' => $task_data]);
    exit;

} elseif ($action === 'update_status') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }

    $allowed    = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
    $new_status = $_POST['status'] ?? '';
    if (!in_array($new_status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
    }

    // STRICT PERMISSION CHECK: Regular staff cannot directly complete their own tasks
    if ($new_status === 'Completed' && function_exists('canUserCompleteTask') && !canUserCompleteTask($user_role)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Restricted: Only Super Admins, Admins, or Project Managers can approve and mark a task as Completed. Please submit your task for "In Review" for manager verification.'
        ]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("si", $new_status, $task_id);
    $stmt->execute();

    // Timestamp milestones & recalculate delay status
    if (function_exists('recordTaskStatusTimestamp')) {
        recordTaskStatusTimestamp($conn, $task_id, $new_status);
    }

    if (in_array($new_status, ['In Review', 'Completed'])) {
        if (file_exists(__DIR__ . '/../core/notification_mailer.php')) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyTaskStatusChanged')) {
                notifyTaskStatusChanged($conn, $task_id, $new_status, $user_id);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Status updated']);
    exit;

} elseif ($action === 'advance_stage') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $target_stage = trim($_POST['target_stage'] ?? '');
    $comment      = trim($_POST['comment'] ?? '');

    $res = advanceTaskStage($conn, $task_id, $target_stage, $user_id, $user_role, $comment);
    echo json_encode($res);
    exit;

} elseif ($action === 'add_subtask') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $title       = trim($_POST['title'] ?? '');
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;
    $due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Subtask title is required']); exit;
    }

    $sub_id = createSubtask($conn, $task_id, $title, $assigned_to, $due_date);
    echo json_encode(['success' => (bool)$sub_id, 'subtask_id' => $sub_id, 'message' => $sub_id ? 'Subtask added' : 'Failed to add subtask']);
    exit;

} elseif ($action === 'toggle_subtask') {
    $subtask_id = intval($_POST['subtask_id'] ?? 0);
    $status     = trim($_POST['status'] ?? 'Pending');
    $success    = toggleSubtaskStatus($conn, $subtask_id, $status);
    echo json_encode(['success' => $success, 'message' => $success ? 'Subtask updated' : 'Failed to update subtask']);
    exit;

} elseif ($action === 'add_task_comment') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $comment_text = trim($_POST['comment_text'] ?? '');
    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'message' => 'Comment text cannot be empty']); exit;
    }
    $comment_id = addTaskComment($conn, $task_id, $user_id, $comment_text);
    echo json_encode(['success' => (bool)$comment_id, 'comment_id' => $comment_id, 'message' => $comment_id ? 'Comment posted' : 'Failed to post comment']);
    exit;

} elseif ($action === 'upload_task_file') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid file to upload']); exit;
    }
    $upload_dir = __DIR__ . '/uploads/tasks/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }
    $orig_name = basename($_FILES['file']['name']);
    $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig_name);
    $target_path = $upload_dir . $safe_name;
    $db_path = 'uploads/tasks/' . $safe_name;
    $file_size = intval($_FILES['file']['size'] ?? 0);
    $is_deliverable = intval($_POST['is_client_deliverable'] ?? 0);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        $stmt_f = $conn->prepare("INSERT INTO task_files (task_id, file_path, file_name, file_size, uploaded_by, is_client_deliverable) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_f) {
            $stmt_f->bind_param("issiii", $task_id, $db_path, $orig_name, $file_size, $user_id, $is_deliverable);
            $stmt_f->execute();
            $stmt_f->close();
        }
        echo json_encode(['success' => true, 'message' => 'File uploaded successfully', 'file_name' => $orig_name]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    }
    exit;

} elseif ($action === 'update_brief') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $brief_objective    = trim($_POST['brief_objective'] ?? '');
    $brief_deliverables = trim($_POST['brief_deliverables'] ?? '');
    $brief_references   = trim($_POST['brief_references'] ?? '');
    $brief_specs        = trim($_POST['brief_specs'] ?? '');
    $brief_copy         = trim($_POST['brief_copy'] ?? '');
    $prod_progress      = max(0, min(100, intval($_POST['production_progress'] ?? 0)));

    $stmt_b = $conn->prepare("UPDATE tasks SET brief_objective = ?, brief_deliverables = ?, brief_references = ?, brief_specs = ?, brief_copy = ?, production_progress = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt_b) {
        $stmt_b->bind_param("sssssii", $brief_objective, $brief_deliverables, $brief_references, $brief_specs, $brief_copy, $prod_progress, $task_id);
        $stmt_b->execute();
        $stmt_b->close();
        echo json_encode(['success' => true, 'message' => 'Creative brief and specifications updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error updating brief']);
    }
    exit;

} elseif ($action === 'request_task_extension') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $requested_date  = trim($_POST['requested_date'] ?? '');
    $reason_category = trim($_POST['reason_category'] ?? 'General');
    $reason          = trim($_POST['reason'] ?? '');

    if (empty($requested_date)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid requested deadline date.']); exit;
    }

    $res = submitTaskExtensionRequest($conn, $task_id, $user_id, $requested_date, $reason_category, $reason);
    echo json_encode($res);
    exit;

} elseif ($action === 'review_task_extension') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Only administrators or managers can review extension requests']); exit;
    }
    $decision    = trim($_POST['decision'] ?? '');
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    $res = reviewTaskExtensionRequest($conn, $task_id, $user_id, $decision, $admin_notes);
    if ($res['success'] && file_exists(__DIR__ . '/../core/notification_mailer.php')) {
        require_once __DIR__ . '/../core/notification_mailer.php';
        if (function_exists('notifyTaskExtensionDecision')) {
            @notifyTaskExtensionDecision($conn, $task_id, $decision, $admin_notes);
        }
    }
    echo json_encode($res);
    exit;

} elseif ($action === 'exempt_task_delay') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Only administrators or managers can exempt delay penalties']); exit;
    }
    $admin_notes = trim($_POST['admin_notes'] ?? 'Exempted by Admin');
    $success = exemptTaskDelayPenalty($conn, $task_id, $user_id, $admin_notes);
    echo json_encode(['success' => $success, 'message' => $success ? 'Delay penalty exempted successfully and payroll updated.' : 'Failed to exempt delay.']);
    exit;

} elseif ($action === 'submit_review') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $review_type = trim($_POST['review_type'] ?? 'Internal'); // 'Internal' or 'Client'
    $decision    = trim($_POST['decision'] ?? ''); // 'Approve', 'Request Changes', 'Reject'
    $comment     = trim($_POST['comment'] ?? '');

    if (!in_array($review_type, ['Internal', 'Client'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid review type']); exit;
    }

    if ($review_type === 'Internal' && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Only administrators or managers can complete internal reviews']); exit;
    }

    if ($review_type === 'Client') {
        $is_client = in_array($role, ['client', 'client_sub']);
        if (!$is_client && !$is_admin) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to submit client reviews for this task']); exit;
        }
    }

    $current_rev = intval($t['current_revision'] ?? 1);

    // Record review in task_reviews table
    $stmt_rev = $conn->prepare("INSERT INTO task_reviews (task_id, reviewer_id, review_type, decision, comment, revision, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt_rev) {
        $stmt_rev->bind_param("iisssi", $task_id, $user_id, $review_type, $decision, $comment, $current_rev);
        $stmt_rev->execute();
        $stmt_rev->close();
    }

    // Workflow stage progression based on review decision
    if ($review_type === 'Internal') {
        if ($decision === 'Approve') {
            $has_client = !empty($t['client_id']);
            $next_stage = $has_client ? 'Client Approval' : 'Publication/Delivery';
            advanceTaskStage($conn, $task_id, $next_stage, $user_id, $role, "Internal review approved: " . $comment);
            $conn->query("UPDATE tasks SET internal_review_status = 'approved' WHERE id = $task_id");
        } else {
            advanceTaskStage($conn, $task_id, 'Production', $user_id, $role, "Internal review requested changes: " . $comment);
            $conn->query("UPDATE tasks SET internal_review_status = 'changes_requested', current_revision = current_revision + 1 WHERE id = $task_id");
        }
    } elseif ($review_type === 'Client') {
        if ($decision === 'Approve') {
            advanceTaskStage($conn, $task_id, 'Publication/Delivery', $user_id, $role, "Client review approved: " . $comment);
            $conn->query("UPDATE tasks SET client_approval_status = 'approved', client_feedback = " . ($comment ? "'" . $conn->real_escape_string($comment) . "'" : "NULL") . " WHERE id = $task_id");
        } else {
            advanceTaskStage($conn, $task_id, 'Production', $user_id, $role, "Client requested changes: " . $comment);
            $conn->query("UPDATE tasks SET client_approval_status = 'changes_requested', client_feedback = " . ($comment ? "'" . $conn->real_escape_string($comment) . "'" : "NULL") . ", current_revision = current_revision + 1 WHERE id = $task_id");
        }
    }

    echo json_encode(['success' => true, 'message' => 'Review recorded and workflow updated successfully']);
    exit;

} elseif ($action === 'delete_task') {
    if (!$task_id || !$t) {
        echo json_encode(['success' => false, 'message' => 'Task not found']); exit;
    }
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Task deleted']);
    exit;

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}
