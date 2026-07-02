<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

$queries = $conn->query("
    SELECT q.*, c.title as course_title 
    FROM course_queries q 
    JOIN courses c ON q.course_id = c.id 
    WHERE q.student_id = $user_id 
    ORDER BY q.created_at DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-question-circle"></i> My Q&A Discussions</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Track the status of your academic questions and instructor replies.</p>
        </div>
    </div>

    <div class="d-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr style="font-size:13px;">
                        <th>Course</th>
                        <th>Question</th>
                        <th>Instructor Reply</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($queries && $queries->num_rows > 0): while($q = $queries->fetch_assoc()): ?>
                        <tr style="font-size:14px;">
                            <td><span class="badge bg-light text-dark border"><i class="fas fa-book"></i> <?= htmlspecialchars($q['course_title']) ?></span></td>
                            <td style="max-width:300px; font-weight:600; color:#1e293b;"><?= nl2br(htmlspecialchars($q['query_text'])) ?></td>
                            <td style="max-width:300px; color:#15803d;">
                                <?php if(!empty($q['answer_text'])): ?>
                                    <div style="background:#f0fdf4; padding:8px 12px; border-radius:8px; border-left:3px solid #10b981;"><?= nl2br(htmlspecialchars($q['answer_text'])) ?></div>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Awaiting reply...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($q['answer_text'])): ?>
                                    <span class="badge bg-success">Answered</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12.5px; color:#64748b;"><?= date('M d, Y', strtotime($q['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">You haven't asked any questions yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
