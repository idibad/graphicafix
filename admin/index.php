<?php

include('dashboard_header.php'); 

    $notice_query = "SELECT * FROM notices ORDER BY `date`";
    $notice_result = mysqli_query($conn, $notice_query);
    $notice_cout = mysqli_num_rows($notice_result);

    // Fetch tasks based on role
    if ($role === 'admin') {
        $task_query = "SELECT * FROM tasks WHERE status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
    } else {
        $task_query = "SELECT * FROM tasks WHERE assigned_to = $user_id AND status NOT IN ('Completed', 'Cancelled') ORDER BY due_date ASC";
    }
    $task_result = mysqli_query($conn, $task_query);
    $task_count  = mysqli_num_rows($task_result);

    // Fetch resources
    $resource_query  = "SELECT * FROM resources ORDER BY created_at DESC";
    $resource_result = mysqli_query($conn, $resource_query);
    $resource_count  = mysqli_num_rows($resource_result);

    // Admin-only stats
    if ($role === 'admin') {
        $total_users_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
        $total_users            = mysqli_fetch_assoc($total_users_result)['cnt'];

        $total_tasks_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks");
        $total_tasks            = mysqli_fetch_assoc($total_tasks_result)['cnt'];

        $completed_tasks_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE status = 'Completed'");
        $completed_tasks        = mysqli_fetch_assoc($completed_tasks_result)['cnt'];

        $pending_tasks_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE status NOT IN ('Completed','Cancelled')");
        $pending_tasks          = mysqli_fetch_assoc($pending_tasks_result)['cnt'];

        $overdue_tasks_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('Completed','Cancelled')");
        $overdue_tasks          = mysqli_fetch_assoc($overdue_tasks_result)['cnt'];

        $high_priority_result   = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE priority = 'High' AND status NOT IN ('Completed','Cancelled')");
        $high_priority_tasks    = mysqli_fetch_assoc($high_priority_result)['cnt'];

        // Recent users
        $recent_users_result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

        // Tasks by priority
        $priority_result = mysqli_query($conn, "SELECT priority, COUNT(*) as cnt FROM tasks WHERE status NOT IN ('Completed','Cancelled') GROUP BY priority");
        $priority_data   = [];
        while ($row = mysqli_fetch_assoc($priority_result)) {
            $priority_data[$row['priority']] = $row['cnt'];
        }
    }
?>

