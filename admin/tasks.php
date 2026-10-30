<?php
// ── AJAX handler — must be BEFORE any HTML output ─────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_action'])) {
    ob_start();
    include('dashboard_header.php');
    while (ob_get_level() > 0) ob_end_clean();

    $user_id  = intval($_SESSION['user_id'] ?? 0);
    $is_admin = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin');
    $action   = $_POST['ajax_action'];
    $task_id  = intval($_POST['task_id'] ?? 0);

    header('Content-Type: application/json');

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }

    if ($task_id > 0) {
        $check = $conn->prepare("SELECT id, created_by, assigned_to FROM tasks WHERE id=?");
        $check->bind_param("i", $task_id);
        $check->execute();
        $t = $check->get_result()->fetch_assoc();

        if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }
    }

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
        if (!in_array($new_status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
        }
        $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("si", $new_status, $task_id);
        $stmt->execute();
        if (function_exists('logActivity')) {
            logActivity($conn, 'update_task_status', "Updated task #$task_id status to '$new_status'", 'task', $task_id);
        }
        if (in_array($new_status, ['In Review', 'Completed']) && file_exists(__DIR__ . '/../core/notification_mailer.php')) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyTaskStatusChanged')) notifyTaskStatusChanged($conn, $task_id, $new_status, $user_id);
        }
        echo json_encode(['success' => true, 'message' => 'Status updated']);
        exit;

    } elseif ($action === 'delete_task') {
        if (!$is_admin && $t['created_by'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Only admins or task creators can delete tasks']); exit;
        }
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        if (function_exists('logActivity')) {
            logActivity($conn, 'delete_task', "Deleted task #$task_id", 'task', $task_id);
        }
        echo json_encode(['success' => true, 'message' => 'Task deleted']);
        exit;

    } elseif ($action === 'create_task') {
        $title           = trim($_POST['title'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $priority        = $_POST['priority'] ?? 'Medium';
        $status          = $_POST['status'] ?? 'Pending';
        $due_date        = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+3 days'));
        $assigned_to     = intval($_POST['assigned_to'] ?? 0) ?: $user_id;
        $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
        $task_type       = trim($_POST['task_type'] ?? 'Design');
        $estimated_hours = floatval($_POST['estimated_hours'] ?? 0);
        $logged_hours    = floatval($_POST['logged_hours'] ?? 0);

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Task title is required']); exit;
        }
        $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
        if (!in_array($status, $allowed)) $status = 'Pending';

        $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, project_id, task_type, estimated_hours, logged_hours, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("sssssiisddi", $title, $description, $priority, $status, $due_date, $assigned_to, $project_id, $task_type, $estimated_hours, $logged_hours, $user_id);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            if (function_exists('logActivity')) {
                logActivity($conn, 'create_task', "Created task: '$title'", 'task', $new_id);
            }
            if ($assigned_to && $assigned_to != $user_id && file_exists(__DIR__ . '/../core/notification_mailer.php')) {
                require_once __DIR__ . '/../core/notification_mailer.php';
                if (function_exists('notifyTaskAssigned')) notifyTaskAssigned($conn, $new_id);
            }
            echo json_encode(['success' => true, 'task_id' => $new_id, 'message' => 'Task created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

include('dashboard_header.php');

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id   = intval($_SESSION['user_id']);
$is_admin  = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin');

// ── Build Base WHERE clause based on Role & Filters ───────────────────────────
$where_parts = [];
if (!$is_admin) {
    $where_parts[] = "(t.assigned_to = $user_id OR t.created_by = $user_id)";
}

// Search Filter
$filter_q = trim($_GET['q'] ?? '');
if ($filter_q !== '') {
    $safe_q = $conn->real_escape_string($filter_q);
    $where_parts[] = "(t.title LIKE '%$safe_q%' OR t.description LIKE '%$safe_q%' OR t.task_type LIKE '%$safe_q%')";
}

// Assignee Filter
$filter_assignee = intval($_GET['assignee'] ?? 0);
if ($filter_assignee > 0) {
    $where_parts[] = "t.assigned_to = $filter_assignee";
}

// Priority Filter
$filter_priority = trim($_GET['priority'] ?? '');
if (in_array($filter_priority, ['Low', 'Medium', 'High'])) {
    $where_parts[] = "t.priority = '$filter_priority'";
}

// Project Filter
$filter_project = intval($_GET['project'] ?? 0);
if ($filter_project > 0) {
    $where_parts[] = "t.project_id = $filter_project";
}

$where_sql = !empty($where_parts) ? implode(' AND ', $where_parts) : "1=1";

// ── Tasks Query with details, comments count, and subtasks count ──────────────
$tasks_result = $conn->query("
    SELECT t.*, 
           u.name AS assignee_name, u.email AS assignee_email,
           p.project_name,
           (SELECT COUNT(*) FROM task_comments tc WHERE tc.task_id = t.id) AS comments_count,
           (SELECT COUNT(*) FROM task_subtasks ts WHERE ts.task_id = t.id) AS subtasks_count,
           (SELECT COUNT(*) FROM task_subtasks ts WHERE ts.task_id = t.id AND ts.status = 'Completed') AS subtasks_done
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE $where_sql
    ORDER BY t.priority = 'High' DESC, t.due_date ASC, t.id DESC
");

// ── Define Canonical Columns matching reference image ─────────────────────────
$columns = [
    'Pending'     => ['title' => 'To-do',        'badge_class' => 'badge-todo',     'db_status' => 'Pending'],
    'In Progress' => ['title' => 'In Progress',  'badge_class' => 'badge-progress', 'db_status' => 'In Progress'],
    'In Review'   => ['title' => 'Review Ready', 'badge_class' => 'badge-review',   'db_status' => 'In Review'],
    'Completed'   => ['title' => 'Completed',    'badge_class' => 'badge-complete', 'db_status' => 'Completed']
];

$tasks_by_column = [
    'Pending'     => [],
    'In Progress' => [],
    'In Review'   => [],
    'Completed'   => [],
    'Cancelled'   => []
];

$all_tasks_list = [];

while ($task = $tasks_result->fetch_assoc()) {
    $s = $task['status'];
    if ($s === 'Pending Review') $s = 'In Review';
    if (!isset($tasks_by_column[$s])) $s = 'Pending';
    $tasks_by_column[$s][] = $task;
    $all_tasks_list[] = $task;
}

// ── Users list for assignment and filtering ───────────────────────────────────
$users_result = $conn->query("SELECT user_id AS id, name, role FROM users WHERE status='active' OR status IS NULL ORDER BY name");
$users_list = [];
while ($u = $users_result->fetch_assoc()) {
    $users_list[] = $u;
}

// ── Projects list for project selector ────────────────────────────────────────
$projects_result = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
$projects_list = [];
if ($projects_result) {
    while ($pr = $projects_result->fetch_assoc()) {
        $projects_list[] = $pr;
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDuration($hours) {
    if (!$hours || $hours <= 0) return '0h';
    $h = floor($hours);
    $m = round(($hours - $h) * 60);
    if ($h > 0 && $m > 0) return "{$h}h {$m}min";
    if ($h > 0) return "{$h}h";
    return "{$m}min";
}

function getTaskTypeBadge($type, $priority) {
    $t = strtolower(trim($type ?: ''));
    if (strpos($t, 'design') !== false || strpos($t, 'ui') !== false || strpos($t, 'ux') !== false) {
        return ['bg' => '#f3e8ff', 'color' => '#7e22ce', 'border' => '#e9d5ff', 'label' => $type ?: 'Design'];
    }
    if (strpos($t, 'dev') !== false || strpos($t, 'code') !== false || strpos($t, 'tech') !== false) {
        return ['bg' => '#e0f2fe', 'color' => '#0369a1', 'border' => '#bae6fd', 'label' => $type ?: 'Development'];
    }
    if (strpos($t, 'wireframe') !== false || strpos($t, 'flow') !== false) {
        return ['bg' => '#eef2ff', 'color' => '#4338ca', 'border' => '#c7d2fe', 'label' => $type ?: 'Wireframes'];
    }
    if (strpos($t, 'media') !== false || strpos($t, 'social') !== false || strpos($t, 'marketing') !== false) {
        return ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a', 'label' => $type ?: 'Media'];
    }
    if (strpos($t, 'brand') !== false || strpos($t, 'identity') !== false) {
        return ['bg' => '#fce7f3', 'color' => '#be185d', 'border' => '#fbcfe8', 'label' => $type ?: 'Branding'];
    }
    if (strpos($t, 'infra') !== false || strpos($t, 'server') !== false || strpos($t, 'devops') !== false) {
        return ['bg' => '#ecfeff', 'color' => '#0e7490', 'border' => '#a5f3fc', 'label' => $type ?: 'Infrastructure'];
    }
    if (strpos($t, 'operation') !== false || strpos($t, 'qa') !== false) {
        return ['bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#bbf7d0', 'label' => $type ?: 'Operations'];
    }

    if (strtolower($priority) === 'high') {
        return ['bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fecaca', 'label' => $type ?: 'High Priority'];
    } elseif (strtolower($priority) === 'medium') {
        return ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a', 'label' => $type ?: 'Medium Priority'];
    }
    return ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0', 'label' => $type ?: 'General'];
}

function getAvatarStyle($name) {
    $palettes = [
        ['bg' => '#024442', 'color' => '#ffffff'],
        ['bg' => '#4f46e5', 'color' => '#ffffff'],
        ['bg' => '#0284c7', 'color' => '#ffffff'],
        ['bg' => '#d97706', 'color' => '#ffffff'],
        ['bg' => '#db2777', 'color' => '#ffffff'],
        ['bg' => '#059669', 'color' => '#ffffff'],
        ['bg' => '#7c3aed', 'color' => '#ffffff'],
        ['bg' => '#475569', 'color' => '#ffffff'],
    ];
    $idx = abs(crc32($name ?: 'User')) % count($palettes);
    return $palettes[$idx];
}

function getUserInitials($name) {
    if (!$name) return 'U';
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>

<div class="height-100 workspace-wrapper">
    <!-- Top Control Bar -->
    <div class="workspace-header">
        <div class="header-left-col">
            <h1 class="workspace-title">Workspace</h1>
            
            <!-- View Mode Switcher -->
            <div class="view-switcher-tabs" role="tablist">
                <button type="button" class="tab-btn active" data-view="board">
                    <span class="active-dot"></span>
                    Board
                </button>
                <button type="button" class="tab-btn" data-view="list">
                    List
                </button>
                <button type="button" class="tab-btn" data-view="calendar">
                    Calendar
                </button>
                <button type="button" class="tab-btn" data-view="table">
                    Table
                </button>
            </div>
        </div>

        <div class="header-right-col">
            <!-- Share button -->
            <button type="button" class="btn-ctrl-action" id="btnShareWorkspace" onclick="copyShareLink()">
                <i class="fas fa-share-alt"></i>
                <span>Share</span>
            </button>

            <!-- Filters Toggle button -->
            <button type="button" class="btn-ctrl-action <?= ($filter_q || $filter_assignee || $filter_priority || $filter_project) ? 'active-filter' : '' ?>" id="btnToggleFilters" onclick="toggleFilterBar()">
                <i class="fas fa-sliders-h"></i>
                <span>Filters <?= ($filter_q || $filter_assignee || $filter_priority || $filter_project) ? '•' : '' ?></span>
            </button>

            <!-- Group by Selector -->
            <div class="dropdown d-inline-block">
                <button class="btn-ctrl-action dropdown-toggle" type="button" id="groupByDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span>Group by: Status</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="groupByDropdown">
                    <li><a class="dropdown-item active" href="tasks.php">Status (Default)</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="switchGroupBy('priority')">Priority</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="switchGroupBy('assignee')">Assignee</a></li>
                </ul>
            </div>

            <!-- Primary Add Task button -->
            <button type="button" class="btn-primary-add" onclick="openAddTaskModal('Pending')">
                <i class="fas fa-plus"></i>
                <span>Add Task</span>
            </button>
        </div>
    </div>

    <!-- Collapsible Filter Bar -->
    <div class="workspace-filter-bar <?= ($filter_q || $filter_assignee || $filter_priority || $filter_project) ? 'show' : '' ?>" id="filterBarContainer">
        <form method="GET" action="tasks.php" class="filter-form">
            <div class="filter-input-wrap search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>" placeholder="Search tasks, titles, tags..." class="form-ctrl-compact" id="liveSearchInput">
            </div>

            <div class="filter-input-wrap">
                <select name="assignee" class="form-ctrl-compact" onchange="this.form.submit()">
                    <option value="">All Assignees</option>
                    <?php foreach ($users_list as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_assignee == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-input-wrap">
                <select name="priority" class="form-ctrl-compact" onchange="this.form.submit()">
                    <option value="">All Priorities</option>
                    <option value="High" <?= $filter_priority === 'High' ? 'selected' : '' ?>>High Priority</option>
                    <option value="Medium" <?= $filter_priority === 'Medium' ? 'selected' : '' ?>>Medium Priority</option>
                    <option value="Low" <?= $filter_priority === 'Low' ? 'selected' : '' ?>>Low Priority</option>
                </select>
            </div>

            <?php if (!empty($projects_list)): ?>
            <div class="filter-input-wrap">
                <select name="project" class="form-ctrl-compact" onchange="this.form.submit()">
                    <option value="">All Projects</option>
                    <?php foreach ($projects_list as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($filter_q || $filter_assignee || $filter_priority || $filter_project): ?>
            <a href="tasks.php" class="btn-clear-filter" title="Reset all filters">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- MAIN VIEWS CONTAINER -->
    <div class="workspace-main-views">

        <!-- 1. BOARD VIEW (Default) -->
        <div class="view-panel active" id="view-board">
            <div class="kanban-grid-wrapper">
                <?php foreach ($columns as $status_key => $col_meta): 
                    $col_tasks = $tasks_by_column[$status_key] ?? [];
                    $task_count = count($col_tasks);
                ?>
                <div class="kanban-column" data-status="<?= $status_key ?>">
                    <!-- Column Header -->
                    <div class="column-header">
                        <div class="col-title-group">
                            <span class="col-title-text"><?= htmlspecialchars($col_meta['title']) ?></span>
                            <span class="col-counter-badge" id="badge-count-<?= $status_key ?>"><?= $task_count ?></span>
                        </div>
                        <div class="col-header-actions">
                            <button type="button" class="btn-col-action" title="Add task to <?= htmlspecialchars($col_meta['title']) ?>" onclick="openAddTaskModal('<?= $status_key ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                            <div class="dropdown d-inline-block">
                                <button type="button" class="btn-col-action" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="openAddTaskModal('<?= $status_key ?>')">+ Add Task</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="filterByColumnStatus('<?= $status_key ?>')">Focus this column</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Column Cards Container (Droppable) -->
                    <div class="kanban-cards-list" data-status="<?= $status_key ?>">
                        <?php if ($task_count === 0): ?>
                        <!-- Empty State matching user screenshot -->
                        <div class="empty-column-box" data-empty-for="<?= $status_key ?>">
                            <div class="empty-icon-wrap">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <p class="empty-text">No tasks currently. Board is empty</p>
                            <button type="button" class="btn-create-task-empty" onclick="openAddTaskModal('<?= $status_key ?>')">
                                + Create Task
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($col_tasks as $task): 
                            $badge = getTaskTypeBadge($task['task_type'], $task['priority']);
                            $avatar = getAvatarStyle($task['assignee_name']);
                            $initials = getUserInitials($task['assignee_name']);
                            $is_overdue = (!empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] !== 'Completed');
                            $est_str = ($task['estimated_hours'] > 0) ? formatDuration($task['estimated_hours']) : '3h';
                            $log_str = formatDuration($task['logged_hours']);
                        ?>
                        <div class="kanban-task-card" draggable="true" data-id="<?= $task['id'] ?>" data-status="<?= $status_key ?>">
                            <!-- Card Top: Tag + Assignee Avatar -->
                            <div class="card-top-row">
                                <span class="task-tag-chip" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>; border: 1px solid <?= $badge['border'] ?>;">
                                    <?= htmlspecialchars($badge['label']) ?>
                                </span>

                                <div class="card-top-right">
                                    <div class="task-avatar-circle" style="background: <?= $avatar['bg'] ?>; color: <?= $avatar['color'] ?>;" title="<?= htmlspecialchars($task['assignee_name'] ?: 'Unassigned') ?>">
                                        <?= $initials ?>
                                    </div>
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="btn-card-more" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="openEditTaskModal(<?= $task['id'] ?>)"><i class="fas fa-edit me-2 text-muted"></i> Edit Task</a></li>
                                            <li><a class="dropdown-item" href="task_details.php?id=<?= $task['id'] ?>"><i class="fas fa-external-link-alt me-2 text-muted"></i> View Details</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header">Move to:</h6></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickMoveTask(<?= $task['id'] ?>, 'Pending')">📋 To-do</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickMoveTask(<?= $task['id'] ?>, 'In Progress')">⚡ In Progress</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickMoveTask(<?= $task['id'] ?>, 'In Review')">🔍 Review Ready</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickMoveTask(<?= $task['id'] ?>, 'Completed')">✅ Completed</a></li>
                                            <?php if ($is_admin || $task['created_by'] == $user_id): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteTask(<?= $task['id'] ?>)"><i class="fas fa-trash-alt me-2"></i> Delete</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Title -->
                            <div class="task-title-text" onclick="openEditTaskModal(<?= $task['id'] ?>)">
                                <?= htmlspecialchars($task['title']) ?>
                            </div>

                            <?php if (!empty($task['description'])): ?>
                            <div class="task-snippet-text">
                                <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 75, '...')) ?>
                            </div>
                            <?php endif; ?>

                            <!-- Card Middle: Date + Estimated Duration -->
                            <div class="card-schedule-row">
                                <span class="schedule-pill <?= $is_overdue ? 'overdue' : '' ?>">
                                    <i class="far fa-calendar-alt"></i>
                                    <span><?= !empty($task['due_date']) ? date('M d', strtotime($task['due_date'])) : 'No date' ?></span>
                                </span>
                                <span class="duration-pill">
                                    <i class="far fa-clock"></i>
                                    <span><?= $est_str ?></span>
                                </span>
                            </div>

                            <!-- Card Footer: Time Logged + Metrics -->
                            <div class="card-footer-row">
                                <div class="log-time-badge">
                                    <i class="fas fa-history"></i>
                                    <span>Log: <?= $log_str ?></span>
                                </div>

                                <div class="card-metrics-group">
                                    <?php if ($task['subtasks_count'] > 0): ?>
                                    <span class="metric-item" title="Subtasks completed">
                                        <i class="far fa-check-square"></i>
                                        <span><?= $task['subtasks_done'] ?>/<?= $task['subtasks_count'] ?></span>
                                    </span>
                                    <?php endif; ?>

                                    <span class="metric-item" title="Comments">
                                        <i class="far fa-comment-alt"></i>
                                        <span><?= intval($task['comments_count']) ?></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2. LIST VIEW TAB -->
        <div class="view-panel" id="view-list">
            <div class="modern-list-container">
                <div class="list-table-responsive">
                    <table class="modern-list-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox"></th>
                                <th>Task Title</th>
                                <th>Category</th>
                                <th>Project</th>
                                <th>Assignee</th>
                                <th>Due Date</th>
                                <th>Log / Est</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_tasks_list)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="far fa-clipboard fa-2x mb-3 d-block text-secondary"></i>
                                    No tasks match your active filters.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($all_tasks_list as $t): 
                                $badge = getTaskTypeBadge($t['task_type'], $t['priority']);
                                $avatar = getAvatarStyle($t['assignee_name']);
                                $initials = getUserInitials($t['assignee_name']);
                                $is_overdue = (!empty($t['due_date']) && strtotime($t['due_date']) < strtotime(date('Y-m-d')) && $t['status'] !== 'Completed');
                            ?>
                            <tr class="list-row <?= $t['status'] === 'Completed' ? 'task-completed-row' : '' ?>" data-id="<?= $t['id'] ?>">
                                <td>
                                    <input type="checkbox" class="task-checkbox" <?= $t['status'] === 'Completed' ? 'checked' : '' ?> onchange="quickToggleComplete(<?= $t['id'] ?>, this.checked)">
                                </td>
                                <td>
                                    <div class="list-task-title-cell">
                                        <span class="list-title" onclick="openEditTaskModal(<?= $t['id'] ?>)">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </span>
                                        <?php if (!empty($t['description'])): ?>
                                        <span class="list-desc-hint"><?= htmlspecialchars(mb_strimwidth($t['description'], 0, 50, '...')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="task-tag-chip" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>; border: 1px solid <?= $badge['border'] ?>;">
                                        <?= htmlspecialchars($badge['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="list-project-name"><?= htmlspecialchars($t['project_name'] ?: '—') ?></span>
                                </td>
                                <td>
                                    <div class="list-assignee-cell">
                                        <div class="task-avatar-circle small" style="background: <?= $avatar['bg'] ?>; color: <?= $avatar['color'] ?>;">
                                            <?= $initials ?>
                                        </div>
                                        <span><?= htmlspecialchars($t['assignee_name'] ?: 'Unassigned') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="schedule-pill <?= $is_overdue ? 'overdue' : '' ?>">
                                        <?= !empty($t['due_date']) ? date('M d, Y', strtotime($t['due_date'])) : '—' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="list-time-stats">
                                        <?= formatDuration($t['logged_hours']) ?> / <?= formatDuration($t['estimated_hours']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-pill priority-<?= strtolower($t['priority'] ?: 'medium') ?>">
                                        <?= ucfirst($t['priority'] ?: 'Medium') ?>
                                    </span>
                                </td>
                                <td>
                                    <select class="status-select-inline" onchange="quickMoveTask(<?= $t['id'] ?>, this.value)">
                                        <option value="Pending" <?= $t['status'] === 'Pending' ? 'selected' : '' ?>>To-do</option>
                                        <option value="In Progress" <?= $t['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="In Review" <?= ($t['status'] === 'In Review' || $t['status'] === 'Pending Review') ? 'selected' : '' ?>>Review Ready</option>
                                        <option value="Completed" <?= $t['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn-table-action" onclick="openEditTaskModal(<?= $t['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="task_details.php?id=<?= $t['id'] ?>" class="btn-table-action" title="Details">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <?php if ($is_admin || $t['created_by'] == $user_id): ?>
                                    <button class="btn-table-action text-danger" onclick="deleteTask(<?= $t['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. CALENDAR VIEW TAB -->
        <div class="view-panel" id="view-calendar">
            <div class="modern-calendar-box">
                <div class="calendar-top-bar">
                    <h3 class="cal-month-title"><?= date('F Y') ?></h3>
                    <div class="cal-nav-buttons">
                        <button class="btn-ctrl-action" onclick="showToast('Calendar synced to current active period')">Today</button>
                    </div>
                </div>
                <div class="calendar-week-headers">
                    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                </div>
                <div class="calendar-days-grid">
                    <?php
                    $c_month = date('m'); $c_year = date('Y');
                    $first_day = mktime(0,0,0,$c_month,1,$c_year);
                    $days_in_m = date('t', $first_day);
                    $start_w   = date('w', $first_day);

                    // tasks mapped by day
                    $day_tasks_map = [];
                    foreach ($all_tasks_list as $t) {
                        if (!empty($t['due_date'])) {
                            $t_m = date('m', strtotime($t['due_date']));
                            $t_y = date('Y', strtotime($t['due_date']));
                            if ($t_m == $c_month && $t_y == $c_year) {
                                $d = intval(date('j', strtotime($t['due_date'])));
                                $day_tasks_map[$d][] = $t;
                            }
                        }
                    }

                    for ($i = 0; $i < $start_w; $i++): ?>
                    <div class="cal-day-cell empty"></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $days_in_m; $day++): 
                        $is_today = ($day == date('j') && $c_month == date('m') && $c_year == date('Y'));
                        $cell_tasks = $day_tasks_map[$day] ?? [];
                    ?>
                    <div class="cal-day-cell <?= $is_today ? 'is-today' : '' ?>">
                        <div class="cal-day-num"><?= $day ?></div>
                        <div class="cal-task-chips">
                            <?php foreach ($cell_tasks as $ct): 
                                $badge = getTaskTypeBadge($ct['task_type'], $ct['priority']);
                            ?>
                            <div class="cal-task-pill" onclick="openEditTaskModal(<?= $ct['id'] ?>)" title="<?= htmlspecialchars($ct['title']) ?>" style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>;">
                                <?= htmlspecialchars(mb_strimwidth($ct['title'], 0, 18, '..')) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- 4. TABLE VIEW TAB -->
        <div class="view-panel" id="view-table">
            <div class="modern-list-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 font-weight-bold">Summary Table View</h5>
                    <button type="button" class="btn-ctrl-action" onclick="exportTasksTableCSV()">
                        <i class="fas fa-file-export me-1"></i> Export CSV
                    </button>
                </div>
                <div class="list-table-responsive">
                    <table class="modern-list-table" id="exportSummaryTable">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Project</th>
                                <th>Assignee</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Est. Hours</th>
                                <th>Logged Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tasks_list as $t): ?>
                            <tr>
                                <td>#<?= $t['id'] ?></td>
                                <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                                <td><?= htmlspecialchars($t['task_type'] ?: 'General') ?></td>
                                <td><?= htmlspecialchars($t['project_name'] ?: 'None') ?></td>
                                <td><?= htmlspecialchars($t['assignee_name'] ?: 'Unassigned') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['status']) ?></span></td>
                                <td><?= ucfirst($t['priority']) ?></td>
                                <td><?= !empty($t['due_date']) ? $t['due_date'] : '—' ?></td>
                                <td><?= floatval($t['estimated_hours']) ?>h</td>
                                <td><?= floatval($t['logged_hours']) ?>h</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── ADD / EDIT TASK MODAL ────────────────────────────────────────────────── -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modern-modal-card">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="modal-header-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" id="taskModalLabel">Create New Task</h5>
                        <p class="modal-subtitle">Organize and assign work across your team</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="taskForm" onsubmit="handleTaskFormSubmit(event)">
                <input type="hidden" name="task_id" id="modalTaskId" value="0">
                <input type="hidden" name="ajax_action" id="modalAjaxAction" value="create_task">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-custom">Task Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="modalTaskTitle" class="form-control form-control-lg modern-input" placeholder="e.g. Drafting the app wireframes and flows" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Category / Tag</label>
                            <input type="text" name="task_type" id="modalTaskType" list="taskTypeList" class="form-control modern-input" placeholder="e.g. Design, Development, Media...">
                            <datalist id="taskTypeList">
                                <option value="Design">
                                <option value="UI Design">
                                <option value="Wireframes">
                                <option value="Development">
                                <option value="Media">
                                <option value="Branding">
                                <option value="Operations">
                                <option value="Infrastructure">
                                <option value="Marketing">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Project</label>
                            <select name="project_id" id="modalTaskProject" class="form-select modern-input">
                                <option value="">No Project (General)</option>
                                <?php foreach ($projects_list as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Assignee</label>
                            <select name="assigned_to" id="modalTaskAssignee" class="form-select modern-input">
                                <?php foreach ($users_list as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $u['id'] == $user_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Priority</label>
                            <select name="priority" id="modalTaskPriority" class="form-select modern-input">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Column / Status</label>
                            <select name="status" id="modalTaskStatus" class="form-select modern-input">
                                <option value="Pending">To-do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="In Review">Review Ready</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label-custom">Due Date</label>
                            <input type="date" name="due_date" id="modalTaskDueDate" class="form-control modern-input" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Estimated Hours</label>
                            <input type="number" step="0.5" name="estimated_hours" id="modalTaskEstHours" class="form-control modern-input" placeholder="e.g. 3.0" value="3.0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Logged Hours</label>
                            <input type="number" step="0.25" name="logged_hours" id="modalTaskLogHours" class="form-control modern-input" placeholder="e.g. 1.75" value="0.0">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label-custom">Description & Details</label>
                        <textarea name="description" id="modalTaskDesc" rows="3" class="form-control modern-input" placeholder="Brief notes, deliverables, or checklist..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-add" id="btnSubmitModal">
                        <span>Save Task</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="workspace-toast-shelf"></div>

<!-- ── STYLES MATCHING THE REFERENCE DESIGN ─────────────────────────────────── -->
<style>
/* Workspace Container & Fonts */
.workspace-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #0f172a;
    padding-bottom: 40px;
}

/* Header & Controls */
.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding-top: 6px;
}

.header-left-col {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.workspace-title {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.02em;
}

/* View Switcher Tabs */
.view-switcher-tabs {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 3px;
    gap: 2px;
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: none;
    padding: 7px 16px;
    font-size: 13.5px;
    font-weight: 600;
    color: #64748b;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.16s ease-in-out;
}

.tab-btn:hover {
    color: #0f172a;
}

.tab-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.active-dot {
    width: 6px;
    height: 6px;
    background: #2563eb;
    border-radius: 50%;
    display: inline-block;
}

/* Header Right Buttons */
.header-right-col {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-ctrl-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #334155;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.btn-ctrl-action:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.btn-ctrl-action.active-filter {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.btn-primary-add {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0f172a;
    color: #ffffff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 2px 4px rgba(15,23,42,0.15);
}

.btn-primary-add:hover {
    background: #1e293b;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(15,23,42,0.2);
}

/* Collapsible Filter Bar */
.workspace-filter-bar {
    display: none;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.workspace-filter-bar.show {
    display: block;
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-input-wrap.search-box {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.filter-input-wrap.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13px;
}

.form-ctrl-compact {
    height: 38px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    font-size: 13px;
    padding: 6px 12px;
    color: #1e293b;
    outline: none;
    background: #f8fafc;
    transition: border 0.15s;
}

.filter-input-wrap.search-box input {
    width: 100%;
    padding-left: 34px;
}

.form-ctrl-compact:focus {
    border-color: #94a3b8;
    background: #ffffff;
}

.btn-clear-filter {
    font-size: 12.5px;
    font-weight: 600;
    color: #ef4444;
    text-decoration: none;
    padding: 6px 10px;
    border-radius: 6px;
    transition: background 0.15s;
}

.btn-clear-filter:hover {
    background: #fee2e2;
}

/* View Panels */
.view-panel {
    display: none;
}

.view-panel.active {
    display: block;
}

/* ── KANBAN BOARD GRID ─────────────────────────────────────────────────────── */
.kanban-grid-wrapper {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    align-items: start;
    width: 100%;
}

@media (max-width: 1200px) {
    .kanban-grid-wrapper {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .kanban-grid-wrapper {
        grid-template-columns: 1fr;
    }
}

/* Kanban Column */
.kanban-column {
    background: #f8fafc;
    border-radius: 14px;
    padding: 14px 14px 18px 14px;
    border: 1px solid #e2e8f0;
    min-height: 520px;
    display: flex;
    flex-direction: column;
}

.column-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding: 2px 4px;
}

.col-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.col-title-text {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.01em;
}

.col-counter-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    min-width: 22px;
    text-align: center;
}

.col-header-actions {
    display: flex;
    align-items: center;
    gap: 4px;
}

.btn-col-action {
    background: transparent;
    border: none;
    color: #94a3b8;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-col-action:hover {
    background: #e2e8f0;
    color: #1e293b;
}

/* Cards List */
.kanban-cards-list {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 200px;
}

.kanban-cards-list.drag-over {
    background: #f1f5f9;
    border-radius: 10px;
    outline: 2px dashed #94a3b8;
}

/* ── KANBAN CARD STYLING ───────────────────────────────────────────────────── */
.kanban-task-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    cursor: grab;
    transition: all 0.18s ease-in-out;
    position: relative;
    user-select: none;
}

.kanban-task-card:hover {
    box-shadow: 0 6px 14px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
    transform: translateY(-2px);
}

.kanban-task-card:active {
    cursor: grabbing;
}

.kanban-task-card.is-dragging {
    opacity: 0.4;
    border: 2px dashed #64748b;
    background: #f8fafc;
}

/* Card Top Row */
.card-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.task-tag-chip {
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.02em;
    display: inline-block;
}

.card-top-right {
    display: flex;
    align-items: center;
    gap: 6px;
}

.task-avatar-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: -0.02em;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.btn-card-more {
    background: transparent;
    border: none;
    color: #94a3b8;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    transition: color 0.15s;
}

.btn-card-more:hover {
    color: #334155;
    background: #f1f5f9;
}

/* Card Title */
.task-title-text {
    font-size: 14.5px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.42;
    margin-bottom: 6px;
    cursor: pointer;
    transition: color 0.15s;
}

.task-title-text:hover {
    color: #2563eb;
}

.task-snippet-text {
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.4;
    margin-bottom: 12px;
}

/* Schedule Row */
.card-schedule-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.schedule-pill, .duration-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f1f5f9;
    color: #475569;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}

.schedule-pill.overdue {
    background: #fef2f2;
    color: #ef4444;
}

/* Card Footer Row */
.card-footer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
}

.log-time-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
}

.card-metrics-group {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #94a3b8;
    font-size: 12px;
}

.metric-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* ── EMPTY STATE MATCHING SCREENSHOT ───────────────────────────────────────── */
.empty-column-box {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background: #ffffff;
    padding: 36px 16px;
    text-align: center;
    margin: 8px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon-wrap {
    margin-bottom: 12px;
    opacity: 0.8;
}

.empty-text {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 14px 0;
    font-weight: 500;
}

.btn-create-task-empty {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-create-task-empty:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}

/* ── LIST VIEW STYLES ──────────────────────────────────────────────────────── */
.modern-list-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.list-table-responsive {
    overflow-x: auto;
}

.modern-list-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}

.modern-list-table th {
    background: #f8fafc;
    padding: 12px 14px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.modern-list-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #334155;
}

.list-row:hover {
    background: #f8fafc;
}

.task-completed-row td {
    opacity: 0.65;
}

.task-completed-row .list-title {
    text-decoration: line-through;
}

.list-task-title-cell {
    display: flex;
    flex-direction: column;
}

.list-title {
    font-weight: 600;
    color: #0f172a;
    cursor: pointer;
}

.list-title:hover {
    color: #2563eb;
}

.list-desc-hint {
    font-size: 11.5px;
    color: #94a3b8;
}

.list-assignee-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.task-avatar-circle.small {
    width: 24px;
    height: 24px;
    font-size: 9.5px;
}

.status-select-inline {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 6px;
    font-size: 12px;
    padding: 4px 8px;
    color: #334155;
    font-weight: 500;
}

.priority-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
}

.priority-high { background: #fee2e2; color: #dc2626; }
.priority-medium { background: #fef3c7; color: #b45309; }
.priority-low { background: #dcfce7; color: #16a34a; }

.btn-table-action {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 4px 6px;
    cursor: pointer;
    font-size: 13px;
    transition: color 0.15s;
    text-decoration: none;
}

.btn-table-action:hover {
    color: #1e293b;
}

/* ── CALENDAR VIEW STYLES ──────────────────────────────────────────────────── */
.modern-calendar-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.calendar-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.cal-month-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.calendar-week-headers {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    font-weight: 600;
    color: #64748b;
    font-size: 13px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.calendar-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    padding-top: 10px;
}

.cal-day-cell {
    min-height: 95px;
    border: 1px solid #f1f5f9;
    background: #f8fafc;
    border-radius: 8px;
    padding: 8px;
    display: flex;
    flex-direction: column;
}

.cal-day-cell.is-today {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.cal-day-cell.is-today .cal-day-num {
    color: #1d4ed8;
    font-weight: 700;
}

.cal-day-num {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}

.cal-task-chips {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cal-task-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── MODAL STYLES ──────────────────────────────────────────────────────────── */
.modern-modal-card {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}

.modal-header {
    border-bottom: 1px solid #f1f5f9;
    padding: 20px 24px;
}

.modal-header-icon {
    width: 38px;
    height: 38px;
    background: #f1f5f9;
    color: #0f172a;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.modal-title {
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.modal-subtitle {
    font-size: 12.5px;
    color: #64748b;
    margin: 0;
}

.form-label-custom {
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.modern-input {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13.5px;
    padding: 8px 12px;
}

.modern-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.btn-cancel-modal {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-weight: 600;
    font-size: 13.5px;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-cancel-modal:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ── TOAST NOTIFICATIONS ───────────────────────────────────────────────────── */
.workspace-toast-shelf {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ws-toast {
    background: #0f172a;
    color: #ffffff;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 500;
    box-shadow: 0 6px 18px rgba(15,23,42,0.25);
    display: flex;
    align-items: center;
    gap: 10px;
    animation: toastSlideIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.ws-toast.error {
    background: #ef4444;
}

@keyframes toastSlideIn {
    from { transform: translateY(12px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<!-- ── JAVASCRIPT INTERACTIONS ──────────────────────────────────────────────── -->
<script>
// ── Toast Notification Helper ─────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const shelf = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `ws-toast ${type}`;
    const icon = type === 'success' ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-exclamation-circle"></i>';
    toast.innerHTML = `${icon}<span>${message}</span>`;
    shelf.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        toast.style.transition = 'all 0.25s ease';
        setTimeout(() => toast.remove(), 250);
    }, 3200);
}

// ── View Mode Switcher ────────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.view-panel').forEach(p => p.classList.remove('active'));

        this.classList.add('active');
        const view = this.dataset.view;
        const panel = document.getElementById(`view-${view}`);
        if (panel) panel.classList.add('active');
    });
});

// ── Toggle Filters Bar ────────────────────────────────────────────────────────
function toggleFilterBar() {
    const bar = document.getElementById('filterBarContainer');
    bar.classList.toggle('show');
}

// ── Share Workspace Link ──────────────────────────────────────────────────────
function copyShareLink() {
    const url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Workspace URL copied to clipboard!');
        });
    } else {
        showToast('Workspace URL: ' + url);
    }
}

// ── Live Instant Search on Board ──────────────────────────────────────────────
const liveSearch = document.getElementById('liveSearchInput');
if (liveSearch) {
    liveSearch.addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('.kanban-task-card').forEach(card => {
            const title = card.querySelector('.task-title-text')?.textContent.toLowerCase() || '';
            const tag   = card.querySelector('.task-tag-chip')?.textContent.toLowerCase() || '';
            if (val === '' || title.includes(val) || tag.includes(val)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// ── AJAX Action Dispatcher ───────────────────────────────────────────────────
function postTaskAction(data) {
    return fetch('task_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(r => r.json());
}

// ── Drag & Drop Kanban Logic ─────────────────────────────────────────────────
let activeDraggedCard = null;
let originColumnStatus = null;

function bindDragAndDrop() {
    document.querySelectorAll('.kanban-task-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            activeDraggedCard = this;
            originColumnStatus = this.closest('.kanban-cards-list').dataset.status;
            this.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.id);
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('is-dragging');
            document.querySelectorAll('.kanban-cards-list').forEach(list => list.classList.remove('drag-over'));
            activeDraggedCard = null;
            originColumnStatus = null;
        });
    });

    document.querySelectorAll('.kanban-cards-list').forEach(list => {
        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');

            const afterElement = getDragAfterElement(this, e.clientY);
            if (!activeDraggedCard) return;

            if (afterElement == null) {
                this.appendChild(activeDraggedCard);
            } else {
                this.insertBefore(activeDraggedCard, afterElement);
            }
        });

        list.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        list.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            if (!activeDraggedCard) return;

            const newStatus = this.dataset.status;
            const taskId    = activeDraggedCard.dataset.id;
            const prevStatus = activeDraggedCard.dataset.status;

            if (newStatus !== prevStatus) {
                activeDraggedCard.dataset.status = newStatus;
                
                postTaskAction({ ajax_action: 'update_status', task_id: taskId, status: newStatus })
                    .then(res => {
                        if (res.success) {
                            showToast(`Moved to ${newStatus}`);
                            refreshColumnCounters();
                        } else {
                            showToast(res.message || 'Failed to update status', 'error');
                            location.reload();
                        }
                    })
                    .catch(err => {
                        showToast('Network error while saving task status', 'error');
                    });
            }

            refreshColumnCounters();
        });
    });
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.kanban-task-card:not(.is-dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function refreshColumnCounters() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const status = col.dataset.status;
        const list = col.querySelector('.kanban-cards-list');
        const cards = list.querySelectorAll('.kanban-task-card');
        const count = cards.length;

        // Update badge
        const badge = col.querySelector('.col-counter-badge');
        if (badge) badge.textContent = count;

        // Toggle Empty state box
        let emptyBox = list.querySelector('.empty-column-box');
        if (count === 0) {
            if (!emptyBox) {
                const box = document.createElement('div');
                box.className = 'empty-column-box';
                box.dataset.emptyFor = status;
                box.innerHTML = `
                    <div class="empty-icon-wrap">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <p class="empty-text">No tasks currently. Board is empty</p>
                    <button type="button" class="btn-create-task-empty" onclick="openAddTaskModal('${status}')">
                        + Create Task
                    </button>
                `;
                list.appendChild(box);
            } else {
                emptyBox.style.display = 'flex';
            }
        } else if (emptyBox) {
            emptyBox.style.display = 'none';
        }
    });
}

// ── Quick Move Task (From Menu or Select) ─────────────────────────────────────
function quickMoveTask(taskId, newStatus) {
    postTaskAction({ ajax_action: 'update_status', task_id: taskId, status: newStatus })
        .then(res => {
            if (res.success) {
                showToast(`Moved to ${newStatus}`);
                setTimeout(() => location.reload(), 300);
            } else {
                showToast(res.message || 'Update failed', 'error');
            }
        });
}

// ── Quick Toggle Complete in List View ────────────────────────────────────────
function quickToggleComplete(taskId, isChecked) {
    const status = isChecked ? 'Completed' : 'In Progress';
    quickMoveTask(taskId, status);
}

// ── Delete Task ───────────────────────────────────────────────────────────────
function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task? This cannot be undone.')) return;
    postTaskAction({ ajax_action: 'delete_task', task_id: taskId })
        .then(res => {
            if (res.success) {
                showToast('Task deleted successfully');
                const card = document.querySelector(`.kanban-task-card[data-id="${taskId}"]`);
                if (card) card.remove();
                const row = document.querySelector(`tr[data-id="${taskId}"]`);
                if (row) row.remove();
                refreshColumnCounters();
            } else {
                showToast(res.message || 'Delete failed', 'error');
            }
        });
}

// ── Add / Edit Modal Logic ────────────────────────────────────────────────────
let bsModal = null;

function getModalInstance() {
    if (!bsModal) {
        bsModal = new bootstrap.Modal(document.getElementById('taskModal'));
    }
    return bsModal;
}

function openAddTaskModal(preselectedStatus = 'Pending') {
    document.getElementById('taskForm').reset();
    document.getElementById('modalTaskId').value = '0';
    document.getElementById('modalAjaxAction').value = 'create_task';
    document.getElementById('taskModalLabel').textContent = 'Create New Task';
    document.getElementById('modalTaskStatus').value = preselectedStatus;
    getModalInstance().show();
}

function openEditTaskModal(taskId) {
    postTaskAction({ ajax_action: 'get_task', task_id: taskId })
        .then(res => {
            if (res.success && res.task) {
                const t = res.task;
                document.getElementById('modalTaskId').value = t.id;
                document.getElementById('modalAjaxAction').value = 'edit_task';
                document.getElementById('taskModalLabel').textContent = 'Edit Task #' + t.id;
                document.getElementById('modalTaskTitle').value = t.title || '';
                document.getElementById('modalTaskType').value = t.task_type || '';
                document.getElementById('modalTaskProject').value = t.project_id || '';
                document.getElementById('modalTaskAssignee').value = t.assigned_to || '';
                document.getElementById('modalTaskPriority').value = t.priority || 'Medium';
                document.getElementById('modalTaskStatus').value = t.status || 'Pending';
                document.getElementById('modalTaskDueDate').value = t.due_date || '';
                document.getElementById('modalTaskEstHours').value = t.estimated_hours || 0;
                document.getElementById('modalTaskLogHours').value = t.logged_hours || 0;
                document.getElementById('modalTaskDesc').value = t.description || '';
                getModalInstance().show();
            } else {
                showToast(res.message || 'Could not load task details', 'error');
            }
        })
        .catch(err => {
            showToast('Failed to load task details', 'error');
        });
}

function handleTaskFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    postTaskAction(data)
        .then(res => {
            if (res.success) {
                showToast(res.message || 'Task saved successfully!');
                getModalInstance().hide();
                setTimeout(() => location.reload(), 400);
            } else {
                showToast(res.message || 'Save failed', 'error');
            }
        })
        .catch(err => {
            showToast('Network error while saving task', 'error');
        });
}

// ── Export CSV for Table View ─────────────────────────────────────────────────
function exportTasksTableCSV() {
    const table = document.getElementById('exportSummaryTable');
    let csv = [];
    for (let row of table.rows) {
        let cols = [];
        for (let cell of row.cells) {
            cols.push('"' + cell.innerText.replace(/"/g, '""').trim() + '"');
        }
        csv.push(cols.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `workspace_tasks_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function filterByColumnStatus(status) {
    showToast(`Focusing column: ${status}`);
}

function switchGroupBy(criterion) {
    showToast(`Grouping by ${criterion} will be enabled in upcoming view update.`);
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    bindDragAndDrop();
    refreshColumnCounters();
});
</script>

<?php include('dashboard_footer.php'); ?>
