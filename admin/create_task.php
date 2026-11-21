<?php
include('dashboard_header.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id   = intval($_SESSION['user_id']);
$user_role = strtolower($role);
require_once __DIR__ . '/../core/task_delay_helper.php';

$can_create = canUserCreateTask($user_role);
$is_admin   = $can_create;

// Edit mode?
$edit_id = intval($_GET['edit'] ?? 0);
$is_edit = $edit_id > 0;
$task = null;
$selected_assignee_ids = [$user_id];

if (!$is_edit && !$can_create) {
    header('Location: tasks.php?error=unauthorized_create');
    exit;
}

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();

    if (!$task) { header('Location: tasks.php'); exit; }

    $is_member = ($task['assigned_to'] == $user_id || $task['created_by'] == $user_id || isUserAssignedToTask($conn, $edit_id, $user_id));
    if (!$is_admin && !$is_member) {
        header('Location: tasks.php'); exit;
    }

    $existing_assignees = getTaskAssignees($conn, $edit_id);
    $selected_assignee_ids = array_map(function($m) { return intval($m['user_id']); }, $existing_assignees);
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $priority     = $_POST['priority']    ?? 'Medium';
    $status       = $_POST['status']      ?? 'Pending';
    $due_date     = $_POST['due_date']    ?? null;
    $project_id   = intval($_POST['project_id'] ?? 0) ?: null;
    $task_type    = trim($_POST['task_type'] ?? 'Design');
    $assignee_ids = parseAssigneeIdsInput($_POST['assignees'] ?? null, intval($_POST['assigned_to'] ?? 0));
    $assigned_to  = !empty($assignee_ids) ? $assignee_ids[0] : null;
    $selected_assignee_ids = $assignee_ids;

    $errors = [];
    if (!$title)    $errors[] = 'Title is required.';
    if (!$due_date) $errors[] = 'Due date is required.';
    if (empty($assignee_ids)) $errors[] = 'Please assign at least one team member.';

    if (!$is_edit && !$can_create) {
        $errors[] = 'Restricted: Only Super Admins, Admins, and Project Managers can create tasks.';
    }

    if ($status === 'Completed' && function_exists('canUserCompleteTask') && !canUserCompleteTask($user_role)) {
        $status = 'In Review';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, priority=?, status=?, due_date=?, project_id=?, task_type=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("sssssisi", $title, $description, $priority, $status, $due_date, $project_id, $task_type, $edit_id);
            $stmt->execute();

            $sync_res = syncTaskAssignees($conn, $edit_id, $assignee_ids, $user_id);
            $msg = $sync_res['is_group']
                ? ('Grouped task updated (' . count($sync_res['all_ids']) . ' members)!')
                : 'Task updated successfully!';
            logActivity($conn, 'edit_task', "Updated task #$edit_id: '$title'", 'task', $edit_id);

            $added_notify = array_values(array_filter($sync_res['added_ids'], function($id) use ($user_id) { return $id != $user_id; }));
            if (!empty($added_notify)) {
                require_once __DIR__ . '/../core/notification_mailer.php';
                notifyTaskAssigned($conn, $edit_id, $added_notify);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, project_id, task_type, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->bind_param("sssssiisi", $title, $description, $priority, $status, $due_date, $assigned_to, $project_id, $task_type, $user_id);
            $stmt->execute();
            $new_task_id = $conn->insert_id;

            $sync_res = syncTaskAssignees($conn, $new_task_id, $assignee_ids, $user_id);
            $msg = $sync_res['is_group']
                ? ('Grouped task created with ' . count($sync_res['all_ids']) . ' members!')
                : 'Task created successfully!';
            logActivity($conn, 'create_task', "Created task: '$title'" . ($sync_res['is_group'] ? ' (Grouped: ' . count($sync_res['all_ids']) . ' members)' : ''), 'task', $new_task_id);

            $notify_ids = array_values(array_filter($sync_res['all_ids'], function($id) use ($user_id) { return $id != $user_id; }));
            if (!empty($notify_ids)) {
                require_once __DIR__ . '/../core/notification_mailer.php';
                notifyTaskAssigned($conn, $new_task_id, $notify_ids);
            }
        }
        echo "<script>window.location='tasks.php';</script>";
        exit;
    }
}

// ── Users & Projects for assignment ───────────────────────────────────────────
$users_result = $conn->query("SELECT user_id AS id, name, role, department FROM users WHERE (status = 'active' OR status IS NULL) AND role != 'student' AND role NOT IN ('client', 'client_sub') ORDER BY name");
$users = [];
while ($u = $users_result->fetch_assoc()) $users[] = $u;

$projects = [];
$p_res = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
if ($p_res) while ($p = $p_res->fetch_assoc()) $projects[] = $p;

