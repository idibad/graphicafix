<?php
include 'dashboard_header.php';

$is_manager = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'manager']);

// Safety fallback table creation
$conn->query("CREATE TABLE IF NOT EXISTS daily_progress_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_date DATE NOT NULL,
    tasks_completed TEXT NOT NULL,
    challenges TEXT,
    plan_tomorrow TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, report_date)
)");

// Fetch internal team members (exclude students and clients) for filter dropdown
$users = [];
$users_result = $conn->query("SELECT user_id, name, role FROM users WHERE role NOT IN ('student', 'client') ORDER BY name ASC");
if ($users_result) {
    while ($u = $users_result->fetch_assoc()) {
        $users[] = $u;
    }
}

// ── Filter Parameters ────────────────────────────────────────────────────────
$search_q          = trim($_GET['q'] ?? '');
$preset            = trim($_GET['preset'] ?? '');
$filter_date       = trim($_GET['date'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date   = trim($_GET['end_date'] ?? '');
$filter_user       = (isset($_GET['user_id']) && $_GET['user_id'] !== '') ? intval($_GET['user_id']) : '';
$filter_blockers   = (isset($_GET['has_blockers']) && $_GET['has_blockers'] !== '') ? intval($_GET['has_blockers']) : '';

// Handle quick presets
if ($preset === 'today') {
    $filter_start_date = date('Y-m-d');
    $filter_end_date   = date('Y-m-d');
} elseif ($preset === 'yesterday') {
    $filter_start_date = date('Y-m-d', strtotime('-1 day'));
    $filter_end_date   = date('Y-m-d', strtotime('-1 day'));
} elseif ($preset === 'week') {
    $filter_start_date = date('Y-m-d', strtotime('monday this week'));
    $filter_end_date   = date('Y-m-d');
} elseif ($preset === 'month') {
    $filter_start_date = date('Y-m-01');
    $filter_end_date   = date('Y-m-d');
}

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ── Build Query & WHERE Clause ────────────────────────────────────────────────
$where_conditions = [];

if (!$is_manager) {
    $where_conditions[] = "dpr.user_id = " . intval($user_id);
} else {
    if ($filter_user !== '') {
        $where_conditions[] = "dpr.user_id = " . intval($filter_user);
    }
}

if (!empty($filter_date)) {
    $where_conditions[] = "dpr.report_date = '" . $conn->real_escape_string($filter_date) . "'";
} else {
    if (!empty($filter_start_date)) {
        $where_conditions[] = "dpr.report_date >= '" . $conn->real_escape_string($filter_start_date) . "'";
    }
    if (!empty($filter_end_date)) {
        $where_conditions[] = "dpr.report_date <= '" . $conn->real_escape_string($filter_end_date) . "'";
    }
}

if ($filter_blockers === 1) {
    $where_conditions[] = "dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != ''";
}

if (!empty($search_q)) {
    $sq = $conn->real_escape_string($search_q);
    $where_conditions[] = "(dpr.tasks_completed LIKE '%$sq%' OR dpr.challenges LIKE '%$sq%' OR dpr.plan_tomorrow LIKE '%$sq%' OR u.name LIKE '%$sq%')";
}

$where_clause = empty($where_conditions) ? "1=1" : implode(" AND ", $where_conditions);

// ── KPI Metrics Queries ───────────────────────────────────────────────────────
// 1. Total matching reports & Roadblocks
$stats_sql = "
    SELECT 
        COUNT(*) as total_reports,
        SUM(CASE WHEN dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != '' THEN 1 ELSE 0 END) as total_blockers
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
    WHERE $where_clause
";
$stats_res = $conn->query($stats_sql);
$stats_data = $stats_res ? $stats_res->fetch_assoc() : ['total_reports' => 0, 'total_blockers' => 0];
$total_records = intval($stats_data['total_reports'] ?? 0);
$total_blockers = intval($stats_data['total_blockers'] ?? 0);

// 2. Today's submissions & Team compliance
$today_str = date('Y-m-d');
if ($is_manager) {
    $today_sub_res = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM daily_progress_reports WHERE report_date = '$today_str'");
    $today_submitted_count = $today_sub_res ? intval($today_sub_res->fetch_assoc()['count']) : 0;
    
    $total_team_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('student', 'client')");
    $total_team_count = $total_team_res ? intval($total_team_res->fetch_assoc()['count']) : count($users);
    $today_pending_count = max(0, $total_team_count - $today_submitted_count);
} else {
    $today_sub_res = $conn->query("SELECT COUNT(*) as count FROM daily_progress_reports WHERE user_id = $user_id AND report_date = '$today_str'");
    $today_submitted_count = $today_sub_res ? intval($today_sub_res->fetch_assoc()['count']) : 0;
    $today_pending_count = $today_submitted_count > 0 ? 0 : 1;
}

// 3. This Month's volume
$month_start = date('Y-m-01');
if ($is_manager) {
    $month_res = $conn->query("SELECT COUNT(*) as count FROM daily_progress_reports WHERE report_date >= '$month_start'");
} else {
    $month_res = $conn->query("SELECT COUNT(*) as count FROM daily_progress_reports WHERE user_id = $user_id AND report_date >= '$month_start'");
}
$month_reports_count = $month_res ? intval($month_res->fetch_assoc()['count']) : 0;

// ── Fetch Paginated Reports ──────────────────────────────────────────────────
$reports_sql = "
    SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
    WHERE $where_clause
    ORDER BY dpr.report_date DESC, dpr.created_at DESC
    LIMIT $limit OFFSET $offset
";
$reports_result = $conn->query($reports_sql);
$total_pages = ceil($total_records / $limit);

// Check if current user already submitted today
$stmt = $conn->prepare("SELECT * FROM daily_progress_reports WHERE user_id = ? AND report_date = ?");
$stmt->bind_param("is", $user_id, $today_str);
$stmt->execute();
$today_report = $stmt->get_result()->fetch_assoc();

// Build URL query strings for exports and pagination
$current_params = $_GET;
unset($current_params['page']);
$export_csv_url = 'dpr_export.php?' . http_build_query(array_merge($current_params, ['format' => 'csv']));
$export_pdf_url = 'dpr_export.php?' . http_build_query(array_merge($current_params, ['format' => 'pdf']));
?>

<style>
/* ── DPR Dashboard Specific Styles ── */
.dpr-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.dpr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.dpr-title-wrap h1 {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-dark);
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.dpr-title-wrap p {
    color: var(--text-muted);
    font-size: 14px;
    margin: 0;
}
.dpr-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* KPI Cards */
.dpr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.dpr-kpi-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s ease;
}
.dpr-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: #cbd5e1;
}
.dpr-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--kpi-accent, var(--primary-color));
}
.dpr-kpi-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: var(--kpi-bg, #f0fdf4);
    color: var(--kpi-accent, var(--primary-color));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.dpr-kpi-content {
    flex-grow: 1;
}
.dpr-kpi-number {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1.1;
}
.dpr-kpi-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.dpr-kpi-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
}

