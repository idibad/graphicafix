<?php
include 'dashboard_header.php';

if ($role !== 'super_admin' && $role !== 'superadmin') {
    header("Location: index.php?error=unauthorized");
    exit;
}

// ── Database Auto-Patch ──
// Ensure the role column can accept 'teacher' and 'student' by changing it to a VARCHAR
$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student'");

// Auto-patch status column
$cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
if (!in_array('status', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
}
if (!in_array('gender', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN gender VARCHAR(10) NOT NULL DEFAULT 'Male' AFTER name");
}

// Fetch all users
$sql = "SELECT user_id, name, gender, email, username, role, status, department, job_title, last_login, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

function getStatus($last_login, $user_status = 'active') {
    $u_stat = strtolower(trim($user_status ?? 'active'));
    if ($u_stat === 'deactivated' || $u_stat === 'deactivate') {
        return ['class' => 'deactivated', 'text' => 'Deactivated', 'style' => 'background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;'];
    }
    if (!empty($last_login) && strtotime($last_login) > strtotime('-7 days')) {
        return ['class' => 'active', 'text' => 'Active', 'style' => ''];
    }
    return ['class' => 'inactive', 'text' => 'Inactive', 'style' => ''];
}

// Role Badge Color & Icon Helper
function getRoleBadge($role) {
    $r = strtolower($role);
    if ($r === 'super_admin' || $r === 'superadmin') return ['bg' => '#fef3c7', 'text' => '#b45309', 'icon' => 'fa-crown', 'label' => 'Super Admin'];
    if ($r === 'admin')   return ['bg' => '#fff4e5', 'text' => '#e17055', 'icon' => 'fa-user-shield', 'label' => 'Admin Officer'];
    if ($r === 'pm')      return ['bg' => '#fce7f3', 'text' => '#db2777', 'icon' => 'fa-project-diagram', 'label' => 'Project Manager'];
    if ($r === 'manager') return ['bg' => '#e0f7fa', 'text' => '#00838f', 'icon' => 'fa-user-tie', 'label' => 'Manager'];
    if ($r === 'sales')   return ['bg' => '#d1fae5', 'text' => '#065f46', 'icon' => 'fa-handshake', 'label' => 'Sales'];
    if ($r === 'hrm')     return ['bg' => '#e0e7ff', 'text' => '#4338ca', 'icon' => 'fa-briefcase', 'label' => 'HR Management'];
    if ($r === 'teacher') return ['bg' => '#e0f2fe', 'text' => '#0284c7', 'icon' => 'fa-chalkboard-teacher', 'label' => 'Teacher'];
    if ($r === 'student') return ['bg' => '#d7f8b8', 'text' => '#2b7a2b', 'icon' => 'fa-user-graduate', 'label' => 'Student'];
    if ($r === 'client' || $r === 'client_sub') return ['bg' => '#fef9c3', 'text' => '#854d0e', 'icon' => 'fa-building', 'label' => ($r === 'client' ? 'Client' : 'Client Sub-user')];
    if ($r === 'user')    return ['bg' => '#f3e8ff', 'text' => '#7c3aed', 'icon' => 'fa-users', 'label' => 'Staff / User'];
    return ['bg' => '#f0f0f0', 'text' => '#555', 'icon' => 'fa-user-tag', 'label' => ucfirst($role)];
}

// Consistent Avatar Gradient Generator
function getUserAvatarGradient($str) {
    $gradients = [
        ['#4f46e5', '#7c3aed'],
        ['#0284c7', '#0369a1'],
        ['#059669', '#10b981'],
        ['#d97706', '#f59e0b'],
        ['#dc2626', '#e11d48'],
        ['#00838f', '#00acc1'],
        ['#db2777', '#f43f5e']
    ];
    $idx = abs(crc32($str)) % count($gradients);
    return "linear-gradient(135deg, {$gradients[$idx][0]}, {$gradients[$idx][1]})";
}

// ── Group Users by Role & Count Stats ──
$total_users = $result->num_rows;
$active_users = 0;
$deactivated_users = 0;
$super_admin_count = 0;
$admin_count = 0;
$male_count = 0;
$female_count = 0;

$all_users = [];
$users_grouped = [
    'super_admin' => [],
    'admin'       => [],
    'manager'     => [],
    'pm'          => [],
    'sales'       => [],
    'hrm'         => [],
    'teacher'     => [],
    'student'     => [],
    'client'      => [],
    'user'        => [],
    'other'       => []
];

foreach($result as $row) {
    $all_users[] = $row;
    $u_stat = strtolower(trim($row['status'] ?? 'active'));
    if ($u_stat === 'deactivated' || $u_stat === 'deactivate') {
        $deactivated_users++;
    } else {
        $active_users++;
    }

    $g_check = strtolower(trim($row['gender'] ?? 'male'));
    if ($g_check === 'female') {
        $female_count++;
    } else {
        $male_count++;
    }
    
    $r = strtolower($row['role']);
    if ($r === 'super_admin' || $r === 'superadmin') {
        $super_admin_count++;
        $users_grouped['super_admin'][] = $row;
    } elseif ($r === 'admin') {
        $admin_count++;
        $users_grouped['admin'][] = $row;
    } elseif ($r === 'client' || $r === 'client_sub') {
        $users_grouped['client'][] = $row;
    } elseif (array_key_exists($r, $users_grouped)) {
        $users_grouped[$r][] = $row;
    } else {
        $users_grouped['other'][] = $row;
    }
}
$result->data_seek(0);

// FontAwesome integrated Role Titles
$role_titles = [
    'super_admin' => ['title' => 'Super Administrators (All Rights)', 'icon' => 'fa-crown', 'color' => '#f59e0b'],
    'admin'       => ['title' => 'Admin Officers / Operations', 'icon' => 'fa-user-shield', 'color' => '#e17055'],
    'manager'     => ['title' => 'Managers', 'icon' => 'fa-user-tie', 'color' => '#00838f'],
    'pm'          => ['title' => 'Project Managers', 'icon' => 'fa-project-diagram', 'color' => '#db2777'],
    'sales'       => ['title' => 'Sales & Business Development', 'icon' => 'fa-handshake', 'color' => '#059669'],
    'hrm'         => ['title' => 'HR Management', 'icon' => 'fa-briefcase', 'color' => '#4338ca'],
    'teacher'     => ['title' => 'Teachers / Instructors', 'icon' => 'fa-chalkboard-teacher', 'color' => '#0284c7'],
    'student'     => ['title' => 'Enrolled Students', 'icon' => 'fa-user-graduate', 'color' => '#2b7a2b'],
    'client'      => ['title' => 'Clients & Client Users', 'icon' => 'fa-building', 'color' => '#854d0e'],
    'user'        => ['title' => 'General Staff / Users', 'icon' => 'fa-users', 'color' => '#7c3aed'],
    'other'       => ['title' => 'Other Custom Roles', 'icon' => 'fa-user-tag', 'color' => '#555555']
];
?>

<style>
/* --- Manage Users Modern UI Polish --- */
.users-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.users-page-title h2 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
}
.users-page-title p {
    margin: 4px 0 0 0;
    color: #64748b;
    font-size: 13.5px;
}
.add-user-hero-btn {
    background: linear-gradient(135deg, var(--primary, #024442) 0%, #035e5b 100%);
    color: #ffffff;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(2,68,66,0.25);
    transition: all 0.2s ease;
}
.add-user-hero-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(2,68,66,0.35);
    color: #ffffff;
}

/* Stats Cards Grid */
.u-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.u-stat-box {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    user-select: none;
}
.u-stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}
.u-stat-box.active-filter {
    border-color: var(--primary, #024442);
    background: #f0fdf4;
    box-shadow: 0 0 0 2px rgba(2,68,66,0.18);
}
.u-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.u-stat-number {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.u-stat-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-top: 3px;
}

/* Control Toolbar & Filters */
.users-control-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}
.filter-toolbar-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.search-wrapper {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 380px;
}
.search-wrapper i.fa-search {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 13.5px;
}
.search-wrapper input {
    width: 100%;
    padding: 9px 36px 9px 38px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 13.5px;
    background: #f8fafc;
    outline: none;
    box-sizing: border-box;
    transition: all 0.2s ease;
    font-family: inherit;
}
.search-wrapper input:focus {
    border-color: var(--primary, #024442);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(2,68,66,0.1);
}
.clear-search-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #e2e8f0;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
}
.filter-dropdowns {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.filter-select {
    padding: 8px 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    background: #f8fafc;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}
.filter-select:focus {
    border-color: var(--primary, #024442);
    background: #fff;
}
.view-switch {
    display: inline-flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 9px;
    border: 1px solid #e2e8f0;
}
.view-switch button {
    border: none;
    background: transparent;
    padding: 6px 11px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.view-switch button.active {
    background: #ffffff;
    color: var(--primary, #024442);
    box-shadow: 0 2px 5px rgba(0,0,0,0.06);
}
.reset-filters-btn {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fca5a5;
    padding: 7px 12px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.reset-filters-btn:hover {
    background: #fecaca;
}

/* Counter & Summary Bar */
.filter-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 12px 0 0 0;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
    font-size: 12.5px;
    color: #64748b;
}

/* User Table Component */
.u-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,0.03);
    margin-bottom: 24px;
}
.u-table-header-title {
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1.5px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.u-table-header-title h4 {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.u-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    text-align: left;
}
.u-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 18px;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}
.u-table td {
    padding: 13px 18px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.u-table tbody tr {
    transition: background-color 0.15s ease;
}
.u-table tbody tr:hover {
    background-color: #f8fafc;
}

/* User Info Layout */
.user-info-box {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar-initial {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.user-title-group {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.user-display-name {
    font-weight: 700;
    font-size: 13.5px;
    color: #0f172a;
}
.user-gender-tag {
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.2px;
    display: inline-block;
}
.user-gender-tag.Male { background: #e0f2fe; color: #0284c7; }
.user-gender-tag.Female { background: #fce7f3; color: #db2777; }
.user-username-badge {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}

/* Status Pill */
.status-indicator-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.status-indicator-badge.active {
    background: #ecfdf5;
    color: #10b981;
    border: 1px solid #a7f3d0;
}
.status-indicator-badge.active .stat-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 2px rgba(16,185,129,0.25);
}
.status-indicator-badge.deactivated {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.status-indicator-badge.deactivated .stat-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #dc2626;
}

/* Action Buttons Group */
.u-action-group {
    display: flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}
.u-act-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    text-decoration: none;
    line-height: 1.2;
}
.u-act-btn.btn-edit {
    background: #f0f9ff;
    color: #0284c7;
    border-color: #bae6fd;
}
.u-act-btn.btn-edit:hover {
    background: #0284c7;
    color: #ffffff;
    border-color: #0284c7;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(2,132,199,0.2);
}
.u-act-btn.btn-activate {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #bbf7d0;
}
.u-act-btn.btn-activate:hover {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(22,163,74,0.2);
}
.u-act-btn.btn-deactivate {
    background: #fff7ed;
    color: #ea580c;
    border-color: #fed7aa;
}
.u-act-btn.btn-deactivate:hover {
    background: #ea580c;
    color: #ffffff;
    border-color: #ea580c;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(234,88,12,0.2);
}
.u-act-btn.btn-del {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
    padding: 5px 8px;
}
.u-act-btn.btn-del:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(220,38,38,0.2);
}
.u-act-btn:disabled {
    opacity: 0.35 !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

/* Empty State Box */
.u-empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #94a3b8;
}
.u-empty-state i {
    font-size: 2.5rem;
    margin-bottom: 12px;
    color: #cbd5e1;
}
</style>

<div class="height-100">

    <!-- Top Page Header -->
    <div class="users-page-header">
        <div class="users-page-title">
            <h2><i class="fas fa-users-cog" style="color: var(--primary);"></i> User Management</h2>
            <p>Manage system credentials, assign staff roles, and oversee account security statuses.</p>
        </div>
        <div>
            <button class="add-user-hero-btn" onclick="openUserModal('add')">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Quick Stat Cards (Clickable Filter Controls) -->
    <div class="u-stats-grid">
        <div class="u-stat-box" id="statCardAll" onclick="setQuickFilter('all')" title="Click to view all users">
            <div class="u-stat-icon" style="background:#e0f2fe; color:#0284c7;">
                <i class="fas fa-users"></i>
            </div>
            <div class="u-stat-info">
                <div class="u-stat-number"><?= $total_users ?></div>
                <div class="u-stat-label">Total Users</div>
            </div>
        </div>

        <div class="u-stat-box" id="statCardActive" onclick="setQuickFilter('active')" title="Click to filter active users">
            <div class="u-stat-icon" style="background:#ecfdf5; color:#10b981;">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="u-stat-info">
                <div class="u-stat-number"><?= $active_users ?></div>
                <div class="u-stat-label">Active Users</div>
            </div>
        </div>

        <div class="u-stat-box" id="statCardDeactivated" onclick="setQuickFilter('deactivated')" title="Click to filter deactivated users">
            <div class="u-stat-icon" style="background:#fef2f2; color:#ef4444;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="u-stat-info">
                <div class="u-stat-number"><?= $deactivated_users ?></div>
                <div class="u-stat-label">Deactivated</div>
            </div>
        </div>

        <div class="u-stat-box" id="statCardSuperAdmin" onclick="setQuickFilter('super_admin')" title="Click to filter super admins">
            <div class="u-stat-icon" style="background:#fef3c7; color:#f59e0b;">
                <i class="fas fa-crown"></i>
            </div>
            <div class="u-stat-info">
                <div class="u-stat-number"><?= $super_admin_count ?></div>
                <div class="u-stat-label">Super Admins</div>
            </div>
        </div>

        <div class="u-stat-box" id="statCardAdmin" onclick="setQuickFilter('admin')" title="Click to filter admin officers">
            <div class="u-stat-icon" style="background:#fff4e5; color:#e17055;">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="u-stat-info">
                <div class="u-stat-number"><?= $admin_count ?></div>
                <div class="u-stat-label">Admin Officers</div>
            </div>
        </div>
    </div>

    <!-- Filter & Toolbar Controls -->
    <div class="users-control-card">
        <div class="filter-toolbar-row">
            <!-- Search -->
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearchInput" placeholder="Search by name, email, or username..." oninput="applyUserFilters()">
                <button type="button" id="clearSearchBtn" class="clear-search-btn" style="display:none;" onclick="clearSearchInput()">&times;</button>
            </div>

            <!-- Dropdowns & Tools -->
            <div class="filter-dropdowns">
                <!-- Role Filter -->
                <select id="roleFilterSelect" class="filter-select" onchange="applyUserFilters()">
                    <option value="all">All Roles (<?= $total_users ?>)</option>
                    <option value="super_admin">Super Admins (<?= $super_admin_count ?>)</option>
                    <option value="admin">Admin Officers (<?= $admin_count ?>)</option>
                    <option value="manager">Managers (<?= count($users_grouped['manager'] ?? []) ?>)</option>
                    <option value="pm">Project Managers (<?= count($users_grouped['pm'] ?? []) ?>)</option>
                    <option value="sales">Sales & Leads (<?= count($users_grouped['sales'] ?? []) ?>)</option>
                    <option value="hrm">HR Management (<?= count($users_grouped['hrm'] ?? []) ?>)</option>
                    <option value="user">Staff / Users (<?= count($users_grouped['user'] ?? []) ?>)</option>
                    <option value="teacher">Teachers (<?= count($users_grouped['teacher'] ?? []) ?>)</option>
                    <option value="student">Students (<?= count($users_grouped['student'] ?? []) ?>)</option>
                    <option value="client">Clients (<?= count($users_grouped['client'] ?? []) ?>)</option>
                    <option value="other">Other Roles (<?= count($users_grouped['other'] ?? []) ?>)</option>
                </select>

                <!-- Status Filter -->
                <select id="statusFilterSelect" class="filter-select" onchange="applyUserFilters()">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="deactivated">Deactivated Only</option>
                </select>

                <!-- Gender Filter -->
                <select id="genderFilterSelect" class="filter-select" onchange="applyUserFilters()">
                    <option value="all">All Genders</option>
                    <option value="male">Male (<?= $male_count ?>)</option>
                    <option value="female">Female (<?= $female_count ?>)</option>
                </select>

                <!-- View Switcher -->
                <div class="view-switch">
                    <button type="button" id="viewGroupedBtn" class="active" onclick="switchViewMode('grouped')" title="Categorized by Role">
                        <i class="fas fa-layer-group"></i> Grouped
                    </button>
                    <button type="button" id="viewFlatBtn" onclick="switchViewMode('flat')" title="Single Unified Table">
                        <i class="fas fa-list"></i> Unified Table
                    </button>
                </div>

                <!-- Reset Filters Button -->
                <button type="button" id="resetFiltersBtn" class="reset-filters-btn" style="display:none;" onclick="resetAllFilters()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>

        <!-- Summary & Counter Line -->
        <div class="filter-summary-row">
            <div>
                Showing <strong id="visibleUsersCount" style="color:var(--primary, #024442);"><?= $total_users ?></strong> of <strong><?= $total_users ?></strong> users
                <span id="activeFilterNote" style="margin-left: 8px; font-weight: 600; color: #0284c7;"></span>
            </div>
            <div>
                <button type="button" onclick="window.print()" style="background:none; border:none; color:#64748b; font-size:12.5px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-print"></i> Print List
                </button>
            </div>
        </div>
    </div>

    <!-- Empty State when filters yield 0 results -->
    <div id="noUsersFoundBox" class="u-table-card" style="display:none;">
        <div class="u-empty-state">
            <i class="fas fa-user-slash"></i>
            <h4 style="margin: 0 0 6px; color:#1e293b;">No matching users found</h4>
            <p style="margin:0 0 16px; font-size:13px;">No users match your current search and filter criteria.</p>
            <button type="button" class="reset-filters-btn" onclick="resetAllFilters()">
                <i class="fas fa-undo"></i> Clear All Filters
            </button>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 1: GROUPED BY ROLE SECTIONS                                       -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="usersGroupedContainer">
        <?php foreach ($users_grouped as $role_key => $users_in_role): ?>
            <?php if (count($users_in_role) > 0): 
                $r_meta = $role_titles[$role_key] ?? ['title' => ucfirst($role_key), 'icon' => 'fa-user-tag', 'color' => '#555'];
            ?>
                <div class="u-table-card role-table-section" data-role-section="<?= htmlspecialchars($role_key) ?>">
                    <div class="u-table-header-title">
                        <h4>
                            <i class="fas <?= $r_meta['icon'] ?>" style="color: <?= $r_meta['color'] ?>;"></i>
                            <span><?= $r_meta['title'] ?></span>
                            <span class="role-sec-count" style="font-size:12px; color:#64748b; font-weight:600; margin-left:6px;">(<?= count($users_in_role) ?>)</span>
                        </h4>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="u-table">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">User</th>
                                    <th style="width: 24%;">Email</th>
                                    <th style="width: 15%;">Role</th>
                                    <th style="width: 11%;">Status</th>
                                    <th style="width: 12%;">Last Login</th>
                                    <th style="width: 10%; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users_in_role as $row): 
                                    $u_status_val = strtolower(trim($row['status'] ?? 'active'));
                                    $is_deactivated = ($u_status_val === 'deactivated' || $u_status_val === 'deactivate');
                                    $status_info = getStatus($row['last_login'], $u_status_val);
                                    $roleBadge = getRoleBadge($row['role']);
                                    $is_self = ($row['user_id'] == $_SESSION['user_id']);
                                    $avatar_gradient = getUserAvatarGradient($row['name'] . $row['user_id']);
                                    $g_val = !empty($row['gender']) ? ucfirst(strtolower(trim($row['gender']))) : 'Male';
                                ?>
                                <tr class="user-item-row" 
                                    data-user-id="<?= $row['user_id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>"
                                    data-username="<?= htmlspecialchars(strtolower($row['username'])) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($row['email'])) ?>"
                                    data-role="<?= htmlspecialchars(strtolower($row['role'])) ?>"
                                    data-status="<?= $is_deactivated ? 'deactivated' : 'active' ?>"
                                    data-gender="<?= strtolower($g_val) ?>">
                                    <td>
                                        <div class="user-info-box">
                                            <div class="user-avatar-initial" style="background: <?= $avatar_gradient ?>;">
                                                <?= strtoupper(substr($row['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="user-title-group">
                                                    <span class="user-display-name"><?= htmlspecialchars($row['name']) ?></span>
                                                    <span class="user-gender-tag <?= $g_val ?>"><?= htmlspecialchars($g_val) ?></span>
                                                </div>
                                                <div class="user-username-badge">@<?= htmlspecialchars($row['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($row['email']) ?>" style="color:#0284c7; text-decoration:none; font-weight:500;">
                                            <?= htmlspecialchars($row['email']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="background:<?= $roleBadge['bg'] ?>; color:<?= $roleBadge['text'] ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; display:inline-flex; align-items:center; gap:5px;">
                                            <i class="fas <?= $roleBadge['icon'] ?>" style="font-size:10px;"></i> <?= htmlspecialchars($roleBadge['label']) ?>
                                        </span>
                                        <?php if (!empty($row['job_title'])): ?>
                                            <div style="font-size:11px; color:#64748b; margin-top:3px;"><?= htmlspecialchars($row['job_title']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-indicator-badge <?= $is_deactivated ? 'deactivated' : 'active' ?>">
                                            <span class="stat-dot"></span>
                                            <?= $is_deactivated ? 'Deactivated' : 'Active' ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12.5px; color:#64748b; white-space:nowrap;">
                                        <?= !empty($row['last_login']) ? date('M d, Y H:i', strtotime($row['last_login'])) : '<span style="color:#cbd5e1;">Never</span>' ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="u-action-group">
                                            <button type="button" class="u-act-btn btn-edit" onclick="openUserModal('edit', <?= $row['user_id'] ?>)" title="Edit User">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <?php if ($is_deactivated): ?>
                                                <button type="button" class="u-act-btn btn-activate" onclick="toggleUserStatus(<?= $row['user_id'] ?>, 'activate')" title="Activate Account">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="u-act-btn btn-deactivate" <?= $is_self ? 'disabled title="You cannot deactivate your own account"' : 'onclick="toggleUserStatus('.$row['user_id'].', \'deactivate\')" title="Deactivate Account"' ?>>
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="u-act-btn btn-del" <?= $is_self ? 'disabled title="You cannot delete yourself"' : 'onclick="deleteUser('.$row['user_id'].', \''.addslashes($row['name']).'\')" title="Delete User"' ?>>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- VIEW 2: UNIFIED TABLE (ALL USERS TOGETHER)                             -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div id="usersFlatContainer" style="display:none;">
        <div class="u-table-card">
            <div class="u-table-header-title">
                <h4>
                    <i class="fas fa-users" style="color:var(--primary, #024442);"></i>
                    <span>All Users Directory</span>
                    <span id="flatTableCountBadge" style="font-size:12px; color:#64748b; font-weight:600; margin-left:6px;">(<?= $total_users ?>)</span>
                </h4>
            </div>

            <div style="overflow-x:auto;">
                <table class="u-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">User</th>
                            <th style="width: 24%;">Email</th>
                            <th style="width: 15%;">Role</th>
                            <th style="width: 11%;">Status</th>
                            <th style="width: 12%;">Last Login</th>
                            <th style="width: 10%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $row): 
                            $u_status_val = strtolower(trim($row['status'] ?? 'active'));
                            $is_deactivated = ($u_status_val === 'deactivated' || $u_status_val === 'deactivate');
                            $roleBadge = getRoleBadge($row['role']);
                            $is_self = ($row['user_id'] == $_SESSION['user_id']);
                            $avatar_gradient = getUserAvatarGradient($row['name'] . $row['user_id']);
                            $g_val = !empty($row['gender']) ? ucfirst(strtolower(trim($row['gender']))) : 'Male';
                        ?>
                        <tr class="user-item-row" 
                            data-user-id="<?= $row['user_id'] ?>"
                            data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>"
                            data-username="<?= htmlspecialchars(strtolower($row['username'])) ?>"
                            data-email="<?= htmlspecialchars(strtolower($row['email'])) ?>"
                            data-role="<?= htmlspecialchars(strtolower($row['role'])) ?>"
                            data-status="<?= $is_deactivated ? 'deactivated' : 'active' ?>"
                            data-gender="<?= strtolower($g_val) ?>">
                            <td>
                                <div class="user-info-box">
                                    <div class="user-avatar-initial" style="background: <?= $avatar_gradient ?>;">
                                        <?= strtoupper(substr($row['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-title-group">
                                            <span class="user-display-name"><?= htmlspecialchars($row['name']) ?></span>
                                            <span class="user-gender-tag <?= $g_val ?>"><?= htmlspecialchars($g_val) ?></span>
                                        </div>
                                        <div class="user-username-badge">@<?= htmlspecialchars($row['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($row['email']) ?>" style="color:#0284c7; text-decoration:none; font-weight:500;">
                                    <?= htmlspecialchars($row['email']) ?>
                                </a>
                            </td>
                            <td>
                                <span style="background:<?= $roleBadge['bg'] ?>; color:<?= $roleBadge['text'] ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fas <?= $roleBadge['icon'] ?>" style="font-size:10px;"></i> <?= htmlspecialchars($roleBadge['label']) ?>
                                </span>
                                <?php if (!empty($row['job_title'])): ?>
                                    <div style="font-size:11px; color:#64748b; margin-top:3px;"><?= htmlspecialchars($row['job_title']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-indicator-badge <?= $is_deactivated ? 'deactivated' : 'active' ?>">
                                    <span class="stat-dot"></span>
                                    <?= $is_deactivated ? 'Deactivated' : 'Active' ?>
                                </span>
                            </td>
                            <td style="font-size:12.5px; color:#64748b; white-space:nowrap;">
                                <?= !empty($row['last_login']) ? date('M d, Y H:i', strtotime($row['last_login'])) : '<span style="color:#cbd5e1;">Never</span>' ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="u-action-group">
                                    <button type="button" class="u-act-btn btn-edit" onclick="openUserModal('edit', <?= $row['user_id'] ?>)" title="Edit User">
                                        <i class="fas fa-pen"></i> Edit
                                    </button>
                                    <?php if ($is_deactivated): ?>
                                        <button type="button" class="u-act-btn btn-activate" onclick="toggleUserStatus(<?= $row['user_id'] ?>, 'activate')" title="Activate Account">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="u-act-btn btn-deactivate" <?= $is_self ? 'disabled title="You cannot deactivate your own account"' : 'onclick="toggleUserStatus('.$row['user_id'].', \'deactivate\')" title="Deactivate Account"' ?>>
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="u-act-btn btn-del" <?= $is_self ? 'disabled title="You cannot delete yourself"' : 'onclick="deleteUser('.$row['user_id'].', \''.addslashes($row['name']).'\')" title="Delete User"' ?>>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- ADD / EDIT USER MODAL                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="userModal" class="modal-overlay" onclick="if(event.target===this)closeUserModal()">
    <div class="modal-content" style="max-width: 620px; border-radius: 18px; overflow: hidden; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.2);">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary, #024442) 0%, #035e5b 100%); color:#ffffff; padding: 20px 24px; border:none; display:flex; justify-content:space-between; align-items:center;">
            <h4 style="margin:0; font-size: 1.25rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 10px;">
                <div class="modal-header-icon" id="modalIcon" style="background: rgba(255,255,255,0.15); width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fas fa-user-plus" style="color:#ffffff;"></i>
                </div>
                <span id="modalTitle">Add New User</span>
            </h4>
            <button type="button" onclick="closeUserModal()" style="color: #ffffff; opacity: 0.8; background: transparent; border: none; font-size: 22px; cursor: pointer; padding: 0; line-height: 1;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="userForm">
            <input type="hidden" name="user_id" id="user_id">
            <div class="modal-body" style="padding: 24px; max-height: calc(85vh - 140px); overflow-y: auto;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- Full Name -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" id="f_name" placeholder="e.g. John Doe" required
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                    </div>
                    
                    <!-- Gender -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Gender <span style="color:#ef4444;">*</span></label>
                        <select name="gender" id="f_gender" required
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <!-- Username -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Username <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="username" id="f_username" placeholder="e.g. johndoe" required
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                        <div style="font-size:11.5px; color:#94a3b8; margin-top:4px;"><i class="fas fa-info-circle"></i> Permanent login handle</div>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Email Address <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" id="f_email" placeholder="e.g. john@example.com" required
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                    </div>

                    <!-- Role -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Role <span style="color:#ef4444;">*</span></label>
                        <select name="role" id="f_role" required
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                            <option value="super_admin">Super Admin (All Rights Unrestricted)</option>
                            <option value="admin">Admin Officer (Operations, Invoices, Docs)</option>
                            <option value="manager">Manager</option>
                            <option value="pm">Project Manager</option>
                            <option value="sales">Sales & Business Development</option>
                            <option value="hrm">HR Management</option>
                            <option value="user">Staff / User</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="client">Client Primary Contact</option>
                            <option value="client_sub">Client Participant</option>
                        </select>
                    </div>

                    <!-- Account Status -->
                    <div class="form-group" style="margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Account Status</label>
                        <select name="status" id="f_status"
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                            <option value="active">Active (Access Allowed)</option>
                            <option value="deactivated">Deactivated (Access Blocked)</option>
                        </select>
                    </div>

                    <!-- Link to Client Company -->
                    <div class="form-group" id="clientFormGroup" style="display:none; grid-column: span 2; margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">Link to Client Company <span style="color:#ef4444;">*</span></label>
                        <select name="client_id" id="f_client_id"
                            style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                            <option value="">-- Select Client Company --</option>
                            <?php
                            $clients_opt = $conn->query("SELECT id, client_name, company FROM clients ORDER BY client_name ASC");
                            while ($c_opt = $clients_opt->fetch_assoc()) {
                                echo "<option value='{$c_opt['id']}'>" . htmlspecialchars($c_opt['client_name']) . " (" . htmlspecialchars($c_opt['company']) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="form-group" style="grid-column: span 2; margin: 0;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px;">
                            Password <span id="passwordRequired" style="color:#ef4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <input type="password" name="password" id="f_password" placeholder="Enter password (min. 8 characters)"
                                style="width:100%; padding:10px 42px 10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; background:#f8fafc; outline:none; box-sizing:border-box; font-family:inherit;">
                            <button type="button" onclick="togglePasswordVisibility()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#94a3b8; cursor:pointer; font-size:14px;">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div style="font-size:12px; color:#94a3b8; margin-top:5px;" id="passwordHint">
                            <i class="fas fa-lock" style="color: var(--primary);"></i> Minimum 8 characters required
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary-custom" onclick="closeUserModal()" style="padding: 9px 18px; border-radius: 9px; font-weight: 600;">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn" style="padding: 9px 22px; border-radius: 9px; font-weight: 700; background: var(--primary, #024442);">
                    <span id="saveBtnText"><i class="fas fa-save"></i> Save User</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
// Global State
let currentViewMode = localStorage.getItem('users_view_mode') || 'grouped';

// ── Real-time Filtering Engine ────────────────────────────────────────────────
function applyUserFilters() {
    const searchVal = document.getElementById('userSearchInput').value.trim().toLowerCase();
    const roleVal = document.getElementById('roleFilterSelect').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value.toLowerCase();
    const genderVal = document.getElementById('genderFilterSelect').value.toLowerCase();

    const clearBtn = document.getElementById('clearSearchBtn');
    clearBtn.style.display = searchVal ? 'flex' : 'none';

    const hasActiveFilters = (searchVal !== '' || roleVal !== 'all' || statusVal !== 'all' || genderVal !== 'all');
    document.getElementById('resetFiltersBtn').style.display = hasActiveFilters ? 'inline-flex' : 'none';

    // Update active filter stat card indicators
    updateStatCardHighlights(roleVal, statusVal);

    let totalVisible = 0;
    const activeFilterTags = [];
    if (roleVal !== 'all') activeFilterTags.push(`Role: ${roleVal}`);
    if (statusVal !== 'all') activeFilterTags.push(`Status: ${statusVal}`);
    if (genderVal !== 'all') activeFilterTags.push(`Gender: ${genderVal}`);
    if (searchVal !== '') activeFilterTags.push(`Search: "${searchVal}"`);
    document.getElementById('activeFilterNote').textContent = activeFilterTags.length > 0 ? `(${activeFilterTags.join(' • ')})` : '';

    // Filter rows in both Grouped View and Flat View
    const targetContainer = (currentViewMode === 'grouped') ? 
        document.getElementById('usersGroupedContainer') : 
        document.getElementById('usersFlatContainer');

    const allRows = targetContainer.querySelectorAll('.user-item-row');
    allRows.forEach(row => {
        const rName     = row.getAttribute('data-name') || '';
        const rUsername = row.getAttribute('data-username') || '';
        const rEmail    = row.getAttribute('data-email') || '';
        const rRole     = row.getAttribute('data-role') || '';
        const rStatus   = row.getAttribute('data-status') || '';
        const rGender   = row.getAttribute('data-gender') || '';

        // Check search match
        const matchesSearch = (!searchVal || rName.includes(searchVal) || rUsername.includes(searchVal) || rEmail.includes(searchVal));

        // Check role match
        let matchesRole = (roleVal === 'all');
        if (!matchesRole) {
            if (roleVal === 'super_admin') matchesRole = (rRole === 'super_admin' || rRole === 'superadmin');
            else if (roleVal === 'client') matchesRole = (rRole === 'client' || rRole === 'client_sub');
            else matchesRole = (rRole === roleVal);
        }

        // Check status match
        const matchesStatus = (statusVal === 'all' || rStatus === statusVal);

        // Check gender match
        const matchesGender = (genderVal === 'all' || rGender === genderVal);

        if (matchesSearch && matchesRole && matchesStatus && matchesGender) {
            row.style.display = '';
            totalVisible++;
        } else {
            row.style.display = 'none';
        }
    });

    // In grouped mode, manage role section visibility and count labels
    if (currentViewMode === 'grouped') {
        const sections = document.querySelectorAll('.role-table-section');
        sections.forEach(section => {
            const visibleInSec = section.querySelectorAll('.user-item-row:not([style*="display: none"])').length;
            const countLabel = section.querySelector('.role-sec-count');
            if (countLabel) countLabel.textContent = `(${visibleInSec})`;
            section.style.display = visibleInSec > 0 ? '' : 'none';
        });
    } else {
        const flatBadge = document.getElementById('flatTableCountBadge');
        if (flatBadge) flatBadge.textContent = `(${totalVisible})`;
    }

    document.getElementById('visibleUsersCount').textContent = totalVisible;
    document.getElementById('noUsersFoundBox').style.display = (totalVisible === 0) ? 'block' : 'none';
}

function clearSearchInput() {
    document.getElementById('userSearchInput').value = '';
    applyUserFilters();
}

function resetAllFilters() {
    document.getElementById('userSearchInput').value = '';
    document.getElementById('roleFilterSelect').value = 'all';
    document.getElementById('statusFilterSelect').value = 'all';
    document.getElementById('genderFilterSelect').value = 'all';
    applyUserFilters();
}

// Quick filter via top stat cards
function setQuickFilter(type) {
    if (type === 'all') {
        resetAllFilters();
    } else if (type === 'active' || type === 'deactivated') {
        document.getElementById('statusFilterSelect').value = type;
        applyUserFilters();
    } else if (type === 'super_admin' || type === 'admin') {
        document.getElementById('roleFilterSelect').value = type;
        applyUserFilters();
    }
}

function updateStatCardHighlights(roleVal, statusVal) {
    const cards = ['statCardAll', 'statCardActive', 'statCardDeactivated', 'statCardSuperAdmin', 'statCardAdmin'];
    cards.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('active-filter');
    });

    if (roleVal === 'all' && statusVal === 'all') {
        document.getElementById('statCardAll')?.classList.add('active-filter');
    } else if (statusVal === 'active') {
        document.getElementById('statCardActive')?.classList.add('active-filter');
    } else if (statusVal === 'deactivated') {
        document.getElementById('statCardDeactivated')?.classList.add('active-filter');
    } else if (roleVal === 'super_admin') {
        document.getElementById('statCardSuperAdmin')?.classList.add('active-filter');
    } else if (roleVal === 'admin') {
        document.getElementById('statCardAdmin')?.classList.add('active-filter');
    }
}

// ── View Mode Switching (Grouped vs Unified Flat Table) ───────────────────────
function switchViewMode(mode) {
    currentViewMode = mode;
    localStorage.setItem('users_view_mode', mode);

    const btnGrouped = document.getElementById('viewGroupedBtn');
    const btnFlat    = document.getElementById('viewFlatBtn');
    const boxGrouped = document.getElementById('usersGroupedContainer');
    const boxFlat    = document.getElementById('usersFlatContainer');

    if (mode === 'flat') {
        btnFlat.classList.add('active');
        btnGrouped.classList.remove('active');
        boxGrouped.style.display = 'none';
        boxFlat.style.display = 'block';
    } else {
        btnGrouped.classList.add('active');
        btnFlat.classList.remove('active');
        boxGrouped.style.display = 'block';
        boxFlat.style.display = 'none';
    }
    applyUserFilters();
}

// ── Password Toggle inside Modal ──────────────────────────────────────────────
function togglePasswordVisibility() {
    const pwInput = document.getElementById('f_password');
    const icon = document.getElementById('togglePasswordIcon');
    if (pwInput.type === 'password') {
        pwInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ── User Modal Open / Close ───────────────────────────────────────────────────
function openUserModal(type, id = null) {
    const modal = document.getElementById('userModal');
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';

    // Reset password visibility
    const pwInput = document.getElementById('f_password');
    pwInput.type = 'password';
    document.getElementById('togglePasswordIcon')?.classList.remove('fa-eye-slash');
    document.getElementById('togglePasswordIcon')?.classList.add('fa-eye');

    if (type === 'add') {
        document.getElementById('modalTitle').textContent       = 'Add New User';
        document.getElementById('modalIcon').innerHTML          = '<i class="fas fa-user-plus" style="color: #ffffff;"></i>';
        document.getElementById('saveBtnText').innerHTML        = '<i class="fas fa-save"></i> Save User';
        document.getElementById('f_password').required          = true;
        document.getElementById('f_username').disabled          = false;
        document.getElementById('f_role').value                 = 'user'; // Default to staff user
        document.getElementById('f_status').value               = 'active';
        document.getElementById('f_gender').value               = 'Male';
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHint').innerHTML       = '<i class="fas fa-lock" style="color: var(--primary);"></i> Minimum 8 characters required';
    } else {
        document.getElementById('modalTitle').textContent       = 'Edit User Profile';
        document.getElementById('modalIcon').innerHTML          = '<i class="fas fa-user-edit" style="color: #ffffff;"></i>';
        document.getElementById('saveBtnText').innerHTML        = '<i class="fas fa-save"></i> Update User';
        document.getElementById('f_password').required          = false;
        document.getElementById('f_username').disabled          = true;
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').innerHTML       = '<i class="fas fa-unlock-alt" style="color: var(--primary);"></i> Leave blank to keep current password';

        const btn = document.getElementById('saveBtn');
        btn.disabled = true; btn.style.opacity = '.6';

        fetch(`user_action.php?action=fetch&id=${id}`)
            .then(r => r.text())
            .then(text => {
                const start = text.lastIndexOf('{"');
                try {
                    const data = JSON.parse(start !== -1 ? text.slice(start) : text);
                    if (data && data.user_id) {
                        document.getElementById('user_id').value    = data.user_id;
                        document.getElementById('f_name').value     = data.name      || '';
                        document.getElementById('f_gender').value   = data.gender    || 'Male';
                        document.getElementById('f_email').value    = data.email     || '';
                        document.getElementById('f_username').value = data.username  || '';
                        document.getElementById('f_role').value     = data.role      || 'user';
                        document.getElementById('f_role').dispatchEvent(new Event('change'));
                        document.getElementById('f_client_id').value= data.client_id || '';
                        document.getElementById('f_status').value   = data.status    || 'active';
                    } else {
                        showToast(data.message || 'Failed to load user', 'error');
                        closeUserModal(); return;
                    }
                } catch(e) { showToast('Server error', 'error'); closeUserModal(); return; }
                btn.disabled = false; btn.style.opacity = '1';
            });
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Activate / Deactivate Toggle ──────────────────────────────────────────────
function toggleUserStatus(id, actionTarget) {
    const confirmMsg = actionTarget === 'deactivate' ? 
        'Are you sure you want to DEACTIVATE this user account? The user will be blocked from logging in.' : 
        'Are you sure you want to ACTIVATE this user account?';
    if (!confirm(confirmMsg)) return;

    fetch(`user_action.php?action=toggle_status&id=${id}&target_status=${actionTarget}`)
        .then(r => r.text())
        .then(raw => {
            const start = raw.lastIndexOf('{"');
            try {
                const data = JSON.parse(start !== -1 ? raw.slice(start) : raw);
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 700);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                }
            } catch(e) {
                showToast('Server response error', 'error');
            }
        })
        .catch(() => showToast('Server connection error', 'error'));
}

// ── Delete User ───────────────────────────────────────────────────────────────
function deleteUser(id, userName = '') {
    const nameLabel = userName ? ` "${userName}"` : '';
    if (!confirm(`Are you sure you want to permanently delete user${nameLabel}? This cannot be undone.`)) return;
    fetch(`user_action.php?action=delete&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) { 
                showToast('User deleted successfully.'); 
                setTimeout(() => location.reload(), 700); 
            } else {
                showToast(data.message || 'Failed to delete user', 'error');
            }
        })
        .catch(() => showToast('Server error', 'error'));
}

// ── Submit User Form ──────────────────────────────────────────────────────────
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn  = document.getElementById('saveBtn');
    const text = document.getElementById('saveBtnText');
    const orig = text.innerHTML;
    btn.disabled = true; text.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    fetch('user_action.php?action=save', { method: 'POST', body: new FormData(this) })
        .then(r => r.text())
        .then(raw => {
            const start = raw.lastIndexOf('{"');
            try {
                const data = JSON.parse(start !== -1 ? raw.slice(start) : raw);
                if (data.success) {
                    closeUserModal();
                    showToast(data.message || 'User saved successfully!');
                    setTimeout(() => location.reload(), 700);
                } else {
                    showToast(data.message || 'Failed to save user', 'error');
                }
            } catch(e) { showToast('Server error', 'error'); }
            btn.disabled = false; text.innerHTML = orig;
        });
});

// ── Toast Notification Helper ─────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const color = type === 'success' ? '#10b981' : '#ef4444';
    t.style.cssText = `background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 10px 25px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${color};`;
    t.innerHTML = `<span style="font-weight:700;color:${color};font-size:16px;"><i class="fas ${icon}"></i></span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3200);
}

// ── Document Ready Lifecycle ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Restore saved view mode (Grouped vs Flat)
    switchViewMode(currentViewMode);

    if (new URLSearchParams(window.location.search).get('add_user') === '1') {
        openUserModal('add');
    }
    
    // Toggle Client company select dropdown
    document.getElementById('f_role').addEventListener('change', function() {
        const cg = document.getElementById('clientFormGroup');
        if (this.value === 'client' || this.value === 'client_sub') {
            cg.style.display = 'block';
            document.getElementById('f_client_id').required = true;
        } else {
            cg.style.display = 'none';
            document.getElementById('f_client_id').required = false;
            document.getElementById('f_client_id').value = '';
        }
    });

    // Run initial filter check
    applyUserFilters();
});
</script>

<?php include('dashboard_footer.php'); ?>
