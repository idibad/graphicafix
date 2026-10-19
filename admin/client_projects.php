<?php
include 'dashboard_header.php';

// Verify role access
if ($role !== 'client' && $role !== 'client_sub') {
    header("Location: index.php");
    exit;
}

$client_id = intval($data['client_id'] ?? 0);
if ($client_id <= 0) {
    echo "<div class='alert alert-warning' style='margin: 40px;'>Your account is not linked to any client company. Please contact the administrator.</div>";
    include 'dashboard_footer.php';
    exit;
}

// Fetch client projects
$stmt = $conn->prepare("
    SELECT p.*, c.client_name 
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.client_id = ? AND p.visibility IN ('client', 'public')
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$project_result = $stmt->get_result();

// Stats
$active = 0;
$completed = 0;
$total = $project_result->num_rows;

$projects = [];
while ($p = $project_result->fetch_assoc()) {
    $projects[] = $p;
    if ($p['status'] === 'In Progress') $active++;
    if ($p['status'] === 'Completed') $completed++;
}
?>

<div class="height-100">
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div class="page-title-section">
                <h1>My Projects</h1>
                <p>Track progress, view timelines, and collaborate on active deliverables</p>
            </div>
            <a href="https://youtu.be/r6KaII-FkR4?si=Bav6a-KWEmLB0sha" target="_blank" class="btn" style="background:#fff; border:1px solid #cbd5e1; color:#024442; font-weight:700; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:13.5px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <i class="fab fa-youtube" style="color:#ef4444; font-size:16px;"></i> How to Use Video Guide
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row" style="margin-bottom: 24px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-number"><?= $total ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                <div class="stat-number"><?= $active ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= $completed ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php if ($total > 0): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="project-card" onclick="window.location='client_project_details.php?id=<?= $project['id'] ?>'" style="cursor:pointer;">
                        <div class="project-header">
                            <span class="project-status-badge" style="background: <?= $project['status'] === 'Completed' ? '#ecfdf5;color:#10b981;' : ($project['status'] === 'In Progress' ? '#e0f2fe;color:#0284c7;' : '#fef3c7;color:#d97706;') ?>"><?= htmlspecialchars($project['status']) ?></span>
                            <div class="project-name" style="margin-top:8px;"><?= htmlspecialchars($project['project_name']) ?></div>
                            <div class="project-client" style="font-size:12px; color:#64748b; margin-top:4px;">
                                Company: <?= htmlspecialchars($project['client_name']) ?>
                            </div>
                        </div>

                        <div class="project-body" style="margin-top:16px;">
                            <div class="project-description" style="font-size:13.5px; color:#475569; min-height:60px;">
                                <p><?= substr(strip_tags($project['description'] ?: $project['public_description']), 0, 120) ?>...</p>
                            </div>

                            <div class="project-progress" style="margin-top:16px;">
                                <div class="progress-header" style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#475569; margin-bottom:6px;">
                                    <span class="progress-label">Completion Status</span>
                                    <span class="progress-percentage"><?= intval($project['progress']) ?>%</span>
                                </div>
                                <div class="progress-bar-custom" style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                                    <div class="progress-fill" style="background:#024442; width: <?= intval($project['progress']) ?>%; height:100%;"></div>
                                </div>
                            </div>

                            <div class="project-meta" style="margin-top:20px; display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
                                <div class="project-deadline">
                                    ⏰ Due: <?= !empty($project['end_date']) && $project['end_date'] !== '0000-00-00' ? date('M d, Y', strtotime($project['end_date'])) : 'TBD' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-folder-open" style="font-size:40px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <p>No projects associated with your company have been shared yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
