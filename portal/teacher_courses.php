<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Get instructor courses
$courses = $conn->query("
    SELECT c.*, 
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status = 'active') as student_cnt,
        (SELECT COUNT(*) FROM recorded_lectures WHERE course_id = c.id) as lec_cnt
    FROM courses c 
    WHERE c.instructor_id = $user_id OR c.instructor_id IS NULL 
    ORDER BY c.created_at DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-book-open"></i> My Courses Overview</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Manage curriculum syllabi, track student enrollments, and upload lectures.</p>
        </div>
        <div>
            <a href="teacher_lectures.php" class="btn btn-primary-custom"><i class="fas fa-video"></i> Upload New Lecture</a>
        </div>
    </div>

    <div class="row g-4">
        <?php if($courses && $courses->num_rows > 0): while($c = $courses->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; height:100%; border:1px solid #e2e8f0;">
                    <div style="height:160px; background:#0f172a; position:relative;">
                        <?php if(!empty($c['thumbnail'])): ?>
                            <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:3rem; background:#024442; color:white;"><i class="fas fa-graduation-cap"></i></div>
                        <?php endif; ?>
                        <span style="position:absolute; bottom:12px; left:12px; background:rgba(0,0,0,0.7); color:#b8f35a; font-size:11px; font-weight:800; padding:4px 10px; border-radius:6px;">
                            <i class="fas fa-users"></i> <?= $c['student_cnt'] ?> Students Enrolled
                        </span>
                    </div>

                    <div style="padding:20px; display:flex; flex-direction:column; flex:1;">
                        <h4 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 8px;"><?= htmlspecialchars($c['title']) ?></h4>
                        <p style="font-size:13px; color:#64748b; margin-bottom:16px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= htmlspecialchars($c['description'] ?? 'No description provided.') ?>
                        </p>

                        <div style="display:flex; justify-content:space-between; border-top:1px solid #f1f5f9; padding-top:12px; margin-top:auto; font-size:12.5px; color:#475569; font-weight:600;">
                            <span><i class="fas fa-video text-primary"></i> <?= $c['lec_cnt'] ?> Lectures</span>
                            <span><i class="fas fa-clock text-warning"></i> <?= htmlspecialchars($c['duration_mins'] ?? 'Self-paced') ?></span>
                        </div>

                        <div style="display:flex; gap:8px; margin-top:16px;">
                            <a href="teacher_lectures.php?filter_course=<?= $c['id'] ?>" class="btn btn-primary-custom" style="flex:1; justify-content:center; font-size:13px; padding:10px;"><i class="fas fa-folder-open"></i> Manage Content</a>
                            <a href="teacher_quizzes.php?course_id=<?= $c['id'] ?>" class="btn btn-secondary-custom" style="padding:10px;" title="Quizzes"><i class="fas fa-tasks"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-book fs-1 text-muted mb-3"></i>
                <h4>No Assigned Courses</h4>
                <p class="text-muted">You have not been assigned as an instructor to any courses yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
