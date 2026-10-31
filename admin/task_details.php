<?php
// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include('dashboard_header.php');
require_once __DIR__ . '/../core/task_delay_schema.php';
require_once __DIR__ . '/../core/task_delay_helper.php';

ensure_task_delay_schema($conn);

$task_id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id   = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$user_role = strtolower($_SESSION['role'] ?? '');
$is_admin  = canUserCompleteTask($user_role);

if (!$task_id || !$user_id) {
    echo "<script>window.location='tasks.php';</script>";
    exit;
}

// ── Handle Status Update from Details Page ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $allowed = array('Pending', 'In Progress', 'In Review', 'Completed', 'Cancelled');
    
    if (in_array($new_status, $allowed)) {
        // STRICT PERMISSION CHECK: Only Super Admins, Admins, or PMs can complete a task
        if ($new_status === 'Completed' && !$is_admin) {
            $_SESSION['toast_msg'] = 'Restricted: Only Super Admins, Admins, or Project Managers can approve and complete tasks. Please submit for "In Review".';
            $_SESSION['toast_type'] = 'error';
            header("Location: task_details.php?id=" . $task_id);
            exit;
        }

        $upd = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?");
        $upd->bind_param("si", $new_status, $task_id);
        $upd->execute();
        
        // Record timestamp and sync task delays / deductions
        if (function_exists('recordTaskStatusTimestamp')) {
            recordTaskStatusTimestamp($conn, $task_id, $new_status);
        }

        if (in_array($new_status, ['In Review', 'Completed'])) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyTaskStatusChanged')) {
                notifyTaskStatusChanged($conn, $task_id, $new_status, $user_id);
            }
        }
        
        header("Location: task_details.php?id=" . $task_id . "&updated=1");
        exit;
    }
}

// ── Fetch Task, Project, Client and User Data ─────────────────────────────────
$stmt = $conn->prepare("
    SELECT t.*, 
           u_assign.name AS assignee_name, u_assign.email AS assignee_email,
           u_create.name AS creator_name, u_create.email AS creator_email,
           u_rev.name AS reviewer_name,
           p.project_name, c.client_name
    FROM tasks t
    LEFT JOIN users u_assign ON t.assigned_to = u_assign.user_id
    LEFT JOIN users u_create ON t.created_by = u_create.user_id
    LEFT JOIN users u_rev ON t.extension_reviewed_by = u_rev.user_id
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN clients c ON t.client_id = c.id
    WHERE t.id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
}

// ── Security Check ────────────────────────────────────────────────────────────
if (!$task) {
    echo "<div class='height-100'><div class='main-card' style='text-align:center; padding: 50px;'><h2 style='color:#ef4444;'>Task Not Found</h2><p>This task does not exist or has been deleted.</p><a href='tasks.php' class='btn-primary-custom'>Go Back</a></div></div>";
    include('dashboard_footer.php');
    exit;
}

if (!$is_admin && $task['assigned_to'] != $user_id && $task['created_by'] != $user_id) {
    echo "<div class='height-100'><div class='main-card' style='text-align:center; padding: 50px;'><h2 style='color:#ef4444;'>Unauthorized Access</h2><p>You do not have permission to view this task.</p><a href='tasks.php' class='btn-primary-custom'>Go Back</a></div></div>";
    include('dashboard_footer.php');
    exit;
}

// ── Fetch Subtasks, Comments, Files and History ───────────────────────────────
$subtasks        = getTaskSubtasks($conn, $task_id);
$comments        = getTaskComments($conn, $task_id);
$task_files      = getTaskFiles($conn, $task_id);
$workflow_history= getTaskWorkflowHistory($conn, $task_id);
$delay_info      = calculateTaskDelayDeduction($conn, $task);

// Calculate subtask completion progress
$total_subtasks = count($subtasks);
$done_subtasks  = count(array_filter($subtasks, function($s) { return $s['status'] === 'Completed'; }));
$subtask_percent= $total_subtasks > 0 ? round(($done_subtasks / $total_subtasks) * 100) : 0;

// Workflow stages definition
$stages = [
    'Brief'                => ['label' => 'Brief',             'icon' => 'fas fa-file-alt'],
    'Assignment'           => ['label' => 'Assignment',        'icon' => 'fas fa-user-check'],
    'Production'           => ['label' => 'Production',        'icon' => 'fas fa-paint-brush'],
    'Internal Review'      => ['label' => 'Review',            'icon' => 'fas fa-clipboard-check'],
    'Client Approval'      => ['label' => 'Client',            'icon' => 'fas fa-user-tie'],
    'Publication/Delivery' => ['label' => 'Delivery',          'icon' => 'fas fa-paper-plane'],
    'Reporting'            => ['label' => 'Done',              'icon' => 'fas fa-check-double']
];

$current_stage = $task['stage'] ?: 'Brief';
$stage_keys    = array_keys($stages);
$current_stage_idx = array_search($current_stage, $stage_keys);
if ($current_stage_idx === false) $current_stage_idx = 0;

function getStatusBadge($s) {
    $status = $s ? $s : 'Pending';
    switch($status) {
        case 'Pending':     return ['label'=>'Pending',     'bg'=>'#f1f5f9','color'=>'#475569'];
        case 'In Progress': return ['label'=>'In Progress', 'bg'=>'#fef3c7','color'=>'#b45309'];
        case 'In Review':   return ['label'=>'In Review',   'bg'=>'#f3e8ff','color'=>'#7e22ce'];
        case 'Completed':   return ['label'=>'Completed',   'bg'=>'#dcfce7','color'=>'#15803d'];
        case 'Cancelled':   return ['label'=>'Cancelled',   'bg'=>'#fee2e2','color'=>'#b91c1c'];
        default:            return ['label'=>$status,       'bg'=>'#f1f5f9','color'=>'#475569'];
    }
}

