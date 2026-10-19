<?php
/**
 * Graphica Fix - Team & Employee Management Dashboard
 * Handles Employee Profiles, Digital ID Badges, QR Verification, and About Page Team Cards
 */

include 'dashboard_header.php';

// Authorization check: Only super_admin, admin, manager, hrm, or pm can access
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'hrm', 'pm'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// ── Database Auto-Patch & Self-Healing ──
if (file_exists(__DIR__ . '/../core/team_schema.php')) {
    require_once __DIR__ . '/../core/team_schema.php';
    if (function_exists('ensureTeamSchema')) {
        ensureTeamSchema($conn);
    }
}

// Inspect active columns defensively
$existing_cols = [];
try {
    $c_res = $conn->query("SHOW COLUMNS FROM `team`");
    if ($c_res) {
        while ($cr = $c_res->fetch_assoc()) {
            $existing_cols[strtolower($cr['Field'])] = true;
        }
    }
} catch (Throwable $e) {}

// If crucial columns are missing, attempt in-place ALTER TABLE directly here
$needed_cols = [
    'employee_id'        => "VARCHAR(50) DEFAULT NULL",
    'photo'              => "VARCHAR(255) DEFAULT NULL",
    'department'         => "VARCHAR(100) DEFAULT NULL",
    'bio'                => "TEXT DEFAULT NULL",
    'gender'             => "VARCHAR(20) DEFAULT 'Male'",
    'status'             => "VARCHAR(20) DEFAULT 'active'",
    'show_on_about'      => "TINYINT(1) DEFAULT 1",
    'display_order'      => "INT DEFAULT 0",
    'verification_code'  => "VARCHAR(64) DEFAULT NULL",
    'linkedin'           => "VARCHAR(255) DEFAULT NULL",
    'github'             => "VARCHAR(255) DEFAULT NULL",
    'twitter'            => "VARCHAR(255) DEFAULT NULL",
    'blood_group'        => "VARCHAR(10) DEFAULT NULL",
    'emergency_contact'  => "VARCHAR(50) DEFAULT NULL",
    'address'            => "VARCHAR(255) DEFAULT NULL"
];

$failed_alter_cols = [];
foreach ($needed_cols as $cname => $cdef) {
    if (!isset($existing_cols[strtolower($cname)])) {
        try {
            $conn->query("ALTER TABLE `team` ADD COLUMN `{$cname}` {$cdef}");
            $existing_cols[strtolower($cname)] = true;
        } catch (Throwable $e) {
            $failed_alter_cols[] = $cname;
        }
    }
}

$alert_msg = '';
$alert_type = 'success';

