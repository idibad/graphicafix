<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

if ($role !== 'student') {
    die("Access denied");
}

// Fetch enrolled courses for student
$enrolled_courses = [];
$c_res = $conn->query("SELECT course_id FROM course_enrollments WHERE student_id = $user_id");
if ($c_res) {
    while($row = $c_res->fetch_assoc()) {
        $enrolled_courses[] = $row['course_id'];
    }
}

// Fetch approved lectures for enrolled courses
$lectures = null;
if (!empty($enrolled_courses)) {
    $course_ids = implode(',', $enrolled_courses);
    $lectures = $conn->query("
        SELECT l.*, c.title as course_title, t.username as teacher_name 
        FROM recorded_lectures l 
        JOIN courses c ON l.course_id = c.id
        JOIN users t ON l.teacher_id = t.user_id
        WHERE l.status = 'approved' AND l.course_id IN ($course_ids)
        ORDER BY l.created_at DESC
    ");
}
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-play-circle"></i> Recorded Lectures</h3>
        <p style="color:#666; font-size:14px;">Watch recorded lectures from your enrolled courses.</p>
    </div>

    <div class="projects-grid">
        <?php if($lectures && $lectures->num_rows > 0): while($l = $lectures->fetch_assoc()): ?>
            <div class="project-card">
                <div class="project-header" style="padding:15px; border-radius:16px 16px 0 0;">
                    <div class="project-status-badge"><i class="fas fa-video"></i> Video</div>
                    <div class="project-name" style="font-size:16px; margin-right:60px;"><?= htmlspecialchars($l['title']) ?></div>
                    <div class="project-client" style="font-size:12px;"><i class="fas fa-book"></i> <?= htmlspecialchars($l['course_title']) ?></div>
                </div>
                <div class="project-body">
                    <?php if(!empty($l['description'])): ?>
                        <div class="project-description" style="font-size:13px; color:#555; margin-bottom:15px; height:40px;">
                            <?= htmlspecialchars($l['description']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="font-size:12px; color:#888; margin-bottom:15px; display:flex; justify-content:space-between;">
                        <span><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($l['teacher_name']) ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($l['created_at'])) ?></span>
                    </div>

                    <a href="<?= htmlspecialchars($l['file_path']) ?>" target="_blank" class="btn btn-primary-custom" style="width:100%; text-align:center; display:block;">
                        <i class="fas fa-play"></i> Watch Lecture
                    </a>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-video-slash empty-state-icon"></i>
                <h3>No Lectures Available</h3>
                <p>There are currently no recorded lectures available for your enrolled courses.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
