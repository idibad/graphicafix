<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($_SESSION['email'] ?? '')));

if ($portal_role === 'student') {
    // Student stats
    $enrolled_cnt = $conn->query("SELECT COUNT(*) as c FROM course_enrollments WHERE LOWER(TRIM(student_email)) = '$safe_email'")->fetch_assoc()['c'] ?? 0;
    $active_cnt   = $conn->query("SELECT COUNT(*) as c FROM course_enrollments WHERE LOWER(TRIM(student_email)) = '$safe_email' AND LOWER(status) = 'active'")->fetch_assoc()['c'] ?? 0;
    $cert_cnt     = $conn->query("SELECT COUNT(*) as c FROM certificates WHERE student_id = $user_id")->fetch_assoc()['c'] ?? 0;
    $query_cnt    = $conn->query("SELECT COUNT(*) as c FROM course_queries WHERE student_id = $user_id")->fetch_assoc()['c'] ?? 0;

    // Active courses
    $my_courses = $conn->query("
        SELECT e.status as enroll_status, c.id, c.title, c.thumbnail, c.duration_mins, u.name as instructor_name
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN users u ON c.instructor_id = u.user_id
        WHERE LOWER(TRIM(e.student_email)) = '$safe_email' AND LOWER(e.status) = 'active'
        ORDER BY e.enrolled_at DESC LIMIT 6
    ");

    // Notices
    $notices = $conn->query("SELECT *, notice_title as title, content as message, created_at FROM notices ORDER BY created_at DESC LIMIT 4");
} else {
    // Teacher stats
    $my_course_cnt = $conn->query("SELECT COUNT(*) as c FROM courses WHERE instructor_id = $user_id OR instructor_id IS NULL")->fetch_assoc()['c'] ?? 0;
    $lec_cnt       = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE teacher_id = $user_id")->fetch_assoc()['c'] ?? 0;
    $assign_cnt    = $conn->query("SELECT COUNT(*) as c FROM assignments WHERE teacher_id = $user_id")->fetch_assoc()['c'] ?? 0;
    $query_pending = $conn->query("SELECT COUNT(*) as c FROM course_queries q JOIN courses c ON q.course_id = c.id WHERE (c.instructor_id = $user_id OR c.instructor_id IS NULL) AND q.status = 'pending'")->fetch_assoc()['c'] ?? 0;

    // Recent lectures
    $recent_lecs = $conn->query("SELECT l.*, c.title as course_title FROM recorded_lectures l JOIN courses c ON l.course_id = c.id WHERE l.teacher_id = $user_id ORDER BY l.created_at DESC LIMIT 5");
}
?>

<div class="height-100">
    <!-- Welcome Hero -->
    <div class="d-card mb-4" style="background: linear-gradient(135deg, #024442 0%, #065f5b 100%); color:white; border: 1px solid rgba(184,243,90,0.3); position:relative; overflow:hidden;">
        <div style="position:absolute; right:-20px; bottom:-30px; font-size:12rem; opacity:0.06; color:#b8f35a; pointer-events:none;"><i class="fas fa-graduation-cap"></i></div>
        <div class="row align-items-center">
            <div class="col-md-8">
                <span style="background:rgba(184,243,90,0.15); color:#b8f35a; font-size:11px; font-weight:800; padding:4px 12px; border-radius:50px; text-transform:uppercase;">
                    <i class="fas fa-bolt"></i> Graphicafix LMS Portal
                </span>
                <h2 style="font-size:1.8rem; font-weight:800; margin:12px 0 8px; color:white;">Welcome, <?= htmlspecialchars($name) ?>!</h2>
                <p style="color:#cbd5e1; font-size:14.5px; margin:0;">
                    <?= $portal_role === 'student' ? 'Pick up right where you left off or explore live classes and certifications.' : 'Manage your courses, upload Vimeo video lectures, and grade assignments.' ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if($portal_role === 'student'): ?>
                    <a href="student_courses.php" class="btn btn-primary-custom" style="padding:14px 28px; font-size:14px; font-weight:800;"><i class="fas fa-play"></i> My Classroom</a>
                <?php else: ?>
                    <a href="teacher_lectures.php" class="btn btn-primary-custom" style="padding:14px 28px; font-size:14px; font-weight:800;"><i class="fas fa-video"></i> Upload Video</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if($portal_role === 'student'): ?>
        <!-- Student Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(2,68,66,0.1); color:#024442; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-book-open"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $enrolled_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Total Enrolled</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(16,185,129,0.1); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-play-circle"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $active_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Active Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(212,175,55,0.15); color:#d4af37; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-certificate"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $cert_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Certificates</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-question-circle"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $query_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">My Queries</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Active Courses Grid -->
            <div class="col-lg-8">
                <div class="d-card">
                    <div class="main-header mb-3">
                        <h4 style="font-size:1.2rem; font-weight:800; color:#024442; margin:0;"><i class="fas fa-laptop-code"></i> Active Classroom</h4>
                        <a href="student_courses.php" style="font-size:13px; font-weight:700; color:var(--primary);">View All &rarr;</a>
                    </div>
                    
                    <div class="row g-3">
                        <?php if($my_courses && $my_courses->num_rows > 0): while($c = $my_courses->fetch_assoc()): 
                            // Get completed lectures count for this course
                            $cid = intval($c['id']);
                            $tot_q = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE course_id = $cid AND status = 'approved'")->fetch_assoc()['c'] ?? 0;
                            $com_q = $conn->query("SELECT COUNT(*) as c FROM student_video_progress WHERE course_id = $cid AND student_id = $user_id AND is_completed = 1")->fetch_assoc()['c'] ?? 0;
                            $prog_pct = $tot_q > 0 ? round(($com_q / $tot_q) * 100) : 0;
                        ?>
                            <div class="col-md-6">
                                <div style="border:1px solid #e5e7eb; border-radius:14px; padding:16px; background:#f9fafb; display:flex; flex-direction:column; height:100%;">
                                    <div style="display:flex; gap:12px; margin-bottom:12px;">
                                        <div style="width:64px; height:64px; border-radius:10px; overflow:hidden; background:#e2e8f0; flex-shrink:0;">
                                            <?php if(!empty($c['thumbnail'])): ?>
                                                <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#024442; color:white;"><i class="fas fa-graduation-cap"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="overflow:hidden;">
                                            <h5 style="font-size:15px; font-weight:800; color:#111; margin:0 0 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($c['title']) ?></h5>
                                            <div style="font-size:12px; color:#666;">By <?= htmlspecialchars($c['instructor_name'] ?? 'Instructor') ?></div>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div style="margin-top:auto;">
                                        <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; color:#475569; margin-bottom:4px;">
                                            <span>Progress (<?= $com_q ?>/<?= $tot_q ?> videos)</span>
                                            <span><?= $prog_pct ?>%</span>
                                        </div>
                                        <div style="width:100%; height:8px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-bottom:14px;">
                                            <div style="width:<?= $prog_pct ?>%; height:100%; background:#10b981; border-radius:10px; transition:width .3s;"></div>
                                        </div>

                                        <a href="course_player.php?course_id=<?= $c['id'] ?>" class="btn btn-primary-custom" style="width:100%; justify-content:center; padding:10px; font-size:13px;"><i class="fas fa-play"></i> Watch Lectures</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <p>You have no active courses currently.</p>
                                <a href="student_courses.php" class="btn btn-primary-custom">Check My Registrations</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Noticeboard Feed -->
            <div class="col-lg-4">
                <div class="d-card">
                    <h4 style="font-size:1.15rem; font-weight:800; color:#024442; margin:0 0 16px;"><i class="fas fa-bullhorn"></i> Campus Noticeboard</h4>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php if($notices && $notices->num_rows > 0): while($n = $notices->fetch_assoc()): ?>
                            <div style="padding:12px; border-left:4px solid var(--accent); background:#f8fafc; border-radius:0 8px 8px 0;">
                                <div style="font-size:13.5px; font-weight:800; color:#1e293b;"><?= htmlspecialchars($n['title']) ?></div>
                                <div style="font-size:12.5px; color:#64748b; margin:4px 0 8px;"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                                <div style="font-size:11px; color:#94a3b8;"><i class="fas fa-user"></i> <?= htmlspecialchars($n['created_by'] ?? $n['teacher_name'] ?? 'Campus Admin') ?> &middot; <?= date('M d', strtotime($n['created_at'])) ?></div>
                            </div>
                        <?php endwhile; else: ?>
                            <p class="text-muted text-center my-4" style="font-size:13px;">No notices posted.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Teacher Stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(2,68,66,0.1); color:#024442; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-book-open"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $my_course_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">My Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(184,243,90,0.25); color:#024442; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-video"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $lec_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Video Lectures</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-tasks"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#111; line-height:1;"><?= $assign_cnt ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Assignments</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-card" style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:50px; height:50px; border-radius:12px; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center; font-size:24px;"><i class="fas fa-question-circle"></i></div>
                    <div>
                        <div style="font-size:24px; font-weight:800; color:#ef4444; line-height:1;"><?= $query_pending ?></div>
                        <div style="font-size:12.5px; color:#666; margin-top:4px; font-weight:600;">Pending Queries</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-card">
                    <div class="main-header mb-3">
                        <h4 style="font-size:1.2rem; font-weight:800; color:#024442; margin:0;"><i class="fas fa-video"></i> Recently Uploaded Lectures</h4>
                        <a href="teacher_lectures.php" class="btn btn-primary-custom" style="padding:8px 16px; font-size:12.5px;"><i class="fas fa-plus"></i> Upload New</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr style="font-size:13px;">
                                    <th>Lecture Title</th>
                                    <th>Course</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_lecs && $recent_lecs->num_rows > 0): while($l = $recent_lecs->fetch_assoc()): ?>
                                    <tr style="font-size:13.5px;">
                                        <td style="font-weight:700; color:#111;"><?= htmlspecialchars($l['title']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['course_title']) ?></span></td>
                                        <td><span class="badge" style="background:#024442; color:#b8f35a;"><?= strtoupper($l['video_type'] ?? 'MP4') ?></span></td>
                                        <td>
                                            <?php if($l['status']==='approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif($l['status']==='rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#666; font-size:12.5px;"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No lectures uploaded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Action Box -->
            <div class="col-lg-4">
                <div class="d-card mb-4" style="background:#0f172a; color:white;">
                    <h4 style="font-size:1.1rem; font-weight:800; color:var(--accent); margin:0 0 16px;"><i class="fas fa-rocket"></i> Instructor Toolkit</h4>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="teacher_quizzes.php" class="btn" style="background:rgba(255,255,255,0.08); color:white; justify-content:flex-start;"><i class="fas fa-question-circle text-info"></i> Create Course Quiz</a>
                        <a href="teacher_resources.php" class="btn" style="background:rgba(255,255,255,0.08); color:white; justify-content:flex-start;"><i class="fas fa-file-download text-warning"></i> Attach Resource Files</a>
                        <a href="teacher_announcements.php" class="btn" style="background:rgba(255,255,255,0.08); color:white; justify-content:flex-start;"><i class="fas fa-bullhorn text-success"></i> Post Announcement</a>
                        <a href="teacher_queries.php" class="btn" style="background:rgba(255,255,255,0.08); color:white; justify-content:flex-start;"><i class="fas fa-comments text-danger"></i> Answer Student Q&A</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include('dashboard_footer.php'); ?>