function getPriorityBadge($p) {
    $priority = $p ? strtolower($p) : 'low';
    switch($priority) {
        case 'high':   return ['label'=>'High Priority',   'bg'=>'#fee2e2','color'=>'#dc2626'];
        case 'medium': return ['label'=>'Medium Priority', 'bg'=>'#fffbeb','color'=>'#d97706'];
        case 'low':    return ['label'=>'Low Priority',    'bg'=>'#f0fdf4','color'=>'#16a34a'];
        default:       return ['label'=>'Normal',          'bg'=>'#f1f5f9','color'=>'#64748b'];
    }
}

function formatDurationH($hrs) {
    $hrs = floatval($hrs);
    return $hrs > 0 ? $hrs . 'h' : '0h';
}

$statusBadge = getStatusBadge($task['status']);
$priorityBadge = getPriorityBadge($task['priority']);
?>

<style>
/* ── Minimalist Clean Layout & Frame Guard ── */
.td-container {
    max-width: 1360px;
    width: 100%;
    margin: 0 auto;
    padding: 16px 20px 48px;
    box-sizing: border-box;
}

/* ── Top Header Bar ── */
.td-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.td-breadcrumb {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.td-breadcrumb a {
    color: #024442;
    text-decoration: none;
    font-weight: 700;
}
.td-breadcrumb a:hover {
    text-decoration: underline;
}

/* ── Task Header Card ── */
.td-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    box-sizing: border-box;
}
.td-header-box {
    padding: 24px 28px;
    margin-bottom: 20px;
}
.td-header-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}
.td-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 10px 0;
    line-height: 1.35;
    word-break: break-word;
}
.td-pill {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* ── Lightweight Pipeline Stepper ── */
.td-stepper-wrap {
    border-top: 1px solid #f1f5f9;
    padding-top: 18px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #e2e8f0 transparent;
}
.td-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 680px;
    position: relative;
    padding: 4px 0 8px;
}
.td-stepper::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}
.td-step-btn {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    outline: none;
    transition: transform 0.15s;
}
.td-step-btn:hover {
    transform: translateY(-2px);
}
.td-step-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #64748b;
    font-weight: 700;
    transition: all 0.2s;
}
.td-step-btn.completed .td-step-dot {
    background: #024442;
    border-color: #024442;
    color: #ffffff;
}
.td-step-btn.active .td-step-dot {
    background: #024442;
    border-color: #0dd3ba;
    color: #ffffff;
    box-shadow: 0 0 0 3px rgba(13, 211, 186, 0.25);
}
.td-step-txt {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    margin-top: 6px;
    white-space: nowrap;
}
.td-step-btn.active .td-step-txt {
    color: #024442;
    font-weight: 800;
}
.td-step-btn.completed .td-step-txt {
    color: #0f172a;
}

