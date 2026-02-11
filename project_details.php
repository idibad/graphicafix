<?php
include('dashboard_header.php');
// Get project ID from URL
$project_id = $_GET['id'] ?? 0;

// Fetch project details
$project_query = "SELECT p.*, c.client_name 
                  FROM projects p 
                  LEFT JOIN clients c ON p.client_id = c.id 
                  WHERE p.id = ?";
$stmt = $conn->prepare($project_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<script>alert('Project not found');window.location='manage_projects.php';</script>";
    exit;
}

// Fetch team members
$team_query = "SELECT u.user_id, u.name, u.email, u.role 
               FROM project_team pt 
               JOIN users u ON pt.user_id = u.user_id 
               WHERE pt.project_id = ?";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$team_members = $stmt->get_result();

// Fetch gallery images
$gallery_query = "SELECT * FROM project_gallery WHERE project_id = ? ORDER BY sort_order";
$stmt = $conn->prepare($gallery_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$gallery = $stmt->get_result();

// Fetch project files
$files_query = "SELECT * FROM project_files WHERE project_id = ? AND file_type != 'thumbnail' ORDER BY uploaded_at DESC";
$stmt = $conn->prepare($files_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$files = $stmt->get_result();

// Status and Priority styling
function getStatusClass($status) {
    $classes = [
        'Not Started' => 'status-not-started',
        'In Progress' => 'status-in-progress',
        'Completed' => 'status-completed',
        'On Hold' => 'status-on-hold'
    ];
    return $classes[$status] ?? 'status-default';
}

function getPriorityClass($priority) {
    $classes = [
        'Low' => 'priority-low',
        'Medium' => 'priority-medium',
        'High' => 'priority-high'
    ];
    return $classes[$priority] ?? 'priority-medium';
}
?>


<div class="height-100" >
    <!-- Project Header -->
    <div class="project-details-header">
        <div class="header-details-top">
            <div class="header-content">
                <h1><?= htmlspecialchars($project['project_name']) ?></h1>
                <div class="client-name">
                    <span>🏢</span> <?= htmlspecialchars($project['client_name']) ?>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location='manage_projects.php'">
                    <span>←</span> Back
                </button>
                <button class="btn btn-secondary" onclick="window.location='edit_project.php?id=<?= $project_id ?>'">
                    <span>✏️</span> Edit
                </button>
                <button class="btn btn-primary">
                    <span>📤</span> Share
                </button>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Status</span>
                <span class="badge <?= getStatusClass($project['status']) ?>">
                    <?= htmlspecialchars($project['status']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Priority</span>
                <span class="badge <?= getPriorityClass($project['priority']) ?>">
                    <?= htmlspecialchars($project['priority']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Start Date</span>
                <span class="meta-value">
                    <?= !empty($project['start_date']) ? date('M d, Y', strtotime($project['start_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">End Date</span>
                <span class="meta-value">
                    <?= !empty($project['end_date']) ? date('M d, Y', strtotime($project['end_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Visibility</span>
                <span class="meta-value" style="text-transform: capitalize;">
                    <?= htmlspecialchars($project['visibility']) ?>
                </span>
            </div>
        </div>

        <?php if (!empty($project['start_date']) && !empty($project['end_date'])): 
            $start = strtotime($project['start_date']);
            $end = strtotime($project['end_date']);
            $now = time();
            $total_days = max(1, ($end - $start) / 86400);
            $elapsed_days = max(0, min($total_days, ($now - $start) / 86400));
            $progress = min(100, ($elapsed_days / $total_days) * 100);
        ?>
        <div class="progress-section">
            <div class="progress-header">
                <span class="progress-label">Project Timeline</span>
                <span class="progress-percent"><?= round($progress) ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Description -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📝 Project Description</h2>
                </div>
                <?php if (!empty($project['description'])): ?>
                    <div class="description-text">
                        <?= nl2br(htmlspecialchars($project['description'])) ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📄</div>
                        <div>No description provided</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($project['public_description'])): ?>
            <!-- Public Description -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👁️ Public Description</h2>
                </div>
                <div class="description-text">
                    <?= nl2br(htmlspecialchars($project['public_description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($project['internal_notes'])): ?>
            <!-- Internal Notes -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🔒 Internal Notes</h2>
                </div>
                <div class="description-text" style="background: var(--warning-light); padding: 16px; border-radius: var(--radius);">
                    <?= nl2br(htmlspecialchars($project['internal_notes'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gallery -->
            <?php if ($gallery->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🖼️ Project Gallery</h2>
                    <button class="card-action">⋮</button>
                </div>
                <div class="gallery-grid">
                    <?php while ($img = $gallery->fetch_assoc()): ?>
                        <div class="gallery-item">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Gallery image">
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Files -->
            <?php if ($files->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📎 Project Files</h2>
                    <button class="card-action">⋮</button>
                </div>
                <div class="file-list">
                    <?php while ($file = $files->fetch_assoc()): 
                        $file_ext = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                        $file_name = basename($file['file_path']);
                    ?>
                        <div class="file-item">
                            <div class="file-icon">📄</div>
                            <div class="file-info">
                                <div class="file-name"><?= htmlspecialchars($file_name) ?></div>
                                <div class="file-meta">
                                    <?= strtoupper($file_ext) ?> • 
                                    <?= !empty($file['uploaded_at']) ? date('M d, Y', strtotime($file['uploaded_at'])) : 'Unknown' ?>
                                </div>
                            </div>
                            <button class="file-download" onclick="window.open('<?= htmlspecialchars($file['file_path']) ?>', '_blank')">
                                ⬇️
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Team Members -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👥 Team Members</h2>
                    <button class="card-action">+</button>
                </div>
                <?php if ($team_members->num_rows > 0): ?>
                    <div class="team-list">
                        <?php while ($member = $team_members->fetch_assoc()): 
                            $initial = strtoupper(substr($member['name'], 0, 1));
                        ?>
                            <div class="team-member">
                                <div class="team-avatar"><?= $initial ?></div>
                                <div class="team-info">
                                    <div class="team-name"><?= htmlspecialchars($member['name']) ?></div>
                                    <div class="team-role"><?= htmlspecialchars($member['role']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <div>No team members assigned</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Project Timeline -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📅 Timeline</h2>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--success);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Project Created</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($project['start_date'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--info);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Start Date</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['start_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($project['end_date'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background: var(--warning);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Target End Date</div>
                            <div class="timeline-date">
                                <?= date('M d, Y', strtotime($project['end_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚡ Quick Actions</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        📤 Upload Files
                    </button>
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        💬 Add Comment
                    </button>
                    <button class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        📊 View Reports
                    </button>
                    <button class="btn btn-danger" style="width: 100%; justify-content: center;">
                        🗑️ Delete Project
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include('dashboard_footer.php');
?>