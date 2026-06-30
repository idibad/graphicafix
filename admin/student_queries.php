<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle Teacher Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_query'])) {
    $qid = intval($_POST['query_id']);
    $answer = trim($_POST['answer_text']);
    $stmt = $conn->prepare("UPDATE course_queries SET answer_text = ?, status = 'answered' WHERE id = ?");
    $stmt->bind_param("si", $answer, $qid);
    $stmt->execute();
    header("Location: student_queries.php?replied=1"); exit;
}

// Fetch queries for this teacher's courses
$queries = $conn->query("
    SELECT q.*, c.title as course_title, u.name as student_name 
    FROM course_queries q
    JOIN courses c ON q.course_id = c.id
    JOIN users u ON q.student_id = u.user_id
    WHERE c.instructor_id = $user_id
    ORDER BY CASE WHEN q.status = 'pending' THEN 1 ELSE 2 END, q.created_at DESC
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom: 20px;">
        <h3><i class="fas fa-comment"></i> Student Queries</h3>
        <p style="color:#666; font-size:14px;">Answer questions from students enrolled in your courses.</p>
    </div>

    <?php if(isset($_GET['replied'])): ?>
        <div style="background:#10b981; color:#fff; padding:10px 20px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-check-circle"></i> Reply sent to student.</div>
    <?php endif; ?>

    <div class="d-card">
        <?php if($queries && $queries->num_rows > 0): while($q = $queries->fetch_assoc()): ?>
            <div style="border: 1px solid #eee; border-radius: 12px; padding: 20px; margin-bottom: 15px; background: <?= $q['status'] === 'pending' ? '#fffaf0' : '#fff' ?>;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                    <div>
                        <strong style="font-size:15px; color:var(--primary);"><?= htmlspecialchars($q['student_name']) ?></strong>
                        <span style="font-size:12px; color:#888; margin-left:10px;"><i class="fas fa-book"></i> <?= htmlspecialchars($q['course_title']) ?></span>
                    </div>
                    <?php if($q['status'] === 'pending'): ?>
                        <span style="background:#fef9c3; color:#a16207; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;">Pending Reply</span>
                    <?php else: ?>
                        <span style="background:#d7f8b8; color:#2b7a2b; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;">Answered</span>
                    <?php endif; ?>
                </div>
                
                <p style="font-size:14px; color:#444; background:#f9f9f9; padding:12px; border-radius:8px; border-left:3px solid #ccc;">
                    <em>"<?= nl2br(htmlspecialchars($q['query_text'])) ?>"</em>
                </p>

                <?php if($q['status'] === 'pending'): ?>
                    <form method="POST" style="margin-top: 15px; display:flex; gap:10px;">
                        <input type="hidden" name="reply_query" value="1">
                        <input type="hidden" name="query_id" value="<?= $q['id'] ?>">
                        <textarea name="answer_text" required placeholder="Type your answer here..." style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none; resize:vertical; min-height:40px;"></textarea>
                        <button type="submit" class="btn btn-primary-custom" style="align-self:flex-start;">Send Reply</button>
                    </form>
                <?php else: ?>
                    <div style="margin-top:15px; background:#eef2eb; padding:12px; border-radius:8px; border-left:3px solid var(--accent);">
                        <strong style="font-size:12px; color:var(--primary); display:block; margin-bottom:5px;">Your Reply:</strong>
                        <span style="font-size:14px; color:#333;"><?= nl2br(htmlspecialchars($q['answer_text'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; else: ?>
            <div class="text-center" style="padding: 40px; color:#888;">No queries found. Your students understand everything! <i class="fas fa-tada"></i></div>
        <?php endif; ?>
    </div>
</div>
<?php include('dashboard_footer.php'); ?>