/* Filter Card */
.dpr-filter-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}
.filter-row-top {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}
.filter-group {
    flex: 1;
    min-width: 180px;
}
.filter-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.filter-input {
    width: 100%;
    padding: 9px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    background: #f8fafc;
    font-size: 13.5px;
    color: var(--text-dark);
    font-weight: 500;
    transition: all 0.2s;
}
.filter-input:focus {
    border-color: var(--primary-color);
    background: #fff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
}
.preset-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    align-items: center;
}
.preset-pill-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin-right: 4px;
}
.preset-btn {
    background: #f1f5f9;
    border: 1px solid transparent;
    color: #475569;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}
.preset-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.preset-btn.active {
    background: var(--primary-color);
    color: #ffffff;
    font-weight: 700;
}

/* Reports Table Card */
.dpr-table-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.dpr-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color);
    background: #ffffff;
    flex-wrap: wrap;
    gap: 12px;
}
.dpr-table-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.dpr-count-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
}
.dpr-table-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Responsive Table */
.dpr-table {
    width: 100%;
    border-collapse: collapse;
}
.dpr-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border-color);
    text-align: left;
}
.dpr-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    vertical-align: top;
    color: #334155;
}
.dpr-table tr:hover td {
    background-color: #fafbfc;
}

/* Cell content helpers */
.dpr-user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.dpr-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary-color) 0%, #064e3b 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
.dpr-user-name {
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}
.dpr-user-role {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
}

.dpr-date-cell {
    white-space: nowrap;
}
.dpr-date-main {
    font-weight: 600;
    color: var(--text-dark);
}
.dpr-date-day {
    font-size: 12px;
    color: #64748b;
}

.dpr-snippet {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.45;
    color: #334155;
    font-size: 13px;
    white-space: pre-line;
}

/* Badges */
.badge-blocker {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-smooth {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-today {
    background: #e0f2fe;
    color: #0284c7;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 12px;
}
.badge-yesterday {
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
}

/* Action buttons */
.dpr-actions-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.btn-icon-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: #ffffff;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-icon-action:hover {
    background: #f8fafc;
    color: var(--primary-color);
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.btn-icon-action.text-danger:hover {
    background: #fef2f2;
    color: #dc2626 !important;
    border-color: #fca5a5;
}

/* Modals - System Default with Scrollbar */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modalSlideUp 0.3s ease-out;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}
@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    background: var(--primary-color, #024442);
    color: #fff;
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}
.modal-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    font-size: 14px;
    color: #fff;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.modal-close:hover {
    color: #fff;
}
.modal-content form {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1 1 auto;
    margin: 0;
    min-height: 0;
}
.modal-body {
    padding: 24px;
    overflow-y: auto !important;
    flex: 1 1 auto;
    min-height: 0;
    max-height: calc(88vh - 140px);
}
.modal-body::-webkit-scrollbar {
    width: 7px;
}
.modal-body::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 4px;
}
.modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #fbfcfc;
    flex-shrink: 0;
}
</style>

