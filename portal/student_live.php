<?php
include('dashboard_header.php');

$safe_email = mysqli_real_escape_string($conn, strtolower(trim($_SESSION['email'] ?? '')));

// Fetch active courses
$enrolled = [];
$e_res = $conn->query("SELECT course_id FROM course_enrollments WHERE LOWER(TRIM(student_email)) = '$safe_email' AND LOWER(status) = 'active'");
if ($e_res) {
    while ($r = $e_res->fetch_assoc()) $enrolled[] = $r['course_id'];
}

$sessions = null;
if (!empty($enrolled)) {
    $c_ids = implode(',', $enrolled);
    $sessions = $conn->query("
        SELECT s.*, c.title as course_title, u.name as teacher_name 
        FROM live_sessions s 
        JOIN courses c ON s.course_id = c.id 
        LEFT JOIN users u ON s.teacher_id = u.user_id 
        WHERE s.course_id IN ($c_ids) 
        ORDER BY s.start_time DESC
    ");
}
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-video"></i> Live Meeting Rooms</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Join live instructor meetings or watch past cloud recordings.</p>
        </div>
    </div>

    <div class="row g-4">
        <?php if($sessions && $sessions->num_rows > 0): while($s = $sessions->fetch_assoc()): 
            $is_past = strtotime($s['start_time']) < time();
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-card" style="padding:24px; display:flex; flex-direction:column; height:100%; border:1px solid <?= !$is_past ? '#024442' : '#e2e8f0' ?>;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                        <span class="badge bg-light text-dark border"><i class="fas fa-book"></i> <?= htmlspecialchars($s['course_title']) ?></span>
                        <?php if(!$is_past): ?>
                            <span class="badge bg-danger animate-pulse"><i class="fas fa-broadcast-tower"></i> Upcoming</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Concluded</span>
                        <?php endif; ?>
                    </div>

                    <h4 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 8px;"><?= htmlspecialchars($s['topic']) ?></h4>
                    <div style="font-size:13px; color:#64748b; margin-bottom:16px;"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($s['teacher_name'] ?? 'Instructor') ?></div>

                    <div style="background:#f8fafc; padding:12px; border-radius:10px; margin-bottom:20px; font-size:13px;">
                        <i class="fas fa-calendar-alt text-success"></i> Time: <strong><?= date('M d, Y @ H:i', strtotime($s['start_time'])) ?></strong>
                    </div>

                    <div style="margin-top:auto; display:flex; gap:10px;">
                        <?php if(!$is_past): ?>
                            <a href="<?= htmlspecialchars($s['meeting_link']) ?>" target="_blank" class="btn btn-primary-custom" style="flex:1; justify-content:center; background:#ef4444; color:white;"><i class="fas fa-external-link-alt"></i> Join Live Meeting</a>
                        <?php elseif(!empty($s['recording_link'])): ?>
                            <a href="<?= htmlspecialchars($s['recording_link']) ?>" target="_blank" class="btn btn-primary-custom" style="flex:1; justify-content:center;"><i class="fas fa-play"></i> Watch Recording</a>
                        <?php else: ?>
                            <button disabled class="btn btn-secondary-custom" style="flex:1; justify-content:center;"><i class="fas fa-video-slash"></i> No Recording</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-video-slash fs-1 mb-3"></i>
                <h4>No Live Classes Scheduled</h4>
                <p>Check back later for upcoming instructor meetings.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
