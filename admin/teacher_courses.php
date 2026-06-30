<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Fetch courses assigned to this teacher
$courses = $conn->query("
    SELECT c.id, c.title, c.category, c.level, c.duration_mins, c.thumbnail,
           (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.status = 'active') as active_students
    FROM courses c
    WHERE c.instructor_id = $user_id
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom: 20px;">
        <h3><i class="fas fa-book"></i> My Assigned Courses</h3>
        <p style="color:#666; font-size:14px;">Overview of your active classes and student counts.</p>
    </div>

    <div class="row g-4">
        <?php if($courses && $courses->num_rows > 0): while($c = $courses->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4">
            <div class="d-card" style="padding:0; overflow:hidden; height:100%; display:flex; flex-direction:column;">
                <div style="height: 160px; background: var(--primary); position:relative;">
                    <?php if(!empty($c['thumbnail'])): ?>
                        <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:3rem;"><i class="fas fa-graduation-cap"></i></div>
                    <?php endif; ?>
                    <span style="position:absolute; top:10px; left:10px; background:var(--accent); color:var(--primary); font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px; text-transform:uppercase;"><?= htmlspecialchars($c['category']) ?></span>
                </div>
                <div style="padding: 20px; display:flex; flex-direction:column; flex-grow:1;">
                    <h4 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-bottom:10px;"><?= htmlspecialchars($c['title']) ?></h4>
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-size:13px; color:#666;">
                        <span><i class="fas fa-signal"></i> <?= htmlspecialchars($c['level']) ?></span>
                        <span><i class="fas fa-clock"></i> <?= floor($c['duration_mins']/60) ?>h <?= $c['duration_mins']%60 ?>m</span>
                    </div>
                    <div style="margin-top:auto; background:#f9fbfc; padding:12px; border-radius:10px; border:1px dashed #ddd; display:flex; align-items:center; justify-content:space-between;">
                        <span style="font-weight:600; font-size:13px;">Active Students:</span>
                        <span style="background:#e0f2fe; color:#0284c7; padding:4px 12px; border-radius:20px; font-weight:800; font-size:14px;"><?= $c['active_students'] ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center" style="padding: 50px 0; color:#888;">
                <i class="fas fa-folder-open" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                <p>You have not been assigned to any courses yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include('dashboard_footer.php'); ?>