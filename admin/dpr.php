<?php
include 'dashboard_header.php';

$is_manager = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'manager']);

// Safety fallback table creation & automated schema migration for missing columns
require_once __DIR__ . '/../core/dpr_schema.php';
ensure_dpr_schema($conn);

// Fetch internal team members for filter dropdown (exclude students and clients)
$users = [];
$users_result = $conn->query("SELECT user_id, name, role, email FROM users WHERE role NOT IN ('student', 'client', 'client_sub') AND (status = 'active' OR status IS NULL) ORDER BY name ASC");
if ($users_result) {
    while ($u = $users_result->fetch_assoc()) {
        $users[] = $u;
    }
}

// Fetch active projects for project association and filtering
$projects = [];
$projects_result = $conn->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
if ($projects_result) {
    while ($p = $projects_result->fetch_assoc()) {
        $projects[] = $p;
    }
}

// ── Filter Parameters ────────────────────────────────────────────────────────
$search_q          = trim($_GET['q'] ?? '');
$preset            = trim($_GET['preset'] ?? '');
$filter_date       = trim($_GET['date'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date   = trim($_GET['end_date'] ?? '');
$filter_user       = (isset($_GET['user_id']) && $_GET['user_id'] !== '') ? intval($_GET['user_id']) : '';
$filter_project    = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? intval($_GET['project_id']) : '';
$filter_blockers   = (isset($_GET['has_blockers']) && $_GET['has_blockers'] !== '') ? intval($_GET['has_blockers']) : '';
$filter_severity   = trim($_GET['severity'] ?? '');

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

if ($filter_project !== '') {
    $where_conditions[] = "dpr.project_id = " . intval($filter_project);
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
    $where_conditions[] = "(dpr.blocker_severity IN ('minor', 'critical') OR (dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != ''))";
}

if (!empty($filter_severity)) {
    $where_conditions[] = "dpr.blocker_severity = '" . $conn->real_escape_string($filter_severity) . "'";
}

if (!empty($search_q)) {
    $sq = $conn->real_escape_string($search_q);
    $where_conditions[] = "(dpr.tasks_completed LIKE '%$sq%' OR dpr.tasks_in_progress LIKE '%$sq%' OR dpr.challenges LIKE '%$sq%' OR dpr.waiting_on LIKE '%$sq%' OR dpr.plan_tomorrow LIKE '%$sq%' OR u.name LIKE '%$sq%' OR p.project_name LIKE '%$sq%')";
}

$where_clause = empty($where_conditions) ? "1=1" : implode(" AND ", $where_conditions);

// ── KPI Metrics Queries ───────────────────────────────────────────────────────
$stats_sql = "
    SELECT 
        COUNT(*) as total_reports,
        COALESCE(SUM(dpr.hours_worked), 0) as total_hours,
        COALESCE(AVG(dpr.productivity_rating), 5.0) as avg_rating,
        SUM(CASE WHEN dpr.blocker_severity IN ('minor','critical') OR (dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != '') THEN 1 ELSE 0 END) as total_blockers,
        SUM(CASE WHEN dpr.blocker_severity = 'critical' THEN 1 ELSE 0 END) as critical_blockers
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
    LEFT JOIN projects p ON dpr.project_id = p.id
    WHERE $where_clause
";
$stats_res = $conn->query($stats_sql);
$stats_data = $stats_res ? $stats_res->fetch_assoc() : [];
$total_records     = intval($stats_data['total_reports'] ?? 0);
$total_hours       = floatval($stats_data['total_hours'] ?? 0);
$avg_rating        = round(floatval($stats_data['avg_rating'] ?? 5.0), 1);
$total_blockers    = intval($stats_data['total_blockers'] ?? 0);
$critical_blockers = intval($stats_data['critical_blockers'] ?? 0);

// Today's submissions & Team compliance
$today_str = date('Y-m-d');
if ($is_manager) {
    $today_sub_res = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM daily_progress_reports WHERE report_date = '$today_str'");
    $today_submitted_count = $today_sub_res ? intval($today_sub_res->fetch_assoc()['count']) : 0;
    
    $total_team_res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('student', 'client', 'client_sub') AND (status = 'active' OR status IS NULL)");
    $total_team_count = $total_team_res ? intval($total_team_res->fetch_assoc()['count']) : count($users);
    $today_pending_count = max(0, $total_team_count - $today_submitted_count);
} else {
    $today_sub_res = $conn->query("SELECT COUNT(*) as count FROM daily_progress_reports WHERE user_id = $user_id AND report_date = '$today_str'");
    $today_submitted_count = $today_sub_res ? intval($today_sub_res->fetch_assoc()['count']) : 0;
    $today_pending_count = $today_submitted_count > 0 ? 0 : 1;
}

// ── Fetch Paginated Reports ──────────────────────────────────────────────────
$reports_sql = "
    SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email, p.project_name
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
    LEFT JOIN projects p ON dpr.project_id = p.id
    WHERE $where_clause
    ORDER BY dpr.report_date DESC, dpr.created_at DESC
    LIMIT $limit OFFSET $offset
";
$reports_result = $conn->query($reports_sql);
$total_pages = ceil($total_records / $limit);

// Check if current user already submitted today (with project details)
$stmt = $conn->prepare("
    SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email, p.project_name 
    FROM daily_progress_reports dpr 
    JOIN users u ON dpr.user_id = u.user_id
    LEFT JOIN projects p ON p.id = dpr.project_id 
    WHERE dpr.user_id = ? AND dpr.report_date = ?
");
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
/* ── DPR Executive Design Tokens ── */
.dpr-wrapper {
    --dpr-primary: #024442;
    --dpr-primary-light: #035c59;
    --dpr-primary-dark: #012b2a;
    --dpr-primary-subtle: #f0fdf9;
    --dpr-accent: #B6F763;
    --dpr-accent-hover: #a3e84e;
    --dpr-accent-dark: #193b0b;
    --dpr-text-dark: #0f172a;
    --dpr-text-main: #334155;
    --dpr-text-muted: #64748b;
    --dpr-text-light: #94a3b8;
    --dpr-border: #e2e8f0;
    --dpr-border-light: #f1f5f9;
    --dpr-card-bg: #ffffff;
    --dpr-radius: 14px;
    --dpr-radius-sm: 8px;
    --dpr-radius-md: 10px;
    --dpr-radius-lg: 18px;
    --dpr-shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
    --dpr-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.03);
    --dpr-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -1px rgba(15, 23, 42, 0.03);
    --dpr-shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -2px rgba(15, 23, 42, 0.04);
    max-width: 1440px;
    margin: 0 auto;
    padding-bottom: 40px;
    font-family: inherit;
    color: var(--dpr-text-main);
}

/* ── Page Header ── */
.dpr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 24px;
    padding: 6px 0;
}
.dpr-header-left {
    flex: 1;
    min-width: 280px;
}
.dpr-header-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.dpr-badge-pulse {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.dpr-header-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--dpr-text-dark);
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: -0.3px;
}
.dpr-header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    background: var(--dpr-primary);
    color: var(--dpr-accent);
    border-radius: 10px;
    font-size: 18px;
    box-shadow: 0 4px 10px rgba(2, 68, 66, 0.2);
}
.dpr-header-sub {
    color: var(--dpr-text-muted);
    font-size: 13.5px;
    margin: 0;
    line-height: 1.5;
}
.dpr-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* ── Standard Button Styles ── */
.btn-dpr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1.4;
    border: none;
    outline: none;
}
.btn-dpr-primary {
    background: var(--dpr-primary);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(2, 68, 66, 0.2);
}
.btn-dpr-primary:hover {
    background: var(--dpr-primary-light);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(2, 68, 66, 0.3);
}
.btn-dpr-accent {
    background: var(--dpr-accent);
    color: var(--dpr-primary-dark);
    box-shadow: 0 2px 10px rgba(182, 247, 99, 0.4);
}
.btn-dpr-accent:hover {
    background: var(--dpr-accent-hover);
    color: var(--dpr-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(182, 247, 99, 0.5);
}
.btn-dpr-secondary {
    background: #ffffff;
    color: var(--dpr-text-dark);
    border: 1.5px solid var(--dpr-border);
    box-shadow: var(--dpr-shadow-xs);
}
.btn-dpr-secondary:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: var(--dpr-primary);
    transform: translateY(-1px);
}
.btn-dpr-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 8px;
}

