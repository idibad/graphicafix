<?php

include('dashboard_header.php');

$is_admin = ($role === 'admin' || $role === 'pm');

// ── Projects query ────────────────────────────────────────────────────────────
if ($role === 'user') {
    $stmt = $conn->prepare("
        SELECT DISTINCT p.*, c.client_name
        FROM projects p
        INNER JOIN project_team pt ON pt.project_id = p.id
        LEFT JOIN clients c ON c.id = p.client_id
        WHERE pt.user_id = ?
        ORDER BY p.start_date DESC
    ");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT p.*, c.client_name
        FROM projects p
        LEFT JOIN clients c ON c.id = p.client_id
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
                <button class="btn-primary-custom" <?php if(!$can_edit): ?>disabled<?php endif; ?>>+ New Project</button>

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
                <div class="stat-icon">⏰</div>
                <div class="stat-number"><?= $due_week ?></div>
                <div class="stat-label">Due This Week</div>
                <?php if ($is_admin): ?>
                <div class="stat-change negative">↑ <?= $due_week ?> urgent</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="filter-section">
            <div class="search-box">
                <input type="text" placeholder="Search projects...">
                <span class="search-icon"><i class="fas fa-search"></i></span>
            </div>
            <div class="filter-tabs">
                <button class="filter-tab active">All</button>
                <button class="filter-tab">Active</button>
                <button class="filter-tab">Completed</button>
                <button class="filter-tab">On Hold</button>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php while($project = mysqli_fetch_assoc($project_result)): ?>
            <div class="project-card" data-project-id="<?= intval($project['id']) ?>">
                <div class="project-header">
                   
                    <span class="project-status-badge"><?= htmlspecialchars($project['status']) ?></span>
                    <div class="project-name"><?= htmlspecialchars($project['project_name']) ?></div>
                    <div class="project-client">
                        Client: <?= htmlspecialchars($project['client_name'] ?? 'Unknown') ?>
                    </div>
                </div>

                <div class="project-body">
                    <div class="project-description">
                        <p>
                        <?= strip_tags($project['description'] ?: $project['public_description']) ?>
                        </p>                    </div>

                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage"><?= intval($project['progress']) ?>%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?= intval($project['progress']) ?>%"></div>
                        </div>
                    </div>

                    <div class="project-meta">
                        <div class="project-deadline">
                            ⏰ Due: <?= date('M d, Y', strtotime($project['end_date'])) ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        const filterTabs = document.querySelectorAll('.filter-tab');
        const projectCards = document.querySelectorAll('.project-card');

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                filterTabs.forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                const filter = this.textContent.toLowerCase();

                // Show/hide projects based on filter
                projectCards.forEach(card => {
                    const status = card.querySelector('.project-status-badge').textContent.toLowerCase();
                    
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else if (filter === 'active' && status === 'in progress') {
                        card.style.display = 'block';
                    } else if (filter === 'completed' && status === 'completed') {
                        card.style.display = 'block';
                    } else if (filter === 'on hold' && status === 'on hold') {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            projectCards.forEach(card => {
                const projectName = card.querySelector('.project-name').textContent.toLowerCase();
                const clientName = card.querySelector('.project-client').textContent.toLowerCase();
                const description = card.querySelector('.project-description').textContent.toLowerCase();

                if (projectName.includes(searchTerm) || clientName.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Project card click handler
        // projectCards.forEach(card => {
        //     card.addEventListener('click', function() {
        //         const projectName = this.querySelector('.project-name').textContent;
        //         // alert(`Opening project: ${projectName}\n\nThis would navigate to the project details page.`);
        //         window.location.href = `project_details.php?name=${encodeURIComponent(projectName)}`;
        //     });
        // });
        document.addEventListener('DOMContentLoaded', function() {
            const projectCards = document.querySelectorAll('.project-card');

            projectCards.forEach(card => {
                card.addEventListener('click', function() {
                    const projectID = this.dataset.projectId;
                    if (!projectID) {
                        console.error('Project ID not found on card', this);
                        return;
                    }
                    window.location.href = `project_details.php?id=${projectID}`;
                });
            });
        });

        // New Project button
        document.querySelector('.btn-primary-custom').addEventListener('click', function() {
            window.location.href = `project_add.php`;
        });

        // Export button
        document.querySelector('.btn-secondary-custom').addEventListener('click', function() {
            alert('Exporting projects data...\n\nThis would trigger a CSV/PDF download.');
        });
    </script>
</div>

<?php 
include('dashboard_footer.php');
?>
