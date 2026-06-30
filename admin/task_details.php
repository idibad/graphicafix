<?php
// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include('dashboard_header.php');

$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$is_admin = (isset($role) && $role === 'admin');

if (!$task_id || !$user_id) {
    echo "<script>window.location='manage_tasks.php';</script>";
    exit;
}

// ── Handle Status Update from Details Page ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    $allowed = array('Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled');
    
    if (in_array($new_status, $allowed)) {
        $upd = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
        $upd->bind_param("si", $new_status, $task_id);
        $upd->execute();
        
        // Refresh to show updated status
        header("Location: task_details.php?id=" . $task_id . "&updated=1");
        exit;
    }
}

// ── Fetch Task and User Data ──────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT t.*, 
           u_assign.name AS assignee_name, u_assign.email AS assignee_email,
           u_create.name AS creator_name, u_create.email AS creator_email
    FROM tasks t
    LEFT JOIN users u_assign ON t.assigned_to = u_assign.user_id
    LEFT JOIN users u_create ON t.created_by = u_create.user_id
    WHERE t.id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
}

// ── Security Check ────────────────────────────────────────────────────────────
if (!$task) {
    echo "<div class='height-100'><div class='main-card' style='text-align:center; padding: 50px;'><h2 style='color:#ef4444;'>Task Not Found</h2><p>This task does not exist or has been deleted.</p><a href='manage_tasks.php' class='btn-primary-custom'>Go Back</a></div></div>";
    include('dashboard_footer.php');
    exit;
}

if (!$is_admin && $task['assigned_to'] != $user_id && $task['created_by'] != $user_id) {
    echo "<div class='height-100'><div class='main-card' style='text-align:center; padding: 50px;'><h2 style='color:#ef4444;'>Unauthorized Access</h2><p>You do not have permission to view this task.</p><a href='manage_tasks.php' class='btn-primary-custom'>Go Back</a></div></div>";
    include('dashboard_footer.php');
    exit;
}

// ── Helper Functions ──────────────────────────────────────────────────────────
function getStatusBadge($s) {
    $status = $s ? $s : 'Pending';
    switch($status) {
        case 'Pending':        return array('label'=>'Pending',        'bg'=>'#f3f4f6','color'=>'#4b5563', 'icon'=>'<i class="fas fa-clipboard"></i>');
        case 'In Progress':    return array('label'=>'In Progress',    'bg'=>'#fffbeb','color'=>'#d97706', 'icon'=>'<i class="fas fa-bolt"></i>');
        case 'In Review':      return array('label'=>'In Review',      'bg'=>'#f3e8ff','color'=>'#7e22ce', 'icon'=>'<i class="fas fa-search"></i>');
        case 'Completed':      return array('label'=>'Completed',      'bg'=>'#dcfce7','color'=>'#16a34a', 'icon'=>'<i class="fas fa-check-circle"></i>');
        case 'Cancelled':      return array('label'=>'Cancelled',      'bg'=>'#fee2e2','color'=>'#dc2626', 'icon'=>'<i class="fas fa-ban"></i>');
        default:               return array('label'=>$status,          'bg'=>'#f0f0f0','color'=>'#555',    'icon'=>'<i class="fas fa-thumbtack"></i>');
    }
}

function getPriorityBadge($p) {
    $priority = $p ? strtolower($p) : 'low';
    switch($priority) {
        case 'high':   return array('label'=>'High Priority',   'bg'=>'#fef2f2','color'=>'#ef4444', 'icon'=>'<i class="fas fa-fire"></i>');
        case 'medium': return array('label'=>'Medium Priority', 'bg'=>'#fffbeb','color'=>'#f59e0b', 'icon'=>'<i class="fas fa-star"></i>');
        case 'low':    return array('label'=>'Low Priority',    'bg'=>'#f0fdf4','color'=>'#10b981', 'icon'=>'<i class="fas fa-chevron-down"></i>');
        default:       return array('label'=>'Normal',          'bg'=>'#f3f4f6','color'=>'#6b7280', 'icon'=>'<i class="fas fa-thumbtack"></i>');
    }
}

function formatTimeAgo($datetime) {
    if (!$datetime) return 'Never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . " seconds ago";
    elseif ($diff < 3600) return round($diff/60) . " minutes ago";
    elseif ($diff < 86400) return round($diff/3600) . " hours ago";
    elseif ($diff < 604800) return round($diff/86400) . " days ago";
    else return date("F j, Y", strtotime($datetime));
}

$statusBadge = getStatusBadge($task['status']);
$priorityBadge = getPriorityBadge($task['priority']);
?>