/* ── KPI Metric Cards ── */
.dpr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.dpr-kpi-card {
    background: var(--dpr-card-bg);
    border: 1px solid var(--dpr-border);
    border-radius: var(--dpr-radius);
    padding: 18px 20px;
    box-shadow: var(--dpr-shadow-xs);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.25s ease;
}
.dpr-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--dpr-shadow-md);
    border-color: #cbd5e1;
}
.dpr-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.kpi-icon-blue    { background: #e0f2fe; color: #0284c7; }
.kpi-icon-purple  { background: #ede9fe; color: #7c3aed; }
.kpi-icon-emerald { background: #d1fae5; color: #059669; }
.kpi-icon-rose    { background: #fee2e2; color: #dc2626; }
.kpi-icon-slate   { background: #f1f5f9; color: #64748b; }

.dpr-kpi-body {
    flex: 1;
    min-width: 0;
}
.dpr-kpi-value {
    font-size: 25px;
    font-weight: 800;
    color: var(--dpr-text-dark);
    line-height: 1.1;
    letter-spacing: -0.5px;
}
.dpr-kpi-value span {
    font-size: 17px;
    font-weight: 700;
    color: var(--dpr-text-muted);
    margin-left: 2px;
}
.dpr-kpi-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--dpr-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.dpr-kpi-sub {
    font-size: 12px;
    color: var(--dpr-text-light);
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dpr-rating-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #fffbeb;
    color: #b45309;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 11px;
}

/* ── Featured Today's Submission Card ── */
.today-showcase-card {
    background: linear-gradient(135deg, #024442 0%, #033634 100%);
    color: #ffffff;
    border-radius: var(--dpr-radius-lg);
    padding: 24px 28px;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px -5px rgba(2, 68, 66, 0.3);
    border: 1px solid rgba(182, 247, 99, 0.3);
    position: relative;
    overflow: hidden;
}
.today-showcase-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 18px;
}
.today-badge-verified {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(182, 247, 99, 0.18);
    color: #B6F763;
    border: 1px solid rgba(182, 247, 99, 0.35);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.today-showcase-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px 0;
    color: #ffffff;
    letter-spacing: -0.2px;
}
.today-showcase-meta {
    font-size: 12.5px;
    color: rgba(255, 255, 255, 0.75);
}
.today-showcase-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-glass-action {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #ffffff;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-glass-action:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.4);
}

.today-metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.today-metric-cell {
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 10px 14px;
    backdrop-filter: blur(4px);
}
.today-metric-cell .label {
    font-size: 11px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.65);
    font-weight: 700;
    letter-spacing: 0.5px;
}
.today-metric-cell .val {
    font-size: 15px;
    font-weight: 800;
    color: #ffffff;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.today-preview-box {
    background: rgba(0, 0, 0, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.55;
    white-space: pre-line;
    max-height: 90px;
    overflow-y: auto;
}

/* ── Today's Pending Banner ── */
.today-pending-banner {
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf9 100%);
    border: 1.5px solid #a7f3d0;
    border-radius: var(--dpr-radius-lg);
    padding: 22px 26px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    box-shadow: 0 4px 14px rgba(2, 68, 66, 0.05);
}
.today-pending-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 260px;
}
.today-pending-icon {
    width: 48px;
    height: 48px;
    background: #ecfdf5;
    color: var(--dpr-primary);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.today-pending-title {
    font-size: 16.5px;
    font-weight: 800;
    color: var(--dpr-text-dark);
    margin: 0 0 4px 0;
}
.today-pending-sub {
    font-size: 13px;
    color: var(--dpr-text-muted);
    margin: 0;
    line-height: 1.45;
}

/* ── Filter Card ── */
.dpr-filter-card {
    background: var(--dpr-card-bg);
    border: 1px solid var(--dpr-border);
    border-radius: var(--dpr-radius);
    padding: 18px 22px;
    margin-bottom: 24px;
    box-shadow: var(--dpr-shadow-xs);
}
.filter-row-top {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.filter-group {
    flex: 1;
    min-width: 150px;
}
.filter-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--dpr-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.filter-input-wrap {
    position: relative;
    width: 100%;
}
.filter-input-wrap i.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--dpr-text-light);
    font-size: 13px;
}
.filter-input {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid var(--dpr-border);
    border-radius: var(--dpr-radius-sm);
    background: #f8fafc;
    font-size: 13px;
    color: var(--dpr-text-dark);
    font-weight: 600;
    transition: all 0.2s;
    outline: none;
    font-family: inherit;
}
.filter-input.with-icon {
    padding-left: 34px;
}
.filter-input:focus {
    border-color: var(--dpr-primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.08);
}
.preset-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--dpr-border-light);
    align-items: center;
}
.preset-pill-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--dpr-text-muted);
    margin-right: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.preset-btn {
    background: #f1f5f9;
    border: 1px solid transparent;
    color: var(--dpr-text-main);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    outline: none;
}
.preset-btn:hover {
    background: #e2e8f0;
    color: var(--dpr-text-dark);
}
.preset-btn.active {
    background: var(--dpr-primary);
    color: #ffffff;
    font-weight: 700;
}

/* ── Table Card & Responsive Feed ── */
.dpr-table-card {
    background: var(--dpr-card-bg);
    border: 1px solid var(--dpr-border);
    border-radius: var(--dpr-radius);
    box-shadow: var(--dpr-shadow-xs);
    overflow: hidden;
}
.dpr-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 22px;
    border-bottom: 1px solid var(--dpr-border);
    background: #ffffff;
    flex-wrap: wrap;
    gap: 12px;
}
.dpr-table-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--dpr-text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}
.dpr-count-badge {
    background: #f1f5f9;
    color: var(--dpr-text-muted);
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
}
.dpr-table-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dpr-table {
    width: 100%;
    border-collapse: collapse;
}
.dpr-table th {
    background: #f8fafc;
    color: var(--dpr-text-muted);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--dpr-border);
    text-align: left;
    white-space: nowrap;
}
.dpr-table td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--dpr-border-light);
    font-size: 13px;
    vertical-align: top;
    color: var(--dpr-text-main);
}
.dpr-table tr:hover td {
    background-color: #fcfdfe;
}

/* Cell content helpers */
.dpr-user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.dpr-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--dpr-primary) 0%, #064e3b 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
}
.dpr-user-name {
    font-weight: 700;
    color: var(--dpr-text-dark);
    line-height: 1.25;
}
.dpr-user-role {
    font-size: 11px;
    color: var(--dpr-text-muted);
    font-weight: 600;
}

