<?php
// admin/task_reports.php
include 'dashboard_header.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$is_admin = in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm']);
if (!$is_admin) {
    header('Location: tasks.php'); exit;
}

// ── 1. GENERAL PERFORMANCE KPIs ───────────────────────────────────────────────
$comp_res = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE status = 'Completed'")->fetch_assoc();
$total_completed = intval($comp_res['cnt']);

$over_res = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE due_date < CURDATE() AND status != 'Completed' AND status != 'Cancelled'")->fetch_assoc();
$total_overdue = intval($over_res['cnt']);

$avg_time_res = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hrs FROM tasks WHERE status = 'Completed'")->fetch_assoc();
$avg_comp_hours = floatval($avg_time_res['avg_hrs'] ?? 0);
$avg_comp_days = ($avg_comp_hours > 0) ? round($avg_comp_hours / 24, 1) : 0;

$avg_rev_res = $conn->query("SELECT AVG(current_revision) as avg_rev FROM tasks")->fetch_assoc();
$avg_revisions = round(floatval($avg_rev_res['avg_rev'] ?? 1), 1);

// ── 2. TASKS BY TEAM MEMBER ───────────────────────────────────────────────────
$team_workload_res = $conn->query("
    SELECT u.name, 
           SUM(CASE WHEN tu.role = 'primary_assignee' THEN 1 ELSE 0 END) as primary_count,
           SUM(CASE WHEN tu.role = 'collaborator' THEN 1 ELSE 0 END) as collab_count,
           SUM(CASE WHEN tu.role = 'reviewer' THEN 1 ELSE 0 END) as review_count
     FROM users u
     JOIN task_user tu ON u.user_id = tu.user_id
     JOIN tasks t ON tu.task_id = t.id
     WHERE t.status != 'Completed' AND t.status != 'Cancelled'
     GROUP BY u.user_id
     ORDER BY primary_count DESC, name ASC
");
$team_workloads = [];
if ($team_workload_res) {
    while ($row = $team_workload_res->fetch_assoc()) $team_workloads[] = $row;
}

// ── 3. TASKS BY CLIENT ────────────────────────────────────────────────────────
$client_tasks_res = $conn->query("
    SELECT c.client_name, COUNT(t.id) as task_count
    FROM clients c
    JOIN tasks t ON c.id = t.client_id
    WHERE t.status != 'Completed' AND t.status != 'Cancelled'
    GROUP BY c.id
    ORDER BY task_count DESC LIMIT 8
");
$client_tasks = [];
if ($client_tasks_res) {
    while ($row = $client_tasks_res->fetch_assoc()) $client_tasks[] = $row;
}

// ── 4. TASKS BY STAGE ─────────────────────────────────────────────────────────
$stage_tasks_res = $conn->query("
    SELECT stage, COUNT(*) as task_count
    FROM tasks
    WHERE status != 'Completed' AND status != 'Cancelled'
    GROUP BY stage
    ORDER BY FIELD(stage, 'Brief', 'Assignment', 'Production', 'Internal Review', 'Client Approval', 'Publication/Delivery', 'Reporting')
");
$stage_tasks = [];
if ($stage_tasks_res) {
    while ($row = $stage_tasks_res->fetch_assoc()) $stage_tasks[] = $row;
}

// ── 5. BOTTLENECK STAGE ANALYSIS ──────────────────────────────────────────────
$bottlenecks_res = $conn->query("
    SELECT from_stage, AVG(TIMESTAMPDIFF(HOUR, created_at, next_created_at)) as avg_hrs
    FROM (
        SELECT task_id, from_stage, created_at,
               LEAD(created_at) OVER (PARTITION BY task_id ORDER BY created_at) as next_created_at
        FROM task_workflow_history
    ) sub
    WHERE next_created_at IS NOT NULL
    GROUP BY from_stage
    ORDER BY avg_hrs DESC
");
$bottlenecks = [];
if ($bottlenecks_res) {
    while ($row = $bottlenecks_res->fetch_assoc()) $bottlenecks[] = $row;
}
?>

<div class="height-100">
<div class="tasks-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title-section">
            <h1>Operations Performance Report</h1>
            <p>Analyze workflow bottlenecks, workload distribution, and campaign revision KPIs</p>
        </div>
        <a href="tasks.php" class="btn-secondary-custom">← Back to Board</a>
    </div>

    <!-- KPIs Row -->
    <div class="stats-row" style="margin-bottom: 24px;">
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <div class="stat-icon" style="color: #10b981;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number" style="color: #10b981;"><?= $total_completed ?></div>
            <div class="stat-label">Tasks Completed</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #ef4444;">
            <div class="stat-icon" style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-number" style="color: #ef4444;"><?= $total_overdue ?></div>
            <div class="stat-label">Tasks Overdue</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--primary-color);">
            <div class="stat-icon" style="color: var(--primary-color);"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-number" style="color: var(--primary-color);"><?= $avg_comp_days ?> Days</div>
            <div class="stat-label">Avg. Delivery Duration</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
            <div class="stat-icon" style="color: #8b5cf6;"><i class="fas fa-undo"></i></div>
            <div class="stat-number" style="color: #8b5cf6;"><?= $avg_revisions ?></div>
            <div class="stat-label">Avg. Revision Rounds</div>
        </div>
    </div>

    <!-- Reports Grid Layout -->
    <div class="reports-grid">
        
        <!-- Bottleneck Stage Card -->
        <div class="sidebar-card">
            <div class="sidebar-header"><i class="fas fa-hourglass-half text-danger"></i> Stage Bottleneck Analysis</div>
            <p style="color:#64748b; font-size:12px; margin-top:-5px; margin-bottom:20px;">Average days tasks spend in each pipeline stage before advancing</p>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php if (empty($bottlenecks)): ?>
                    <div class="text-center text-muted py-4 italic" style="font-size: 13px; font-style: italic;">No historical transition logs found.</div>
                <?php else: 
                    $max_hrs = 1;
                    foreach ($bottlenecks as $b) { if ($b['avg_hrs'] > $max_hrs) $max_hrs = $b['avg_hrs']; }
                    foreach ($bottlenecks as $b):
                        $days = round($b['avg_hrs'] / 24, 1);
                        $pct = round(($b['avg_hrs'] / $max_hrs) * 100);
                        if ($pct < 8) $pct = 8;
                ?>
                    <div>
                        <div class="d-flex justify-content-between mb-1" style="font-size:12.5px;">
                            <span class="font-weight-bold text-dark"><strong><?= htmlspecialchars($b['from_stage']) ?></strong></span>
                            <span class="text-muted"><?= $days ?> Days</span>
                        </div>
                        <div style="height: 10px; background-color: #e2e8f0; border-radius: 5px; overflow: hidden;">
                            <div style="background-color: #ef4444; width: <?= $pct ?>%; height: 100%; border-radius: 5px;"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Team Workloads Card -->
        <div class="sidebar-card">
            <div class="sidebar-header"><i class="fas fa-users text-primary"></i> Team Workload Allocation</div>
            <p style="color:#64748b; font-size:12px; margin-top:-5px; margin-bottom:20px;">Active tasks counts assigned per member roles</p>
            
            <?php if (empty($team_workloads)): ?>
                <div class="text-center text-muted py-4 italic" style="font-size: 13px; font-style: italic;">No active workload recorded.</div>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th style="text-align: center;">Primary</th>
                            <th style="text-align: center;">Collab</th>
                            <th style="text-align: center;">Reviewer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_workloads as $w): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; background-color: var(--primary-color); font-size: 9px; font-weight: bold; text-transform: uppercase;">
                                            <?= strtoupper(substr($w['name'], 0, 1)) ?>
                                        </div>
                                        <strong><?= htmlspecialchars($w['name']) ?></strong>
                                    </div>
                                </td>
                                <td style="text-align: center;"><span class="badge" style="background-color: var(--primary-color); color: #fff; font-size: 10px; font-weight: 700;"><?= intval($w['primary_count']) ?></span></td>
                                <td style="text-align: center;"><span class="badge bg-primary text-white" style="font-size: 10px; font-weight: 700;"><?= intval($w['collab_count']) ?></span></td>
                                <td style="text-align: center;"><span class="badge bg-dark text-white" style="font-size: 10px; font-weight: 700;"><?= intval($w['review_count']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Active Tasks by Client Card -->
        <div class="sidebar-card">
            <div class="sidebar-header"><i class="fas fa-briefcase text-success"></i> Active Tasks by Client / Account</div>
            <p style="color:#64748b; font-size:12px; margin-top:-5px; margin-bottom:20px;">Distribution of in-progress tasks across client partners</p>
            
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                <?php if (empty($client_tasks)): ?>
                    <div class="text-center text-muted py-4 italic" style="font-size:13px; font-style:italic;">No active client tasks.</div>
                <?php else: ?>
                    <?php foreach ($client_tasks as $ct): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background-color: var(--bg-light); border: 1px solid var(--border-color); font-size: 13px;">
                            <strong><?= htmlspecialchars($ct['client_name']) ?></strong>
                            <span class="badge" style="background-color: var(--accent-color); color: var(--primary-color); font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 20px;"><?= intval($ct['task_count']) ?> Active</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Tasks by Stage Card -->
        <div class="sidebar-card">
            <div class="sidebar-header"><i class="fas fa-chart-bar text-info"></i> Pipeline Stage Distribution</div>
            <p style="color:#64748b; font-size:12px; margin-top:-5px; margin-bottom:20px;">Active tasks distributed along the 7 workflow stages</p>
            
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                <?php if (empty($stage_tasks)): ?>
                    <div class="text-center text-muted py-4 italic" style="font-size:13px; font-style:italic;">No active stage items.</div>
                <?php else: ?>
                    <?php foreach ($stage_tasks as $st): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background-color: var(--bg-light); border: 1px solid var(--border-color); font-size: 13px;">
                            <strong><?= htmlspecialchars($st['stage']) ?></strong>
                            <span class="badge" style="background-color: var(--primary-color); color: white; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 20px;"><?= intval($st['task_count']) ?> Tasks</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</div>

<style>
/* ── Reports Grid Layout Styles ── */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; }
.page-header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: var(--text-dark); }
.page-header p { margin: 0; color: #888; font-size: 14px; }

.reports-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px; align-items: start;
}

.sidebar-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #f0f3f2; }
.sidebar-header { font-size: 15px; font-weight: 700; color: #333; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }
.sidebar-header i { color: var(--primary-color); }

/* Table layout customization */
.report-table {
    width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px;
}
.report-table th {
    text-align: left; padding: 8px 10px; font-weight: 700; color: var(--text-muted); font-size: 10.5px; text-transform: uppercase; border-bottom: 2px solid var(--border-color);
}
.report-table td {
    padding: 10px; border-bottom: 1px solid #f1f5f9; color: var(--text-dark);
}
.report-table tr:last-child td { border-bottom: none; }

@media (max-width: 768px) {
    .reports-grid { grid-template-columns: 1fr; }
}
</style>

<?php include 'dashboard_footer.php'; ?>