$page_title = $is_edit ? 'Edit Task' : 'Create New Task';
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1><i class="fas <?= $is_edit ? 'fa-edit' : 'fa-plus-circle' ?>" style="font-size:22px; margin-right:6px;"></i> <?= $page_title ?></h1>
        <p><?= $is_edit ? 'Update task details or manage assigned team members' : 'Assign to one member or select multiple members to create a Grouped Task' ?></p>
    </div>
    <a href="tasks.php" class="btn btn-secondary">← Back to Tasks</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-box error">
    <?php foreach($errors as $e): ?><p><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="form-card">
<form method="POST" id="taskForm">

    <div class="form-grid">
        <div class="form-group" style="grid-column: 1 / -1;">
            <label>Task Title <span class="required">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($task['title'] ?? ($_POST['title'] ?? '')) ?>"
                   placeholder="Enter a clear, descriptive title" required>
        </div>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="4" placeholder="Describe what needs to be done..."><?= htmlspecialchars($task['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Category / Tag</label>
            <input type="text" name="task_type" list="ctTaskTypes" value="<?= htmlspecialchars($task['task_type'] ?? ($_POST['task_type'] ?? 'Design')) ?>" placeholder="Design, Development, Marketing...">
            <datalist id="ctTaskTypes">
                <option value="Design"><option value="Development"><option value="Marketing"><option value="Branding"><option value="Operations"><option value="Media">
            </datalist>
        </div>

        <div class="form-group">
            <label>Project <span style="color:#94a3b8;font-weight:500;">(optional)</span></label>
            <select name="project_id">
                <option value="">General / No Project</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= intval($task['project_id'] ?? ($_POST['project_id'] ?? 0)) === intval($p['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['project_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Priority <span class="required">*</span></label>
            <select name="priority" required>
                <?php foreach (['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($task['priority'] ?? ($_POST['priority'] ?? 'Medium')) === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <?php foreach (['Pending' => 'To-do (Pending)', 'In Progress' => 'In Progress', 'In Review' => 'In Review', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($task['status'] ?? ($_POST['status'] ?? 'Pending')) === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Due Date <span class="required">*</span></label>
            <input type="date" name="due_date"
                   value="<?= htmlspecialchars($task['due_date'] ?? ($_POST['due_date'] ?? '')) ?>"
                   required>
        </div>
        <div class="form-group" style="justify-content:flex-end;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; font-size:12.5px; color:#475569;">
                <strong style="color:#024442;"><i class="fas fa-users"></i> Grouped Task Tip:</strong><br>
                Select <strong>2 or more members</strong> below to automatically turn this into a collaborative Grouped Task. The first selected member acts as the Task Lead.
            </div>
        </div>
    </div>

    <!-- Multi-Member Picker (Grouped Tasks) -->
    <div class="form-group" style="margin-top:4px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <label style="margin:0;">
                Assigned Team Members <span class="required">*</span>
                <span id="ctGroupBadge" class="ct-group-badge" style="display:none;">
                    <i class="fas fa-users"></i> Grouped Task (<span id="ctGroupCount">0</span>)
                </span>
            </label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="text" id="ctMemberSearch" oninput="filterCtMembers(this.value)" placeholder="Filter members..." style="padding:5px 10px; font-size:12.5px; border:1px solid #d1d5db; border-radius:7px; width:180px;">
                <button type="button" onclick="clearCtMembers()" style="background:none; border:none; font-size:12px; color:#dc2626; font-weight:600; cursor:pointer;">Clear</button>
            </div>
        </div>

        <div id="ctSelectedPills" class="ct-selected-pills"></div>

        <div class="ct-member-grid" id="ctMemberGrid">
            <?php foreach ($users as $u):
                $checked = in_array(intval($u['id']), $selected_assignee_ids, true);
                $meta = getTaskMemberAvatarMeta($u['name']);
            ?>
            <label class="ct-member-item <?= $checked ? 'selected' : '' ?>" data-name="<?= htmlspecialchars(strtolower($u['name'] . ' ' . ($u['role'] ?? '') . ' ' . ($u['department'] ?? ''))) ?>">
                <input type="checkbox" name="assignees[]" value="<?= intval($u['id']) ?>" <?= $checked ? 'checked' : '' ?> onchange="syncCtMemberUI()">
                <span class="ct-avatar" style="background:<?= $meta['bg'] ?>; color:<?= $meta['color'] ?>;"><?= $meta['initials'] ?></span>
                <span class="ct-member-info">
                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                    <small><?= htmlspecialchars(ucfirst($u['role'] ?? 'Staff')) ?></small>
                </span>
                <i class="fas fa-check ct-check-ico"></i>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions">
        <a href="tasks.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" id="ctSubmitBtn">
            <i class="fas fa-check" style="color:#fff !important;"></i>
            <span><?= $is_edit ? 'Update Task' : 'Create Task' ?></span>
        </button>
    </div>

</form>
</div>

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px;}
.page-header h1{margin:0 0 4px;font-size:22px;font-weight:800;color:#0f172a;}
.page-header p{margin:0;color:#64748b;font-size:13.5px;}
.form-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px 32px;max-width:860px;box-shadow:0 2px 10px rgba(15,23,42,0.04);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}
.form-group label{font-size:13px;font-weight:700;color:#334155;}
.form-group input,.form-group select,.form-group textarea{
    padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;
    font-size:13.5px;outline:none;transition:border-color .2s;font-family:inherit;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#024442;box-shadow:0 0 0 3px rgba(2,68,66,.08);}
.form-group textarea{resize:vertical;}
.required{color:#ef4444;}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:8px;padding-top:16px;border-top:1px solid #f1f5f9;}
.btn{padding:9px 20px;border-radius:9px;font-size:13.5px;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-primary{background:#024442;color:#fff;}
.btn-primary:hover{background:#035e5b;}
.btn-secondary{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
.btn-secondary:hover{background:#e5e7eb;}
.alert-box{border-radius:10px;padding:12px 16px;margin-bottom:16px;}
.alert-box.error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-box p{margin:4px 0;font-size:13.5px;}

.ct-group-badge{display:inline-flex;align-items:center;gap:5px;background:#e6f4f2;color:#024442;border:1px solid #b5ded9;font-size:11.5px;font-weight:800;padding:2px 9px;border-radius:12px;margin-left:8px;}
.ct-selected-pills{display:flex;flex-wrap:wrap;gap:6px;min-height:28px;margin:4px 0 6px;}
.ct-pill{display:inline-flex;align-items:center;gap:6px;background:#024442;color:#fff;padding:4px 10px;border-radius:16px;font-size:12px;font-weight:700;}
.ct-pill .ct-lead{background:rgba(255,255,255,0.22);font-size:10px;padding:1px 5px;border-radius:6px;text-transform:uppercase;}
.ct-pill button{background:none;border:none;color:rgba(255,255,255,0.85);cursor:pointer;font-size:14px;line-height:1;padding:0;}
.ct-member-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:8px;max-height:240px;overflow-y:auto;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;}
.ct-member-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;background:#fff;border:1.5px solid #e2e8f0;cursor:pointer;transition:all .14s ease;margin:0 !important;}
.ct-member-item input{display:none;}
.ct-member-item:hover{border-color:#94a3b8;}
.ct-member-item.selected{border-color:#024442;background:#f2faf9;}
.ct-avatar{width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;}
.ct-member-info{display:flex;flex-direction:column;min-width:0;flex:1;}
.ct-member-info strong{font-size:12.5px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ct-member-info small{font-size:11px;color:#64748b;}
.ct-check-ico{font-size:11px;color:#024442 !important;opacity:0;transition:opacity .14s;}
.ct-member-item.selected .ct-check-ico{opacity:1;}
@media(max-width:640px){.form-grid{grid-template-columns:1fr;}.form-card{padding:20px;}}
</style>

<script>
function syncCtMemberUI() {
    const pillsWrap = document.getElementById('ctSelectedPills');
    const badge     = document.getElementById('ctGroupBadge');
    const cntEl     = document.getElementById('ctGroupCount');
    const items     = document.querySelectorAll('#ctMemberGrid .ct-member-item');
    const selected  = [];

    items.forEach(item => {
        const cb = item.querySelector('input[type="checkbox"]');
        item.classList.toggle('selected', cb.checked);
        if (cb.checked) {
            selected.push({ id: cb.value, name: item.querySelector('strong').textContent });
        }
    });

    if (selected.length > 1) {
        badge.style.display = 'inline-flex';
        cntEl.textContent = selected.length;
    } else {
        badge.style.display = 'none';
    }

    if (selected.length === 0) {
        pillsWrap.innerHTML = '<span style="font-size:12px;color:#94a3b8;">No members selected yet — click team members below</span>';
    } else {
        pillsWrap.innerHTML = selected.map((m, idx) => `
            <span class="ct-pill">
                <span>${m.name}</span>
                ${idx === 0 && selected.length > 1 ? '<span class="ct-lead">Lead</span>' : ''}
                <button type="button" onclick="removeCtMember('${m.id}')" title="Remove">&times;</button>
            </span>
        `).join('');
    }
}

function removeCtMember(id) {
    const cb = document.querySelector(`#ctMemberGrid input[value="${id}"]`);
    if (cb) { cb.checked = false; syncCtMemberUI(); }
}

function clearCtMembers() {
    document.querySelectorAll('#ctMemberGrid input[type="checkbox"]').forEach(cb => cb.checked = false);
    syncCtMemberUI();
}

function filterCtMembers(q) {
    const term = q.trim().toLowerCase();
    document.querySelectorAll('#ctMemberGrid .ct-member-item').forEach(item => {
        item.style.display = (!term || item.dataset.name.includes(term)) ? 'flex' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', syncCtMemberUI);
</script>

</div>
<?php include('dashboard_footer.php'); ?>