<style>
/* ── Task Details Specific Styling ── */
.td-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;
}
.td-title { font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 10px 0; line-height: 1.3; }
.td-id { font-size: 14px; color: #6b7280; font-weight: 600; letter-spacing: 1px; }

.td-grid {
    display: grid; grid-template-columns: 2.5fr 1fr; gap: 30px;
}

/* Left Content */
.td-section-title {
    font-size: 15px; font-weight: 700; color: #374151;
    margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;
}
.td-description {
    background: #f9fafb; padding: 24px; border-radius: 12px; border: 1px solid #f3f4f6;
    font-size: 15px; color: #4b5563; line-height: 1.8; white-space: pre-line;
    min-height: 200px;
}

/* Right Sidebar (Meta) */
.td-meta-card {
    background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px;
    padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.td-meta-row {
    margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f3f4f6;
}
.td-meta-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.td-meta-label { font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
.td-meta-value { font-size: 14px; color: #111827; font-weight: 500; display: flex; align-items: center; gap: 8px; }

.td-avatar {
    width: 32px; height: 32px; border-radius: 50%; background: #024442; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;
}

.td-badge {
    padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 6px;
}

/* Action Buttons */
.td-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
.btn-outline-custom {
    display: block; width: 100%; text-align: center; padding: 10px 16px;
    border: 1px solid #d1d5db; background: #fff; color: #374151; font-weight: 600;
    border-radius: 8px; cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.btn-outline-custom:hover { background: #f9fafb; border-color: #9ca3af; }

.status-form { display: flex; gap: 8px; flex-direction: column; }
.status-select {
    width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 14px; font-family: inherit; outline: none; background: #f9fafb;
}
.status-select:focus { border-color: #024442; }

@media (max-width: 991px) {
    .td-grid { grid-template-columns: 1fr; }
    .td-header { flex-direction: column; gap: 15px; }
}
</style>

<div class="height-100">

    <div class="main-card" style="margin-bottom: 30px; padding: 30px;">
        
        <div class="td-header">
            <div>
                <span class="td-id">TASK-<?php echo str_pad($task['id'], 4, '0', STR_PAD_LEFT); ?></span>
                <h1 class="td-title"><?php echo htmlspecialchars($task['title']); ?></h1>
                <div style="display:flex; gap:10px; margin-top: 10px;">
                    <span class="td-badge" style="background: <?php echo $statusBadge['bg']; ?>; color: <?php echo $statusBadge['color']; ?>;">
                        <?php echo $statusBadge['icon']; ?> <?php echo $statusBadge['label']; ?>
                    </span>
                    <span class="td-badge" style="background: <?php echo $priorityBadge['bg']; ?>; color: <?php echo $priorityBadge['color']; ?>;">
                        <?php echo $priorityBadge['icon']; ?> <?php echo $priorityBadge['label']; ?>
                    </span>
                </div>
            </div>
            <div>
                <a href="javascript:history.back()" class="btn-outline-custom" style="display:inline-block; width:auto;">← Back to Board</a>
                <?php if ($is_admin): ?>
                <a href="create_task.php?edit=<?php echo $task['id']; ?>" class="btn-primary-custom" style="display:inline-block; margin-left:10px;"><i class="fas fa-pencil-alt"></i>️ Edit Task</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="td-grid">
            
            <div>
                <div class="td-section-title">Task Description</div>
                <div class="td-description">
                    <?php 
                    if (!empty($task['description'])) {
                        echo htmlspecialchars($task['description']);
                    } else {
                        echo "<span style='color:#9ca3af; font-style:italic;'>No description provided for this task.</span>";
                    }
                    ?>
                </div>
            </div>

            <div>
                <div class="td-meta-card">
                    
                    <div class="td-meta-row">
                        <div class="td-meta-label">Assigned To</div>
                        <div class="td-meta-value">
                            <?php if ($task['assignee_name']): ?>
                                <div class="td-avatar"><?php echo strtoupper(substr($task['assignee_name'], 0, 1)); ?></div>
                                <div>
                                    <div><?php echo htmlspecialchars($task['assignee_name']); ?></div>
                                    <div style="font-size: 12px; color: #6b7280; font-weight: 400;"><?php echo htmlspecialchars($task['assignee_email']); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="td-avatar" style="background:#e5e7eb; color:#9ca3af;">?</div>
                                <span style="color:#9ca3af; font-style:italic;">Unassigned</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="td-meta-row">
                        <div class="td-meta-label">Reported By</div>
                        <div class="td-meta-value">
                            <?php if ($task['creator_name']): ?>
                                <div class="td-avatar" style="background:#f3f4f6; color:#4b5563;"><?php echo strtoupper(substr($task['creator_name'], 0, 1)); ?></div>
                                <div><?php echo htmlspecialchars($task['creator_name']); ?></div>
                            <?php else: ?>
                                System
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="td-meta-row">
                        <div class="td-meta-label">Dates</div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:#6b7280;">Due Date:</span>
                                <strong style="<?php echo (strtotime($task['due_date']) < time() && $task['status'] !== 'Completed') ? 'color:#ef4444;' : ''; ?>">
                                    <?php echo $task['due_date'] ? date('F j, Y', strtotime($task['due_date'])) : 'No Date'; ?>
                                </strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:#6b7280;">Created:</span>
                                <strong><?php echo date('M j, Y', strtotime($task['created_at'])); ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:#6b7280;">Updated:</span>
                                <strong><?php echo formatTimeAgo($task['updated_at']); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="td-meta-row" style="border:none; margin:0; padding:0;">
                        <div class="td-meta-label">Update Status</div>
                        <form method="POST" class="status-form">
                            <input type="hidden" name="update_status" value="1">
                            <select name="new_status" class="status-select">
                                <option value="Pending" <?php echo $task['status'] == 'Pending' ? 'selected' : ''; ?>> Pending</option>
                                <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>> In Progress</option>
                                <option value="In Review" <?php echo $task['status'] == 'In Review' ? 'selected' : ''; ?>> In Review</option>
                                <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>> Completed</option>
                                <option value="Cancelled" <?php echo $task['status'] == 'Cancelled' ? 'selected' : ''; ?>> Cancelled</option>
                            </select>
                            <button type="submit" class="btn-primary-custom" style="width:100%; text-align:center;">Update</button>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:200px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['updated'])): ?> 
        showToast('Task status updated successfully!'); 
        // Remove the query param from URL so it doesn't pop up again on refresh
        window.history.replaceState({}, document.title, "task_details.php?id=<?php echo $task['id']; ?>");
    <?php endif; ?>
});
</script>

<?php include('dashboard_footer.php'); ?>