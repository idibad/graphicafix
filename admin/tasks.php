<?php
// ── AJAX handler — must be BEFORE any HTML output ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_start(); // buffer anything that leaks (e.g. from includes)
    include('dashboard_header.php'); // needed for $conn and session
    while (ob_get_level() > 0) ob_end_clean(); // discard ALL nested buffers

    $user_id  = intval($_SESSION['user_id'] ?? 0);
    $is_admin = ($role === 'admin' || $role === 'pm');
    $action   = $_POST['ajax_action'];
    $task_id  = intval($_POST['task_id'] ?? 0);

    header('Content-Type: application/json');

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }

    // Verify ownership
    $check = $conn->prepare("SELECT id, created_by, assigned_to FROM tasks WHERE id=?");
    $check->bind_param("i", $task_id);
    $check->execute();
    $t = $check->get_result()->fetch_assoc();

    if (!$t || (!$is_admin && $t['assigned_to'] != $user_id && $t['created_by'] != $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $allowed = ['Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled'];
        if (!in_array($new_status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
        }
        
        if ($new_status === 'Completed' && !$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Only admins can mark a task as completed. Please mark it "In Review" instead.']); exit;
        }
        $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("si", $new_status, $task_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Status updated']);

    } elseif ($action === 'delete_task') {
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Task deleted']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

include('dashboard_header.php');

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id   = intval($_SESSION['user_id']);
$is_admin  = ($role === 'admin' || $role === 'pm');


// ── Build WHERE clause ────────────────────────────────────────────────────────
$where = $is_admin ? "1=1" : "(assigned_to = $user_id OR created_by = $user_id)";

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status='In Progress'    THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status='Pending Review' THEN 1 ELSE 0 END) AS pending_review,
        SUM(CASE WHEN status='Completed'      THEN 1 ELSE 0 END) AS completed
    FROM tasks WHERE $where
")->fetch_assoc();

// ── Tasks grouped by status ───────────────────────────────────────────────────
$tasks_result = $conn->query("
    SELECT t.*, u.name AS assignee_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    WHERE $where
    ORDER BY t.due_date ASC
");

$tasks_by_status = ['Pending' => [], 'In Progress' => [], 'In Review' => [], 'Completed' => [], 'Cancelled' => []];
while ($task = $tasks_result->fetch_assoc()) {
    $s = $task['status'];
    if (!isset($tasks_by_status[$s])) $s = 'Pending';
    $tasks_by_status[$s][] = $task;
}

// ── Calendar data ─────────────────────────────────────────────────────────────
$month = date('m'); $year = date('Y');
$task_dates_result = $conn->query("
    SELECT due_date FROM tasks
    WHERE MONTH(due_date)=$month AND YEAR(due_date)=$year AND $where
");
$task_dates = [];
while ($row = $task_dates_result->fetch_assoc()) {
    $task_dates[] = date('j', strtotime($row['due_date']));
}

// ── Recent Activity ───────────────────────────────────────────────────────────
$activity_result = $conn->query("
    SELECT t.title, t.status, t.updated_at, t.created_at, u.name AS assignee_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    WHERE $where
    ORDER BY GREATEST(UNIX_TIMESTAMP(t.updated_at), UNIX_TIMESTAMP(t.created_at)) DESC
    LIMIT 10
");

// ── Users list (for admin assign display) ─────────────────────────────────────
$users_result = $conn->query("SELECT user_id AS id, name FROM users ORDER BY name");
$users_map = [];
while ($u = $users_result->fetch_assoc()) $users_map[$u['id']] = $u['name'];

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . "s ago";
    elseif ($diff < 3600) return round($diff/60) . "m ago";
    elseif ($diff < 86400) return round($diff/3600) . "h ago";
    elseif ($diff < 604800) return round($diff/86400) . "d ago";
    else return date("M j, Y", strtotime($datetime));
}

$column_config = [
    'Pending'     => ['icon' => '<i class="fas fa-clipboard"></i>', 'color' => '#6b7280'],
    'In Progress' => ['icon' => '<i class="fas fa-bolt"></i>', 'color' => '#f59e0b'],
    'In Review'   => ['icon' => '<i class="fas fa-search"></i>', 'color' => '#8b5cf6'],
    'Completed'   => ['icon' => '<i class="fas fa-check-circle"></i>', 'color' => '#10b981'],
    'Cancelled'   => ['icon' => '<i class="fas fa-ban"></i>', 'color' => '#ef4444'],
];
?>

<div class="height-100">
<div class="tasks-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title-section">
            <h1>Task Management</h1>
            <p><?= $is_admin ? 'Viewing all tasks across the team' : 'Viewing your assigned tasks' ?></p>
        </div>
        <div class="header-actions">
            <?php if ($is_admin): ?>
            <a href="manage_users.php" class="btn-secondary-custom"><i class="fas fa-users"></i> Team</a>
             <?php endif; ?>
            <a href="create_task.php" class="btn-primary-custom">+ New Task</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $stats['total_tasks'] ?></div><div class="stat-label">Total Tasks</div></div>
        <div class="stat-card"><div class="stat-number"><?= $stats['in_progress'] ?></div><div class="stat-label">In Progress</div></div>
        <div class="stat-card"><div class="stat-number"><?= $stats['pending_review'] ?></div><div class="stat-label">Pending Review</div></div>
        <div class="stat-card"><div class="stat-number"><?= $stats['completed'] ?></div><div class="stat-label">Completed</div></div>
    </div>

    <!-- Main Content -->
    <div class="tasks-main">

        <!-- Task Board -->
        <div class="task-board">
            <?php foreach ($tasks_by_status as $status => $tasks): ?>
            <div class="task-column" data-status="<?= htmlspecialchars($status) ?>">
                <div class="column-header" style="border-top: 3px solid <?= $column_config[$status]['color'] ?>;">
                    <span class="column-title"><?= $column_config[$status]['icon'] ?> <?= $status ?></span>
                    <span class="column-count"><?= count($tasks) ?></span>
                </div>
                <div class="task-list" data-status="<?= htmlspecialchars($status) ?>">
                    <?php foreach ($tasks as $task): ?>
                    
                    <div class="task-card" draggable="true" data-id="<?= $task['id'] ?>">
                        <div class="task-card-header">
                            <span class="task-priority priority-<?= strtolower($task['priority'] ?? 'low') ?>"><?= ucfirst($task['priority'] ?? 'Low') ?></span>
                               
                            <div class="task-actions-menu">
                                <button class="task-menu-btn" onclick="toggleMenu(<?= $task['id'] ?>)">⋮</button>
                                <div class="task-dropdown" id="menu-<?= $task['id'] ?>">
                                    <?php if($is_admin): ?>
                                    <a href="create_task.php?edit=<?= $task['id'] ?>"><i class="fas fa-pencil-alt"></i>️ Edit</a>
                                    <?php endif; ?>
                                    <a href="#" onclick="updateStatus(<?= $task['id'] ?>, 'In Progress'); return false;"><i class="fas fa-bolt"></i> Mark In Progress</a>
                                    <a href="#" onclick="updateStatus(<?= $task['id'] ?>, 'In Review'); return false;"><i class="fas fa-search"></i> Mark In Review</a>
                                    
                                    <?php if($is_admin): ?>
                                    <a href="#" onclick="updateStatus(<?= $task['id'] ?>, 'Completed'); return false;"><i class="fas fa-check-circle"></i> Mark Completed</a>
                                    <?php endif; ?>
                                    
                                    <a href="#" onclick="updateStatus(<?= $task['id'] ?>, 'Cancelled'); return false;" style="color:#f59e0b;"><i class="fas fa-ban"></i> Cancel Task</a>
                                    <?php if($is_admin): ?>
                                    <a href="#" onclick="deleteTask(<?= $task['id'] ?>); return false;" style="color:#ef4444;"><i class="fas fa-trash"></i>️ Delete</a>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                        <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                        <?php if (!empty($task['description'])): ?>
                        <div class="task-description"><?= htmlspecialchars(substr($task['description'], 0, 80)) . (strlen($task['description']) > 80 ? '…' : '') ?></div>
                        <?php endif; ?>
                        <div class="task-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?= date('M d', strtotime($task['due_date'])) ?></span>
                            <?php if ($task['assignee_name']): ?>
                            <div class="assignee-avatar" title="<?= htmlspecialchars($task['assignee_name']) ?>">
                                <?= strtoupper(substr($task['assignee_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <a href="task_details.php?id=<?php echo $task['id']; ?>" class="color-dark">
                                View Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sidebar -->
        <div class="tasks-sidebar">

            <!-- Calendar -->
            <div class="sidebar-card">
                <div class="sidebar-header"><i class="fas fa-calendar-alt"></i> Calendar</div>
                <div class="mini-calendar">
                    <div class="calendar-month"><?= date('F', mktime(0,0,0,$month,1)) ?> <?= $year ?></div>
                    <div class="calendar-grid">
                        <?php foreach (['S','M','T','W','T','F','S'] as $d): ?>
                        <div class="calendar-day"><?= $d ?></div>
                        <?php endforeach; ?>
                        <?php
                        $first = mktime(0,0,0,$month,1,$year);
                        $total = date('t',$first);
                        $start = date('w',$first);
                        for($i=0;$i<$start;$i++) echo "<div class='calendar-day empty'></div>";
                        for($day=1;$day<=$total;$day++){
                            $cls = 'calendar-day';
                            if(in_array($day,$task_dates)) $cls .= ' has-tasks';
                            if($day==date('j')&&$month==date('m')&&$year==date('Y')) $cls .= ' today';
                            echo "<div class='$cls'>$day</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="sidebar-card">
                <div class="sidebar-header"><i class="fas fa-chart-bar"></i> Recent Activity</div>
                <?php while($activity = $activity_result->fetch_assoc()): ?>
                <?php
                    $icon  = $activity['status'] === 'Completed' ? '<i class="fas fa-check-circle"></i>' : ($activity['status'] === 'Cancelled' ? '<i class="fas fa-ban"></i>' : '<i class="fas fa-edit"></i>');
                    $label = $activity['status'] === 'Completed' ? 'Completed' : ($activity['status'] === 'Cancelled' ? 'Cancelled' : 'Updated');
                    $time  = timeAgo($activity['updated_at'] > $activity['created_at'] ? $activity['updated_at'] : $activity['created_at']);
                ?>
                <div class="activity-item">
                    <div class="activity-icon"><?= $icon ?></div>
                    <div class="activity-content">
                        <div class="activity-title"><?= $label ?>: <?= htmlspecialchars($activity['title']) ?></div>
                        <div class="activity-time"><?= $time ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px;}
.page-header h1{margin:0 0 4px;}
.page-header p{margin:0;color:#888;font-size:14px;}
.task-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.task-priority {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    margin: 0 30px;
    display: inline-block;
}
.task-card .priority-high   { background: #fef2f2; color: #ef4444; }
.task-card .priority-medium { background: #fffbeb; color: #f59e0b; }
.task-card .priority-low    { background: #f0fdf4; color: #10b981; }
.task-actions-menu{position:relative;}
.task-menu-btn{background:none;border:none;cursor:pointer;font-size:18px;color:#999;padding:2px 6px;border-radius:4px;line-height:1;}
.task-menu-btn:hover{background:#f3f4f6;color:#374151;}
.task-dropdown{display:none;position:absolute;right:0;top:100%;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);min-width:170px;z-index:100;overflow:hidden;}
.task-dropdown a{display:block;padding:9px 14px;font-size:13px;color:#374151;text-decoration:none;transition:background .15s;}
.task-dropdown a:hover{background:#f9fafb;}
.task-dropdown.open{display:block;}
.assignee-avatar{width:26px;height:26px;border-radius:50%;background:#024442;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;}
.column-header{padding:12px 14px 10px;border-radius:8px 8px 0 0;}
.toast{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;}
.toast.success{border-left:4px solid #10b981;}
.toast.error{border-left:4px solid #ef4444;}
.task-card.drag-placeholder{opacity:.4;border:2px dashed #ccc;background:#f9fafb;}
</style>

<script>
// ── Dropdown menus ────────────────────────────────────────────────────────────
function toggleMenu(id) {
    document.querySelectorAll('.task-dropdown').forEach(d => {
        if (d.id !== 'menu-' + id) d.classList.remove('open');
    });
    document.getElementById('menu-' + id).classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('.task-actions-menu')) {
        document.querySelectorAll('.task-dropdown').forEach(d => d.classList.remove('open'));
    }
});

// ── AJAX helper ───────────────────────────────────────────────────────────────
function postAction(data) {
    return fetch('task_action.php', {
        method: 'POST',
        body: new URLSearchParams(data)
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
    })
    .then(text => {
        const start = text.lastIndexOf('{"success"');
        if (start === -1) {
            console.error('No JSON found in response:', text);
            throw new Error('No JSON in response');
        }
        const jsonStr = text.slice(start);
        console.log('BEFORE:', JSON.stringify(text.slice(Math.max(0, start-30), start)));
        console.log('JSON:', JSON.stringify(jsonStr.slice(0, 50)));
        return JSON.parse(jsonStr);
    });
}

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// ── Status update ─────────────────────────────────────────────────────────────
function updateStatus(taskId, newStatus) {
    postAction({ ajax_action: 'update_status', task_id: taskId, status: newStatus })
        .then(data => {
            if (data.success) { location.reload(); }
            else { showToast(data.message || 'Update failed', 'error'); }
        }).catch(err => {
            console.error('updateStatus error:', err);
            showToast('Server error: ' + err.message, 'error');
        });
}

// ── Delete task ───────────────────────────────────────────────────────────────
function deleteTask(taskId) {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    postAction({ ajax_action: 'delete_task', task_id: taskId })
        .then(data => {
            if (data.success) { location.reload(); }
            else { showToast(data.message || 'Delete failed', 'error'); }
        }).catch(err => {
            console.error('deleteTask error:', err);
            showToast('Server error: ' + err.message, 'error');
        });
}

// ── Drag & drop ───────────────────────────────────────────────────────────────
let draggedCard = null;

document.querySelectorAll('.task-card').forEach(card => {
    card.addEventListener('dragstart', function() {
        draggedCard = this;
        setTimeout(() => this.classList.add('drag-placeholder'), 0);
    });
    card.addEventListener('dragend', function() {
        this.classList.remove('drag-placeholder');
        draggedCard = null;
    });
});

document.querySelectorAll('.task-list').forEach(list => {
    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        const after = getDragAfterElement(this, e.clientY);
        if (after == null) this.appendChild(draggedCard);
        else this.insertBefore(draggedCard, after);
    });

    list.addEventListener('drop', function() {
        const newStatus = this.dataset.status;
        const taskId    = draggedCard?.dataset.id;
        if (!taskId || !newStatus) return;

        <?php if(!$is_admin): ?>
        if (newStatus === 'Completed') {
            showToast('Only admins can mark a task as completed. Please mark it "In Review" instead.', 'error');
            setTimeout(() => location.reload(), 1500); // Reload to reset the card's position
            return;
        }
        <?php endif; ?>

        postAction({ ajax_action: 'update_status', task_id: taskId, status: newStatus })
            .then(data => {
                if (data.success) {
                    showToast('Moved to: ' + newStatus);
                } else {
                    showToast(data.message || 'Update failed', 'error');
                    setTimeout(() => location.reload(), 1500); // Reload to reset UI on failure
                }
            });

        document.querySelectorAll('.task-column').forEach(col => {
            col.querySelector('.column-count').textContent =
                col.querySelector('.task-list').querySelectorAll('.task-card').length;
        });
    });
});

function getDragAfterElement(container, y) {
    const els = [...container.querySelectorAll('.task-card:not(.drag-placeholder)')];
    return els.reduce((closest, child) => {
        const box    = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
</script>

</div>
<?php include('dashboard_footer.php'); ?>