/* ── Content Grid ── */
.td-grid {
    display: grid;
    grid-template-columns: minmax(0, 2.7fr) minmax(0, 1.3fr);
    gap: 20px;
    width: 100%;
    box-sizing: border-box;
}
@media (max-width: 1024px) {
    .td-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

/* ── Tabs Navigation ── */
.td-tabs-header {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    border-top-left-radius: 14px;
    border-top-right-radius: 14px;
    overflow-x: auto;
    padding: 0 12px;
}
.td-tab-item {
    padding: 14px 18px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    border: none;
    background: transparent;
    border-bottom: 2.5px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all 0.15s;
}
.td-tab-item:hover {
    color: #024442;
}
.td-tab-item.active {
    color: #024442;
    border-bottom-color: #024442;
}
.td-tab-count {
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 700;
}
.td-pane {
    padding: 24px;
    display: none;
}
.td-pane.active {
    display: block;
}

/* ── Sidebar Cards ── */
.td-side-section {
    padding: 18px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.td-side-section:last-child {
    border-bottom: none;
}
.td-side-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 10px;
}

/* ── Subtasks / Checklist Items ── */
.td-check-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.15s;
}
.td-check-item:hover {
    background: #f8fafc;
}
.td-check-item.completed {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.td-check-item.completed span.item-title {
    text-decoration: line-through;
    color: #94a3b8;
}

/* ── Comments ── */
.td-comment {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 12px;
}

/* ── Modal Popups ── */
#briefModal, #extensionModal {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(15, 23, 42, 0.6) !important;
    backdrop-filter: blur(5px) !important;
    z-index: 10500 !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    box-sizing: border-box !important;
}
#briefModal .modal-box, #extensionModal .modal-box {
    background: #ffffff !important;
    border-radius: 16px !important;
    max-width: 560px !important;
    width: 100% !important;
    max-height: 90vh !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid #e2e8f0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
}
.modal-head {
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-body-scroll {
    padding: 22px 24px;
    max-height: calc(90vh - 130px);
    overflow-y: auto;
}
.modal-foot {
    padding: 14px 24px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* ── Modern Button Design System ── */
.btn-outline-custom {
    background: #ffffff !important;
    color: #334155 !important;
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
    line-height: 1.4 !important;
    font-family: inherit !important;
    outline: none !important;
    box-sizing: border-box !important;
}
.btn-outline-custom:hover {
    background: #f8fafc !important;
    border-color: #94a3b8 !important;
    color: #024442 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
}
.btn-outline-custom:active {
    transform: translateY(0) !important;
    background: #f1f5f9 !important;
}

.btn-primary-custom {
    background: #024442 !important;
    color: #ffffff !important;
    border: 1.5px solid #024442 !important;
    border-radius: 8px !important;
    padding: 8px 18px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    box-shadow: 0 2px 5px rgba(2, 68, 66, 0.15) !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
    line-height: 1.4 !important;
    font-family: inherit !important;
    outline: none !important;
    box-sizing: border-box !important;
}
.btn-primary-custom:hover {
    background: #035a57 !important;
    border-color: #035a57 !important;
    color: #ffffff !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 10px rgba(2, 68, 66, 0.25) !important;
}
.btn-primary-custom:active {
    transform: translateY(0) !important;
    background: #01302f !important;
}

.modal-close-btn {
    background: transparent !important;
    border: none !important;
    color: #94a3b8 !important;
    font-size: 20px !important;
    line-height: 1 !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}
.modal-close-btn:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}
</style>

<div class="height-100">
<div class="td-container">

    <!-- ── Top Breadcrumb Bar ─────────────────────────────────────────────── -->
    <div class="td-topbar">
        <div class="td-breadcrumb">
            <a href="tasks.php">← Tasks</a>
            <span>/</span>
            <span>#<?= str_pad($task['id'], 4, '0', STR_PAD_LEFT) ?></span>
        </div>

        <div style="display:flex; gap:8px;">
            <button type="button" class="btn-outline-custom" style="padding:7px 14px; font-size:12.5px;" onclick="openBriefModal()">
                <i class="fas fa-edit"></i> Edit Brief
            </button>
            <?php if ($is_admin): ?>
            <a href="create_task.php?edit=<?= $task['id'] ?>" class="btn-primary-custom" style="padding:7px 14px; font-size:12.5px;">
                <i class="fas fa-cog"></i> Settings
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Main Task Header Card ──────────────────────────────────────────── -->
    <div class="td-card td-header-box">
        <div class="td-header-title-row">
            <div>
                <h1 class="td-title"><?= htmlspecialchars($task['title']) ?></h1>
                
                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <span class="td-pill" style="background:<?= $statusBadge['bg'] ?>; color:<?= $statusBadge['color'] ?>;">
                        <?= $statusBadge['label'] ?>
                    </span>
                    <span class="td-pill" style="background:<?= $priorityBadge['bg'] ?>; color:<?= $priorityBadge['color'] ?>;">
                        <?= $priorityBadge['label'] ?>
                    </span>
                    <?php if ($delay_info['is_delayed'] && $delay_info['penalty_amount'] > 0): ?>
                    <span class="td-pill" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;">
                        <?= $delay_info['delay_days'] ?>d Overdue · PKR <?= number_format($delay_info['penalty_amount']) ?>
                    </span>
                    <?php elseif ($delay_info['is_grace_period']): ?>
                    <span class="td-pill" style="background:#eff6ff; color:#2563eb;">
                        Grace Buffer Active
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Clean 7-Stage Workflow Pipeline ────────────────────────────── -->
        <div class="td-stepper-wrap">
            <div class="td-stepper">
                <?php foreach ($stages as $key => $stg): 
                    $idx = array_search($key, $stage_keys);
                    $step_class = '';
                    if ($idx < $current_stage_idx) $step_class = 'completed';
                    elseif ($idx === $current_stage_idx) $step_class = 'active';
                ?>
                <button type="button" class="td-step-btn <?= $step_class ?>" onclick="requestStageAdvance('<?= $key ?>')" title="Switch to <?= $stg['label'] ?>">
                    <div class="td-step-dot">
                        <?php if ($idx < $current_stage_idx): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <i class="<?= $stg['icon'] ?>"></i>
                        <?php endif; ?>
                    </div>
                    <span class="td-step-txt"><?= $stg['label'] ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Extension Pending Alert (if any) ────────────────────────────────── -->
    <?php if (($task['extension_status'] ?? '') === 'pending'): ?>
    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:14px 18px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <strong style="color:#92400e; font-size:14px;"><i class="fas fa-clock"></i> Deadline Extension Requested</strong>
            <div style="font-size:13px; color:#78350f; margin-top:2px;">
                Requested Date: <strong><?= date('M j, Y', strtotime($task['extension_requested_date'])) ?></strong>
                <?= !empty($task['extension_reason']) ? '— "' . htmlspecialchars($task['extension_reason']) . '"' : '' ?>
            </div>
        </div>
        <?php if ($is_admin): ?>
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn-primary-custom" style="background:#16a34a !important; border-color:#16a34a !important; font-size:12.5px; padding:6px 14px;" onclick="reviewExtension('approved')">
                <i class="fas fa-check"></i> Approve
            </button>
            <button type="button" class="btn-primary-custom" style="background:#dc2626 !important; border-color:#dc2626 !important; font-size:12.5px; padding:6px 14px;" onclick="reviewExtension('rejected')">
                <i class="fas fa-times"></i> Reject
            </button>
        </div>
        <?php else: ?>
        <span style="font-size:12px; font-weight:700; color:#b45309;">Awaiting Approval</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Main Grid ──────────────────────────────────────────────────────── -->
    <div class="td-grid">
        
        <!-- Left Column: Work Hub with Clean Tabs -->
        <div>
            <div class="td-card" style="overflow:hidden;">
                <!-- Clean Tabs Navigation -->
                <div class="td-tabs-header">
                    <button type="button" class="td-tab-item active" onclick="switchTab(event, 'tab-overview')">
                        <i class="fas fa-align-left"></i> Overview
                    </button>
                    <button type="button" class="td-tab-item" onclick="switchTab(event, 'tab-subtasks')">
                        <i class="fas fa-tasks"></i> Checklist <span class="td-tab-count" id="subtaskBadge"><?= $done_subtasks ?>/<?= $total_subtasks ?></span>
                    </button>
                    <button type="button" class="td-tab-item" onclick="switchTab(event, 'tab-files')">
                        <i class="fas fa-paperclip"></i> Files <span class="td-tab-count"><?= count($task_files) ?></span>
                    </button>
                    <button type="button" class="td-tab-item" onclick="switchTab(event, 'tab-discussion')">
                        <i class="fas fa-comment-alt"></i> Discussion <span class="td-tab-count"><?= count($comments) ?></span>
                    </button>
                    <button type="button" class="td-tab-item" onclick="switchTab(event, 'tab-reviews')">
                        <i class="fas fa-check-circle"></i> Reviews & QA
                    </button>
                    <button type="button" class="td-tab-item" onclick="switchTab(event, 'tab-history')">
                        <i class="fas fa-history"></i> History
                    </button>
                </div>

                <!-- ── TAB 1: Overview ──────────────────────────────────────── -->
                <div id="tab-overview" class="td-pane active">
                    <!-- General Description -->
                    <div style="font-size:14.5px; color:#334155; line-height:1.7; white-space:pre-line; margin-bottom:24px;">
                        <?= !empty($task['description']) ? htmlspecialchars($task['description']) : '<span style="color:#94a3b8; font-style:italic;">No description provided.</span>' ?>
                    </div>

                    <!-- Progress Slider Bar -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#475569; margin-bottom:6px;">
                            <span>Production Progress</span>
                            <span style="color:#024442;"><?= intval($task['production_progress']) ?>%</span>
                        </div>
                        <div style="background:#e2e8f0; height:8px; border-radius:10px; overflow:hidden;">
                            <div style="width:<?= intval($task['production_progress']) ?>%; background:#024442; height:100%; border-radius:10px;"></div>
                        </div>
                    </div>

                    <!-- Creative Brief Specifications (if available) -->
                    <?php 
                    $has_brief = !empty($task['brief_objective']) || !empty($task['brief_deliverables']) || !empty($task['brief_specs']) || !empty($task['brief_copy']) || !empty($task['brief_references']);
                    if ($has_brief): 
                    ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px;">
                        <?php if (!empty($task['brief_objective'])): ?>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Target Objective</div>
                            <div style="font-size:13.5px; color:#1e293b;"><?= nl2br(htmlspecialchars($task['brief_objective'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($task['brief_deliverables'])): ?>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Deliverables & Formats</div>
                            <div style="font-size:13.5px; color:#1e293b;"><?= nl2br(htmlspecialchars($task['brief_deliverables'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($task['brief_specs'])): ?>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Technical Specs</div>
                            <div style="font-size:13.5px; color:#1e293b;"><?= nl2br(htmlspecialchars($task['brief_specs'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($task['brief_copy'])): ?>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Copy / Caption</div>
                            <div style="font-size:13.5px; color:#1e293b;"><?= nl2br(htmlspecialchars($task['brief_copy'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($task['brief_references'])): ?>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:4px;">References</div>
                            <div style="font-size:13.5px; color:#1e293b;"><?= nl2br(htmlspecialchars($task['brief_references'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── TAB 2: Checklist (Subtasks) ──────────────────────────── -->
                <div id="tab-subtasks" class="td-pane">
                    <!-- Inline Add Subtask Form -->
                    <form onsubmit="handleAddSubtask(event)" style="display:flex; gap:8px; margin-bottom:16px;">
                        <input type="text" id="newSubtaskTitle" required placeholder="Add item..." style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px;">
                        <input type="date" id="newSubtaskDue" style="padding:9px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
                        <button type="submit" class="btn-primary-custom" style="padding:9px 16px; font-size:13px; font-weight:700;">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </form>

                    <div id="subtasksListContainer">
                        <?php if (empty($subtasks)): ?>
                            <div id="subtaskEmptyState" style="text-align:center; padding:30px; color:#94a3b8; font-style:italic;">
                                No subtasks yet. Add items above to track progress.
                            </div>
                        <?php else: ?>
                            <?php foreach ($subtasks as $sub): 
                                $is_done = ($sub['status'] === 'Completed');
                            ?>
                            <div class="td-check-item <?= $is_done ? 'completed' : '' ?>" id="subtaskRow_<?= $sub['id'] ?>">
                                <label style="display:flex; align-items:center; gap:10px; margin:0; cursor:pointer;">
                                    <input type="checkbox" style="width:17px; height:17px; cursor:pointer;" <?= $is_done ? 'checked' : '' ?> onchange="handleToggleSubtask(<?= $sub['id'] ?>, this.checked)">
                                    <span class="item-title" style="font-size:13.5px; font-weight:600; color:#1e293b;"><?= htmlspecialchars($sub['title']) ?></span>
                                </label>
                                <div style="font-size:12px; color:#64748b;">
                                    <?= !empty($sub['due_date']) ? date('M d', strtotime($sub['due_date'])) : '' ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── TAB 3: Files & Deliverables ──────────────────────────── -->
                <div id="tab-files" class="td-pane">
                    <!-- Dropzone -->
                    <form onsubmit="handleFileUpload(event)" style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:16px; margin-bottom:18px; text-align:center;">
                        <input type="file" id="taskFileInput" required style="display:none;" onchange="updateSelectedFileName(this)">
                        <div style="cursor:pointer;" onclick="document.getElementById('taskFileInput').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem; color:#024442; margin-bottom:4px;"></i>
                            <div style="font-size:13.5px; font-weight:700; color:#0f172a;" id="uploadFilePrompt">Click to attach file</div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Images, source files, PDFs (Max 50MB)</div>
                        </div>

                        <div style="margin-top:12px; display:flex; justify-content:center; align-items:center; gap:12px;">
                            <label style="font-size:12.5px; color:#475569; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" id="isDeliverableCheckbox"> Client Deliverable
                            </label>
                            <button type="submit" class="btn-primary-custom" id="btnUploadFile" style="padding:6px 14px; font-size:12.5px; font-weight:700;">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                        </div>
                    </form>

                    <!-- Files Table -->
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <tbody>
                                <?php if (empty($task_files)): ?>
                                <tr>
                                    <td style="text-align:center; padding:24px; color:#94a3b8; font-style:italic;">No files attached.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($task_files as $f): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:10px 6px; font-weight:600; color:#0f172a;">
                                            <i class="far fa-file" style="color:#024442; margin-right:6px;"></i>
                                            <?= htmlspecialchars($f['file_name']) ?>
                                        </td>
                                        <td style="padding:10px 6px; color:#64748b; font-size:12px;"><?= htmlspecialchars($f['uploader_name']) ?></td>
                                        <td style="padding:10px 6px; color:#64748b; font-size:12px;"><?= date('M d', strtotime($f['uploaded_at'])) ?></td>
                                        <td style="padding:10px 6px; text-align:right;">
                                            <a href="<?= htmlspecialchars($f['file_path']) ?>" download class="btn-outline-custom" style="padding:4px 10px; font-size:11.5px; text-decoration:none;">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── TAB 4: Discussion ────────────────────────────────────── -->
                <div id="tab-discussion" class="td-pane">
                    <div style="max-height:400px; overflow-y:auto; margin-bottom:16px;" id="commentsFeed">
                        <?php if (empty($comments)): ?>
                            <div style="text-align:center; padding:24px; color:#94a3b8; font-style:italic;" id="commentsEmpty">
                                No comments posted yet.
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                            <div class="td-comment">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                    <strong style="font-size:13px; color:#0f172a;"><?= htmlspecialchars($c['user_name']) ?></strong>
                                    <span style="font-size:11px; color:#94a3b8;"><?= date('M d, h:i A', strtotime($c['created_at'])) ?></span>
                                </div>
                                <div style="font-size:13.5px; color:#334155; line-height:1.5; white-space:pre-line;"><?= htmlspecialchars($c['comment_text']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Post Comment Form -->
                    <form onsubmit="handlePostComment(event)" style="display:flex; flex-direction:column; gap:8px;">
                        <textarea id="commentTextInput" rows="2" required placeholder="Write a note or update..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px; font-family:inherit;"></textarea>
                        <div style="text-align:right;">
                            <button type="submit" class="btn-primary-custom" id="btnPostComment" style="padding:7px 18px; font-size:13px; font-weight:700;">
                                <i class="fas fa-paper-plane"></i> Post
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TAB 5: Reviews & QA ──────────────────────────────────── -->
                <div id="tab-reviews" class="td-pane">
                    <!-- Internal Manager QA Review -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:18px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b;">Internal QA Status</div>
                                <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">
                                    <?= htmlspecialchars($task['internal_review_status'] ?: 'Pending Review') ?>
                                </div>
                            </div>
                            <?php if ($is_admin): ?>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn-primary-custom" style="background:#16a34a !important; border-color:#16a34a !important; font-size:12px; padding:6px 14px;" onclick="submitInternalReview('Approve')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn-primary-custom" style="background:#ea580c !important; border-color:#ea580c !important; font-size:12px; padding:6px 14px;" onclick="submitInternalReview('Request Changes')">
                                    <i class="fas fa-undo"></i> Request Changes
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Client Portal Approval Gateway -->
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:14px;">
                            <div>
                                <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b;">Client</div>
                                <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">
                                    <?= !empty($task['client_name']) ? htmlspecialchars($task['client_name']) : 'Internal Graphicafix Task' ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:11.5px; font-weight:800; text-transform:uppercase; color:#64748b;">Client Status</div>
                                <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">
                                    <?= htmlspecialchars($task['client_approval_status'] ?: 'Pending') ?> (Rev #<?= intval($task['current_revision'] ?: 1) ?>)
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($task['client_token'])): 
                            $approval_url = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com') . '/admin/client_approval.php?token=' . $task['client_token'];
                        ?>
                        <div style="display:flex; gap:8px;">
                            <input type="text" readonly value="<?= htmlspecialchars($approval_url) ?>" id="clientApprovalLink" style="flex:1; padding:7px 10px; font-size:12.5px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; color:#475569;">
                            <button type="button" class="btn-outline-custom" style="padding:7px 12px; font-size:12px;" onclick="copyApprovalLink()">
                                <i class="fas fa-copy"></i> Copy Link
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($task['client_feedback'])): ?>
                        <div style="margin-top:14px; background:#fff7ed; border-left:3px solid #f97316; padding:10px 14px; border-radius:4px; font-size:13px; color:#7c2d12;">
                            <strong>Latest Client Feedback:</strong><br>
                            <?= nl2br(htmlspecialchars($task['client_feedback'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── TAB 6: Audit History ─────────────────────────────────── -->
                <div id="tab-history" class="td-pane">
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                            <thead>
                                <tr style="border-bottom:1px solid #e2e8f0; color:#64748b; text-align:left;">
                                    <th style="padding:8px 6px;">Date</th>
                                    <th style="padding:8px 6px;">From</th>
                                    <th style="padding:8px 6px;">To</th>
                                    <th style="padding:8px 6px;">User</th>
                                    <th style="padding:8px 6px;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($workflow_history)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:24px; color:#94a3b8; font-style:italic;">No recorded history.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($workflow_history as $h): ?>
                                    <tr style="border-bottom:1px solid #f8fafc;">
                                        <td style="padding:8px 6px; color:#64748b;"><?= date('M d, H:i', strtotime($h['created_at'])) ?></td>
                                        <td style="padding:8px 6px;"><?= htmlspecialchars($h['from_stage']) ?></td>
                                        <td style="padding:8px 6px; font-weight:700; color:#024442;"><?= htmlspecialchars($h['to_stage']) ?></td>
                                        <td style="padding:8px 6px;"><?= htmlspecialchars($h['changer_name'] ?: 'System') ?></td>
                                        <td style="padding:8px 6px; color:#475569;"><?= htmlspecialchars($h['comment'] ?: '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Column: Clean Meta Sidebar -->
        <div>
            <div class="td-card">
                
                <!-- Assignee -->
                <div class="td-side-section">
                    <div class="td-side-title">Assignee</div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:34px; height:34px; border-radius:50%; background:#e6f4f2; color:#024442; font-weight:800; font-size:13px; display:inline-flex; align-items:center; justify-content:center;">
                            <?= strtoupper(substr($task['assignee_name'] ?: 'U', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size:13.5px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($task['assignee_name'] ?: 'Unassigned') ?></div>
                            <div style="font-size:11.5px; color:#64748b;"><?= htmlspecialchars($task['assignee_email']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Project -->
                <div class="td-side-section">
                    <div class="td-side-title">Project</div>
                    <div style="font-size:13.5px; font-weight:700; color:#0f172a;">
                        <?= htmlspecialchars($task['project_name'] ?: 'General Agency') ?>
                    </div>
                    <?php if (!empty($task['client_name'])): ?>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        Client: <?= htmlspecialchars($task['client_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Schedule -->
                <div class="td-side-section">
                    <div class="td-side-title">Schedule</div>
                    <div style="display:flex; flex-direction:column; gap:6px; font-size:12.5px;">
                        <div style="display:flex; justify-content:space-between;">
                            <span style="color:#64748b;">Due Date:</span>
                            <strong style="<?= ($delay_info['is_delayed'] && $task['status'] !== 'Completed') ? 'color:#ef4444;' : 'color:#0f172a;' ?>">
                                <?= $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No Date' ?>
                            </strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="color:#64748b;">Effort:</span>
                            <strong><?= formatDurationH($task['logged_hours']) ?> / <?= formatDurationH($task['estimated_hours']) ?></strong>
                        </div>
                    </div>

                    <?php if (in_array($task['status'], ['Pending', 'In Progress'])): ?>
                    <button type="button" class="btn-outline-custom" style="width:100%; font-size:12.5px; margin-top:10px; padding:8px 12px;" onclick="openExtensionModal()">
                        <i class="fas fa-clock"></i> Request Extension
                    </button>
                    <?php endif; ?>

                    <?php if ($is_admin && $delay_info['penalty_amount'] > 0): ?>
                    <button type="button" class="btn-primary-custom" style="width:100%; font-size:12.5px; margin-top:8px; padding:8px 12px; background:#024442 !important;" onclick="exemptDelay()">
                        <i class="fas fa-shield-alt"></i> Exempt Delay
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Status Select -->
                <div class="td-side-section">
                    <div class="td-side-title">Status</div>
                    <form method="POST">
                        <input type="hidden" name="update_status" value="1">
                        <select name="new_status" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:600; color:#0f172a;">
                            <option value="Pending" <?= $task['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="In Progress" <?= $task['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="In Review" <?= $task['status'] == 'In Review' ? 'selected' : '' ?>>In Review (QA)</option>
                            <?php if ($is_admin): ?>
                            <option value="Completed" <?= $task['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <?php else: ?>
                            <option value="Completed" disabled style="color:#94a3b8;">Completed (Admin only)</option>
                            <?php endif; ?>
                            <option value="Cancelled" <?= $task['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn-primary-custom" style="width:100%; margin-top:10px; padding:8px 14px; font-size:12.5px; font-weight:700;">
                            <i class="fas fa-sync-alt"></i> Update Status
                        </button>
                    </form>
                </div>

            </div>
        </div>

    </div>

</div>
</div>

<!-- ── Brief Editing Modal ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="briefModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Edit Creative Brief</h3>
            <button type="button" class="modal-close-btn" onclick="closeBriefModal()">&times;</button>
        </div>
        <form onsubmit="handleSaveBrief(event)">
            <div class="modal-body-scroll">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Target Objective</label>
                    <textarea id="editBriefObjective" rows="2" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"><?= htmlspecialchars($task['brief_objective'] ?? '') ?></textarea>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Deliverable Formats / Sizes</label>
                    <textarea id="editBriefDeliverables" rows="2" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"><?= htmlspecialchars($task['brief_deliverables'] ?? '') ?></textarea>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Technical Specs</label>
                    <textarea id="editBriefSpecs" rows="2" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"><?= htmlspecialchars($task['brief_specs'] ?? '') ?></textarea>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Copy / Caption</label>
                    <textarea id="editBriefCopy" rows="3" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"><?= htmlspecialchars($task['brief_copy'] ?? '') ?></textarea>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">References</label>
                    <textarea id="editBriefReferences" rows="2" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"><?= htmlspecialchars($task['brief_references'] ?? '') ?></textarea>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Progress: <span id="lblProgressVal"><?= intval($task['production_progress']) ?>%</span></label>
                    <input type="range" id="editProdProgress" min="0" max="100" value="<?= intval($task['production_progress']) ?>" style="width:100%;" oninput="document.getElementById('lblProgressVal').textContent = this.value + '%'">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-outline-custom" style="padding:7px 16px; font-size:12.5px;" onclick="closeBriefModal()">Cancel</button>
                <button type="submit" class="btn-primary-custom" id="btnSaveBrief" style="padding:7px 18px; font-size:12.5px;"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Extension Request Modal ─────────────────────────────────────────────── -->
<div class="modal-overlay" id="extensionModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Request Deadline Extension</h3>
            <button type="button" class="modal-close-btn" onclick="closeExtensionModal()">&times;</button>
        </div>
        <form id="extensionForm" onsubmit="submitExtensionRequest(event)">
            <div class="modal-body-scroll">
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Current Due Date</label>
                    <input type="text" readonly value="<?= $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'None' ?>" style="width:100%; padding:8px 10px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; color:#64748b;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Requested New Date *</label>
                    <input type="date" id="requestedDueDate" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Category *</label>
                    <select id="reasonCategory" required style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                        <option value="Client Blocker">Pending Client Feedback / Assets</option>
                        <option value="Technical Issue">Technical Block</option>
                        <option value="Scope Expansion">Scope Expansion / Revisions</option>
                        <option value="Health / Personal">Medical / Emergency</option>
                        <option value="General">Other Legitimate Dependency</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">Justification *</label>
                    <textarea id="extensionReason" required rows="3" placeholder="Briefly describe the blocker..." style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-outline-custom" style="padding:7px 16px; font-size:12.5px;" onclick="closeExtensionModal()">Cancel</button>
                <button type="submit" class="btn-primary-custom" id="btnSubmitExtension" style="padding:7px 18px; font-size:12.5px;"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
// Toast function
function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,.08);font-size:13px;border-left:3px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:800;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'✓':'✕'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .25s'; setTimeout(()=>t.remove(),250); }, 3000);
}

// Tab Switching
function switchTab(e, tabId) {
    document.querySelectorAll('.td-tab-item').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.td-pane').forEach(p => p.classList.remove('active'));
    e.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

// Stage Advancement
async function requestStageAdvance(targetStage) {
    const isRestrictedStage = (targetStage === 'Publication/Delivery' || targetStage === 'Reporting');
    const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;

    if (isRestrictedStage && !isAdmin) {
        showToast('Only Super Admins, Admins, or PMs can approve delivery or completion.', 'error');
        return;
    }

    const comment = await showRemarkDialog({
        title: 'Advance Stage',
        message: `Advance task to <strong>"${targetStage}"</strong>? You can optionally enter stage notes:`,
        placeholder: 'Optional notes...',
        confirmText: `Advance to ${targetStage}`,
        confirmClass: 'btn-primary',
        icon: 'fas fa-layer-group'
    });
    if (comment === null) return;

    const payload = new FormData();
    payload.append('ajax_action', 'advance_stage');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('target_stage', targetStage);
    payload.append('comment', comment);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Stage advance failed', 'error');
            }
        })
        .catch(() => showToast('Error communicating with server', 'error'));
}

// Subtasks / Checklist
function handleAddSubtask(e) {
    e.preventDefault();
    const title = document.getElementById('newSubtaskTitle').value.trim();
    const dueDate = document.getElementById('newSubtaskDue').value;
    if (!title) return;

    const payload = new FormData();
    payload.append('ajax_action', 'add_subtask');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('title', title);
    payload.append('due_date', dueDate);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Item added');
                location.reload();
            } else {
                showToast(d.message || 'Failed to add item', 'error');
            }
        });
}

function handleToggleSubtask(subtaskId, isChecked) {
    const payload = new FormData();
    payload.append('ajax_action', 'toggle_subtask');
    payload.append('subtask_id', subtaskId);
    payload.append('status', isChecked ? 'Completed' : 'Pending');

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const row = document.getElementById('subtaskRow_' + subtaskId);
                if (row) {
                    if (isChecked) row.classList.add('completed');
                    else row.classList.remove('completed');
                }
                const allSubtasks = document.querySelectorAll('.td-check-item');
                const doneSubtasks = document.querySelectorAll('.td-check-item.completed');
                const badge = document.getElementById('subtaskBadge');
                if (badge && allSubtasks.length > 0) {
                    badge.textContent = `${doneSubtasks.length}/${allSubtasks.length}`;
                }
            } else {
                showToast(d.message || 'Failed to update item', 'error');
            }
        });
}

// Comments
function handlePostComment(e) {
    e.preventDefault();
    const text = document.getElementById('commentTextInput').value.trim();
    if (!text) return;

    const btn = document.getElementById('btnPostComment');
    btn.disabled = true;

    const payload = new FormData();
    payload.append('ajax_action', 'add_task_comment');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('comment_text', text);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.success) {
                document.getElementById('commentTextInput').value = '';
                location.reload();
            } else {
                showToast(d.message || 'Failed to post comment', 'error');
            }
        });
}

// Files Upload
function updateSelectedFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('uploadFilePrompt').textContent = input.files[0].name;
    }
}

function handleFileUpload(e) {
    e.preventDefault();
    const fileInput = document.getElementById('taskFileInput');
    if (!fileInput.files || !fileInput.files[0]) {
        showToast('Please select a file to upload', 'error');
        return;
    }
    const btn = document.getElementById('btnUploadFile');
    btn.disabled = true;
    btn.textContent = 'Uploading...';

    const payload = new FormData();
    payload.append('ajax_action', 'upload_task_file');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('file', fileInput.files[0]);
    payload.append('is_client_deliverable', document.getElementById('isDeliverableCheckbox').checked ? 1 : 0);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = 'Upload';
            if (d.success) {
                showToast(d.message);
                location.reload();
            } else {
                showToast(d.message || 'Failed to upload file', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = 'Upload';
            showToast('Network error during upload', 'error');
        });
}

// Creative Brief Modal
function openBriefModal() {
    const m = document.getElementById('briefModal');
    if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeBriefModal() {
    const m = document.getElementById('briefModal');
    if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
}

function handleSaveBrief(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveBrief');
    btn.disabled = true;

    const payload = new FormData();
    payload.append('ajax_action', 'update_brief');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('brief_objective', document.getElementById('editBriefObjective').value);
    payload.append('brief_deliverables', document.getElementById('editBriefDeliverables').value);
    payload.append('brief_specs', document.getElementById('editBriefSpecs').value);
    payload.append('brief_copy', document.getElementById('editBriefCopy').value);
    payload.append('brief_references', document.getElementById('editBriefReferences').value);
    payload.append('production_progress', document.getElementById('editProdProgress').value);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.success) {
                closeBriefModal();
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Error saving brief', 'error');
            }
        });
}

// Copy Approval Link
function copyApprovalLink() {
    const copyText = document.getElementById('clientApprovalLink');
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    showToast('Link copied to clipboard!');
}

// Extension Modal
function openExtensionModal() {
    const m = document.getElementById('extensionModal');
    if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeExtensionModal() {
    const m = document.getElementById('extensionModal');
    if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
}

function submitExtensionRequest(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitExtension');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    const payload = new FormData();
    payload.append('ajax_action', 'request_task_extension');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('requested_date', document.getElementById('requestedDueDate').value);
    payload.append('reason_category', document.getElementById('reasonCategory').value);
    payload.append('reason', document.getElementById('extensionReason').value);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
            if (d.success) {
                closeExtensionModal();
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Failed to submit extension', 'error');
            }
        });
}

async function reviewExtension(decision) {
    const isApprove = decision === 'approved';
    const notes = await showRemarkDialog({
        title: `${isApprove ? 'Approve' : 'Reject'} Extension`,
        message: `Provide remarks for <strong>${decision.toUpperCase()}</strong>:`,
        placeholder: isApprove ? 'e.g. Approved due to revision timeline' : 'e.g. Schedule constraint does not allow',
        confirmText: isApprove ? 'Approve' : 'Reject',
        confirmClass: isApprove ? 'btn-success' : 'btn-danger',
        icon: isApprove ? 'fas fa-calendar-check' : 'fas fa-calendar-times'
    });
    if (notes === null) return;

    const payload = new FormData();
    payload.append('ajax_action', 'review_task_extension');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('decision', decision);
    payload.append('admin_notes', notes);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Action failed', 'error');
            }
        });
}

async function exemptDelay() {
    const notes = await showRemarkDialog({
        title: 'Exempt Delay Penalty',
        message: 'Exempting this task waives the delay penalty and syncs employee payroll. Enter the audit reason:',
        defaultValue: 'Exempted by Project Administration',
        placeholder: 'Audit reason...',
        confirmText: 'Exempt Penalty',
        confirmClass: 'btn-success',
        icon: 'fas fa-shield-alt'
    });
    if (notes === null) return;

    const payload = new FormData();
    payload.append('ajax_action', 'exempt_task_delay');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('admin_notes', notes);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Exemption failed', 'error');
            }
        });
}