// Ensure upload directory exists
$upload_dir = __DIR__ . '/../uploads/team/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// ── Action Handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD EMPLOYEE
    if ($action === 'add') {
        $name        = trim($_POST['member_name'] ?? '');
        $role_title  = trim($_POST['role'] ?? '');
        $department  = trim($_POST['department'] ?? 'Operations & Creative');
        $emp_id      = strtoupper(trim($_POST['employee_id'] ?? ''));
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        $gender      = $_POST['gender'] ?? 'Male';
        $status      = $_POST['status'] ?? 'active';
        $show_about  = isset($_POST['show_on_about']) ? 1 : 0;
        $display_ord = intval($_POST['display_order'] ?? 0);
        $blood_group = trim($_POST['blood_group'] ?? '');
        $joined_at   = !empty($_POST['joined_at']) ? $_POST['joined_at'] : date('Y-m-d');
        $linkedin    = trim($_POST['linkedin'] ?? '');
        $github      = trim($_POST['github'] ?? '');
        $twitter     = trim($_POST['twitter'] ?? '');

        if (empty($name)) {
            $alert_msg = 'Employee name is required.';
            $alert_type = 'danger';
        } else {
            // Auto-generate employee_id if blank
            if (empty($emp_id)) {
                $max_res = $conn->query("SELECT MAX(id) as max_id FROM team");
                $next_id = ($max_res ? ($max_res->fetch_assoc()['max_id'] ?? 0) : 0) + 1;
                $emp_id  = sprintf("EMP%04d", $next_id);
            }

            // Check for duplicate employee_id if column exists
            $is_dup = false;
            if (isset($existing_cols['employee_id'])) {
                try {
                    $check_stmt = $conn->prepare("SELECT id FROM team WHERE employee_id = ?");
                    if ($check_stmt) {
                        $check_stmt->bind_param("s", $emp_id);
                        $check_stmt->execute();
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $is_dup = true;
                        }
                        $check_stmt->close();
                    }
                } catch (Throwable $e) {}
            }

            if ($is_dup) {
                $alert_msg = "An employee with ID {$emp_id} already exists.";
                $alert_type = 'danger';
            } else {
                // Handle Photo Upload
                $photo_path = null;
                if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $filename = 'emp_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $emp_id) . '_' . time() . '.' . $ext;
                        $target = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                            $photo_path = 'uploads/team/' . $filename;
                        }
                    }
                }

                // Generate secure verification hash
                $vcode = hash('sha256', 'GF_' . $emp_id . '_' . time() . '_salt');

                // Dynamically build INSERT fields matching existing columns
                $insert_data = [
                    'member_name' => $name,
                    'role'        => $role_title,
                    'email'       => $email,
                    'phone'       => $phone
                ];
                if (isset($existing_cols['employee_id']))       $insert_data['employee_id'] = $emp_id;
                if (isset($existing_cols['department']))        $insert_data['department'] = $department;
                if (isset($existing_cols['bio']))               $insert_data['bio'] = $bio;
                if (isset($existing_cols['gender']))            $insert_data['gender'] = $gender;
                if (isset($existing_cols['status']))            $insert_data['status'] = $status;
                if (isset($existing_cols['show_on_about']))     $insert_data['show_on_about'] = $show_about;
                if (isset($existing_cols['display_order']))     $insert_data['display_order'] = $display_ord;
                if (isset($existing_cols['verification_code'])) $insert_data['verification_code'] = $vcode;
                if (isset($existing_cols['photo']))             $insert_data['photo'] = $photo_path;
                if (isset($existing_cols['blood_group']))       $insert_data['blood_group'] = $blood_group;
                if (isset($existing_cols['joined_at']))         $insert_data['joined_at'] = $joined_at;
                if (isset($existing_cols['linkedin']))          $insert_data['linkedin'] = $linkedin;
                if (isset($existing_cols['github']))            $insert_data['github'] = $github;
                if (isset($existing_cols['twitter']))           $insert_data['twitter'] = $twitter;

                $cols_list = [];
                $vals_list = [];
                foreach ($insert_data as $k => $v) {
                    $cols_list[] = "`$k`";
                    $vals_list[] = is_null($v) ? "NULL" : "'" . $conn->real_escape_string($v) . "'";
                }

                $insert_sql = "INSERT INTO team (" . implode(', ', $cols_list) . ") VALUES (" . implode(', ', $vals_list) . ")";
                try {
                    if ($conn->query($insert_sql)) {
                        $alert_msg = "Employee {$name} ({$emp_id}) successfully registered!";
                        $alert_type = 'success';
                    } else {
                        $alert_msg = "Error registering employee: " . $conn->error;
                        $alert_type = 'danger';
                    }
                } catch (Throwable $e) {
                    $alert_msg = "Error registering employee: " . $e->getMessage();
                    $alert_type = 'danger';
                }
            }
        }
    }

    // 2. EDIT EMPLOYEE
    elseif ($action === 'edit') {
        $id          = intval($_POST['id'] ?? 0);
        $name        = trim($_POST['member_name'] ?? '');
        $role_title  = trim($_POST['role'] ?? '');
        $department  = trim($_POST['department'] ?? 'Operations & Creative');
        $emp_id      = strtoupper(trim($_POST['employee_id'] ?? ''));
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        $gender      = $_POST['gender'] ?? 'Male';
        $status      = $_POST['status'] ?? 'active';
        $show_about  = isset($_POST['show_on_about']) ? 1 : 0;
        $display_ord = intval($_POST['display_order'] ?? 0);
        $blood_group = trim($_POST['blood_group'] ?? '');
        $joined_at   = !empty($_POST['joined_at']) ? $_POST['joined_at'] : null;
        $linkedin    = trim($_POST['linkedin'] ?? '');
        $github      = trim($_POST['github'] ?? '');
        $twitter     = trim($_POST['twitter'] ?? '');

        if ($id <= 0 || empty($name)) {
            $alert_msg = 'Invalid employee record data.';
            $alert_type = 'danger';
        } else {
            // Check duplicate employee_id on other rows
            $is_dup = false;
            if (isset($existing_cols['employee_id'])) {
                try {
                    $check_stmt = $conn->prepare("SELECT id FROM team WHERE employee_id = ? AND id != ?");
                    if ($check_stmt) {
                        $check_stmt->bind_param("si", $emp_id, $id);
                        $check_stmt->execute();
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $is_dup = true;
                        }
                        $check_stmt->close();
                    }
                } catch (Throwable $e) {}
            }

            if ($is_dup) {
                $alert_msg = "Employee ID {$emp_id} is already assigned to another team member.";
                $alert_type = 'danger';
            } else {
                // Fetch current record
                $curr = false;
                try {
                    $curr_res = $conn->query("SELECT * FROM team WHERE id = {$id}");
                    $curr = $curr_res ? $curr_res->fetch_assoc() : false;
                } catch (Throwable $e) {}
                $photo_path = $curr['photo'] ?? null;

                // Check for new photo upload
                if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $filename = 'emp_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $emp_id) . '_' . time() . '.' . $ext;
                        $target = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                            if (!empty($photo_path) && file_exists(__DIR__ . '/../' . $photo_path) && strpos($photo_path, 'arslan_ahmed.png') === false) {
                                @unlink(__DIR__ . '/../' . $photo_path);
                            }
                            $photo_path = 'uploads/team/' . $filename;
                        }
                    }
                }

                // Dynamically build UPDATE matching existing columns
                $update_data = [
                    'member_name' => $name,
                    'role'        => $role_title,
                    'email'       => $email,
                    'phone'       => $phone
                ];
                if (isset($existing_cols['employee_id']))   $update_data['employee_id'] = $emp_id;
                if (isset($existing_cols['department']))    $update_data['department'] = $department;
                if (isset($existing_cols['bio']))           $update_data['bio'] = $bio;
                if (isset($existing_cols['gender']))        $update_data['gender'] = $gender;
                if (isset($existing_cols['status']))        $update_data['status'] = $status;
                if (isset($existing_cols['show_on_about'])) $update_data['show_on_about'] = $show_about;
                if (isset($existing_cols['display_order'])) $update_data['display_order'] = $display_ord;
                if (isset($existing_cols['photo']))         $update_data['photo'] = $photo_path;
                if (isset($existing_cols['blood_group']))   $update_data['blood_group'] = $blood_group;
                if (isset($existing_cols['joined_at']))     $update_data['joined_at'] = $joined_at;
                if (isset($existing_cols['linkedin']))      $update_data['linkedin'] = $linkedin;
                if (isset($existing_cols['github']))        $update_data['github'] = $github;
                if (isset($existing_cols['twitter']))       $update_data['twitter'] = $twitter;

                $set_pairs = [];
                foreach ($update_data as $k => $v) {
                    $set_pairs[] = "`$k` = " . (is_null($v) ? "NULL" : "'" . $conn->real_escape_string($v) . "'");
                }

                $update_sql = "UPDATE team SET " . implode(', ', $set_pairs) . " WHERE id = {$id}";
                try {
                    if ($conn->query($update_sql)) {
                        $alert_msg = "Employee profile for {$name} updated successfully!";
                        $alert_type = 'success';
                    } else {
                        $alert_msg = "Error updating employee: " . $conn->error;
                        $alert_type = 'danger';
                    }
                } catch (Throwable $e) {
                    $alert_msg = "Error updating employee: " . $e->getMessage();
                    $alert_type = 'danger';
                }
            }
        }
    }

    // 3. DELETE EMPLOYEE
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $curr = $conn->query("SELECT member_name, photo FROM team WHERE id = {$id}")->fetch_assoc();
                if ($curr) {
                    if (!empty($curr['photo']) && file_exists(__DIR__ . '/../' . $curr['photo']) && strpos($curr['photo'], 'arslan_ahmed.png') === false) {
                        @unlink(__DIR__ . '/../' . $curr['photo']);
                    }
                    $conn->query("DELETE FROM team WHERE id = {$id}");
                    $alert_msg = "Team member {$curr['member_name']} removed successfully.";
                    $alert_type = 'success';
                }
            } catch (Throwable $e) {
                $alert_msg = "Error deleting member: " . $e->getMessage();
                $alert_type = 'danger';
            }
        }
    }

    // 4. QUICK TOGGLE SHOW ON ABOUT
    elseif ($action === 'toggle_about') {
        $id = intval($_POST['id'] ?? 0);
        $val = intval($_POST['val'] ?? 0);
        if (isset($existing_cols['show_on_about'])) {
            try {
                $conn->query("UPDATE team SET show_on_about = {$val} WHERE id = {$id}");
            } catch (Throwable $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// ── GET Actions (e.g. quick toggle from link) ──
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'toggle_about' && isset($_GET['id']) && isset($existing_cols['show_on_about'])) {
        $id = intval($_GET['id']);
        try {
            $conn->query("UPDATE team SET show_on_about = IF(show_on_about = 1, 0, 1) WHERE id = {$id}");
        } catch (Throwable $e) {}
        header("Location: manage_team.php?msg=toggled");
        exit;
    }
}

// ── Statistics (Completely Defensive against missing columns) ──
$status_sql = isset($existing_cols['status']) ? "SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)" : "COUNT(*)";
$about_sql = isset($existing_cols['show_on_about']) ? "SUM(CASE WHEN show_on_about = 1 THEN 1 ELSE 0 END)" : "0";
$verified_sql = isset($existing_cols['employee_id']) ? "SUM(CASE WHEN employee_id IS NOT NULL AND employee_id != '' THEN 1 ELSE 0 END)" : "0";

try {
    $stats_res = $conn->query("SELECT 
        COUNT(*) as total,
        {$status_sql} as active_count,
        {$about_sql} as about_count,
        {$verified_sql} as verified_count
    FROM team");
    $stats = $stats_res ? $stats_res->fetch_assoc() : ['total' => 0, 'active_count' => 0, 'about_count' => 0, 'verified_count' => 0];
} catch (Throwable $e) {
    $stats = ['total' => 0, 'active_count' => 0, 'about_count' => 0, 'verified_count' => 0];
}

// Suggest next employee ID
$next_emp_suggest = "EMP0001";
try {
    $max_id_res = $conn->query("SELECT MAX(id) as max_id FROM team");
    if ($max_id_res) {
        $next_emp_suggest = sprintf("EMP%04d", ($max_id_res->fetch_assoc()['max_id'] ?? 0) + 1);
    }
} catch (Throwable $e) {}

// ── Filter & Search Queries (Defensive) ──
$search_q = trim($_GET['search'] ?? '');
$dept_filter = trim($_GET['dept'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$about_filter = trim($_GET['about'] ?? '');

$where = ["1=1"];
if (!empty($search_q)) {
    $sq = $conn->real_escape_string($search_q);
    $search_sub = ["member_name LIKE '%{$sq}%'", "role LIKE '%{$sq}%'", "email LIKE '%{$sq}%'"];
    if (isset($existing_cols['employee_id'])) {
        $search_sub[] = "employee_id LIKE '%{$sq}%'";
    }
    $where[] = "(" . implode(" OR ", $search_sub) . ")";
}
if (!empty($dept_filter) && isset($existing_cols['department'])) {
    $df = $conn->real_escape_string($dept_filter);
    $where[] = "department = '{$df}'";
}
if (!empty($status_filter) && isset($existing_cols['status'])) {
    $sf = $conn->real_escape_string($status_filter);
    $where[] = "status = '{$sf}'";
}
if ($about_filter !== '' && isset($existing_cols['show_on_about'])) {
    $af = intval($about_filter);
    $where[] = "show_on_about = {$af}";
}

$where_clause = implode(" AND ", $where);
$order_clause = isset($existing_cols['display_order']) ? "display_order ASC, id DESC" : "id DESC";

try {
    $members = $conn->query("SELECT * FROM team WHERE {$where_clause} ORDER BY {$order_clause}");
} catch (Throwable $e) {
    $members = false;
}

// Fetch distinct departments for filter
$dept_rows = false;
if (isset($existing_cols['department'])) {
    try {
        $dept_rows = $conn->query("SELECT DISTINCT department FROM team WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    } catch (Throwable $e) {}
}
?>

<script src="<?= BASE_URL ?>assets/js/qrcode.min.js"></script>
<script>
if (typeof QRCode === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>');
}
</script>

<style>
    :root {
        --primary: #024442;
        --primary-light: #085a57;
        --accent: #b8f35a;
        --border: #e2e8f0;
        --text-dark: #0f172a;
        --text-muted: #64748b;
    }

    /* ── Table Container ── */
    .table-container {
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }
    .emp-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    .emp-table th {
        background: #f8fafc;
        padding: 12px 18px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    .emp-table td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
    }
    .emp-table tr:last-child td {
        border-bottom: none;
    }
    .emp-table tr:hover td {
        background: #f8fafc;
    }

    /* Member Identity Cell */
    .member-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .member-avatar-wrap {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        flex-shrink: 0;
        overflow: hidden;
    }
    .member-avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    .member-avatar-init {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: var(--primary);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        font-weight: 800;
    }

    .member-name-text {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 0.92rem;
        line-height: 1.2;
    }
    .member-id-tag {
        display: inline-block;
        font-family: monospace;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    /* Status Pills */
    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: capitalize;
    }
    .status-pill.active { background: #dcfce7; color: #15803d; }
    .status-pill.on_leave { background: #fef3c7; color: #b45309; }
    .status-pill.inactive { background: #fee2e2; color: #b91c1c; }

    /* Action Buttons */
    .action-btn-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .act-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        border: 1px solid var(--border);
        background: #ffffff;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .act-btn:hover {
        background: #f8fafc;
        color: var(--text-dark);
    }
    .act-btn.id-badge-btn:hover {
        color: var(--primary);
        border-color: var(--primary);
        background: #f0fdf4;
    }
    .act-btn.edit-btn:hover {
        color: #0284c7;
        border-color: #0284c7;
        background: #f0f9ff;
    }
    .act-btn.del-btn:hover {
        color: #dc2626;
        border-color: #dc2626;
        background: #fef2f2;
    }

    /* Toggle Switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 34px;
        height: 18px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #cbd5e1;
        transition: .25s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .25s;
        border-radius: 50%;
    }
    input:checked + .slider { background-color: #10b981; }
    input:checked + .slider:before { transform: translateX(16px); }

    @media (max-width: 992px) {
        .emp-table { display: block; overflow-x: auto; }
    }
</style>

<div class="height-100 p-3 p-md-4">

    <!-- Alert Message -->
    <?php if (!empty($alert_msg)): ?>
        <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert" style="border-radius:12px;">
            <i class="fas <?= $alert_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($alert_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ── Page Header ── -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color:var(--primary); font-size:1.55rem;">Team Management</h2>
            <p class="text-muted mb-0" style="font-size:0.88rem;">Manage employee records and QR verification credentials</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"
                    style="background:var(--primary); border-color:var(--primary); color:var(--accent); font-weight:700; border-radius:8px; padding:9px 18px; font-size:0.88rem;">
                <i class="fas fa-plus me-1"></i> Add Member
            </button>
        </div>
    </div>

    <!-- ── Stats Row ── -->
    <div class="stats-row mb-4" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
        <div style="background:#ffffff; border:1px solid var(--border); border-radius:12px; padding:16px 20px;">
            <div style="font-size:1.6rem; font-weight:800; color:var(--primary);"><?= intval($stats['total'] ?? 0) ?></div>
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-top:2px;">Total Members</div>
        </div>
        <div style="background:#ffffff; border:1px solid var(--border); border-radius:12px; padding:16px 20px;">
            <div style="font-size:1.6rem; font-weight:800; color:#10b981;"><?= intval($stats['active_count'] ?? 0) ?></div>
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-top:2px;">Active Staff</div>
        </div>
        <div style="background:#ffffff; border:1px solid var(--border); border-radius:12px; padding:16px 20px;">
            <div style="font-size:1.6rem; font-weight:800; color:var(--text-dark);"><?= intval($stats['about_count'] ?? 0) ?></div>
            <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-top:2px;">On About Page</div>
        </div>
    </div>

    <!-- ── Filter & Search Toolbar ── -->
    <div class="mb-3" style="background:#ffffff; border:1px solid var(--border); border-radius:12px; padding:12px 16px;">
        <form method="GET" action="manage_team.php" class="d-flex align-items-center gap-2 flex-wrap m-0">
            <div style="position:relative; flex:1; min-width:220px;">
                <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.85rem;"></i>
                <input type="text" name="search" class="form-control" placeholder="Search name, ID, role..." value="<?= htmlspecialchars($search_q) ?>" style="padding-left:34px; border-radius:8px; font-size:0.88rem;">
            </div>

            <select name="dept" class="form-select" onchange="this.form.submit()" style="width:auto; border-radius:8px; font-size:0.88rem;">
                <option value="">All Departments</option>
                <?php if ($dept_rows): while ($d = $dept_rows->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($d['department']) ?>" <?= $dept_filter === $d['department'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['department']) ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>

            <select name="status" class="form-select" onchange="this.form.submit()" style="width:auto; border-radius:8px; font-size:0.88rem;">
                <option value="">All Statuses</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="on_leave" <?= $status_filter === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button type="submit" class="btn btn-sm btn-dark" style="border-radius:8px; padding:7px 14px; font-size:0.85rem;">
                Filter
            </button>
            <?php if (!empty($search_q) || !empty($dept_filter) || !empty($status_filter)): ?>
                <a href="manage_team.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px; padding:7px 12px; font-size:0.85rem;">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Employee Table ── -->
    <div class="table-container">
        <table class="emp-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Role & Dept</th>
                    <th>Contact</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>About Page</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members && $members->num_rows > 0): ?>
                    <?php while ($row = $members->fetch_assoc()): 
                        $eid = htmlspecialchars($row['employee_id'] ?? ('EMP' . str_pad($row['id'], 4, '0', STR_PAD_LEFT)));
                        $mname = htmlspecialchars($row['member_name']);
                        $mrole = htmlspecialchars($row['role'] ?? 'Staff');
                        $mdept = htmlspecialchars($row['department'] ?? 'Operations');
                        $memail = htmlspecialchars($row['email'] ?? '-');
                        $mphone = htmlspecialchars($row['phone'] ?? '-');
                        $mstatus = strtolower($row['status'] ?? 'active');
                        $show_about = intval($row['show_on_about'] ?? 1);
                        $photo = !empty($row['photo']) ? BASE_URL . ltrim($row['photo'], '/') : '';
                        $joined = !empty($row['joined_at']) ? date('M d, Y', strtotime($row['joined_at'])) : '-';
                        $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td>
                                <div class="member-cell">
                                    <div class="member-avatar-wrap">
                                        <?php if (!empty($photo)): ?>
                                            <img src="<?= $photo ?>" alt="<?= $mname ?>" class="member-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="member-avatar-init" style="display:none;"><?= strtoupper(substr($mname, 0, 1)) ?></div>
                                        <?php else: ?>
                                            <div class="member-avatar-init"><?= strtoupper(substr($mname, 0, 1)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="member-name-text"><?= $mname ?></div>
                                        <span class="member-id-tag"><?= $eid ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div style="font-weight:600; color:var(--text-dark);"><?= $mrole ?></div>
                                <div style="font-size:0.8rem; color:var(--text-muted);"><?= $mdept ?></div>
                            </td>

                            <td>
                                <div style="font-size:0.85rem; color:var(--text-dark);"><?= $memail ?></div>
                                <?php if ($mphone !== '-'): ?>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= $mphone ?></div>
                                <?php endif; ?>
                            </td>

                            <td style="color:var(--text-muted); font-size:0.85rem;">
                                <?= $joined ?>
                            </td>

                            <td>
                                <span class="status-pill <?= $mstatus ?>">
                                    <?= str_replace('_', ' ', $mstatus) ?>
                                </span>
                            </td>

                            <td>
                                <label class="switch" title="Toggle visibility on About page">
                                    <input type="checkbox" <?= $show_about === 1 ? 'checked' : '' ?> onchange="toggleAbout(<?= $row['id'] ?>, this.checked ? 1 : 0)">
                                    <span class="slider"></span>
                                </label>
                            </td>

                            <td style="text-align: right;">
                                <div class="action-btn-group" style="justify-content: flex-end;">
                                    <button type="button" class="act-btn id-badge-btn" title="QR Code" onclick='openQrModal(<?= $row_json ?>)'>
                                        <i class="fas fa-qrcode"></i>
                                    </button>
                                    <button type="button" class="act-btn edit-btn" title="Edit Profile" onclick='openEditModal(<?= $row_json ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="act-btn del-btn" title="Delete Member" onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes($mname) ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <p class="mb-0">No employees found.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 1: ADD EMPLOYEE                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px; border:none; box-shadow:0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:var(--primary); color:#ffffff; border-radius:18px 18px 0 0; padding:18px 25px;">
                <h5 class="modal-title font-weight-bold" id="addEmployeeLabel" style="color:var(--accent);">
                    <i class="fas fa-user-plus me-2"></i> Add New Employee / Staff Member
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="manage_team.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body" style="padding: 25px;">
                    <div class="row g-3">
                        
                        <!-- Full Name -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="member_name" class="form-control" placeholder="e.g. Arslan Ahmed" required style="border-radius:10px;">
                        </div>

                        <!-- Employee ID -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Official Employee ID</label>
                            <input type="text" name="employee_id" class="form-control font-monospace" placeholder="e.g. <?= $next_emp_suggest ?>" value="<?= $next_emp_suggest ?>" style="border-radius:10px; font-weight:700;">
                            <small class="text-muted" style="font-size:0.75rem;">Used for QR code scanning & credential verification.</small>
                        </div>

                        <!-- Designation / Role -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Designation / Title <span class="text-danger">*</span></label>
                            <input type="text" name="role" class="form-control" placeholder="e.g. Admin, Lead Designer, Web Developer" required style="border-radius:10px;">
                        </div>

                        <!-- Department -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Department</label>
                            <select name="department" class="form-select" style="border-radius:10px;">
                                <option value="Executive Leadership & Operations">Executive Leadership & Operations</option>
                                <option value="Creative & UI/UX Design">Creative & UI/UX Design</option>
                                <option value="Web & Software Engineering">Web & Software Engineering</option>
                                <option value="Digital Marketing & Content">Digital Marketing & Content</option>
                                <option value="Human Resources">Human Resources</option>
                                <option value="Client Relations & Sales">Client Relations & Sales</option>
                            </select>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Work Email</label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. contact@graphicafix.com" style="border-radius:10px;">
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Official Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 0345 4568986" style="border-radius:10px;">
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Profile Headshot Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/png, image/jpeg, image/webp" style="border-radius:10px;">
                            <small class="text-muted" style="font-size:0.75rem;">Square headshot recommended (PNG, JPG, WEBP).</small>
                        </div>

                        <!-- Gender & Blood Group -->
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Gender</label>
                            <select name="gender" class="form-select" style="border-radius:10px;">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Blood Group</label>
                            <input type="text" name="blood_group" class="form-control" placeholder="e.g. O+, A+" style="border-radius:10px;">
                        </div>

                        <!-- Date of Joining -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Date of Joining</label>
                            <input type="date" name="joined_at" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:10px;">
                        </div>

                        <!-- Status & Display Order -->
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Status</label>
                            <select name="status" class="form-select" style="border-radius:10px;">
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Display Order</label>
                            <input type="number" name="display_order" class="form-control" value="0" min="0" style="border-radius:10px;">
                        </div>

                        <!-- Bio Description -->
                        <div class="col-12">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Bio / About Summary</label>
                            <textarea name="bio" class="form-control" rows="2" placeholder="Brief summary displayed on the website About page..." style="border-radius:10px;"></textarea>
                        </div>

                        <!-- Social Links -->
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-linkedin text-primary me-1"></i> LinkedIn URL</label>
                            <input type="url" name="linkedin" class="form-control form-control-sm" placeholder="https://linkedin.com/in/..." style="border-radius:8px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-github me-1"></i> GitHub / Portfolio</label>
                            <input type="url" name="github" class="form-control form-control-sm" placeholder="https://..." style="border-radius:8px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-twitter text-info me-1"></i> Twitter / X</label>
                            <input type="url" name="twitter" class="form-control form-control-sm" placeholder="https://..." style="border-radius:8px;">
                        </div>

                        <!-- Checkbox: Show on About page -->
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_about" id="addShowAbout" value="1" checked>
                                <label class="form-check-label font-weight-bold" for="addShowAbout">
                                    Display this employee's card on the public About page
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer" style="background:#f8fafc; border-top:1px solid var(--border); border-radius:0 0 18px 18px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary); border-color:var(--primary); color:var(--accent); font-weight:700; border-radius:8px;">
                        <i class="fas fa-check me-1"></i> Save Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2: EDIT EMPLOYEE                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px; border:none; box-shadow:0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:var(--primary); color:#ffffff; border-radius:18px 18px 0 0; padding:18px 25px;">
                <h5 class="modal-title font-weight-bold" id="editEmployeeLabel" style="color:var(--accent);">
                    <i class="fas fa-user-edit me-2"></i> Edit Employee Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="manage_team.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="modal-body" style="padding: 25px;">
                    <div class="row g-3">
                        
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="member_name" id="editName" class="form-control" required style="border-radius:10px;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Official Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" id="editEmpId" class="form-control font-monospace" required style="border-radius:10px; font-weight:700;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Designation / Title <span class="text-danger">*</span></label>
                            <input type="text" name="role" id="editRole" class="form-control" required style="border-radius:10px;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Department</label>
                            <select name="department" id="editDepartment" class="form-select" style="border-radius:10px;">
                                <option value="Executive Leadership & Operations">Executive Leadership & Operations</option>
                                <option value="Creative & UI/UX Design">Creative & UI/UX Design</option>
                                <option value="Web & Software Engineering">Web & Software Engineering</option>
                                <option value="Digital Marketing & Content">Digital Marketing & Content</option>
                                <option value="Human Resources">Human Resources</option>
                                <option value="Client Relations & Sales">Client Relations & Sales</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Work Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control" style="border-radius:10px;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Official Phone</label>
                            <input type="text" name="phone" id="editPhone" class="form-control" style="border-radius:10px;">
                        </div>

                        <!-- Current photo preview & upload -->
                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Replace Profile Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/png, image/jpeg, image/webp" style="border-radius:10px;">
                            <div id="editPhotoPreview" class="mt-2" style="font-size:0.75rem; color:#64748b;"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Gender</label>
                            <select name="gender" id="editGender" class="form-select" style="border-radius:10px;">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Blood Group</label>
                            <input type="text" name="blood_group" id="editBloodGroup" class="form-control" style="border-radius:10px;">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Date of Joining</label>
                            <input type="date" name="joined_at" id="editJoinedAt" class="form-control" style="border-radius:10px;">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Status</label>
                            <select name="status" id="editStatus" class="form-select" style="border-radius:10px;">
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Display Order</label>
                            <input type="number" name="display_order" id="editDisplayOrder" class="form-control" min="0" style="border-radius:10px;">
                        </div>

                        <div class="col-12">
                            <label class="form-label font-weight-bold" style="font-size:0.85rem;">Bio / About Summary</label>
                            <textarea name="bio" id="editBio" class="form-control" rows="2" style="border-radius:10px;"></textarea>
                        </div>

                        <!-- Socials -->
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-linkedin text-primary me-1"></i> LinkedIn URL</label>
                            <input type="url" name="linkedin" id="editLinkedin" class="form-control form-control-sm" style="border-radius:8px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-github me-1"></i> GitHub / Portfolio</label>
                            <input type="url" name="github" id="editGithub" class="form-control form-control-sm" style="border-radius:8px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:0.8rem;"><i class="fab fa-twitter text-info me-1"></i> Twitter / X</label>
                            <input type="url" name="twitter" id="editTwitter" class="form-control form-control-sm" style="border-radius:8px;">
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_on_about" id="editShowAbout" value="1">
                                <label class="form-check-label font-weight-bold" for="editShowAbout">
                                    Display this employee's card on the public About page
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer" style="background:#f8fafc; border-top:1px solid var(--border); border-radius:0 0 18px 18px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--primary); border-color:var(--primary); color:var(--accent); font-weight:700; border-radius:8px;">
                        <i class="fas fa-save me-1"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 3: EMPLOYEE QR CODE VIEWER (PNG ONLY - NO FULL CARD)            -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 15px 40px rgba(0,0,0,0.15); overflow:hidden;">
            
            <div class="modal-header" style="background:#ffffff; border-bottom:1px solid var(--border); padding:14px 20px;">
                <h6 class="modal-title fw-bold mb-0" id="qrCodeModalLabel" style="color:var(--primary); font-size:1.05rem;">
                    <i class="fas fa-qrcode me-1"></i> Employee QR Code
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body text-center" style="padding: 24px 20px; background:#ffffff;">
                <!-- QR Code PNG Container -->
                <div id="modalQrBox" style="width:200px; height:200px; margin:0 auto; padding:8px; background:#ffffff; border:1px solid var(--border); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <!-- QR Code PNG rendered here -->
                </div>

                <!-- Employee Details Under QR -->
                <div style="margin-top:14px;">
                    <div id="modalEmpName" style="font-size:1.1rem; font-weight:800; color:var(--primary);"></div>
                    <div id="modalEmpRole" style="font-size:0.85rem; color:var(--text-muted); font-weight:500; margin-top:2px;"></div>
                    <div id="modalEmpId" style="font-family:monospace; font-weight:700; font-size:0.82rem; color:var(--primary); background:rgba(2,68,66,0.08); padding:3px 10px; border-radius:6px; display:inline-block; margin-top:6px;"></div>
                </div>
            </div>

            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid var(--border); padding:12px 18px; display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px; font-weight:600; padding:6px 14px;">
                    Close
                </button>
                <button type="button" class="btn btn-sm btn-primary" onclick="downloadQrCode()" style="background:var(--primary); border-color:var(--primary); color:var(--accent); font-weight:700; border-radius:8px; padding:6px 16px;">
                    <i class="fas fa-download me-1"></i> Download PNG
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- DELETE CONFIRMATION FORM                                               -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<form id="deleteForm" action="manage_team.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
// ── Quick Toggle Show on About ──
function toggleAbout(id, val) {
    const fd = new FormData();
    fd.append('action', 'toggle_about');
    fd.append('id', id);
    fd.append('val', val);

    fetch('manage_team.php', {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(res => {
        // Updated
    }).catch(err => console.error(err));
}

// ── Open Edit Modal & Populate Fields ──
function openEditModal(emp) {
    document.getElementById('editId').value = emp.id;
    document.getElementById('editName').value = emp.member_name || '';
    document.getElementById('editEmpId').value = emp.employee_id || '';
    document.getElementById('editRole').value = emp.role || '';
    document.getElementById('editDepartment').value = emp.department || 'Operations & Creative';
    document.getElementById('editEmail').value = emp.email || '';
    document.getElementById('editPhone').value = emp.phone || '';
    document.getElementById('editGender').value = emp.gender || 'Male';
    document.getElementById('editBloodGroup').value = emp.blood_group || '';
    document.getElementById('editJoinedAt').value = emp.joined_at || '';
    document.getElementById('editStatus').value = emp.status || 'active';
    document.getElementById('editDisplayOrder').value = emp.display_order || 0;
    document.getElementById('editBio').value = emp.bio || '';
    document.getElementById('editLinkedin').value = emp.linkedin || '';
    document.getElementById('editGithub').value = emp.github || '';
    document.getElementById('editTwitter').value = emp.twitter || '';
    document.getElementById('editShowAbout').checked = (parseInt(emp.show_on_about) === 1);

    const prevWrap = document.getElementById('editPhotoPreview');
    if (emp.photo) {
        prevWrap.innerHTML = `Current Photo: <a href="<?= BASE_URL ?>${emp.photo}" target="_blank">View File</a>`;
    } else {
        prevWrap.innerHTML = 'No photo uploaded yet.';
    }

    new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
}

// ── Delete Confirmation ──
function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to permanently delete employee "${name}"? This action cannot be undone.`)) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// ── Open QR Code Modal (PNG Only) ──
let currentModalEmp = null;

function openQrModal(emp) {
    currentModalEmp = emp;
    const empId = emp.employee_id || ('EMP' + String(emp.id).padStart(4, '0'));
    const verifyUrl = "<?= BASE_URL ?>verify-employee.php?id=" + encodeURIComponent(empId);

    document.getElementById('modalEmpName').innerText = emp.member_name || '';
    document.getElementById('modalEmpRole').innerText = (emp.role || 'Staff Member') + (emp.department ? ' • ' + emp.department : '');
    document.getElementById('modalEmpId').innerText = empId;

    const qrBox = document.getElementById('modalQrBox');
    qrBox.innerHTML = '';
    const qrApiUrl = '<?= BASE_URL ?>api/qr_code.php?id=' + encodeURIComponent(empId);

    let qrSuccess = false;
    if (typeof QRCode !== 'undefined') {
        try {
            new QRCode(qrBox, {
                text: verifyUrl,
                width: 190,
                height: 190,
                colorDark : "#024442",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
            qrSuccess = true;
        } catch (e) {
            console.warn("Client QR failed, using server fallback", e);
        }
    }

    if (!qrSuccess) {
        qrBox.innerHTML = `<img src="${qrApiUrl}" alt="QR Code" style="width:190px;height:190px;object-fit:contain;display:block;">`;
    }

    try {
        new bootstrap.Modal(document.getElementById('qrCodeModal')).show();
    } catch (e) {
        document.getElementById('qrCodeModal').style.display = 'block';
    }
}

// Alias for backwards compatibility
function openIdCardModal(emp) {
    openQrModal(emp);
}

function downloadQrCode() {
    if (!currentModalEmp) return;
    const empId = currentModalEmp.employee_id || ('EMP' + String(currentModalEmp.id).padStart(4, '0'));
    const qrCanvas = document.querySelector('#modalQrBox canvas');
    if (qrCanvas) {
        const link = document.createElement('a');
        link.download = empId + '_QR.png';
        link.href = qrCanvas.toDataURL('image/png');
        link.click();
    } else {
        // Direct download using pure PHP server endpoint
        window.location.href = '<?= BASE_URL ?>api/qr_code.php?id=' + encodeURIComponent(empId) + '&download=1';
    }
}
</script>

<?php include 'dashboard_footer.php'; ?>