<div class="height-100">
    <div class="dpr-container">

        <!-- ── Page Header ────────────────────────────────────────────────────── -->
        <div class="dpr-header">
            <div class="dpr-title-wrap">
                <h1><i class="fas fa-calendar-check" style="color:var(--primary-color);"></i> Daily Progress Reports</h1>
                <p><?= $is_manager ? 'Track team accomplishments, unblock workflow obstacles, and export executive logs' : 'Submit your daily accomplishments, log any roadblocks, and review your progress history' ?></p>
            </div>
            <div class="dpr-header-actions">
                <!-- Export Dropdown / Modal Trigger -->
                <button type="button" class="btn-secondary-custom" onclick="openExportModal()" title="Export Reports">
                    <i class="fas fa-file-export" style="color:var(--primary-color);"></i> Export Reports
                </button>

                <!-- DPR Submission / Edit Trigger -->
                <?php if (!$today_report): ?>
                    <button type="button" class="btn-primary-custom" onclick="openDprModal(false)">
                        <i class="fas fa-plus-circle"></i> Submit Today's Report
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-volt" onclick="openDprModal(true)">
                        <i class="fas fa-edit"></i> Edit Today's Report
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── KPI Metrics Row ────────────────────────────────────────────────── -->
        <div class="dpr-kpi-grid">
            <!-- KPI 1: Total Reports -->
            <div class="dpr-kpi-card" style="--kpi-accent: #0284c7; --kpi-bg: #e0f2fe;">
                <div class="dpr-kpi-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="dpr-kpi-content">
                    <div class="dpr-kpi-number"><?= number_format($total_records) ?></div>
                    <div class="dpr-kpi-label"><?= ($where_clause !== '1=1') ? 'Filtered Reports' : 'Total Reports' ?></div>
                    <div class="dpr-kpi-sub"><?= $is_manager ? 'All team logs' : 'Your submitted logs' ?></div>
                </div>
            </div>

            <!-- KPI 2: Today's Submissions -->
            <div class="dpr-kpi-card" style="--kpi-accent: #10b981; --kpi-bg: #d1fae5;">
                <div class="dpr-kpi-icon"><i class="fas fa-check-double"></i></div>
                <div class="dpr-kpi-content">
                    <div class="dpr-kpi-number"><?= $today_submitted_count ?></div>
                    <div class="dpr-kpi-label"><?= $is_manager ? "Submitted Today" : "Today's Status" ?></div>
                    <div class="dpr-kpi-sub">
                        <?php if ($is_manager): ?>
                            <?= $today_pending_count > 0 ? "$today_pending_count members pending" : "All members on track" ?>
                        <?php else: ?>
                            <?= $today_report ? "Submitted at " . date('h:i A', strtotime($today_report['created_at'])) : "Pending your submission" ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KPI 3: Roadblocks Flagged -->
            <div class="dpr-kpi-card" style="--kpi-accent: #ef4444; --kpi-bg: #fee2e2;">
                <div class="dpr-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="dpr-kpi-content">
                    <div class="dpr-kpi-number" style="color:<?= $total_blockers > 0 ? '#dc2626' : 'inherit' ?>;"><?= $total_blockers ?></div>
                    <div class="dpr-kpi-label">Roadblocks Flagged</div>
                    <div class="dpr-kpi-sub"><?= $total_blockers > 0 ? "Items needing assistance" : "No blockers reported" ?></div>
                </div>
            </div>

            <!-- KPI 4: Month Velocity -->
            <div class="dpr-kpi-card" style="--kpi-accent: #8b5cf6; --kpi-bg: #ede9fe;">
                <div class="dpr-kpi-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="dpr-kpi-content">
                    <div class="dpr-kpi-number"><?= $month_reports_count ?></div>
                    <div class="dpr-kpi-label">Logged This Month</div>
                    <div class="dpr-kpi-sub">Since <?= date('M 1, Y') ?></div>
                </div>
            </div>
        </div>

        <!-- ── Advanced Filter & Search Bar ───────────────────────────────────── -->
        <div class="dpr-filter-card">
            <form method="GET" id="filterForm">
                <div class="filter-row-top">
                    <!-- Search Input -->
                    <div class="filter-group" style="flex: 2; min-width: 240px;">
                        <label class="filter-label"><i class="fas fa-search"></i> Search Keyword</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" class="filter-input" placeholder="Search tasks, roadblocks, plans, or names...">
                    </div>

                    <?php if ($is_manager): ?>
                    <!-- Member Selector for Managers -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-user-tag"></i> Team Member</label>
                        <select name="user_id" class="filter-input">
                            <option value="">All Team Members</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Start Date -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar-day"></i> From Date</label>
                        <input type="date" name="start_date" id="inputStartDate" value="<?= htmlspecialchars($filter_start_date) ?>" class="filter-input">
                    </div>

                    <!-- End Date -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar-day"></i> To Date</label>
                        <input type="date" name="end_date" id="inputEndDate" value="<?= htmlspecialchars($filter_end_date) ?>" class="filter-input">
                    </div>

                    <!-- Blockers Filter -->
                    <div class="filter-group" style="max-width: 170px;">
                        <label class="filter-label"><i class="fas fa-filter"></i> Roadblocks</label>
                        <select name="has_blockers" class="filter-input">
                            <option value="">All Reports</option>
                            <option value="1" <?= $filter_blockers === 1 ? 'selected' : '' ?>>⚠️ Has Blockers</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display:flex; gap:8px; align-items:flex-end;">
                        <button type="submit" class="btn-primary-custom" style="padding: 10px 18px; border-radius:8px;">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="dpr.php" class="btn-secondary-custom" style="padding: 10px 16px; border-radius:8px;" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Preset Pills -->
                <div class="preset-pills">
                    <span class="preset-pill-label"><i class="fas fa-bolt" style="color:var(--accent-color,#b8f35a);"></i> Quick Presets:</span>
                    <button type="button" class="preset-btn <?= ($preset === '' && empty($filter_start_date) && empty($filter_end_date)) ? 'active' : '' ?>" onclick="setPreset('')">All Time</button>
                    <button type="button" class="preset-btn <?= $preset === 'today' || ($filter_start_date === date('Y-m-d') && $filter_end_date === date('Y-m-d')) ? 'active' : '' ?>" onclick="setPreset('today')">Today</button>
                    <button type="button" class="preset-btn <?= $preset === 'yesterday' ? 'active' : '' ?>" onclick="setPreset('yesterday')">Yesterday</button>
                    <button type="button" class="preset-btn <?= $preset === 'week' ? 'active' : '' ?>" onclick="setPreset('week')">This Week</button>
                    <button type="button" class="preset-btn <?= $preset === 'month' ? 'active' : '' ?>" onclick="setPreset('month')">This Month</button>
                </div>
            </form>
        </div>

        <!-- ── Progress Reports Table Card ────────────────────────────────────── -->
        <div class="dpr-table-card">
            <div class="dpr-table-header">
                <div>
                    <h3 class="dpr-table-title">
                        <i class="fas fa-stream" style="color:var(--primary-color);"></i>
                        <span>Progress Reports Feed</span>
                        <span class="dpr-count-badge"><?= $total_records ?> record<?= $total_records != 1 ? 's' : '' ?></span>
                    </h3>
                </div>
                <div class="dpr-table-actions">
                    <!-- Direct Export Shortcuts for Current View -->
                    <a href="<?= htmlspecialchars($export_csv_url) ?>" class="btn-icon-action" style="width:auto; padding:0 12px; font-weight:600; font-size:12.5px; gap:6px;" title="Export current filtered view to CSV">
                        <i class="fas fa-file-csv" style="color:#10b981;"></i> CSV
                    </a>
                    <a href="<?= htmlspecialchars($export_pdf_url) ?>&preview=1" target="_blank" class="btn-icon-action" style="width:auto; padding:0 12px; font-weight:600; font-size:12.5px; gap:6px;" title="Preview Branded PDF Summary Dossier">
                        <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF Dossier
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div style="overflow-x:auto;">
                <table class="dpr-table">
                    <thead>
                        <tr>
                            <?php if ($is_manager): ?>
                                <th style="width: 22%;">Team Member</th>
                            <?php endif; ?>
                            <th style="width: 14%;">Date</th>
                            <th style="width: 32%;">Tasks Completed</th>
                            <th style="width: 16%;">Roadblocks</th>
                            <th style="width: 10%; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                            <?php while ($row = $reports_result->fetch_assoc()): ?>
                                <?php 
                                    $report_date_ts = strtotime($row['report_date']);
                                    $is_today = ($row['report_date'] === date('Y-m-d'));
                                    $is_yesterday = ($row['report_date'] === date('Y-m-d', strtotime('-1 day')));
                                    $has_challenges = !empty(trim($row['challenges'] ?? ''));
                                    $can_delete = ($is_manager || $row['user_id'] == $user_id);
                                    $can_edit = ($row['user_id'] == $user_id && $is_today);
                                ?>
                                <tr id="dpr-row-<?= $row['id'] ?>">
                                    <?php if ($is_manager): ?>
                                    <td>
                                        <div class="dpr-user-cell">
                                            <div class="dpr-avatar">
                                                <?= strtoupper(substr($row['user_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="dpr-user-name"><?= htmlspecialchars($row['user_name'] ?? 'Unknown') ?></div>
                                                <div class="dpr-user-role"><?= ucfirst($row['user_role'] ?? 'User') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>

                                    <td>
                                        <div class="dpr-date-cell">
                                            <div class="dpr-date-main"><?= date('M j, Y', $report_date_ts) ?></div>
                                            <div class="dpr-date-day">
                                                <?= date('l', $report_date_ts) ?>
                                                <?php if ($is_today): ?>
                                                    <span class="badge-today">Today</span>
                                                <?php elseif ($is_yesterday): ?>
                                                    <span class="badge-yesterday">Yesterday</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="dpr-snippet" title="<?= htmlspecialchars($row['tasks_completed']) ?>">
                                            <?= htmlspecialchars($row['tasks_completed']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($has_challenges): ?>
                                            <div class="badge-blocker" title="<?= htmlspecialchars($row['challenges']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i> Roadblock
                                            </div>
                                            <div class="dpr-snippet" style="color:#b91c1c; font-size:12px; margin-top:4px;">
                                                <?= htmlspecialchars($row['challenges']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-smooth"><i class="fas fa-check-circle"></i> Smooth</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align:right;">
                                        <div class="dpr-actions-cell" style="justify-content:flex-end;">
                                            <!-- View Details Modal Trigger -->
                                            <button type="button" class="btn-icon-action" title="View Full Report"
                                                data-id="<?= $row['id'] ?>"
                                                data-name="<?= htmlspecialchars($row['user_name'] ?? 'Team Member') ?>"
                                                data-role="<?= htmlspecialchars(ucfirst($row['user_role'] ?? 'User')) ?>"
                                                data-email="<?= htmlspecialchars($row['user_email'] ?? '') ?>"
                                                data-date="<?= date('l, F j, Y', $report_date_ts) ?>"
                                                data-rawdate="<?= $row['report_date'] ?>"
                                                data-created="<?= date('M j, Y h:i A', strtotime($row['created_at'])) ?>"
                                                data-tasks="<?= htmlspecialchars($row['tasks_completed']) ?>"
                                                data-challenges="<?= htmlspecialchars($row['challenges'] ?? '') ?>"
                                                data-plan="<?= htmlspecialchars($row['plan_tomorrow'] ?? '') ?>"
                                                onclick="openViewModal(this)">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- View / Print Single PDF Dossier -->
                                            <a href="dpr_export.php?format=pdf&id=<?= $row['id'] ?>&preview=1" target="_blank" class="btn-icon-action" title="View / Print Branded PDF Dossier">
                                                <i class="fas fa-file-pdf" style="color:#ef4444;"></i>
                                            </a>

                                            <?php if ($can_edit): ?>
                                            <!-- Edit Today's Report -->
                                            <button type="button" class="btn-icon-action" title="Edit Report" onclick="openDprModal(true)">
                                                <i class="fas fa-pencil-alt" style="color:var(--primary-color);"></i>
                                            </button>
                                            <?php endif; ?>

                                            <?php if ($can_delete): ?>
                                            <!-- Delete Report -->
                                            <button type="button" class="btn-icon-action text-danger" title="Delete Report" onclick="deleteReport(<?= $row['id'] ?>, '<?= date('M j, Y', $report_date_ts) ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_manager ? 5 : 4 ?>" style="text-align:center; padding: 50px 20px;">
                                    <div style="max-width: 380px; margin: 0 auto; color: #64748b;">
                                        <div style="font-size: 42px; color: #cbd5e1; margin-bottom: 12px;">
                                            <i class="fas fa-clipboard-check"></i>
                                        </div>
                                        <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 6px;">No Progress Reports Found</h4>
                                        <p style="font-size: 13.5px; margin-bottom: 18px;">No reports matched your current filters. Try resetting the filters or submit today's update.</p>
                                        <?php if (!$today_report): ?>
                                            <button type="button" class="btn-primary-custom" onclick="openDprModal(false)">
                                                <i class="fas fa-plus"></i> Submit Today's Report
                                            </button>
                                        <?php else: ?>
                                            <a href="dpr.php" class="btn-secondary-custom">
                                                <i class="fas fa-undo"></i> Reset Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Pagination Controls ────────────────────────────────────────── -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px; border-top:1px solid var(--border-color); flex-wrap:wrap; gap:12px;">
                <div style="font-size:13px; color:#64748b;">
                    Showing <strong><?= min($offset + 1, $total_records) ?></strong> to <strong><?= min($offset + $limit, $total_records) ?></strong> of <strong><?= $total_records ?></strong> reports
                </div>
                <div style="display:flex; gap:6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($current_params, ['page' => $page - 1])) ?>" class="btn-icon-action" style="width:36px; height:36px;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="?<?= http_build_query(array_merge($current_params, ['page' => $i])) ?>" 
                               class="btn-icon-action <?= $i === $page ? 'active' : '' ?>" 
                               style="width:36px; height:36px; font-weight:700; <?= $i === $page ? 'background:var(--primary-color); color:#fff; border-color:var(--primary-color);' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:36px; color:#94a3b8;">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($current_params, ['page' => $page + 1])) ?>" class="btn-icon-action" style="width:36px; height:36px;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 1: Submit / Edit Today's Report                                       -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="dprModal" class="modal-overlay" onclick="if(event.target===this)closeDprModal()">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-pen-alt"></i></div>
                <span id="modalTitle">Submit Today's Report</span>
            </h4>
            <button class="modal-close" onclick="closeDprModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="dprForm">
            <input type="hidden" name="action" id="formAction" value="save_dpr">
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:18px; font-size:13px; color:#475569; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-info-circle" style="color:var(--primary-color);"></i>
                    <span>Reporting for: <strong><?= date('l, F j, Y') ?></strong></span>
                </div>

                <!-- Tasks Completed -->
                <div class="form-group" style="margin-bottom:18px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:var(--text-dark); margin:0;">
                            Tasks Completed Today <span style="color:#ef4444;">*</span>
                        </label>
                        <button type="button" class="btn-secondary-custom" style="padding:2px 8px; font-size:11px; border-radius:4px;" onclick="addBullet('f_tasks')">
                            + Bullet
                        </button>
                    </div>
                    <textarea name="tasks_completed" id="f_tasks" rows="5" required class="filter-input" style="width:100%; resize:vertical; font-family:inherit; line-height:1.5;" placeholder="• Completed UI revamp for DPR&#10;• Implemented CSV and PDF export&#10;• Fixed responsive mobile layout"></textarea>
                </div>

                <!-- Challenges & Blockers -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label style="display:block; font-size:13px; font-weight:700; color:var(--text-dark); margin-bottom:6px;">
                        Challenges / Roadblocks (Optional)
                    </label>
                    <textarea name="challenges" id="f_challenges" rows="3" class="filter-input" style="width:100%; resize:vertical; font-family:inherit; line-height:1.5;" placeholder="Any issues blocking your progress? (e.g. Waiting for client credentials, server timeout). Leave blank if none."></textarea>
                </div>

                <!-- Plan for Tomorrow -->
                <div class="form-group" style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:var(--text-dark); margin:0;">
                            Plan for Tomorrow <span style="color:#ef4444;">*</span>
                        </label>
                        <button type="button" class="btn-secondary-custom" style="padding:2px 8px; font-size:11px; border-radius:4px;" onclick="addBullet('f_plan')">
                            + Bullet
                        </button>
                    </div>
                    <textarea name="plan_tomorrow" id="f_plan" rows="4" required class="filter-input" style="width:100%; resize:vertical; font-family:inherit; line-height:1.5;" placeholder="• QA test new features on staging&#10;• Review pending pull requests"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeDprModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="dprSubmitBtn">
                    <span id="dprBtnText"><i class="fas fa-paper-plane"></i> Submit Report</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2: View Report Details                                                -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-file-alt"></i></div>
                <span id="viewModalTitle">Daily Progress Report</span>
            </h4>
            <button class="modal-close" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <!-- Member Metadata Card -->
            <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div id="viewModalAvatar" class="dpr-avatar" style="width:44px; height:44px; font-size:16px;">U</div>
                    <div>
                        <div id="viewModalUser" style="font-size:15px; font-weight:800; color:var(--text-dark);">User Name</div>
                        <div id="viewModalRole" style="font-size:12px; color:#64748b;">Role • user@graphicafix.com</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div id="viewModalDate" style="font-weight:700; color:var(--primary-color); font-size:13.5px;">Date</div>
                    <div id="viewModalCreated" style="font-size:11.5px; color:#94a3b8;">Submitted at</div>
                </div>
            </div>

            <!-- Tasks Completed -->
            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#0284c7; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                    <i class="fas fa-check-circle"></i> Tasks Completed
                </label>
                <div id="viewTasks" style="font-size:14px; color:#334155; background:#f0fdf4; border:1px solid #bbf7d0; padding:14px; border-radius:8px; border-left:4px solid #10b981; white-space:pre-wrap; line-height:1.6;"></div>
            </div>

            <!-- Roadblocks / Challenges -->
            <div style="margin-bottom:18px;" id="viewChallengesWrap">
                <label style="display:block; font-size:12px; font-weight:800; color:#dc2626; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                    <i class="fas fa-exclamation-triangle"></i> Challenges & Roadblocks
                </label>
                <div id="viewChallenges" style="font-size:14px; color:#7f1d1d; background:#fef2f2; border:1px solid #fecaca; padding:14px; border-radius:8px; border-left:4px solid #ef4444; white-space:pre-wrap; line-height:1.6;"></div>
            </div>

            <!-- Plan Tomorrow -->
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">
                    <i class="fas fa-arrow-alt-circle-right"></i> Plan for Tomorrow
                </label>
                <div id="viewPlan" style="font-size:14px; color:#334155; background:#f8fafc; border:1px solid #e2e8f0; padding:14px; border-radius:8px; border-left:4px solid #0284c7; white-space:pre-wrap; line-height:1.6;"></div>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
            <div style="display:flex; gap:8px;">
                <!-- Copy Standup text -->
                <button type="button" class="btn-secondary-custom" id="btnCopyStandup" onclick="copyCurrentStandup()" style="font-size:13px; padding:8px 14px;">
                    <i class="fas fa-copy"></i> Copy for Standup
                </button>
                <!-- View / Print Single PDF Dossier -->
                <a id="btnDownloadSinglePdf" href="#" target="_blank" class="btn-secondary-custom" style="font-size:13px; padding:8px 14px;">
                    <i class="fas fa-file-pdf" style="color:#ef4444;"></i> View PDF Dossier
                </a>
            </div>
            <button type="button" class="btn-primary-custom" style="font-size:13px; padding:8px 18px;" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 3: Custom Export Options                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-file-export"></i></div>
                <span>Export Progress Reports</span>
            </h4>
            <button class="modal-close" onclick="closeExportModal()"><i class="fas fa-times"></i></button>
        </div>
        <form action="dpr_export.php" method="GET" target="_blank" id="customExportForm">
            <div class="modal-body">
                <p style="font-size:13.5px; color:#64748b; margin-top:0; margin-bottom:18px;">
                    Configure your export parameters below to download an Excel CSV or a branded PDF dossier.
                </p>

                <!-- Export Format Radio -->
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        Select Format
                    </label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <label style="border:2px solid #e2e8f0; border-radius:10px; padding:14px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.2s;" id="labelFormatCsv" class="export-format-opt">
                            <input type="radio" name="format" value="csv" checked onchange="toggleFormatStyle(this)">
                            <div>
                                <div style="font-weight:700; font-size:14px; color:#0f172a;"><i class="fas fa-file-csv" style="color:#10b981;"></i> CSV Spreadsheet</div>
                                <div style="font-size:11.5px; color:#64748b;">For Excel & Google Sheets</div>
                            </div>
                        </label>
                        <label style="border:2px solid #e2e8f0; border-radius:10px; padding:14px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.2s;" id="labelFormatPdf" class="export-format-opt">
                            <input type="radio" name="format" value="pdf" onchange="toggleFormatStyle(this)">
                            <div>
                                <div style="font-weight:700; font-size:14px; color:#0f172a;"><i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF Document</div>
                                <div style="font-size:11.5px; color:#64748b;">Branded Executive Dossier</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Date Range Selection -->
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        Date Range
                    </label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <span style="font-size:11px; color:#64748b;">From:</span>
                            <input type="date" name="start_date" id="exportStartDate" value="<?= htmlspecialchars($filter_start_date) ?>" class="filter-input">
                        </div>
                        <div>
                            <span style="font-size:11px; color:#64748b;">To:</span>
                            <input type="date" name="end_date" id="exportEndDate" value="<?= htmlspecialchars($filter_end_date) ?>" class="filter-input">
                        </div>
                    </div>
                </div>

                <?php if ($is_manager): ?>
                <!-- Team Member -->
                <div style="margin-bottom:18px;">
                    <label style="display:block; font-size:12px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        Filter by Team Member
                    </label>
                    <select name="user_id" class="filter-input">
                        <option value="">All Team Members</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Only Blockers Checkbox -->
                <div style="margin-bottom:10px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13.5px; font-weight:600; color:#334155;">
                        <input type="checkbox" name="has_blockers" value="1" <?= $filter_blockers === 1 ? 'checked' : '' ?> style="width:16px; height:16px;">
                        <span>Export only reports with flagged Roadblocks / Challenges</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" onclick="setTimeout(closeExportModal, 500)">
                    <i class="fas fa-download"></i> Download Export
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- JAVASCRIPT LOGIC                                                            -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<script>
const existingReport = <?= json_encode($today_report) ?>;
let activeModalData = null;

// Preset Date Filter Helpers
function setPreset(preset) {
    const today = new Date();
    const formatDate = (d) => d.toISOString().split('T')[0];

    if (preset === 'today') {
        document.getElementById('inputStartDate').value = formatDate(today);
        document.getElementById('inputEndDate').value = formatDate(today);
    } else if (preset === 'yesterday') {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        document.getElementById('inputStartDate').value = formatDate(y);
        document.getElementById('inputEndDate').value = formatDate(y);
    } else if (preset === 'week') {
        const d = new Date(today);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday
        const monday = new Date(d.setDate(diff));
        document.getElementById('inputStartDate').value = formatDate(monday);
        document.getElementById('inputEndDate').value = formatDate(today);
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('inputStartDate').value = formatDate(firstDay);
        document.getElementById('inputEndDate').value = formatDate(today);
    } else {
        document.getElementById('inputStartDate').value = '';
        document.getElementById('inputEndDate').value = '';
    }
    document.getElementById('filterForm').submit();
}

// ── View Modal ───────────────────────────────────────────────────────────────
function openViewModal(btn) {
    activeModalData = {
        id: btn.dataset.id,
        name: btn.dataset.name,
        role: btn.dataset.role,
        email: btn.dataset.email,
        date: btn.dataset.date,
        rawdate: btn.dataset.rawdate,
        created: btn.dataset.created,
        tasks: btn.dataset.tasks,
        challenges: btn.dataset.challenges,
        plan: btn.dataset.plan
    };

    document.getElementById('viewModalTitle').textContent = `DPR — ${activeModalData.name} (${activeModalData.date})`;
    document.getElementById('viewModalAvatar').textContent = activeModalData.name.charAt(0).toUpperCase();
    document.getElementById('viewModalUser').textContent = activeModalData.name;
    document.getElementById('viewModalRole').textContent = `${activeModalData.role} • ${activeModalData.email || 'Graphicafix Staff'}`;
    document.getElementById('viewModalDate').textContent = activeModalData.date;
    document.getElementById('viewModalCreated').textContent = `Submitted: ${activeModalData.created}`;
    
    document.getElementById('viewTasks').textContent = activeModalData.tasks;

    const chWrap = document.getElementById('viewChallengesWrap');
    if (activeModalData.challenges && activeModalData.challenges.trim() !== '') {
        chWrap.style.display = 'block';
        document.getElementById('viewChallenges').textContent = activeModalData.challenges;
    } else {
        chWrap.style.display = 'none';
    }

    document.getElementById('viewPlan').textContent = activeModalData.plan;
    document.getElementById('btnDownloadSinglePdf').href = `dpr_export.php?format=pdf&id=${activeModalData.id}&preview=1`;

    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function copyCurrentStandup() {
    if (!activeModalData) return;
    const text = `📋 Daily Progress Report: ${activeModalData.name} (${activeModalData.date})\n\n` +
        `✅ Tasks Completed:\n${activeModalData.tasks}\n\n` +
        `⚠️ Challenges / Blockers:\n${activeModalData.challenges && activeModalData.challenges.trim() ? activeModalData.challenges : 'None (Smooth)'}\n\n` +
        `🎯 Plan for Tomorrow:\n${activeModalData.plan}`;

    navigator.clipboard.writeText(text).then(() => {
        showToast('Report copied to clipboard in standup format!');
    }).catch(() => {
        showToast('Unable to copy text', 'error');
    });
}

// ── Submit / Edit Modal ──────────────────────────────────────────────────────
function openDprModal(isEdit = false) {
    const modal = document.getElementById('dprModal');
    document.getElementById('dprForm').reset();

    if (isEdit && existingReport) {
        document.getElementById('modalTitle').textContent = "Edit Today's Report";
        document.getElementById('dprBtnText').innerHTML = '<i class="fas fa-save"></i> Update Report';
        document.getElementById('f_tasks').value = existingReport.tasks_completed;
        document.getElementById('f_challenges').value = existingReport.challenges || '';
        document.getElementById('f_plan').value = existingReport.plan_tomorrow;
    } else {
        document.getElementById('modalTitle').textContent = "Submit Today's Report";
        document.getElementById('dprBtnText').innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDprModal() {
    document.getElementById('dprModal').classList.remove('active');
    document.body.style.overflow = '';
}

function addBullet(textareaId) {
    const ta = document.getElementById(textareaId);
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const val = ta.value;
    const prefix = val.length === 0 || val.endsWith('\n') ? '• ' : '\n• ';
    ta.value = val.substring(0, start) + prefix + val.substring(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + prefix.length;
}

document.getElementById('dprForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('dprSubmitBtn');
    const txt = document.getElementById('dprBtnText');
    const orig = txt.innerHTML;

    btn.disabled = true;
    txt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const payload = new URLSearchParams();
    payload.append('action', 'save_dpr');
    payload.append('tasks_completed', document.getElementById('f_tasks').value);
    payload.append('challenges', document.getElementById('f_challenges').value);
    payload.append('plan_tomorrow', document.getElementById('f_plan').value);

    fetch('dpr_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeDprModal();
            showToast(data.message || 'Report saved successfully!');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Error saving report', 'error');
            btn.disabled = false;
            txt.innerHTML = orig;
        }
    })
    .catch(() => {
        showToast('Server error while saving report', 'error');
        btn.disabled = false;
        txt.innerHTML = orig;
    });
});

// ── Delete Report ────────────────────────────────────────────────────────────
function deleteReport(id, dateStr) {
    if (!confirm(`Are you sure you want to delete the daily progress report for ${dateStr}? This action cannot be undone.`)) {
        return;
    }

    const payload = new URLSearchParams();
    payload.append('action', 'delete_dpr');
    payload.append('id', id);

    fetch('dpr_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Report deleted successfully');
            const row = document.getElementById(`dpr-row-${id}`);
            if (row) {
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';
                setTimeout(() => { row.remove(); location.reload(); }, 300);
            } else {
                location.reload();
            }
        } else {
            showToast(data.message || 'Error deleting report', 'error');
        }
    })
    .catch(() => {
        showToast('Server error while deleting report', 'error');
    });
}

// ── Export Modal ─────────────────────────────────────────────────────────────
function openExportModal() {
    document.getElementById('exportModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
    document.body.style.overflow = '';
}

function toggleFormatStyle(radio) {
    const csvOpt = document.getElementById('labelFormatCsv');
    const pdfOpt = document.getElementById('labelFormatPdf');
    if (radio.value === 'csv') {
        csvOpt.style.borderColor = 'var(--primary-color)';
        csvOpt.style.background = '#f0fdf4';
        pdfOpt.style.borderColor = '#e2e8f0';
        pdfOpt.style.background = '#ffffff';
    } else {
        pdfOpt.style.borderColor = 'var(--primary-color)';
        pdfOpt.style.background = '#fef2f2';
        csvOpt.style.borderColor = '#e2e8f0';
        csvOpt.style.background = '#ffffff';
    }
}

// ── Toast Notification Helper ────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const color = type === 'success' ? '#10b981' : '#ef4444';
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 16px rgba(0,0,0,.1);font-size:13.5px;min-width:260px;border-left:4px solid ${color};animation:slideUp .2s ease;`;
    t.innerHTML = `<span style="font-weight:700;color:${color}"><i class="fas ${icon}"></i></span><span style="font-weight:600;color:#1e293b;">${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transition = 'opacity .3s';
        setTimeout(() => t.remove(), 300);
    }, 3200);
}

// Global Keyboard ESC handler for modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeDprModal();
        closeExportModal();
    }
});
</script>

<?php include('dashboard_footer.php'); ?>
