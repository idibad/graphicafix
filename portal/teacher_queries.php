<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_query'])) {
    $qid = intval($_POST['query_id']);
    $ans = trim($_POST['answer_text']);
    
    $stmt = $conn->prepare("UPDATE course_queries SET answer_text = ?, status = 'answered' WHERE id = ?");
    $stmt->bind_param("si", $ans, $qid);
    $stmt->execute();
    header("Location: teacher_queries.php?replied=1"); exit;
}

$queries = $conn->query("
    SELECT q.*, c.title as course_title, u.name as student_name, u.email as student_email
    FROM course_queries q
    JOIN courses c ON q.course_id = c.id
    JOIN users u ON q.student_id = u.user_id
    WHERE c.instructor_id = $user_id OR c.instructor_id IS NULL
    ORDER BY (q.status = 'pending') DESC, q.created_at DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-comments"></i> Student Q&A Discussion Helpdesk</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Review student questions and transmit official academic responses.</p>
        </div>
    </div>

    <?php if(isset($_GET['replied'])): ?><div class="alert alert-success fw-bold">Reply transmitted to student!</div><?php endif; ?>

    <div class="row g-4">
        <?php if($queries && $queries->num_rows > 0): while($q = $queries->fetch_assoc()): 
            $is_pending = ($q['status'] === 'pending');
        ?>
            <div class="col-md-6">
                <div class="d-card p-4 h-100 d-flex flex-column" style="border:1px solid <?= $is_pending ? '#ef4444' : '#10b981' ?>;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                        <span class="badge bg-light text-dark border"><i class="fas fa-book"></i> <?= htmlspecialchars($q['course_title']) ?></span>
                        <?php if($is_pending): ?>
                            <span class="badge bg-danger animate-pulse">Needs Reply</span>
                        <?php else: ?>
                            <span class="badge bg-success">Answered</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:12.5px; color:#64748b; margin-bottom:8px;"><i class="fas fa-user-circle"></i> Student: <strong style="color:#0f172a;"><?= htmlspecialchars($q['student_name']) ?></strong> (<?= htmlspecialchars($q['student_email']) ?>)</div>
                    <div style="background:#f8fafc; p-3; padding:14px; border-radius:10px; font-size:14px; font-weight:600; color:#1e293b; margin-bottom:16px;">
                        <?= nl2br(htmlspecialchars($q['query_text'])) ?>
                    </div>

                    <?php if(!$is_pending): ?>
                        <div style="background:#f0fdf4; border-left:4px solid #10b981; padding:12px; border-radius:0 8px 8px 0; margin-bottom:16px;">
                            <div style="font-size:11px; font-weight:800; color:#166534; margin-bottom:4px;"><i class="fas fa-reply"></i> YOUR TRANSMITTED REPLY</div>
                            <div style="font-size:13.5px; color:#15803d;"><?= nl2br(htmlspecialchars($q['answer_text'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:auto;">
                        <button class="btn <?= $is_pending ? 'btn-primary-custom' : 'btn-secondary-custom' ?>" style="width:100%; justify-content:center; padding:10px; font-size:13px;" 
                            onclick="openReplyModal(<?= $q['id'] ?>, '<?= htmlspecialchars(addslashes($q['student_name'])) ?>', '<?= htmlspecialchars(addslashes($q['query_text'])) ?>', '<?= htmlspecialchars(addslashes($q['answer_text'] ?? '')) ?>')">
                            <i class="fas fa-reply"></i> <?= $is_pending ? 'Transmit Response' : 'Update Response' ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-comment-dots fs-1 mb-3"></i>
                <h4>No Student Inquiries</h4>
                <p>Your inbox is cleared. No pending questions from students.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-reply text-accent"></i> Instructor Response Desk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="reply_query" value="1">
                    <input type="hidden" name="query_id" id="modal_qid">
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Student: <strong id="modal_sname" class="text-dark"></strong></small>
                        <div id="modal_qprompt" class="p-3 bg-light rounded mt-1 fs-6 fst-italic text-dark border"></div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Official Instructor Answer</label>
                        <textarea name="answer_text" id="modal_ans" class="form-control" rows="5" required placeholder="Type comprehensive academic explanation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Transmit Reply &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReplyModal(qid, sname, qtext, curAns) {
    document.getElementById('modal_qid').value = qid;
    document.getElementById('modal_sname').textContent = sname;
    document.getElementById('modal_qprompt').textContent = qtext;
    document.getElementById('modal_ans').value = curAns;
    new bootstrap.Modal(document.getElementById('replyModal')).show();
}
</script>

<?php include('dashboard_footer.php'); ?>
