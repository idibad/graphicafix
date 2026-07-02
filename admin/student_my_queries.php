<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

// Fetch all queries by this student
$queries = $conn->query("
    SELECT q.*, c.title as course_title, u.name as teacher_name
    FROM course_queries q
    JOIN courses c ON q.course_id = c.id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    WHERE q.student_id = $user_id
    ORDER BY q.created_at DESC
");

$total = $queries ? $queries->num_rows : 0;
$pending = $conn->query("SELECT COUNT(*) as c FROM course_queries WHERE student_id = $user_id AND status = 'pending'")->fetch_assoc()['c'] ?? 0;
$answered = $conn->query("SELECT COUNT(*) as c FROM course_queries WHERE student_id = $user_id AND status = 'answered'")->fetch_assoc()['c'] ?? 0;
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-question-circle"></i> My Queries</h3>
        <p style="color:#666; font-size:14px;">Track all questions you've asked your instructors.</p>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-comments"></i></div>
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-label">Total Queries</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $pending ?></div>
            <div class="stat-label">Awaiting Reply</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $answered ?></div>
            <div class="stat-label">Answered</div>
        </div>
    </div>

    <?php if($queries && $queries->num_rows > 0): ?>
    <div class="d-card">
        <?php while($q = $queries->fetch_assoc()): ?>
        <div style="border:1px solid #eee; border-radius:12px; padding:20px; margin-bottom:15px; transition:0.2s;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                <div>
                    <span style="font-size:13px; font-weight:700; color:var(--primary);"><?= htmlspecialchars($q['course_title']) ?></span>
                    <span style="font-size:12px; color:#999; margin-left:10px;"><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($q['teacher_name'] ?? 'Instructor') ?></span>
                </div>
                <?php if($q['status'] === 'pending'): ?>
                    <span style="background:#fff7ed; color:#f59e0b; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700;"><i class="fas fa-clock"></i> Pending Reply</span>
                <?php else: ?>
                    <span style="background:#ecfdf5; color:#10b981; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Answered</span>
                <?php endif; ?>
            </div>
            
            <p style="font-size:14px; color:#444; background:#f9f9f9; padding:12px; border-radius:8px; border-left:3px solid #ddd; margin-bottom:10px;">
                <em>"<?= nl2br(htmlspecialchars($q['query_text'])) ?>"</em>
            </p>
            
            <div style="font-size:11px; color:#aaa; margin-bottom:8px;"><i class="fas fa-calendar"></i> Asked on <?= date('M d, Y \a\t g:i A', strtotime($q['created_at'])) ?></div>

            <?php if($q['status'] === 'answered' && !empty($q['answer_text'])): ?>
            <div style="background:#eef2eb; padding:14px; border-radius:8px; border-left:3px solid var(--accent);">
                <strong style="font-size:12px; color:var(--primary); display:block; margin-bottom:5px;"><i class="fas fa-reply"></i> Instructor's Reply:</strong>
                <span style="font-size:14px; color:#333;"><?= nl2br(htmlspecialchars($q['answer_text'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="d-card" style="text-align:center; padding:60px 20px; color:#888;">
        <i class="fas fa-inbox" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
        <p style="font-size:16px; font-weight:600;">You haven't asked any questions yet.</p>
        <p style="font-size:13px;">Go to <a href="student_courses.php" style="color:var(--primary); font-weight:600;">My Courses</a> and ask your instructor!</p>
    </div>
    <?php endif; ?>
</div>
<?php include('dashboard_footer.php'); ?>