.dpr-date-main {
    font-weight: 700;
    color: var(--dpr-text-dark);
    font-size: 13px;
}
.dpr-date-day {
    font-size: 11.5px;
    color: var(--dpr-text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}

/* Badges & Tags */
.badge-hours {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    color: var(--dpr-text-dark);
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    border: 1px solid var(--dpr-border);
    margin-top: 4px;
}
.badge-project {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    max-width: 190px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.badge-project-general {
    background: #f8fafc;
    color: var(--dpr-text-muted);
    border-color: var(--dpr-border);
}
.badge-blocker-critical {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-blocker-minor {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 2px 8px;
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
    padding: 2px 8px;
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
    font-size: 10.5px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 10px;
}
.badge-yesterday {
    background: #f1f5f9;
    color: var(--dpr-text-main);
    font-size: 10.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
}
.badge-rating {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fef08a;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
}
.dpr-tasks-clamp {
    line-height: 1.5;
    color: var(--dpr-text-dark);
    font-size: 12.5px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: pre-line;
}
.dpr-wip-tag {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 11.5px;
    line-height: 1.35;
    margin-top: 6px;
    display: block;
}
.dpr-waiting-tag {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #c2410c;
    border-radius: 6px;
    padding: 3px 6px;
    font-size: 11px;
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-deliverables {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    margin-top: 5px;
}

/* Action buttons */
.dpr-actions-cell {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.btn-icon-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--dpr-border);
    background: #ffffff;
    color: var(--dpr-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.15s;
    text-decoration: none;
}
.btn-icon-action:hover {
    background: #f8fafc;
    color: var(--dpr-primary);
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.btn-icon-action.text-danger:hover {
    background: #fef2f2;
    color: #dc2626 !important;
    border-color: #fca5a5;
}

/* ── Modals & Overlay ── */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
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
    background: #ffffff;
    border-radius: 18px;
    width: 100%;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
    overflow: hidden;
    animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    border: 1px solid rgba(255, 255, 255, 0.15);
}
@keyframes modalSlideUp {
    from { transform: translateY(20px) scale(0.98); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.modal-header {
    background: var(--dpr-primary);
    color: #ffffff;
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #ffffff;
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
    color: var(--dpr-accent);
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
    padding: 4px;
    border-radius: 6px;
    transition: all 0.15s;
}
.modal-close:hover {
    color: #ffffff;
    background: rgba(255,255,255,0.1);
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
    padding: 22px 26px;
    overflow-y: auto !important;
    flex: 1 1 auto;
    min-height: 0;
}
.modal-footer {
    padding: 14px 24px;
    border-top: 1px solid var(--dpr-border);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f8fafc;
    flex-shrink: 0;
}

/* Modal Form Cards */
.form-section-card {
    background: #ffffff;
    border: 1px solid var(--dpr-border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}
.form-section-title {
    font-size: 11.5px;
    font-weight: 800;
    color: var(--dpr-primary);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.form-field-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--dpr-text-dark);
    margin-bottom: 5px;
}

/* Modal Date Banner */
.dpr-locked-date-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f0fdf9;
    border: 1.5px solid #a7f3d0;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
    gap: 12px;
}
.dpr-locked-icon {
    width: 40px;
    height: 40px;
    background: var(--dpr-primary);
    color: var(--dpr-accent);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.dpr-locked-info {
    flex-grow: 1;
}
.dpr-locked-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #065f46;
}
.dpr-locked-date {
    font-size: 15px;
    font-weight: 800;
    color: var(--dpr-text-dark);
    margin-top: 2px;
}
.dpr-locked-tag {
    background: var(--dpr-primary);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}

/* Preset Hour Pills */
.hour-pill-btn {
    border: 1px solid var(--dpr-border);
    background: #f8fafc;
    color: var(--dpr-text-main);
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    outline: none;
}
.hour-pill-btn:hover {
    background: #e2e8f0;
    color: var(--dpr-text-dark);
}
.hour-pill-btn.active {
    background: var(--dpr-primary);
    color: #ffffff;
    border-color: var(--dpr-primary);
}

/* Rating Pill Buttons */
.rating-pill-opt {
    flex: 1;
    text-align: center;
    padding: 8px 4px;
    border: 1.5px solid var(--dpr-border);
    border-radius: 8px;
    background: #f8fafc;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    color: var(--dpr-text-main);
    transition: all 0.2s;
}
.rating-pill-opt:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}
.rating-pill-opt.active {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
}

/* Severity Pill Buttons */
.severity-pill-opt {
    flex: 1;
    text-align: center;
    padding: 8px 6px;
    border: 1.5px solid var(--dpr-border);
    border-radius: 8px;
    background: #f8fafc;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    color: var(--dpr-text-main);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.severity-pill-opt.active-none {
    background: #f0fdf4;
    border-color: #10b981;
    color: #047857;
}
.severity-pill-opt.active-minor {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #b45309;
}
.severity-pill-opt.active-critical {
    background: #fef2f2;
    border-color: #ef4444;
    color: #b91c1c;
}

/* View Dossier Section Cards */
.dossier-card {
    background: #ffffff;
    border: 1px solid var(--dpr-border);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
    border-left: 4px solid var(--dpr-border);
}
.dossier-card.card-emerald {
    border-left-color: #10b981;
    background: #fcfdfd;
}
.dossier-card.card-blue {
    border-left-color: #3b82f6;
    background: #fcfdfd;
}
.dossier-card.card-amber {
    border-left-color: #f59e0b;
    background: #fffdfa;
}
.dossier-card.card-purple {
    border-left-color: #8b5cf6;
    background: #fdfcff;
}
.dossier-card.card-sky {
    border-left-color: #0284c7;
    background: #fbfdfd;
}
.dossier-title {
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.dossier-content {
    font-size: 13.5px;
    line-height: 1.6;
    white-space: pre-wrap;
    color: var(--dpr-text-dark);
}

/* Export format card */
.export-format-card {
    border: 2px solid var(--dpr-border);
    background: #ffffff;
    border-radius: 10px;
    padding: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
}
.export-format-card.active {
    border-color: var(--dpr-primary);
    background: #f0fdf9;
}

/* Responsiveness */
@media (max-width: 768px) {
    .dpr-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .dpr-header-actions {
        width: 100%;
    }
    .dpr-header-actions .btn-dpr {
        flex: 1;
    }
    .filter-row-top {
        flex-direction: column;
        align-items: stretch;
    }
    .today-showcase-card {
        padding: 18px 20px;
    }
}
</style>

<div class="height-100">
    <div class="dpr-wrapper">

        <!-- ── Page Header ────────────────────────────────────────────────────── -->
        <div class="dpr-header">
            <div class="dpr-header-left">
                <div class="dpr-header-badge">
                    <span class="dpr-badge-pulse"></span>
                    <span>Daily Progress & Standup</span>
                </div>
                <h1 class="dpr-header-title">
                    <span class="dpr-header-icon"><i class="fas fa-calendar-check"></i></span>
                    <span>Daily Progress Reports</span>
                </h1>
                <p class="dpr-header-sub">
                    <?= $is_manager 
                        ? 'Executive dashboard for team work logs, billable hours, project milestones, and blocker mitigation.' 
                        : 'Submit and track your daily work achievements, billable project time, obstacles, and next objectives.' ?>
                </p>
            </div>
            <div class="dpr-header-actions">
                <!-- Export Trigger -->
                <button type="button" class="btn-dpr btn-dpr-secondary" onclick="openExportModal()" title="Export Reports">
                    <i class="fas fa-file-export" style="color:var(--dpr-primary);"></i>
                    <span>Export Reports</span>
                </button>

                <!-- DPR Submission / Edit Trigger -->
                <?php if (!$today_report): ?>
                    <button type="button" class="btn-dpr btn-dpr-primary" onclick="openDprModal(false)">
                        <i class="fas fa-plus-circle"></i>
                        <span>Submit Today's Report</span>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-dpr btn-dpr-accent" onclick="openDprModal(true, <?= htmlspecialchars(json_encode($today_report)) ?>)">
                        <i class="fas fa-edit"></i>
                        <span>Edit Today's Report</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── KPI Metrics Grid ────────────────────────────────────────────────── -->
        <div class="dpr-kpi-grid">
            <!-- KPI 1: Total Reports -->
            <div class="dpr-kpi-card">
                <div class="dpr-kpi-icon kpi-icon-blue">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="dpr-kpi-body">
                    <div class="dpr-kpi-value"><?= number_format($total_records) ?></div>
                    <div class="dpr-kpi-label"><?= ($where_clause !== '1=1') ? 'Filtered Reports' : 'Total Work Reports' ?></div>
                    <div class="dpr-kpi-sub"><?= $is_manager ? 'Across all team members' : 'Your submitted logs' ?></div>
                </div>
            </div>

            <!-- KPI 2: Total Hours Logged -->
            <div class="dpr-kpi-card">
                <div class="dpr-kpi-icon kpi-icon-purple">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="dpr-kpi-body">
                    <div class="dpr-kpi-value"><?= number_format($total_hours, 1) ?><span>h</span></div>
                    <div class="dpr-kpi-label">Work Hours Logged</div>
                    <div class="dpr-kpi-sub">
                        <span class="dpr-rating-pill"><i class="fas fa-star"></i> <?= number_format($avg_rating, 1) ?></span> / 5.0 Productivity
                    </div>
                </div>
            </div>

            <!-- KPI 3: Today's Submissions -->
            <div class="dpr-kpi-card">
                <div class="dpr-kpi-icon kpi-icon-emerald">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="dpr-kpi-body">
                    <div class="dpr-kpi-value"><?= $today_submitted_count ?></div>
                    <div class="dpr-kpi-label"><?= $is_manager ? "Submitted Today" : "Today's Status" ?></div>
                    <div class="dpr-kpi-sub">
                        <?php if ($is_manager): ?>
                            <?= $today_pending_count > 0 ? "<span style='color:#b45309;font-weight:700;'>$today_pending_count pending</span>" : "<span style='color:#059669;font-weight:700;'>100% compliance</span>" ?>
                        <?php else: ?>
                            <?= $today_report ? "<span style='color:#059669;font-weight:700;'>Logged at " . date('h:i A', strtotime($today_report['created_at'])) . "</span>" : "<span style='color:#b45309;font-weight:700;'>Pending submission</span>" ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KPI 4: Roadblocks Flagged -->
            <div class="dpr-kpi-card">
                <div class="dpr-kpi-icon <?= $total_blockers > 0 ? 'kpi-icon-rose' : 'kpi-icon-slate' ?>">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="dpr-kpi-body">
                    <div class="dpr-kpi-value" style="<?= $total_blockers > 0 ? 'color:#dc2626;' : '' ?>"><?= $total_blockers ?></div>
                    <div class="dpr-kpi-label">Roadblocks Flagged</div>
                    <div class="dpr-kpi-sub">
                        <?php if ($critical_blockers > 0): ?>
                            <span class="badge-blocker-critical"><i class="fas fa-exclamation-circle"></i> <?= $critical_blockers ?> critical</span>
                        <?php elseif ($total_blockers > 0): ?>
                            <span class="badge-blocker-minor">Minor delays noted</span>
                        <?php else: ?>
                            <span class="badge-smooth"><i class="fas fa-check"></i> Operating smoothly</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── FEATURED: Today's Submission Showcase Card (If Submitted) ──────── -->
        <?php if ($today_report): ?>
        <div class="today-showcase-card">
            <div class="today-showcase-header">
                <div>
                    <div class="today-badge-verified">
                        <i class="fas fa-check-circle"></i> Today's Report Recorded & Verified
                    </div>
                    <h2 class="today-showcase-title">
                        Work Log: <?= date('l, F j, Y', strtotime($today_report['report_date'])) ?>
                    </h2>
                    <div class="today-showcase-meta">
                        Submitted at <?= date('h:i A', strtotime($today_report['created_at'])) ?> &bull; Logged by <?= htmlspecialchars($today_report['user_name'] ?? 'You') ?>
                    </div>
                </div>

                <!-- Quick Actions on Today's Card -->
                <div class="today-showcase-actions">
                    <button type="button" class="btn-dpr btn-dpr-accent btn-dpr-sm" onclick="openDprModal(true, <?= htmlspecialchars(json_encode($today_report)) ?>)">
                        <i class="fas fa-pencil-alt"></i> Edit Today's Report
                    </button>
                    <a href="dpr_export.php?format=pdf&id=<?= $today_report['id'] ?>&preview=1" target="_blank" class="btn-glass-action" title="Preview PDF Dossier">
                        <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF Dossier
                    </a>
                    <button type="button" class="btn-glass-action" onclick="openViewModalFromObj(<?= htmlspecialchars(json_encode($today_report)) ?>)">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>

            <!-- Quick Metrics Strip -->
            <div class="today-metrics-grid">
                <div class="today-metric-cell">
                    <div class="label">Hours Worked</div>
                    <div class="val">⏱ <?= number_format($today_report['hours_worked'] ?? 8, 1) ?>h</div>
                </div>
                <div class="today-metric-cell">
                    <div class="label">Project Engagement</div>
                    <div class="val" style="color:#B6F763;">
                        📁 <?= htmlspecialchars($today_report['project_name'] ?? 'General / Internal') ?>
                    </div>
                </div>
                <div class="today-metric-cell">
                    <div class="label">Productivity</div>
                    <div class="val" style="color:#fde047;">★ <?= intval($today_report['productivity_rating'] ?? 5) ?> / 5</div>
                </div>
                <div class="today-metric-cell">
                    <div class="label">Obstacle Status</div>
                    <div class="val">
                        <?php if (($today_report['blocker_severity'] ?? '') === 'critical'): ?>
                            <span style="color:#fca5a5;">🔴 Critical Blocker</span>
                        <?php elseif (($today_report['blocker_severity'] ?? '') === 'minor' || !empty(trim($today_report['challenges'] ?? ''))): ?>
                            <span style="color:#fde047;">🟡 Minor Delay</span>
                        <?php else: ?>
                            <span style="color:#86efac;">🟢 Operating Smoothly</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Accomplishments Snippet -->
            <div class="today-preview-box">
                <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:rgba(255,255,255,0.6); margin-bottom:4px; letter-spacing:0.5px;">
                    <i class="fas fa-check-circle" style="color:#B6F763;"></i> Key Accomplishments
                </div>
                <?= htmlspecialchars($today_report['tasks_completed']) ?>
            </div>
        </div>
        <?php else: ?>
        <!-- ── Pending Today's Report Banner ─────────────────────────────────── -->
        <div class="today-pending-banner">
            <div class="today-pending-left">
                <div class="today-pending-icon"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <h3 class="today-pending-title">Today's Progress Report Pending &bull; <?= date('l, F j, Y') ?></h3>
                    <p class="today-pending-sub">You have not submitted your daily work update for today yet. Logging your accomplishments keeps the entire team aligned.</p>
                </div>
            </div>
            <button type="button" class="btn-dpr btn-dpr-primary" onclick="openDprModal(false)" style="white-space:nowrap;">
                <i class="fas fa-plus-circle"></i> Submit Today's Report
            </button>
        </div>
        <?php endif; ?>

        <!-- ── Advanced Filter & Search Bar ───────────────────────────────────── -->
        <div class="dpr-filter-card">
            <form method="GET" id="filterForm">
                <div class="filter-row-top">
                    <!-- Search Input -->
                    <div class="filter-group" style="flex: 2; min-width: 220px;">
                        <label class="filter-label"><i class="fas fa-search"></i> Search Keyword</label>
                        <div class="filter-input-wrap">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" class="filter-input with-icon" placeholder="Search tasks, deliverables, blockers, projects...">
                        </div>
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

                    <!-- Project Selector -->
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-folder"></i> Project</label>
                        <select name="project_id" class="filter-input">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div class="filter-group" style="min-width: 140px;">
                        <label class="filter-label"><i class="fas fa-calendar-day"></i> From Date</label>
                        <input type="date" name="start_date" id="inputStartDate" value="<?= htmlspecialchars($filter_start_date) ?>" class="filter-input">
                    </div>

                    <!-- End Date -->
                    <div class="filter-group" style="min-width: 140px;">
                        <label class="filter-label"><i class="fas fa-calendar-day"></i> To Date</label>
                        <input type="date" name="end_date" id="inputEndDate" value="<?= htmlspecialchars($filter_end_date) ?>" class="filter-input">
                    </div>

                    <!-- Blockers / Severity Filter -->
                    <div class="filter-group" style="min-width: 150px;">
                        <label class="filter-label"><i class="fas fa-filter"></i> Severity</label>
                        <select name="severity" class="filter-input">
                            <option value="">All Statuses</option>
                            <option value="none" <?= $filter_severity === 'none' ? 'selected' : '' ?>>🟢 Smooth (None)</option>
                            <option value="minor" <?= $filter_severity === 'minor' ? 'selected' : '' ?>>🟡 Minor Blocker</option>
                            <option value="critical" <?= $filter_severity === 'critical' ? 'selected' : '' ?>>🔴 Critical Blocker</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display:flex; gap:8px; align-items:flex-end;">
                        <button type="submit" class="btn-dpr btn-dpr-primary" style="padding: 9px 18px;">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="dpr.php" class="btn-dpr btn-dpr-secondary" style="padding: 9px 14px;" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Preset Pills -->
                <div class="preset-pills">
                    <span class="preset-pill-label"><i class="fas fa-bolt" style="color:var(--dpr-primary);"></i> Quick Presets:</span>
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
                        <i class="fas fa-stream" style="color:var(--dpr-primary);"></i>
                        <span>Progress Reports Feed</span>
                        <span class="dpr-count-badge"><?= $total_records ?> record<?= $total_records != 1 ? 's' : '' ?></span>
                    </h3>
                </div>
                <div class="dpr-table-actions">
                    <!-- Direct Export Shortcuts for Current View -->
                    <a href="<?= htmlspecialchars($export_csv_url) ?>" class="btn-dpr btn-dpr-secondary btn-dpr-sm" title="Export current filtered view to CSV">
                        <i class="fas fa-file-csv" style="color:#10b981;"></i> CSV
                    </a>
                    <a href="<?= htmlspecialchars($export_pdf_url) ?>&preview=1" target="_blank" class="btn-dpr btn-dpr-secondary btn-dpr-sm" title="Preview PDF Dossier">
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
                                <th style="width: 17%;">Team Member</th>
                            <?php endif; ?>
                            <th style="width: 14%;">Report Date</th>
                            <th style="width: 16%;">Project Engagement</th>
                            <th style="width: 26%;">Accomplishments & WIP</th>
                            <th style="width: 16%;">Roadblocks</th>
                            <th style="width: 6%;">Score</th>
                            <th style="width: 5%; text-align:right;">Actions</th>
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
                                    $has_wip = !empty(trim($row['tasks_in_progress'] ?? ''));
                                    $has_links = !empty(trim($row['deliverable_links'] ?? ''));
                                    $has_waiting = !empty(trim($row['waiting_on'] ?? ''));
                                    $severity = $row['blocker_severity'] ?? 'none';
                                    $can_delete = ($is_manager || $row['user_id'] == $user_id);
                                    $can_edit = ($is_manager || $row['user_id'] == $user_id);
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
                                        <div class="dpr-date-main"><?= date('M j, Y', $report_date_ts) ?></div>
                                        <div class="dpr-date-day">
                                            <span><?= date('l', $report_date_ts) ?></span>
                                            <?php if ($is_today): ?>
                                                <span class="badge-today">Today</span>
                                            <?php elseif ($is_yesterday): ?>
                                                <span class="badge-yesterday">Y'day</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge-hours">
                                            <i class="fas fa-clock" style="color:var(--dpr-primary);"></i> <?= number_format($row['hours_worked'] ?? 8, 1) ?>h Logged
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['project_name'])): ?>
                                            <span class="badge-project" title="<?= htmlspecialchars($row['project_name']) ?>">
                                                <i class="fas fa-folder-open"></i> <?= htmlspecialchars($row['project_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-project badge-project-general">
                                                <i class="fas fa-layer-group"></i> General Work
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="dpr-tasks-clamp" title="<?= htmlspecialchars($row['tasks_completed']) ?>">
                                            <?= htmlspecialchars($row['tasks_completed']) ?>
                                        </div>
                                        <?php if ($has_wip): ?>
                                            <div class="dpr-wip-tag" title="<?= htmlspecialchars($row['tasks_in_progress']) ?>">
                                                <i class="fas fa-spinner fa-spin"></i> <strong>WIP:</strong> <?= htmlspecialchars(mb_strimwidth($row['tasks_in_progress'], 0, 75, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($has_links): ?>
                                            <span class="badge-deliverables" title="<?= htmlspecialchars($row['deliverable_links']) ?>">
                                                <i class="fas fa-link"></i> Deliverables Attached
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($severity === 'critical'): ?>
                                            <div class="badge-blocker-critical">
                                                <i class="fas fa-radiation"></i> Critical Block
                                            </div>
                                        <?php elseif ($severity === 'minor' || $has_challenges): ?>
                                            <div class="badge-blocker-minor">
                                                <i class="fas fa-exclamation-triangle"></i> Minor Delay
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-smooth"><i class="fas fa-check-circle"></i> On Track</span>
                                        <?php endif; ?>

                                        <?php if ($has_challenges): ?>
                                            <div style="color:#b91c1c; font-size:12px; margin-top:4px; line-height:1.4;">
                                                <?= htmlspecialchars(mb_strimwidth($row['challenges'], 0, 70, '...')) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($has_waiting): ?>
                                            <div class="dpr-waiting-tag" title="Waiting on: <?= htmlspecialchars($row['waiting_on']) ?>">
                                                <i class="fas fa-user-clock"></i> <strong>Wait:</strong> <?= htmlspecialchars(mb_strimwidth($row['waiting_on'], 0, 30, '...')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="badge-rating">
                                            <i class="fas fa-star" style="color:#f59e0b;"></i> <?= intval($row['productivity_rating'] ?? 5) ?>/5
                                        </span>
                                    </td>

                                    <td style="text-align:right;">
                                        <div class="dpr-actions-cell" style="justify-content:flex-end;">
                                            <!-- View Details Modal Trigger -->
                                            <button type="button" class="btn-icon-action" title="View Report Dossier"
                                                data-id="<?= $row['id'] ?>"
                                                data-name="<?= htmlspecialchars($row['user_name'] ?? 'Team Member') ?>"
                                                data-role="<?= htmlspecialchars(ucfirst($row['user_role'] ?? 'User')) ?>"
                                                data-email="<?= htmlspecialchars($row['user_email'] ?? '') ?>"
                                                data-date="<?= date('l, F j, Y', $report_date_ts) ?>"
                                                data-rawdate="<?= $row['report_date'] ?>"
                                                data-created="<?= date('M j, Y h:i A', strtotime($row['created_at'])) ?>"
                                                data-hours="<?= number_format($row['hours_worked'] ?? 8, 1) ?>"
                                                data-project="<?= htmlspecialchars($row['project_name'] ?? 'General / Internal Work') ?>"
                                                data-projectid="<?= $row['project_id'] ?? '' ?>"
                                                data-rating="<?= intval($row['productivity_rating'] ?? 5) ?>"
                                                data-severity="<?= htmlspecialchars($row['blocker_severity'] ?? 'none') ?>"
                                                data-tasks="<?= htmlspecialchars($row['tasks_completed']) ?>"
                                                data-inprogress="<?= htmlspecialchars($row['tasks_in_progress'] ?? '') ?>"
                                                data-challenges="<?= htmlspecialchars($row['challenges'] ?? '') ?>"
                                                data-waiting="<?= htmlspecialchars($row['waiting_on'] ?? '') ?>"
                                                data-links="<?= htmlspecialchars($row['deliverable_links'] ?? '') ?>"
                                                data-plan="<?= htmlspecialchars($row['plan_tomorrow'] ?? '') ?>"
                                                onclick="openViewModal(this)">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- View / Print Single PDF Dossier -->
                                            <a href="dpr_export.php?format=pdf&id=<?= $row['id'] ?>&preview=1" target="_blank" class="btn-icon-action" title="View PDF Dossier">
                                                <i class="fas fa-file-pdf" style="color:#ef4444;"></i>
                                            </a>

                                            <?php if ($can_edit): ?>
                                            <!-- Edit Report -->
                                            <button type="button" class="btn-icon-action" title="Edit Report" onclick="openDprModal(true, <?= htmlspecialchars(json_encode($row)) ?>)">
                                                <i class="fas fa-pencil-alt" style="color:var(--dpr-primary);"></i>
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
                                <td colspan="<?= $is_manager ? 7 : 6 ?>" style="text-align:center; padding: 60px 20px;">
                                    <div style="max-width: 420px; margin: 0 auto; color: var(--dpr-text-muted);">
                                        <div style="font-size: 46px; color: #cbd5e1; margin-bottom: 14px;">
                                            <i class="fas fa-clipboard-check"></i>
                                        </div>
                                        <h4 style="font-weight: 800; color: var(--dpr-text-dark); margin-bottom: 6px; font-size:17px;">No Progress Reports Found</h4>
                                        <p style="font-size: 13.5px; margin-bottom: 20px; line-height: 1.5;">No reports matched your current filter criteria. Try resetting your search filters or submit today's work report.</p>
                                        <?php if (!$today_report): ?>
                                            <button type="button" class="btn-dpr btn-dpr-primary" onclick="openDprModal(false)">
                                                <i class="fas fa-plus"></i> Submit Today's Report
                                            </button>
                                        <?php else: ?>
                                            <a href="dpr.php" class="btn-dpr btn-dpr-secondary">
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
            <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 22px; border-top:1px solid var(--dpr-border); flex-wrap:wrap; gap:12px; background:#fafbfc;">
                <div style="font-size:13px; color:var(--dpr-text-muted);">
                    Showing <strong><?= min($offset + 1, $total_records) ?></strong> to <strong><?= min($offset + $limit, $total_records) ?></strong> of <strong><?= $total_records ?></strong> reports
                </div>
                <div style="display:flex; gap:6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($current_params, ['page' => $page - 1])) ?>" class="btn-icon-action" style="width:34px; height:34px;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="?<?= http_build_query(array_merge($current_params, ['page' => $i])) ?>" 
                               class="btn-icon-action <?= $i === $page ? 'active' : '' ?>" 
                               style="width:34px; height:34px; font-weight:700; <?= $i === $page ? 'background:var(--dpr-primary); color:#fff; border-color:var(--dpr-primary);' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; color:var(--dpr-text-light);">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($current_params, ['page' => $page + 1])) ?>" class="btn-icon-action" style="width:34px; height:34px;">
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
<!-- MODAL 1: Submit / Edit Daily Progress Report                                -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="dprModal" class="modal-overlay" onclick="if(event.target===this)closeDprModal()">
    <div class="modal-content" style="max-width:720px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-pen-alt"></i></div>
                <span id="modalTitle">Daily Progress Report (DPR) Submission</span>
            </h4>
            <button class="modal-close" onclick="closeDprModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="dprForm">
            <input type="hidden" name="action" id="formAction" value="save_dpr">
            <input type="hidden" name="productivity_rating" id="f_productivity_rating" value="5">
            <input type="hidden" name="blocker_severity" id="f_blocker_severity" value="none">

            <div class="modal-body">
                <!-- Locked Date Indicator: Strictly Today by Default -->
                <div class="dpr-locked-date-banner">
                    <div class="dpr-locked-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="dpr-locked-info">
                        <div class="dpr-locked-label">Reporting For</div>
                        <div class="dpr-locked-date" id="modalHumanDate"><?= date('l, F j, Y') ?></div>
                    </div>
                    <div class="dpr-locked-tag" id="modalLockedTag">
                        <i class="fas fa-lock"></i> Today Only
                    </div>
                </div>
                <input type="hidden" name="report_date" id="f_report_date" value="<?= date('Y-m-d') ?>">

                <!-- Section 1: Time, Project & Productivity Rating -->
                <div class="form-section-card">
                    <div class="form-section-title">
                        <i class="fas fa-clock"></i> Time Allocation & Project Focus
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:16px; margin-bottom:14px;">
                        <!-- Hours Worked -->
                        <div>
                            <label class="form-field-label">
                                Hours Worked <span style="color:#ef4444;">*</span>
                            </label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="number" step="0.5" min="0.5" max="24" name="hours_worked" id="f_hours_worked" value="8.0" required class="filter-input" style="font-weight:700; font-size:14px; width:95px;">
                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                    <button type="button" class="hour-pill-btn" onclick="setHours(4.0)">4h</button>
                                    <button type="button" class="hour-pill-btn" onclick="setHours(6.0)">6h</button>
                                    <button type="button" class="hour-pill-btn active" id="btnHour8" onclick="setHours(8.0)">8h</button>
                                    <button type="button" class="hour-pill-btn" onclick="setHours(10.0)">10h</button>
                                </div>
                            </div>
                        </div>

                        <!-- Associated Project -->
                        <div>
                            <label class="form-field-label">
                                Project Engagement
                            </label>
                            <select name="project_id" id="f_project_id" class="filter-input">
                                <option value="">-- General / Internal / Multiple --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Productivity / Morale Rating -->
                    <div>
                        <label class="form-field-label">
                            Productivity & Morale Self-Assessment
                        </label>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <div class="rating-pill-opt" onclick="setRating(1)" id="rate-1">1 • Blocked</div>
                            <div class="rating-pill-opt" onclick="setRating(2)" id="rate-2">2 • Low</div>
                            <div class="rating-pill-opt" onclick="setRating(3)" id="rate-3">3 • Normal</div>
                            <div class="rating-pill-opt" onclick="setRating(4)" id="rate-4">4 • High</div>
                            <div class="rating-pill-opt active" onclick="setRating(5)" id="rate-5">5 • Peak Velocity ★</div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Tasks Completed Today -->
                <div style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="form-field-label" style="margin:0;">
                            <i class="fas fa-check-circle" style="color:#10b981;"></i> Key Accomplishments & Deliverables <span style="color:#ef4444;">*</span>
                        </label>
                        <button type="button" class="btn-dpr btn-dpr-secondary btn-dpr-sm" style="padding:2px 8px; font-size:11px;" onclick="addBullet('f_tasks')">
                            + Bullet
                        </button>
                    </div>
                    <textarea name="tasks_completed" id="f_tasks" rows="3" required class="filter-input" style="width:100%; resize:vertical; line-height:1.5;" placeholder="• Completed UI revamp for DPR&#10;• Implemented CSV and PDF export&#10;• Fixed responsive mobile layout and verified on staging"></textarea>
                </div>

                <!-- Section 3: In-Progress / Ongoing Work -->
                <div style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="form-field-label" style="margin:0;">
                            <i class="fas fa-spinner fa-spin" style="color:#0284c7;"></i> In-Progress & Continuing Objectives (Optional)
                        </label>
                        <button type="button" class="btn-dpr btn-dpr-secondary btn-dpr-sm" style="padding:2px 8px; font-size:11px;" onclick="addBullet('f_inprogress')">
                            + Bullet
                        </button>
                    </div>
                    <textarea name="tasks_in_progress" id="f_inprogress" rows="2" class="filter-input" style="width:100%; resize:vertical; line-height:1.5;" placeholder="• Migrating payment gateway webhook (approx 70% complete)&#10;• Drafting new client onboarding wireframes"></textarea>
                </div>

                <!-- Section 4: Roadblocks, Risks & Dependencies -->
                <div class="form-section-card">
                    <div class="form-section-title" style="color:#dc2626;">
                        <i class="fas fa-exclamation-triangle"></i> Roadblocks, Obstacles & Dependency Log
                    </div>

                    <div style="margin-bottom:12px;">
                        <label class="form-field-label">
                            Blocker Severity Classification
                        </label>
                        <div style="display:flex; gap:8px;">
                            <div class="severity-pill-opt active-none" id="sev-none" onclick="setSeverity('none')">
                                <i class="fas fa-check-circle"></i> On Track / None
                            </div>
                            <div class="severity-pill-opt" id="sev-minor" onclick="setSeverity('minor')">
                                <i class="fas fa-exclamation-circle"></i> Minor Delay
                            </div>
                            <div class="severity-pill-opt" id="sev-critical" onclick="setSeverity('critical')">
                                <i class="fas fa-radiation"></i> Critical Impediment
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label class="form-field-label">
                            Obstacle Narrative / Issues Encountered (Optional)
                        </label>
                        <textarea name="challenges" id="f_challenges" rows="2" class="filter-input" style="width:100%; resize:vertical; line-height:1.5;" placeholder="Describe what is causing friction, delays, or blocking delivery..."></textarea>
                    </div>

                    <div>
                        <label class="form-field-label">
                            Waiting On / Dependency Owner (Optional)
                        </label>
                        <input type="text" name="waiting_on" id="f_waiting_on" class="filter-input" placeholder="e.g. Waiting on client logo vector assets, API keys, or PR review">
                    </div>
                </div>

                <!-- Section 5: Work Proof / Deliverable Links -->
                <div style="margin-bottom:16px;">
                    <label class="form-field-label">
                        <i class="fas fa-link" style="color:#7c3aed;"></i> Deliverables, Artifacts & Work Proof Links (Optional)
                    </label>
                    <textarea name="deliverable_links" id="f_links" rows="2" class="filter-input" style="width:100%; resize:vertical; line-height:1.5;" placeholder="Paste URLs to GitHub PRs, Figma prototypes, Staging URLs, or Google Docs (one per line)"></textarea>
                </div>

                <!-- Section 6: Plan for Tomorrow -->
                <div style="margin-bottom:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="form-field-label" style="margin:0;">
                            <i class="fas fa-arrow-alt-circle-right" style="color:var(--dpr-primary);"></i> Tomorrow's Action Plan & Priorities <span style="color:#ef4444;">*</span>
                        </label>
                        <button type="button" class="btn-dpr btn-dpr-secondary btn-dpr-sm" style="padding:2px 8px; font-size:11px;" onclick="addBullet('f_plan')">
                            + Bullet
                        </button>
                    </div>
                    <textarea name="plan_tomorrow" id="f_plan" rows="3" required class="filter-input" style="width:100%; resize:vertical; line-height:1.5;" placeholder="• Finalize payment webhook deployment and verify with test credentials&#10;• Sync with product designer on mobile navigation"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-dpr btn-dpr-secondary" onclick="closeDprModal()">Cancel</button>
                <button type="submit" class="btn-dpr btn-dpr-primary" id="dprSubmitBtn">
                    <span id="dprBtnText"><i class="fas fa-paper-plane"></i> Submit Daily Report</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2: View Detailed Progress Report (Executive Dossier)                  -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:740px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-file-alt"></i></div>
                <span id="viewModalTitle">Daily Progress Report Dossier</span>
            </h4>
            <button class="modal-close" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <!-- Header & Member Metadata Card -->
            <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border:1px solid var(--dpr-border); border-radius:12px; padding:16px 20px; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div id="viewModalAvatar" class="dpr-avatar" style="width:46px; height:46px; font-size:17px;">U</div>
                    <div>
                        <div id="viewModalUser" style="font-size:16px; font-weight:800; color:var(--dpr-text-dark);">User Name</div>
                        <div id="viewModalRole" style="font-size:12.5px; color:var(--dpr-text-muted);">Role &bull; user@graphicafix.com</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div id="viewModalDate" style="font-weight:800; color:var(--dpr-primary); font-size:14.5px;">Date</div>
                    <div id="viewModalCreated" style="font-size:12px; color:var(--dpr-text-light);">Logged at</div>
                </div>
            </div>

            <!-- Highlights Bar: Hours, Project, Productivity, Roadblocks -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap:10px; margin-bottom:18px;">
                <div style="background:#f1f5f9; border:1px solid var(--dpr-border); border-radius:8px; padding:10px 12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:var(--dpr-text-muted); text-transform:uppercase;">Hours Worked</div>
                    <div id="viewHours" style="font-size:15px; font-weight:800; color:var(--dpr-text-dark); margin-top:2px;">8.0h</div>
                </div>
                <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:10px 12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#065f46; text-transform:uppercase;">Project</div>
                    <div id="viewProject" style="font-size:13.5px; font-weight:800; color:#065f46; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">General</div>
                </div>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#b45309; text-transform:uppercase;">Productivity</div>
                    <div id="viewRating" style="font-size:14px; font-weight:800; color:#b45309; margin-top:2px;">★ 5 / 5</div>
                </div>
                <div id="viewStatusBox" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 12px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#166534; text-transform:uppercase;">Obstacle Status</div>
                    <div id="viewSeverity" style="font-size:13px; font-weight:800; color:#166534; margin-top:2px;">On Track</div>
                </div>
            </div>

            <!-- Section 1: Tasks Completed -->
            <div class="dossier-card card-emerald">
                <div class="dossier-title" style="color:#047857;">
                    <i class="fas fa-check-circle"></i> 1. Key Accomplishments & Deliverables
                </div>
                <div id="viewTasks" class="dossier-content"></div>
            </div>

            <!-- Section 2: In-Progress / Ongoing Tasks -->
            <div class="dossier-card card-blue" id="viewInProgressWrap">
                <div class="dossier-title" style="color:#1d4ed8;">
                    <i class="fas fa-spinner fa-spin"></i> 2. In-Progress & Continuing Objectives
                </div>
                <div id="viewInProgress" class="dossier-content"></div>
            </div>

            <!-- Section 3: Roadblocks / Challenges -->
            <div class="dossier-card card-amber" id="viewChallengesWrap">
                <div class="dossier-title" style="color:#b45309;">
                    <i class="fas fa-exclamation-triangle"></i> 3. Obstacles, Roadblocks & Dependency Log
                </div>
                <div id="viewChallenges" class="dossier-content"></div>
                <div id="viewWaitingWrap" style="margin-top:8px;">
                    <div style="display:inline-flex; align-items:center; gap:6px; background:#fff7ed; border:1px solid #fed7aa; color:#c2410c; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700;">
                        <i class="fas fa-hourglass-half"></i> <span>Waiting On: <span id="viewWaiting" style="font-weight:600; color:#9a3412;"></span></span>
                    </div>
                </div>
            </div>

            <!-- Section 4: Deliverables & Proof Links -->
            <div class="dossier-card card-purple" id="viewLinksWrap">
                <div class="dossier-title" style="color:#6d28d9;">
                    <i class="fas fa-link"></i> 4. Deliverables, Artifacts & Work Proof
                </div>
                <div id="viewLinks" class="dossier-content" style="word-break:break-all;"></div>
            </div>

            <!-- Section 5: Plan Tomorrow -->
            <div class="dossier-card card-sky" style="margin-bottom:6px;">
                <div class="dossier-title" style="color:#0284c7;">
                    <i class="fas fa-arrow-alt-circle-right"></i> 5. Tomorrow's Action Plan & Priorities
                </div>
                <div id="viewPlan" class="dossier-content"></div>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; gap:8px;">
                <!-- Copy Standup text -->
                <button type="button" class="btn-dpr btn-dpr-secondary btn-dpr-sm" id="btnCopyStandup" onclick="copyCurrentStandup()">
                    <i class="fas fa-copy"></i> Copy Standup Summary
                </button>
                <!-- View / Print Single PDF Dossier -->
                <a id="btnDownloadSinglePdf" href="#" target="_blank" class="btn-dpr btn-dpr-secondary btn-dpr-sm">
                    <i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF Dossier
                </a>
            </div>
            <button type="button" class="btn-dpr btn-dpr-primary btn-dpr-sm" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 3: Custom Export Options                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-file-export"></i></div>
                <span>Export Progress Reports</span>
            </h4>
            <button class="modal-close" onclick="closeExportModal()"><i class="fas fa-times"></i></button>
        </div>
        <form action="dpr_export.php" method="GET" target="_blank" id="customExportForm">
            <div class="modal-body">
                <p style="font-size:13px; color:var(--dpr-text-muted); margin-top:0; margin-bottom:16px;">
                    Generate a Daily Progress Report download in Excel CSV spreadsheet format or branded PDF dossier.
                </p>

                <!-- Export Format Selector -->
                <div style="margin-bottom:18px;">
                    <label class="form-field-label">
                        Select Format
                    </label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <label class="export-format-card active" id="labelFormatCsv">
                            <input type="radio" name="format" value="csv" checked onchange="toggleFormatStyle(this)">
                            <div>
                                <div style="font-weight:700; font-size:13.5px; color:var(--dpr-text-dark);"><i class="fas fa-file-csv" style="color:#10b981;"></i> CSV Spreadsheet</div>
                                <div style="font-size:11px; color:var(--dpr-text-muted);">For Microsoft Excel & Sheets</div>
                            </div>
                        </label>
                        <label class="export-format-card" id="labelFormatPdf">
                            <input type="radio" name="format" value="pdf" onchange="toggleFormatStyle(this)">
                            <div>
                                <div style="font-weight:700; font-size:13.5px; color:var(--dpr-text-dark);"><i class="fas fa-file-pdf" style="color:#ef4444;"></i> PDF Document</div>
                                <div style="font-size:11px; color:var(--dpr-text-muted);">Executive Letterhead Dossier</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Date Range Selection -->
                <div style="margin-bottom:16px;">
                    <label class="form-field-label">
                        Date Range
                    </label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <span style="font-size:11px; color:var(--dpr-text-muted);">From:</span>
                            <input type="date" name="start_date" id="exportStartDate" value="<?= htmlspecialchars($filter_start_date) ?>" class="filter-input">
                        </div>
                        <div>
                            <span style="font-size:11px; color:var(--dpr-text-muted);">To:</span>
                            <input type="date" name="end_date" id="exportEndDate" value="<?= htmlspecialchars($filter_end_date) ?>" class="filter-input">
                        </div>
                    </div>
                </div>

                <?php if ($is_manager): ?>
                <!-- Team Member -->
                <div style="margin-bottom:16px;">
                    <label class="form-field-label">
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

                <!-- Project Selector -->
                <div style="margin-bottom:16px;">
                    <label class="form-field-label">
                        Filter by Project
                    </label>
                    <select name="project_id" class="filter-input">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Only Blockers Checkbox -->
                <div style="margin-bottom:6px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:600; color:var(--dpr-text-dark);">
                        <input type="checkbox" name="has_blockers" value="1" <?= $filter_blockers === 1 ? 'checked' : '' ?> style="width:16px; height:16px;">
                        <span>Export only reports with flagged Roadblocks / Challenges</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-dpr btn-dpr-secondary" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn-dpr btn-dpr-primary" onclick="window.setTimeout(closeExportModal, 500)">
                    <i class="fas fa-download"></i> Download Export
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:8px;"></div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- JAVASCRIPT LOGIC                                                            -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<script>
const existingTodayReport = <?= json_encode($today_report) ?>;
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

// ── Rating & Severity Pill Setters ───────────────────────────────────────────
function setHours(hrs) {
    document.getElementById('f_hours_worked').value = parseFloat(hrs).toFixed(1);
    document.querySelectorAll('.hour-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (hrs === 8.0) {
        const b = document.getElementById('btnHour8');
        if (b) b.classList.add('active');
    }
}

function setRating(val) {
    document.getElementById('f_productivity_rating').value = val;
    for (let i = 1; i <= 5; i++) {
        const el = document.getElementById(`rate-${i}`);
        if (el) {
            if (i === val) el.classList.add('active');
            else el.classList.remove('active');
        }
    }
}

function setSeverity(val) {
    document.getElementById('f_blocker_severity').value = val;
    const sNone = document.getElementById('sev-none');
    const sMinor = document.getElementById('sev-minor');
    const sCrit = document.getElementById('sev-critical');

    sNone.className = 'severity-pill-opt' + (val === 'none' ? ' active-none' : '');
    sMinor.className = 'severity-pill-opt' + (val === 'minor' ? ' active-minor' : '');
    sCrit.className = 'severity-pill-opt' + (val === 'critical' ? ' active-critical' : '');
}

// ── Date Formatter Helper ────────────────────────────────────────────────────
function formatReadableDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
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
        hours: btn.dataset.hours,
        project: btn.dataset.project,
        projectid: btn.dataset.projectid,
        rating: btn.dataset.rating,
        severity: btn.dataset.severity,
        tasks: btn.dataset.tasks,
        inprogress: btn.dataset.inprogress,
        challenges: btn.dataset.challenges,
        waiting: btn.dataset.waiting,
        links: btn.dataset.links,
        plan: btn.dataset.plan
    };

    renderActiveViewModal();
}

function openViewModalFromObj(row) {
    activeModalData = {
        id: row.id,
        name: row.user_name || 'Team Member',
        role: row.user_role ? (row.user_role.charAt(0).toUpperCase() + row.user_role.slice(1)) : 'Staff',
        email: row.user_email || '',
        date: formatReadableDate(row.report_date),
        rawdate: row.report_date,
        created: row.created_at,
        hours: parseFloat(row.hours_worked || 8).toFixed(1),
        project: row.project_name || 'General / Internal Work',
        projectid: row.project_id || '',
        rating: parseInt(row.productivity_rating || 5),
        severity: row.blocker_severity || 'none',
        tasks: row.tasks_completed || '',
        inprogress: row.tasks_in_progress || '',
        challenges: row.challenges || '',
        waiting: row.waiting_on || '',
        links: row.deliverable_links || '',
        plan: row.plan_tomorrow || ''
    };

    renderActiveViewModal();
}

function renderActiveViewModal() {
    if (!activeModalData) return;

    document.getElementById('viewModalTitle').textContent = `Daily Progress Report — ${activeModalData.name} (${activeModalData.date})`;
    document.getElementById('viewModalAvatar').textContent = activeModalData.name.charAt(0).toUpperCase();
    document.getElementById('viewModalUser').textContent = activeModalData.name;
    document.getElementById('viewModalRole').textContent = `${activeModalData.role} • ${activeModalData.email || 'Graphicafix Team'}`;
    document.getElementById('viewModalDate').textContent = activeModalData.date;
    document.getElementById('viewModalCreated').textContent = `Logged: ${activeModalData.created}`;
    
    // Highlights Bar
    document.getElementById('viewHours').textContent = `${activeModalData.hours}h Logged`;
    document.getElementById('viewProject').textContent = activeModalData.project || 'General / Internal Work';
    document.getElementById('viewProject').title = activeModalData.project || 'General / Internal Work';
    document.getElementById('viewRating').textContent = `★ ${activeModalData.rating} / 5.0`;

    const statusBox = document.getElementById('viewStatusBox');
    const sevLabel = document.getElementById('viewSeverity');
    if (activeModalData.severity === 'critical') {
        statusBox.style.background = '#fef2f2';
        statusBox.style.borderColor = '#fecaca';
        sevLabel.style.color = '#dc2626';
        sevLabel.textContent = 'Critical Block';
    } else if (activeModalData.severity === 'minor' || (activeModalData.challenges && activeModalData.challenges.trim() !== '')) {
        statusBox.style.background = '#fffbeb';
        statusBox.style.borderColor = '#fde68a';
        sevLabel.style.color = '#b45309';
        sevLabel.textContent = 'Minor Delay';
    } else {
        statusBox.style.background = '#f0fdf4';
        statusBox.style.borderColor = '#bbf7d0';
        sevLabel.style.color = '#166534';
        sevLabel.textContent = 'On Track';
    }

    // Tasks Completed
    document.getElementById('viewTasks').textContent = activeModalData.tasks;

    // In-Progress Tasks
    const wipWrap = document.getElementById('viewInProgressWrap');
    if (activeModalData.inprogress && activeModalData.inprogress.trim() !== '') {
        wipWrap.style.display = 'block';
        document.getElementById('viewInProgress').textContent = activeModalData.inprogress;
    } else {
        wipWrap.style.display = 'none';
    }

    // Challenges & Waiting On
    const chWrap = document.getElementById('viewChallengesWrap');
    if (activeModalData.challenges && activeModalData.challenges.trim() !== '') {
        chWrap.style.display = 'block';
        document.getElementById('viewChallenges').textContent = activeModalData.challenges;
    } else {
        chWrap.style.display = 'none';
    }

    const waitWrap = document.getElementById('viewWaitingWrap');
    if (activeModalData.waiting && activeModalData.waiting.trim() !== '') {
        waitWrap.style.display = 'block';
        document.getElementById('viewWaiting').textContent = activeModalData.waiting;
    } else {
        waitWrap.style.display = 'none';
    }

    // Deliverables / Links
    const linksWrap = document.getElementById('viewLinksWrap');
    if (activeModalData.links && activeModalData.links.trim() !== '') {
        linksWrap.style.display = 'block';
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        const linkedText = activeModalData.links.replace(urlRegex, function(url) {
            return `<a href="${url}" target="_blank" style="color:#6d28d9; text-decoration:underline; font-weight:700;"><i class="fas fa-external-link-alt"></i> ${url}</a>`;
        });
        document.getElementById('viewLinks').innerHTML = linkedText;
    } else {
        linksWrap.style.display = 'none';
    }

    // Plan Tomorrow
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
    let text = `📋 *Daily Progress Report — ${activeModalData.name}*\n` +
        `📅 *Report Date:* ${activeModalData.date}\n` +
        `📁 *Project Engagement:* ${activeModalData.project || 'General / Internal Work'}\n` +
        `⏱ *Hours Logged:* ${activeModalData.hours}h | ⭐ *Productivity Score:* ${activeModalData.rating}/5.0\n\n` +
        `✅ *1. Key Accomplishments & Deliverables:*\n${activeModalData.tasks}\n\n`;

    if (activeModalData.inprogress && activeModalData.inprogress.trim() !== '') {
        text += `⏳ *2. Work In Progress / Continuing:*\n${activeModalData.inprogress}\n\n`;
    }

    if (activeModalData.challenges && activeModalData.challenges.trim() !== '') {
        text += `⚠️ *3. Roadblocks & Dependency Log [${activeModalData.severity.toUpperCase()}]:*\n${activeModalData.challenges}\n`;
        if (activeModalData.waiting && activeModalData.waiting.trim() !== '') {
            text += `✋ *Waiting On:* ${activeModalData.waiting}\n`;
        }
        text += '\n';
    } else {
        text += `🟢 *3. Roadblocks:* None (Operating Smoothly on Schedule)\n\n`;
    }

    if (activeModalData.links && activeModalData.links.trim() !== '') {
        text += `🔗 *4. Deliverables & Proof Links:*\n${activeModalData.links}\n\n`;
    }

    text += `🎯 *5. Tomorrow's Action Plan:*\n${activeModalData.plan}`;

    navigator.clipboard.writeText(text).then(() => {
        showToast('Standup summary copied to clipboard!');
    }).catch(() => {
        showToast('Unable to copy text to clipboard', 'error');
    });
}

// ── Submit / Edit Modal ──────────────────────────────────────────────────────
function openDprModal(isEdit = false, customData = null) {
    const modal = document.getElementById('dprModal');
    document.getElementById('dprForm').reset();
    
    const todayStr = '<?= date('Y-m-d') ?>';
    const todayHuman = '<?= date('l, F j, Y') ?>';
    const dataToUse = customData || (isEdit ? existingTodayReport : null);

    if (dataToUse) {
        const reportDate = dataToUse.report_date || todayStr;
        const isToday = (reportDate === todayStr);
        document.getElementById('f_report_date').value = reportDate;
        document.getElementById('modalHumanDate').textContent = formatReadableDate(reportDate);
        document.getElementById('modalTitle').textContent = isToday ? "Edit Today's Daily Progress Report" : `Edit Daily Progress Report (${formatReadableDate(reportDate)})`;
        document.getElementById('dprBtnText').innerHTML = isToday ? '<i class="fas fa-save"></i> Save & Update Today\'s Report' : `<i class="fas fa-save"></i> Save Report for ${reportDate}`;
        
        const tag = document.getElementById('modalLockedTag');
        if (tag) tag.innerHTML = isToday ? '<i class="fas fa-edit"></i> Editing Today\'s Report' : `<i class="fas fa-edit"></i> Editing Report`;

        setHours(dataToUse.hours_worked || 8.0);
        document.getElementById('f_project_id').value = dataToUse.project_id || '';
        setRating(parseInt(dataToUse.productivity_rating || 5));
        setSeverity(dataToUse.blocker_severity || (dataToUse.challenges ? 'minor' : 'none'));

        document.getElementById('f_tasks').value = dataToUse.tasks_completed || '';
        document.getElementById('f_inprogress').value = dataToUse.tasks_in_progress || '';
        document.getElementById('f_challenges').value = dataToUse.challenges || '';
        document.getElementById('f_waiting_on').value = dataToUse.waiting_on || '';
        document.getElementById('f_links').value = dataToUse.deliverable_links || '';
        document.getElementById('f_plan').value = dataToUse.plan_tomorrow || '';
    } else {
        document.getElementById('f_report_date').value = todayStr;
        document.getElementById('modalHumanDate').textContent = todayHuman;
        document.getElementById('modalTitle').textContent = "Submit Today's Daily Progress Report";
        document.getElementById('dprBtnText').innerHTML = '<i class="fas fa-paper-plane"></i> Submit Today\'s Report';
        
        const tag = document.getElementById('modalLockedTag');
        if (tag) tag.innerHTML = '<i class="fas fa-lock"></i> Today Only';

        setHours(8.0);
        setRating(5);
        setSeverity('none');
        document.getElementById('f_project_id').value = '';
        document.getElementById('f_tasks').value = '';
        document.getElementById('f_inprogress').value = '';
        document.getElementById('f_challenges').value = '';
        document.getElementById('f_waiting_on').value = '';
        document.getElementById('f_links').value = '';
        document.getElementById('f_plan').value = '';
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
    txt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Report...';

    const payload = new URLSearchParams();
    payload.append('action', 'save_dpr');
    payload.append('report_date', document.getElementById('f_report_date').value);
    payload.append('hours_worked', document.getElementById('f_hours_worked').value);
    payload.append('project_id', document.getElementById('f_project_id').value);
    payload.append('productivity_rating', document.getElementById('f_productivity_rating').value);
    payload.append('blocker_severity', document.getElementById('f_blocker_severity').value);
    payload.append('tasks_completed', document.getElementById('f_tasks').value);
    payload.append('tasks_in_progress', document.getElementById('f_inprogress').value);
    payload.append('challenges', document.getElementById('f_challenges').value);
    payload.append('waiting_on', document.getElementById('f_waiting_on').value);
    payload.append('deliverable_links', document.getElementById('f_links').value);
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
            showToast(data.message || 'Daily Progress Report saved successfully!');
            setTimeout(() => location.reload(), 700);
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
        csvOpt.classList.add('active');
        pdfOpt.classList.remove('active');
    } else {
        pdfOpt.classList.add('active');
        csvOpt.classList.remove('active');
    }
}

// ── Toast Notification Helper ────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const color = type === 'success' ? '#10b981' : '#ef4444';
    t.style.cssText = `background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;box-shadow:0 10px 25px -5px rgba(0,0,0,.1);font-size:13.5px;min-width:260px;border-left:4px solid ${color};animation:modalSlideUp .2s ease;`;
    t.innerHTML = `<span style="font-weight:700;color:${color}"><i class="fas ${icon}"></i></span><span style="font-weight:700;color:#0f172a;">${msg}</span>`;
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