async function submitInternalReview(decision) {
    const isApprove = (decision === 'Approve');
    const defaultComment = isApprove ? 'Approved for client presentation/delivery' : 'Revisions requested';
    const comment = await showRemarkDialog({
        title: `Internal QA: ${isApprove ? 'Approve' : 'Request Changes'}`,
        message: isApprove 
            ? 'Sign off on internal quality review and advance task:' 
            : 'Return task to <strong>Production</strong> stage for adjustments. Specify the revisions:',
        defaultValue: defaultComment,
        placeholder: 'Review remarks...',
        confirmText: isApprove ? 'Approve' : 'Return to Production',
        confirmClass: isApprove ? 'btn-success' : 'btn-danger',
        icon: isApprove ? 'fas fa-clipboard-check' : 'fas fa-undo'
    });
    if (comment === null) return;

    const payload = new FormData();
    payload.append('ajax_action', 'submit_review');
    payload.append('task_id', '<?= $task_id ?>');
    payload.append('review_type', 'Internal');
    payload.append('decision', decision);
    payload.append('comment', comment);

    fetch('task_action.php', { method: 'POST', body: payload })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message);
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(d.message || 'Review submission failed', 'error');
            }
        })
        .catch(() => showToast('Error submitting review', 'error'));
}

document.addEventListener('DOMContentLoaded', () => {
    ['briefModal', 'extensionModal'].forEach(id => {
        const m = document.getElementById(id);
        if (m) {
            m.addEventListener('click', (e) => {
                if (e.target === m) {
                    m.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    });

    <?php if (isset($_GET['updated'])): ?> 
        showToast('Status updated successfully'); 
        window.history.replaceState({}, document.title, "task_details.php?id=<?= $task['id'] ?>");
    <?php endif; ?>
});
</script>

<?php include('dashboard_footer.php'); ?>