<style>
    * { padding: 0; margin: 0; }

    /* ── Admin stat cards ── */
    .admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        gap: 8px;
        border-top: 4px solid var(--accent);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 100px;
        height: 100px;
        background: rgba(0,0,0,0.03);
        border-radius: 50%;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    }

    .stat-card.red    { border-top-color: #d63031; }
    .stat-card.green  { border-top-color: #00b894; }
    .stat-card.orange { border-top-color: #e17055; }
    .stat-card.blue   { border-top-color: #0984e3; }

    .stat-icon  { font-size: 2rem; }
    .stat-value { font-size: 2.2rem; font-weight: 700; color: #333; line-height: 1; }
    .stat-label { font-size: 13px; color: #999; font-weight: 500; }

    /* ── Quick actions ── */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 20px 12px;
        background: #f8f9fa;
        border: 2px solid transparent;
        border-radius: 12px;
        text-decoration: none;
        color: #333;
        font-weight: 600;
        font-size: 13px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .quick-action-btn:hover {
        background: #fcfff5ff;
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .quick-action-btn .qa-icon { font-size: 1.6rem; }

    /* ── Two-column admin panels ── */
    .admin-panels {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (max-width: 900px) {
        .admin-panels { grid-template-columns: 1fr; }
    }

    /* ── Users table ── */
    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th {
        font-size: 11px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 8px 10px;
        text-align: left;
        border-bottom: 2px solid #f0f0f0;
    }

    .users-table td {
        padding: 12px 10px;
        font-size: 14px;
        color: #333;
        border-bottom: 1px solid #f8f9fa;
    }

    .users-table tr:last-child td { border-bottom: none; }
    .users-table tr:hover td { background: #fcfff5ff; }

    .role-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .role-badge.admin { background: #fff4e5; color: #e17055; }
    .role-badge.user  { background: #e5f5ff; color: #0984e3; }

    /* ── Priority bars ── */
    .priority-bars { display: flex; flex-direction: column; gap: 18px; }

    .pbar-row { display: flex; align-items: center; gap: 12px; }

    .pbar-label {
        width: 75px;
        font-size: 13px;
        font-weight: 600;
        color: #555;
    }

    .pbar-track {
        flex: 1;
        height: 10px;
        background: #f0f0f0;
        border-radius: 99px;
        overflow: hidden;
    }

    .pbar-fill {
        height: 100%;
        border-radius: 99px;
        transition: width 0.6s ease;
    }

    .pbar-fill.high   { background: linear-gradient(135deg, #d63031, #ff7675); }
    .pbar-fill.medium { background: linear-gradient(135deg, #e17055, #fdcb6e); }
    .pbar-fill.low    { background: linear-gradient(135deg, var(--accent), #87bd0aff); }

    .pbar-count {
        font-size: 13px;
        font-weight: 700;
        color: #333;
        width: 24px;
        text-align: right;
    }

    /* ── Admin task rows (mirrors .task-item) ── */
    .admin-task-row {
        display: flex;
        align-items: start;
        padding: 18px;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .admin-task-row:hover {
        background: white;
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .assignee-chip {
        background: #fff4e5;
        color: #e17055;
        border-radius: 12px;
        padding: 3px 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .view-all-link {
        font-size: 13px;
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
    }

    .view-all-link:hover { text-decoration: underline; }

    @media (max-width: 768px) {
        .admin-stats-grid   { grid-template-columns: repeat(2, 1fr); }
        .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
</style>

<div class="dashboard-container">

    <div class="welcome-text">
        <h1>Welcome back, <?php echo htmlspecialchars($name); ?> <?php echo $role === 'admin' ? '🛡️' : '👋'; ?></h1>
        <p><?php echo $role === 'admin' ? "Here's your system overview for today." : "Here's what's happening with your projects today."; ?></p>
    </div>

    <?php if ($role === 'admin'): ?>
    <!-- ══════════════════════════════════════════════════════ -->
    <!--                      ADMIN VIEW                        -->
    <!-- ══════════════════════════════════════════════════════ -->

    <!-- Stat Cards -->
    <div class="admin-stats-grid">
        <div class="stat-card">
            <span class="stat-icon">👥</span>
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📋</span>
            <div class="stat-value"><?= $total_tasks ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
        <div class="stat-card green">
            <span class="stat-icon">✅</span>
            <div class="stat-value"><?= $completed_tasks ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card orange">
            <span class="stat-icon">⏳</span>
            <div class="stat-value"><?= $pending_tasks ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>
        <div class="stat-card red">
            <span class="stat-icon">🚨</span>
            <div class="stat-value"><?= $overdue_tasks ?></div>
            <div class="stat-label">Overdue</div>
        </div>
        <div class="stat-card blue">
            <span class="stat-icon">🔥</span>
            <div class="stat-value"><?= $high_priority_tasks ?></div>
            <div class="stat-label">High Priority</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="tasks-card" style="margin-bottom:30px;">
        <div class="card-header-custom">
            <h3>⚡ Quick Actions</h3>
        </div>
        <div class="quick-actions-grid">
            <a href="manage_users.php?add_user=1"     class="quick-action-btn"><span class="qa-icon">➕</span> Add User</a>
            <a href="create_task.php"     class="quick-action-btn"><span class="qa-icon">📝</span> New Task</a>
            <a href="manage_users.php" class="quick-action-btn"><span class="qa-icon">👥</span> Manage Users</a>
            <a href="tasks.php" class="quick-action-btn"><span class="qa-icon">📋</span> All Tasks</a>
            <a href="manage_notices.php?add_notice=1"   class="quick-action-btn"><span class="qa-icon">📢</span> Post Notice</a>
            <a href="manage_resources.php?add_resource=1" class="quick-action-btn"><span class="qa-icon">📚</span> Add Resource</a>
            <!-- <a href="reports.php"      class="quick-action-btn"><span class="qa-icon">📊</span> Reports</a> -->
            <a href="settings.php"     class="quick-action-btn"><span class="qa-icon">⚙️</span> Settings</a>
        </div>
    </div>

    <!-- Two-column: Recent Users + Priority Breakdown -->
    <div class="admin-panels">

        <!-- Recent Users -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3>👥 Recent Users</h3>
                <a href="manage_users.php" class="view-all-link">View All →</a>
            </div>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($u = mysqli_fetch_assoc($recent_users_result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Priority Breakdown + Latest Notices -->
        <div class="tasks-card">
            <div class="card-header-custom">
                <h3>📊 Tasks by Priority</h3>
                <a href="manage_tasks.php" class="view-all-link">View All →</a>
            </div>
            <?php
                $maxP   = max(array_values($priority_data) ?: [1]);
                $high   = $priority_data['High']   ?? 0;
                $medium = $priority_data['Medium']  ?? 0;
                $low    = $priority_data['Low']     ?? 0;
            ?>
            <div class="priority-bars" style="margin-bottom:25px;">
                <div class="pbar-row">
                    <span class="pbar-label">🔴 High</span>
                    <div class="pbar-track"><div class="pbar-fill high"   style="width:<?= $maxP ? round($high/$maxP*100)   : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $high ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label">🟠 Medium</span>
                    <div class="pbar-track"><div class="pbar-fill medium" style="width:<?= $maxP ? round($medium/$maxP*100) : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $medium ?></span>
                </div>
                <div class="pbar-row">
                    <span class="pbar-label">🟢 Low</span>
                    <div class="pbar-track"><div class="pbar-fill low"    style="width:<?= $maxP ? round($low/$maxP*100)    : 0 ?>%"></div></div>
                    <span class="pbar-count"><?= $low ?></span>
                </div>
            </div>

            <div class="card-header-custom" style="margin-top:10px;">
                <h3>📢 Latest Notices</h3>
                <a href="notices.php" class="view-all-link">Manage →</a>
            </div>
            <?php
                mysqli_data_seek($notice_result, 0);
                $nc = 0;
                while($nd = mysqli_fetch_assoc($notice_result)):
                    if ($nc >= 3) break; $nc++;
            ?>
                <div class="notice-item">
                    <div class="notice-title"><?= htmlspecialchars($nd['notice_title']) ?></div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- All Active Tasks -->
    <div class="tasks-card" style="margin-bottom:30px;">
        <div class="card-header-custom">
            <h3>✅ All Active Tasks</h3>
            <span class="badge-count"><?= $task_count ?> Active</span>
        </div>
        <?php while($task = mysqli_fetch_assoc($task_result)): ?>
        <div class="admin-task-row">
            <div class="task-checkbox" style="margin-right:15px; margin-top:3px;">
                <input type="checkbox" class="task-status" data-task-id="<?= $task['id'] ?>" <?= $task['status'] === 'Completed' ? 'checked' : '' ?>>
            </div>
            <div class="task-content" style="flex:1;">
                <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                <div class="task-meta">
                    <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?> Priority</span>
                    <span class="task-deadline">⏰ <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                    <?php if (!empty($task['assigned_to'])): ?>
                    <span class="assignee-chip">👤 User #<?= $task['assigned_to'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Resources -->
    <div class="resources-card">
        <div class="card-header-custom">
            <h3>📚 Resources & Links</h3>
            <a href="add_resource.php" class="view-all-link">+ Add New</a>
        </div>
        <div class="resources-list">
            <?php mysqli_data_seek($resource_result, 0); while($resource = mysqli_fetch_assoc($resource_result)): ?>
            <a href="<?= htmlspecialchars($resource['link']) ?>" class="resource-item" target="_blank">
                <div class="resource-icon">📄</div>
                <div class="resource-info">
                    <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                    <div class="resource-description"><?= htmlspecialchars($resource['description']) ?></div>
                </div>
                <span class="resource-link-icon">→</span>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════ -->
    <!--                      USER VIEW                         -->
    <!-- ══════════════════════════════════════════════════════ -->

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="user-card">
                <div class="user-avatar">👨‍💼</div>
                <div class="user-name"><?php echo htmlspecialchars($name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($designation ?? ''); ?></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="notice-board">
                <div class="card-header-custom">
                    <h3>📢 Notice Board</h3>
                    <span class="badge-count"><?php echo $notice_cout; ?></span>
                </div>
                <div class="notices-body">
                <?php mysqli_data_seek($notice_result, 0); while($data = mysqli_fetch_assoc($notice_result)): ?>
                    <div class="notice-item">
                        <div class="notice-title"><?= htmlspecialchars($data['notice_title']) ?></div>
                        <div class="notice-time">Today, 2:30 PM</div>
                    </div>
                <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="tasks-card">
                <div class="card-header-custom">
                    <h3>✅ Your Tasks</h3>
                    <span class="badge-count"><?php echo $task_count; ?> Active</span>
                </div>
                <?php while($task = mysqli_fetch_assoc($task_result)): ?>
                <div class="task-item">
                    <div class="task-checkbox">
                        <input type="checkbox" class="task-status" data-task-id="<?= $task['id'] ?>" <?= $task['status'] === 'Completed' ? 'checked' : '' ?>>
                    </div>
                    <div class="task-content">
                        <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                        <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                        <div class="task-meta">
                            <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?> Priority</span>
                            <span class="task-deadline">⏰ Due: <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="resources-card">
                <div class="card-header-custom">
                    <h3>📚 Resources & Links</h3>
                    <span class="badge-count"><?php echo $resource_count; ?> Items</span>
                </div>
                <div class="resources-list">
                    <?php while($resource = mysqli_fetch_assoc($resource_result)): ?>
                    <a href="<?= htmlspecialchars($resource['link']) ?>" class="resource-item" target="_blank">
                        <div class="resource-icon">📄</div>
                        <div class="resource-info">
                            <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                            <div class="resource-description"><?= htmlspecialchars($resource['description']) ?></div>
                        </div>
                        <span class="resource-link-icon">→</span>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    $('.task-status').on('change', function () {
        var $checkbox = $(this);
        var taskId    = $checkbox.data('task-id');
        var isChecked = $checkbox.is(':checked');
        var status    = isChecked ? 'Completed' : 'Pending';
        var $taskItem = $checkbox.closest('.task-item, .admin-task-row');

        if (isChecked) {
            $taskItem.css('opacity', '0.5');
            $taskItem.find('.task-title').css('text-decoration', 'line-through');
        } else {
            $taskItem.css('opacity', '1');
            $taskItem.find('.task-title').css('text-decoration', 'none');
        }

        $.ajax({
            url: 'update_task_status.php',
            type: 'POST',
            dataType: 'json',
            data: { id: taskId, status: status },
            success: function (response) {
                if (!response.success) {
                    $checkbox.prop('checked', !isChecked);
                    $taskItem.css('opacity', '1');
                    $taskItem.find('.task-title').css('text-decoration', 'none');
                    alert('Failed to update: ' + response.message);
                }
            },
            error: function (xhr) {
                $checkbox.prop('checked', !isChecked);
                $taskItem.css('opacity', '1');
                $taskItem.find('.task-title').css('text-decoration', 'none');
                console.error('AJAX Error:', xhr.responseText);
                alert('Server error. Please try again.');
            }
        });
    });
});
</script>

<?php include('dashboard_footer.php'); ?>