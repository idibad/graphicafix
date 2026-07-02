<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'dashboard_header.php';

// NO SESSION OVERRIDES HERE! We use the $email variable directly from dashboard_header.php

// Safely escape and format the email for case-insensitive matching
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($email)));

// Fetch meetings for enrolled courses using bulletproof matching
$meetings = $conn->query("
    SELECT ls.*, c.title as course_title, u.name as teacher_name 
    FROM live_sessions ls 
    JOIN courses c ON ls.course_id = c.id 
    JOIN course_enrollments e ON e.course_id = c.id
    LEFT JOIN users u ON ls.teacher_id = u.user_id
    WHERE LOWER(TRIM(e.student_email)) = '$safe_email' 
      AND LOWER(TRIM(e.status)) IN ('active', 'completed')
    ORDER BY ls.start_time DESC
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom: 20px;">
        <h3><i class="fas fa-video"></i> Live Classes & Recordings</h3>
        <p style="color:#666; font-size:14px;">Access your upcoming zoom links and past session recordings.</p>
    </div>

    <div class="d-card">
        <?php if($meetings && $meetings->num_rows > 0): while($m = $meetings->fetch_assoc()): 
            $is_past = time() > strtotime($m['start_time']);
        ?>
            <div style="border: 1px solid #eee; border-radius: 12px; padding: 20px; margin-bottom: 15px; display:flex; flex-wrap:wrap; gap:15px; justify-content:space-between; align-items:center; background: <?= $is_past ? '#fefefe' : '#f0f9ff' ?>;">
                <div>
                    <div style="font-size:.75rem; color:<?= $is_past ? '#888' : '#0284c7' ?>; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">
                        <?= $is_past ? 'Past Session' : 'Upcoming Session' ?> • <?= date('D, M j, Y \a\t g:i A', strtotime($m['start_time'])) ?>
                    </div>
                    <h4 style="font-size:1.1rem; font-weight:700; color:#333; margin-bottom:5px;"><?= htmlspecialchars($m['topic']) ?></h4>
                    <div style="font-size:13px; color:#666;"><i class="fas fa-book-open"></i> <?= htmlspecialchars($m['course_title']) ?> &nbsp; | &nbsp; <i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($m['teacher_name'] ?? 'Instructor') ?></div>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <?php if(!$is_past): ?>
                        <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" style="background:#0284c7; color:#fff; padding:10px 20px; border-radius:50px; font-size:13px; font-weight:700; text-decoration:none;"><i class="fas fa-video"></i> Join Live</a>
                    <?php endif; ?>

                    <?php if(!empty($m['recording_link'])): ?>
                        <a href="<?= htmlspecialchars($m['recording_link']) ?>" target="_blank" style="background:#f3e8ff; color:#7c3aed; border:1px solid #d8b4fe; padding:10px 20px; border-radius:50px; font-size:13px; font-weight:700; text-decoration:none;"><i class="fas fa-play-circle"></i> Watch Recording</a>
                    <?php elseif($is_past): ?>
                        <span style="background:#f1f5f9; color:#94a3b8; padding:10px 20px; border-radius:50px; font-size:13px; font-weight:600;"><i class="fas fa-clock"></i> Recording Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="text-center" style="padding: 40px; color:#888;">
                <i class="fas fa-calendar-times" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                <p>You have no scheduled live classes for your active courses.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include('dashboard_footer.php'); ?>