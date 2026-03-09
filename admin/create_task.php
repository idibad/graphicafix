<?php
include('dashboard_header.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id   = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'User';
$is_admin  = ($user_role === 'Admin');

// Edit mode?
$edit_id = intval($_GET['edit'] ?? 0);
$is_edit = $edit_id > 0;
$task = null;

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();

    if (!$task) { header('Location: manage_tasks.php'); exit; }

    // Only admin or task owner can edit
    if (!$is_admin && $task['assigned_to'] != $user_id && $task['created_by'] != $user_id) {
        header('Location: manage_tasks.php'); exit;
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority']    ?? 'Medium';
    $status      = $_POST['status']      ?? 'Pending';
    $due_date    = $_POST['due_date']    ?? null;
    $assigned_to = intval($_POST['assigned_to'] ?? $user_id) ?: null;

    $errors = [];
    if (!$title)    $errors[] = 'Title is required.';
    if (!$due_date) $errors[] = 'Due date is required.';

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, priority=?, status=?, due_date=?, assigned_to=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("sssssii", $title, $description, $priority, $status, $due_date, $assigned_to, $edit_id);
            $stmt->execute();
            $msg = 'Task updated successfully!';
            $redirect_id = $edit_id;
        } else {
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->bind_param("sssssii", $title, $description, $priority, $status, $due_date, $assigned_to, $user_id);
            $stmt->execute();
            $msg = 'Task created successfully!';
        }
        echo "<script>alert('$msg'); window.location='manage_tasks.php';</script>";
        exit;
    }
}

// ── Users for assignment ──────────────────────────────────────────────────────
$users_result = $conn->query("SELECT user_id AS id, name FROM users ORDER BY name");
$users = [];
while ($u = $users_result->fetch_assoc()) $users[] = $u;

$page_title = $is_edit ? 'Edit Task' : 'Create New Task';
$page_icon  = $is_edit ? '✏️' : '➕';
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1><?= $page_icon ?> <?= $page_title ?></h1>
        <p><?= $is_edit ? 'Update task details below' : 'Fill in the details to create a new task' ?></p>
    </div>
    <a href="manage_tasks.php" class="btn btn-secondary">← Back to Tasks</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-box error">
    <?php foreach($errors as $e): ?><p>⚠️ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="form-card">
<form method="POST" id="taskForm">

    <div class="form-grid">
        <div class="form-group" style="grid-column: 1 / -1;">
            <label>Task Title <span class="required">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($task['title'] ?? '') ?>"
                   placeholder="Enter a clear, descriptive title" required>
        </div>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="4" placeholder="Describe what needs to be done..."><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Priority <span class="required">*</span></label>
            <select name="priority" required>
                <?php foreach (['Low' => '🟢 Low', 'Medium' => '🟡 Medium', 'High' => '🔴 High'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($task['priority'] ?? 'Medium') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <?php foreach (['Pending' => '📋 Pending', 'In Progress' => '⚡ In Progress', 'In Review' => '🔍 In Review', 'Completed' => '✅ Completed', 'Cancelled' => '🚫 Cancelled'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($task['status'] ?? 'Pending') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Due Date <span class="required">*</span></label>
            <input type="date" name="due_date"
                   value="<?= $task['due_date'] ?? '' ?>"
                   min="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
            <label>Assign To <?= $is_admin ? '' : '<span style="color:#aaa;font-weight:400;">(optional)</span>' ?></label>
            <select name="assigned_to">
                <?php if (!$is_admin): ?>
                <option value="<?= $user_id ?>" selected>— Assign to myself —</option>
                <?php else: ?>
                <option value="">— Unassigned —</option>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"
                    <?= isset($task['assigned_to']) && $task['assigned_to'] == $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-actions">
        <a href="manage_tasks.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <?= $is_edit ? '✓ Update Task' : '➕ Create Task' ?>
        </button>
    </div>

</form>
</div>

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px;}
.page-header h1{margin:0 0 4px;}
.page-header p{margin:0;color:#888;font-size:14px;}
.form-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px 32px;max-width:760px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}
.form-group label{font-size:13px;font-weight:600;color:#374151;}
.form-group input,.form-group select,.form-group textarea{
    padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;
    font-size:14px;outline:none;transition:border-color .2s;font-family:inherit;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#024442;box-shadow:0 0 0 3px rgba(2,68,66,.08);}
.form-group textarea{resize:vertical;}
.required{color:#ef4444;}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:8px;padding-top:16px;border-top:1px solid #f3f4f6;}
.btn{padding:9px 20px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-primary{background:#024442;color:#fff;}
.btn-primary:hover{background:#035e5b;}
.btn-secondary{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;}
.btn-secondary:hover{background:#e5e7eb;}
.alert-box{border-radius:8px;padding:12px 16px;margin-bottom:16px;}
.alert-box.error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-box p{margin:4px 0;font-size:13.5px;}
@media(max-width:600px){.form-grid{grid-template-columns:1fr;}}
</style>

</div>
<?php include('dashboard_footer.php'); ?>