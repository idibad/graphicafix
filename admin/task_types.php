<?php
// admin/task_types.php
include 'dashboard_header.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id   = intval($_SESSION['user_id']);
$is_admin  = in_array($role, ['super_admin', 'superadmin', 'admin']);

if (!$is_admin) {
    header('Location: tasks.php'); exit;
}

$message = '';
$error = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $error = 'Task type name cannot be empty.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM task_types WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Task type '$name' already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO task_types (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $message = "Task type '$name' added successfully!";
            } else {
                $error = "Failed to add task type.";
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    // Verify it exists
    $stmt = $conn->prepare("SELECT name FROM task_types WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $type_row = $stmt->get_result()->fetch_assoc();
    
    if ($type_row) {
        $name = $type_row['name'];
        // Check if there are tasks using this type
        $stmt_check = $conn->prepare("SELECT id FROM tasks WHERE task_type = ?");
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "Cannot delete '$name' because it is currently assigned to one or more tasks.";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM task_types WHERE id = ?");
            $stmt_del->bind_param("i", $del_id);
            if ($stmt_del->execute()) {
                $message = "Task type '$name' deleted successfully!";
            } else {
                $error = "Failed to delete task type.";
            }
        }
    }
}

// Fetch all task types
$types_res = $conn->query("SELECT * FROM task_types ORDER BY name ASC");
$types = [];
while ($t = $types_res->fetch_assoc()) {
    $types[] = $t;
}
?>

<div class="height-100">
    <div class="page-header">
        <div class="page-title-section">
            <h1><i class="fas fa-cog"></i> Task Types Settings</h1>
            <p>Manage the configurable categories of tasks available for selection</p>
        </div>
        <a href="tasks.php" class="btn btn-secondary">← Back to Board</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom:20px; border-radius:10px;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom:20px; border-radius:10px;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row" style="display: flex; gap: 30px; flex-wrap: wrap;">
        <!-- Left: Add Form -->
        <div style="flex: 1; min-width: 300px;">
            <div class="main-card" style="padding: 24px; border-radius:16px;">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size:18px; font-weight:700; color:#111827;">Add New Task Type</h3>
                <form method="POST">
                    <input type="hidden" name="add_type" value="1">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#4b5563; margin-bottom:8px;">Task Type Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" placeholder="e.g. Graphic Design, Motion Graphics" required
                               style="width:100%; padding:11px 14px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px; background:#f9fafb; outline:none; font-family:inherit; box-sizing:border-box;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight:600; border-radius:10px; background:#024442; color:#fff; border:none; cursor:pointer;">
                        <i class="fas fa-plus"></i> Add Task Type
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: List Table -->
        <div style="flex: 2; min-width: 450px;">
            <div class="main-card" style="padding: 24px; border-radius:16px; min-height: 200px;">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size:18px; font-weight:700; color:#111827;">Active Task Types</h3>
                <div style="overflow-x: auto;">
                    <table class="table" style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e5e7eb; color:#6b7280; font-weight:600;">
                                <th style="padding:12px 16px;">Name</th>
                                <th style="padding:12px 16px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($types)): ?>
                                <tr>
                                    <td colspan="2" style="padding:24px; text-align:center; color:#9ca3af; font-style:italic;">No task types configured.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($types as $t): ?>
                                    <tr style="border-bottom: 1px solid #f3f4f6; color:#111827;">
                                        <td style="padding:12px 16px; font-weight:600;"><?= htmlspecialchars($t['name']) ?></td>
                                        <td style="padding:12px 16px; text-align:right;">
                                            <a href="task_types.php?delete=<?= $t['id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this task type?');"
                                               style="color:#ef4444; text-decoration:none; font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #fee2e2; background:#fef2f2; border-radius:8px;">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
