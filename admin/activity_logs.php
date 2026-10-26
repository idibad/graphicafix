<?php
include 'dashboard_header.php';

// Access check: Only administrative and management roles can view system activity logs
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// ── Search & Filter Parameters ────────────────────────────────────────────────
$search_q    = trim($_GET['q'] ?? '');
$filter_action = trim($_GET['action_filter'] ?? '');
$filter_role   = trim($_GET['role_filter'] ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types  = "";

if ($search_q !== '') {
    $where_clauses[] = "(a.description LIKE ? OR a.username LIKE ? OR a.action LIKE ? OR a.ip_address LIKE ?)";
    $like = '%' . $search_q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}

if ($filter_action !== '') {
    $where_clauses[] = "a.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if ($filter_role !== '') {
    $where_clauses[] = "a.user_role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

if ($date_from !== '') {
    $where_clauses[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $where_clauses[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Pagination
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Count total records
$count_query = "SELECT COUNT(*) AS total FROM activity_logs a WHERE $where_sql";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_pages   = max(1, ceil($total_records / $limit));

// Fetch records with user join
$sql = "SELECT a.*, u.name as full_name 
        FROM activity_logs a 
        LEFT JOIN users u ON a.user_id = u.user_id 
        WHERE $where_sql 
        ORDER BY a.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

// Get unique actions for filter dropdown
$distinct_actions = [];
$act_res = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
if ($act_res) {
    while ($r = $act_res->fetch_assoc()) {
        $distinct_actions[] = $r['action'];
    }
}

// ── Helper: parse user agent ──────────────────────────────────────────────────
function parseActivityAgent($ua) {
    if (empty($ua)) return "CLI / Unknown";
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';
    if      (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif  (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    elseif  (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif  (preg_match('/Mac/i', $ua)) $os = 'macOS';
    elseif  (preg_match('/Linux/i', $ua)) $os = 'Linux';
    if      (preg_match('/Edg/i', $ua))     $browser = 'Edge';
    elseif  (preg_match('/Chrome/i', $ua))  $browser = 'Chrome';
    elseif  (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif  (preg_match('/Safari/i', $ua))  $browser = 'Safari';
    return "$os • $browser";
}

function getActionBadgeStyle($action) {
    if (strpos($action, 'delete') !== false || strpos($action, 'reject') !== false || strpos($action, 'failed') !== false) {
        return ['bg' => '#fef2f2', 'color' => '#dc2626', 'icon' => 'fa-trash-alt'];
    }
    if (strpos($action, 'create') !== false || strpos($action, 'add') !== false || strpos($action, 'approve') !== false || strpos($action, 'success') !== false) {
        return ['bg' => '#f0fdf4', 'color' => '#16a34a', 'icon' => 'fa-plus-circle'];
    }
    if (strpos($action, 'edit') !== false || strpos($action, 'update') !== false) {
        return ['bg' => '#eff6ff', 'color' => '#2563eb', 'icon' => 'fa-edit'];
    }
    return ['bg' => '#f8fafc', 'color' => '#475569', 'icon' => 'fa-bolt'];
}
?>

<div class="height-100">
    <div class="main-card mb-4" style="padding:24px;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h3 style="font-weight:800; color:var(--primary-color, #024442); margin:0;">
                    <i class="fas fa-clipboard-list text-accent me-2"></i> System Activity & Audit Trail
                </h3>
                <p style="color:#64748b; font-size:13.5px; margin:4px 0 0;">
                    Real-time audit log tracking user identity, role, executed action, target entity, and exact timestamp.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size:13px;">
                    Total: <?= number_format($total_records) ?> Events
                </span>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 px-3 py-2">
                    <i class="fas fa-print"></i> Print / Export
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search user, action, detail, IP..." value="<?= htmlspecialchars($search_q) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="action_filter" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    <?php foreach ($distinct_actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= $filter_action === $act ? 'selected' : '' ?>>
                            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($act))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="role_filter" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="super_admin" <?= $filter_role === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="manager" <?= $filter_role === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="pm" <?= $filter_role === 'pm' ? 'selected' : '' ?>>PM</option>
                    <option value="teacher" <?= $filter_role === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                    <option value="student" <?= $filter_role === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="client" <?= $filter_role === 'client' ? 'selected' : '' ?>>Client</option>
                    <option value="vendor" <?= $filter_role === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" title="From Date" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" title="To Date" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" style="background:var(--primary-color, #024442); border-color:var(--primary-color, #024442);">
                    <i class="fas fa-filter"></i>
                </button>
                <?php if ($search_q || $filter_action || $filter_role || $date_from || $date_to): ?>
                    <a href="activity_logs.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                <thead class="table-light">
                    <tr style="text-transform:uppercase; font-size:11px; letter-spacing:0.5px; color:#64748b;">
                        <th style="width:160px;">Date & Time</th>
                        <th style="width:200px;">User / Actor</th>
                        <th style="width:170px;">Action</th>
                        <th style="width:130px;">Target</th>
                        <th>Details</th>
                        <th style="width:180px;">Client Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs || $logs->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-history fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                No activity records found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($r = $logs->fetch_assoc()): 
                            $dt = strtotime($r['created_at']);
                            $date_formatted = date('M d, Y', $dt);
                            $time_formatted = date('h:i:s A', $dt);
                            $badge = getActionBadgeStyle($r['action']);
                            $uname = $r['username'] ?: 'Guest / System';
                            $role_display = ucfirst(str_replace('_', ' ', $r['user_role'] ?? 'guest'));
                            $device = parseActivityAgent($r['user_agent']);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= $date_formatted ?></div>
                                    <div class="text-muted" style="font-size:11.5px;"><i class="far fa-clock me-1"></i><?= $time_formatted ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--accent, #b8f35a), #87bd0a); color:#024442; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <?= strtoupper(substr($uname, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($r['full_name'] ?: $uname) ?></div>
                                            <div style="font-size:11px; color:#64748b;">
                                                <?= htmlspecialchars($uname) ?> &middot; <span class="badge bg-secondary-subtle text-secondary px-1.5 py-0.5"><?= htmlspecialchars($role_display) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:600; background:<?= $badge['bg'] ?>; color:<?= $badge['color'] ?>; border:1px solid <?= $badge['color'] ?>33;">
                                        <i class="fas <?= $badge['icon'] ?>"></i>
                                        <?= htmlspecialchars($r['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($r['target_type'])): ?>
                                        <span class="text-dark fw-medium"><?= htmlspecialchars(ucfirst($r['target_type'])) ?></span>
                                        <?php if (!empty($r['target_id'])): ?>
                                            <span class="text-muted" style="font-size:11.5px;">#<?= intval($r['target_id']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width:380px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($r['description'] ?? '') ?>">
                                        <?= htmlspecialchars($r['description'] ?? '—') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark" style="font-family:monospace; font-size:11.5px;"><?= htmlspecialchars($r['ip_address'] ?? '—') ?></div>
                                    <div class="text-muted text-truncate" style="font-size:11px; max-width:170px;" title="<?= htmlspecialchars($r['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars($device) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                <div style="font-size:13px; color:#64748b;">
                    Showing page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_records) ?> total items)
                </div>
                <div class="btn-group btn-group-sm">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search_q) ?>&action_filter=<?= urlencode($filter_action) ?>&role_filter=<?= urlencode($filter_role) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-secondary">&laquo; Previous</a>
                    <?php endif; ?>
                    <button class="btn btn-primary disabled" style="background:var(--primary-color, #024442); border-color:var(--primary-color, #024442);"><?= $page ?></button>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search_q) ?>&action_filter=<?= urlencode($filter_action) ?>&role_filter=<?= urlencode($filter_role) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-secondary">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
