<?php

include 'dashboard_header.php';

if (in_array($role, ['client', 'client_sub'])) {
    header("Location: client_projects.php");
    exit;
}

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'sales', 'hrm', 'user'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$is_admin = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'pm');
$is_super_admin = in_array($role, ['super_admin', 'superadmin']);

// ── Projects query with task aggregation ─────────────────────────────────────────
$task_agg_sql = "
    LEFT JOIN (
        SELECT 
            project_id,
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN status IN ('Pending', 'Not Started', 'Active') THEN 1 ELSE 0 END) AS pending_tasks,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
            SUM(CASE WHEN status IN ('In Review', 'Pending Review') THEN 1 ELSE 0 END) AS in_review_tasks,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks,
            SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) AS overdue_tasks
        FROM tasks
        WHERE project_id IS NOT NULL
        GROUP BY project_id
    ) tk ON tk.project_id = p.id
";

if ($role === 'user') {
    $stmt = $conn->prepare("
        SELECT DISTINCT p.*, c.client_name,
            COALESCE(tk.total_tasks, 0) AS total_tasks,
            COALESCE(tk.pending_tasks, 0) AS pending_tasks,
            COALESCE(tk.in_progress_tasks, 0) AS in_progress_tasks,
            COALESCE(tk.in_review_tasks, 0) AS in_review_tasks,
            COALESCE(tk.completed_tasks, 0) AS completed_tasks,
            COALESCE(tk.overdue_tasks, 0) AS overdue_tasks
        FROM projects p
        INNER JOIN project_team pt ON pt.project_id = p.id
        LEFT JOIN clients c ON c.id = p.client_id
        {$task_agg_sql}
        WHERE pt.user_id = ?
        ORDER BY p.start_date DESC
    ");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT p.*, c.client_name,
            COALESCE(tk.total_tasks, 0) AS total_tasks,
            COALESCE(tk.pending_tasks, 0) AS pending_tasks,
            COALESCE(tk.in_progress_tasks, 0) AS in_progress_tasks,
            COALESCE(tk.in_review_tasks, 0) AS in_review_tasks,
            COALESCE(tk.completed_tasks, 0) AS completed_tasks,
            COALESCE(tk.overdue_tasks, 0) AS overdue_tasks
        FROM projects p
        LEFT JOIN clients c ON c.id = p.client_id
        {$task_agg_sql}
        ORDER BY p.start_date DESC
    ");
}

$stmt->execute();
$project_result = $stmt->get_result();
// ── Stats — scoped by role ────────────────────────────────────────────────────
if ($role === 'user') {
    // Only count projects this user is assigned to
    $stats_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT p.id) AS total,
            SUM(CASE WHEN p.status = 'active'    OR p.progress < 100  THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN p.status = 'completed' OR p.progress = 100  THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS due_week
        FROM projects p
        INNER JOIN project_team pt ON pt.project_id = p.id
        WHERE pt.user_id = ?
    ");
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
} else {
    // Admin sees all projects
    $stats = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'active'    OR progress < 100  THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'completed' OR progress = 100  THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS due_week
        FROM projects
    ")->fetch_assoc();
}
 
$total     = $stats['total']     ?? 0;
$active    = $stats['active']    ?? 0;
$completed = $stats['completed'] ?? 0;
$due_week  = $stats['due_week']  ?? 0;
?>


<div class="height-100" >

    <!-- Main Projects Section -->
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1>Projects</h1>
                <p>Manage and track all your design projects</p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary-custom">Export</button>
                <button class="btn-primary-custom" <?php if(!$can_create_project): ?>disabled<?php endif; ?>>+ New Project</button>

            </div>
        </div>
 
       <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label"><?= $is_admin ? 'Total Projects' : 'Assigned to You' ?></div>
                <?php if ($is_admin): ?>
                <div class="stat-change">↑ <?= round($total * 0.12) ?>% from last month</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-bolt"></i></div>
                <div class="stat-number"><?= $active ?></div>
                <div class="stat-label"><?= $is_admin ? 'Active Projects' : 'In Progress' ?></div>
                <?php if ($is_admin): ?>
                <div class="stat-change">↑ <?= round($active * 0.08) ?>% increase</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $completed ?></div>
                <div class="stat-label">Completed</div>
                <?php if ($is_admin): ?>
                <div class="stat-change">↑ <?= round($completed * 0.15) ?>% this month</div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $due_week ?></div>
                <div class="stat-label">Due This Week</div>
                <?php if ($is_admin): ?>
                <div class="stat-change negative">↑ <?= $due_week ?> urgent</div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            /* ── Horizontally Scrollable Filter Tabs ── */
            .filter-section {
                background: white;
                padding: 16px 20px;
                border-radius: 16px;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
                margin-bottom: 24px;
                display: flex;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
            }
            .filter-tabs-wrapper {
                flex: 1;
                min-width: 0;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
                padding: 4px 2px 6px 2px;
            }
            .filter-tabs-wrapper::-webkit-scrollbar {
                height: 4px;
            }
            .filter-tabs-wrapper::-webkit-scrollbar-track {
                background: transparent;
            }
            .filter-tabs-wrapper::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            .filter-tabs {
                display: inline-flex !important;
                flex-wrap: nowrap !important;
                gap: 8px !important;
                align-items: center;
                white-space: nowrap !important;
                margin: 0 !important;
            }
            .filter-tab {
                flex-shrink: 0 !important;
                white-space: nowrap !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                padding: 8px 14px !important;
                border: 1.5px solid #e2e8f0 !important;
                background: #ffffff !important;
                color: #475569 !important;
                border-radius: 8px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .filter-tab:hover {
                background: #f8fafc !important;
                border-color: #cbd5e1 !important;
                color: #0f172a !important;
            }
            .filter-tab.active {
                background: #024442 !important;
                color: #ffffff !important;
                border-color: #024442 !important;
                box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
            }
            .filter-tab .tab-badge {
                background: #f1f5f9;
                color: #475569;
                padding: 1px 7px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 700;
            }
            .filter-tab.active .tab-badge {
                background: rgba(255, 255, 255, 0.25);
                color: #ffffff;
            }
            .filter-tab-separator {
                width: 1px;
                height: 22px;
                background: #e2e8f0;
                margin: 0 4px;
                flex-shrink: 0;
            }
            .project-tasks-chip {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 11.5px;
                padding: 3px 8px;
                border-radius: 6px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                color: #475569;
                font-weight: 600;
            }
        </style>

        <!-- Filter and Search -->
        <div class="filter-section">
            <div class="search-box" style="flex-shrink:0;">
                <input type="text" id="projectSearchInput" placeholder="Search projects...">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>

            <!-- Horizontally Scrollable Tabs with Task Filters -->
            <div class="filter-tabs-wrapper">
                <div class="filter-tabs">
                    <!-- Project Status Filters -->
                    <button class="filter-tab active" data-filter="all">
                        <i class="fas fa-layer-group"></i> All Projects <span class="tab-badge" id="badge-all">0</span>
                    </button>
                    <button class="filter-tab" data-filter="active">
                        <i class="fas fa-bolt"></i> Active <span class="tab-badge" id="badge-active">0</span>
                    </button>
                    <button class="filter-tab" data-filter="completed">
                        <i class="fas fa-check-circle"></i> Completed <span class="tab-badge" id="badge-completed">0</span>
                    </button>
                    <button class="filter-tab" data-filter="on-hold">
                        <i class="fas fa-pause-circle"></i> On Hold <span class="tab-badge" id="badge-on-hold">0</span>
                    </button>

                    <!-- Divider -->
                    <div class="filter-tab-separator"></div>

                    <!-- Task Filters -->
                    <button class="filter-tab" data-filter="with-tasks" title="Projects with at least one task">
                        <i class="fas fa-tasks"></i> With Tasks <span class="tab-badge" id="badge-with-tasks">0</span>
                    </button>
                    <button class="filter-tab" data-filter="pending-tasks" title="Projects with pending or unstarted tasks">
                        <i class="fas fa-clock"></i> Pending Tasks <span class="tab-badge" id="badge-pending-tasks">0</span>
                    </button>
                    <button class="filter-tab" data-filter="in-progress-tasks" title="Projects with tasks currently active">
                        <i class="fas fa-spinner"></i> In Progress Tasks <span class="tab-badge" id="badge-in-progress-tasks">0</span>
                    </button>
                    <button class="filter-tab" data-filter="in-review-tasks" title="Projects with tasks waiting for review">
                        <i class="fas fa-search"></i> In Review Tasks <span class="tab-badge" id="badge-in-review-tasks">0</span>
                    </button>
                    <button class="filter-tab" data-filter="overdue-tasks" title="Projects with overdue tasks">
                        <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Overdue Tasks <span class="tab-badge" id="badge-overdue-tasks">0</span>
                    </button>
                    <button class="filter-tab" data-filter="no-tasks" title="Projects with no tasks created yet">
                        <i class="fas fa-folder-minus"></i> No Tasks <span class="tab-badge" id="badge-no-tasks">0</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php while($project = mysqli_fetch_assoc($project_result)): ?>
            <div class="project-card" 
                 data-project-id="<?= intval($project['id']) ?>"
                 data-project-status="<?= htmlspecialchars(strtolower($project['status'])) ?>"
                 data-total-tasks="<?= intval($project['total_tasks']) ?>"
                 data-pending-tasks="<?= intval($project['pending_tasks']) ?>"
                 data-in-progress-tasks="<?= intval($project['in_progress_tasks']) ?>"
                 data-in-review-tasks="<?= intval($project['in_review_tasks']) ?>"
                 data-completed-tasks="<?= intval($project['completed_tasks']) ?>"
                 data-overdue-tasks="<?= intval($project['overdue_tasks']) ?>">
                <div class="project-header">
                   
                    <span class="project-status-badge"><?= htmlspecialchars($project['status']) ?></span>
                    <div class="project-name"><?= htmlspecialchars($project['project_name']) ?></div>
                    <?php if ($is_super_admin): ?>
                    <div class="project-client">
                        Client: <?= htmlspecialchars($project['client_name'] ?? 'Unknown') ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="project-body">
                    <div class="project-description">
                        <p>
                        <?= strip_tags($project['description'] ?: $project['public_description']) ?>
                        </p>                    </div>

                    <!-- Task Counter & Progress Info -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div class="project-tasks-chip">
                            <i class="fas fa-tasks text-muted"></i>
                            <span><?= intval($project['completed_tasks']) ?>/<?= intval($project['total_tasks']) ?> Tasks</span>
                            <?php if ($project['overdue_tasks'] > 0): ?>
                                <span class="badge bg-danger ms-1" style="font-size:10px; padding:2px 5px;"><i class="fas fa-exclamation-circle"></i> <?= $project['overdue_tasks'] ?> Overdue</span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:12px; color:#64748b; font-weight:700;">
                            <?= intval($project['progress']) ?>%
                        </span>
                    </div>

                    <div class="project-progress">
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?= intval($project['progress']) ?>%"></div>
                        </div>
                    </div>

                    <div class="project-meta">
                        <div class="project-deadline">
                            <i class="far fa-calendar-alt me-1"></i> Due: <?= date('M d, Y', strtotime($project['end_date'])) ?>
                        </div>
                        <div class="project-team">
                        <?php
                        $stmt_team = $conn->prepare("
                            SELECT u.name
                            FROM project_team pt
                            JOIN users u ON u.user_id = pt.user_id
                            WHERE pt.project_id = ?
                        ");
                        $stmt_team->bind_param("i", $project['id']);
                        $stmt_team->execute();
                        $team_result = $stmt_team->get_result();
                        
                        while ($member = $team_result->fetch_assoc()):
                            $name = trim($member['name']);
                            $initials = '';
                        
                            $parts = explode(' ', $name);
                            foreach ($parts as $p) {
                                if (!empty($p)) $initials .= strtoupper($p[0]);
                                if (strlen($initials) == 2) break; // max 2 letters
                            }
                        ?>
                            <div class="team-avatar"><?= htmlspecialchars($initials) ?></div>
                        <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

        <!-- Empty State (shown when no projects match filters) -->
        <div id="projectsEmptyState" style="display:none; text-align:center; padding:50px 20px; background:#fff; border-radius:16px; border:1.5px dashed #cbd5e1; margin-top:16px;">
            <div style="font-size:40px; color:#94a3b8; margin-bottom:12px;"><i class="fas fa-folder-open"></i></div>
            <h5 style="color:#1e293b; font-weight:700; margin-bottom:6px; font-size:17px;">No matching projects found</h5>
            <p style="color:#64748b; font-size:13.5px; margin-bottom:0;">Try switching tabs or adjusting your search term.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterTabs = document.querySelectorAll('.filter-tab');
        const projectCards = document.querySelectorAll('.project-card');
        const searchInput = document.getElementById('projectSearchInput');
        const emptyState = document.getElementById('projectsEmptyState');
        const tabsWrapper = document.querySelector('.filter-tabs-wrapper');

        let currentFilter = 'all';

        // 1. Calculate and update badge counts on all tabs
        function updateTabBadges() {
            let counts = {
                'all': 0,
                'active': 0,
                'completed': 0,
                'on-hold': 0,
                'with-tasks': 0,
                'pending-tasks': 0,
                'in-progress-tasks': 0,
                'in-review-tasks': 0,
                'overdue-tasks': 0,
                'no-tasks': 0
            };

            projectCards.forEach(card => {
                const status = (card.dataset.projectStatus || '').toLowerCase();
                const totalTasks = parseInt(card.dataset.totalTasks, 10) || 0;
                const pendingTasks = parseInt(card.dataset.pendingTasks, 10) || 0;
                const inProgressTasks = parseInt(card.dataset.inProgressTasks, 10) || 0;
                const inReviewTasks = parseInt(card.dataset.inReviewTasks, 10) || 0;
                const overdueTasks = parseInt(card.dataset.overdueTasks, 10) || 0;

                counts['all']++;

                if (status === 'active' || status === 'in progress') {
                    counts['active']++;
                } else if (status === 'completed') {
                    counts['completed']++;
                } else if (status === 'on hold' || status === 'on-hold') {
                    counts['on-hold']++;
                }

                if (totalTasks > 0) counts['with-tasks']++;
                if (pendingTasks > 0) counts['pending-tasks']++;
                if (inProgressTasks > 0) counts['in-progress-tasks']++;
                if (inReviewTasks > 0) counts['in-review-tasks']++;
                if (overdueTasks > 0) counts['overdue-tasks']++;
                if (totalTasks === 0) counts['no-tasks']++;
            });

            for (const [key, val] of Object.entries(counts)) {
                const badge = document.getElementById('badge-' + key);
                if (badge) badge.textContent = val;
            }
        }

        // 2. Filter application logic
        function applyFilters() {
            const searchTerm = (searchInput ? searchInput.value : '').trim().toLowerCase();
            let visibleCount = 0;

            projectCards.forEach(card => {
                const status = (card.dataset.projectStatus || '').toLowerCase();
                const totalTasks = parseInt(card.dataset.totalTasks, 10) || 0;
                const pendingTasks = parseInt(card.dataset.pendingTasks, 10) || 0;
                const inProgressTasks = parseInt(card.dataset.inProgressTasks, 10) || 0;
                const inReviewTasks = parseInt(card.dataset.inReviewTasks, 10) || 0;
                const overdueTasks = parseInt(card.dataset.overdueTasks, 10) || 0;

                // Tab filter match
                let matchesTab = false;
                switch (currentFilter) {
                    case 'all':
                        matchesTab = true;
                        break;
                    case 'active':
                        matchesTab = (status === 'active' || status === 'in progress');
                        break;
                    case 'completed':
                        matchesTab = (status === 'completed');
                        break;
                    case 'on-hold':
                        matchesTab = (status === 'on hold' || status === 'on-hold');
                        break;
                    case 'with-tasks':
                        matchesTab = (totalTasks > 0);
                        break;
                    case 'pending-tasks':
                        matchesTab = (pendingTasks > 0);
                        break;
                    case 'in-progress-tasks':
                        matchesTab = (inProgressTasks > 0);
                        break;
                    case 'in-review-tasks':
                        matchesTab = (inReviewTasks > 0);
                        break;
                    case 'overdue-tasks':
                        matchesTab = (overdueTasks > 0);
                        break;
                    case 'no-tasks':
                        matchesTab = (totalTasks === 0);
                        break;
                    default:
                        matchesTab = true;
                }

                // Search term match
                let matchesSearch = true;
                if (searchTerm !== '') {
                    const projectName = (card.querySelector('.project-name')?.textContent || '').toLowerCase();
                    const clientName = (card.querySelector('.project-client')?.textContent || '').toLowerCase();
                    const description = (card.querySelector('.project-description')?.textContent || '').toLowerCase();
                    matchesSearch = projectName.includes(searchTerm) || clientName.includes(searchTerm) || description.includes(searchTerm);
                }

                if (matchesTab && matchesSearch) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (emptyState) {
                emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }

        // 3. Tab click events
        filterTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter') || 'all';
                applyFilters();

                // Smooth scroll tab into view if partially hidden
                this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            });
        });

        // 4. Search input event
        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        // 5. Horizontal mouse-wheel scroll on tabs wrapper
        if (tabsWrapper) {
            tabsWrapper.addEventListener('wheel', function(e) {
                if (e.deltaY !== 0) {
                    e.preventDefault();
                    this.scrollLeft += e.deltaY;
                }
            }, { passive: false });
        }

        // 6. Project card click navigation
        projectCards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.closest('button') || e.target.closest('a')) return;
                const projectID = this.dataset.projectId;
                if (projectID) {
                    window.location.href = `project_details.php?id=${projectID}`;
                }
            });
        });

        // 7. Header buttons
        const newProjectBtn = document.querySelector('.btn-primary-custom');
        if (newProjectBtn) {
            newProjectBtn.addEventListener('click', function() {
                window.location.href = `project_add.php`;
            });
        }

        const exportBtn = document.querySelector('.btn-secondary-custom');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                alert('Exporting projects data...\n\nThis would trigger a CSV/PDF download.');
            });
        }

        // Initialize badges & view
        updateTabBadges();
        applyFilters();
    });
    </script>
</div>

<?php 
include('dashboard_footer.php');
?>
