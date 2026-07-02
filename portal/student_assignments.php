<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($_SESSION['email'] ?? '')));

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assign_id = intval($_POST['assignment_id']);
    $notes = trim($_POST['notes']);
    
    if (!empty($_FILES['sub_file']['name'])) {
        $upload_dir = '../admin/uploads/assignments/' . date('Y_m');
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = time() . '_' . basename($_FILES['sub_file']['name']);
        $file_path = 'uploads/assignments/' . date('Y_m') . '/' . $file_name;
        
        if (move_uploaded_file($_FILES['sub_file']['tmp_name'], $upload_dir . '/' . $file_name)) {
            $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, notes, submitted_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $assign_id, $user_id, $file_path, $notes);
            $stmt->execute();
            header("Location: student_assignments.php?submitted=1"); exit;
        } else {
            $error = "File upload failed.";
        }
    } else {
        $error = "Please select a file to submit.";
    }
}

// Get enrolled courses
$enrolled = [];
$e_res = $conn->query("SELECT course_id FROM course_enrollments WHERE LOWER(TRIM(student_email)) = '$safe_email' AND LOWER(status) = 'active'");
if ($e_res) {
    while ($r = $e_res->fetch_assoc()) $enrolled[] = $r['course_id'];
}

$assignments = null;
if (!empty($enrolled)) {
    $c_ids = implode(',', $enrolled);
    $assignments = $conn->query("
        SELECT a.*, c.title as course_title, u.name as teacher_name 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        LEFT JOIN users u ON a.teacher_id = u.user_id 
        WHERE a.course_id IN ($c_ids) 
        ORDER BY a.due_date ASC, a.id DESC
    ");
}
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-tasks"></i> Course Assignments</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Review instructor tasks and upload your project submissions.</p>
        </div>
    </div>

    <?php if(isset($_GET['submitted'])): ?>
        <div class="alert alert-success fw-bold"><i class="fas fa-check-circle"></i> Assignment submitted successfully!</div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if($assignments && $assignments->num_rows > 0): while($a = $assignments->fetch_assoc()): 
            $aid = intval($a['id']);
            $sub_q = $conn->query("SELECT * FROM assignment_submissions WHERE assignment_id = $aid AND student_id = $user_id ORDER BY id DESC LIMIT 1");
            $sub = $sub_q ? $sub_q->fetch_assoc() : null;
            $is_overdue = (!$sub && strtotime($a['due_date']) < time());
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-card" style="padding:24px; display:flex; flex-direction:column; height:100%; border:1px solid <?= $sub ? '#10b981' : ($is_overdue ? '#ef4444' : '#e2e8f0') ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <span class="badge bg-light text-dark border"><i class="fas fa-book"></i> <?= htmlspecialchars($a['course_title']) ?></span>
                        <?php if($sub): ?>
                            <span class="badge bg-success">Submitted</span>
                        <?php elseif($is_overdue): ?>
                            <span class="badge bg-danger">Overdue</span>
                        <?php else: ?>
                            <span class="badge" style="background:#fef9c3; color:#854d0e;">Pending</span>
                        <?php endif; ?>
                    </div>

                    <h4 style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:0 0 8px;"><?= htmlspecialchars($a['title']) ?></h4>
                    <div style="font-size:12.5px; color:#64748b; margin-bottom:16px;">Due: <strong style="color:<?= $is_overdue ? '#dc2626' : '#334155' ?>;"><?= date('M d, Y', strtotime($a['due_date'])) ?></strong></div>
                    
                    <?php if(!empty($a['description'])): ?>
                        <div style="font-size:13.5px; color:#475569; margin-bottom:20px; line-height:1.6; flex:1;"><?= nl2br(htmlspecialchars($a['description'])) ?></div>
                    <?php endif; ?>

                    <?php if($sub): ?>
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:14px; border-radius:12px; margin-top:auto;">
                            <div style="font-size:12px; font-weight:800; color:#166534; margin-bottom:4px;"><i class="fas fa-file-check"></i> YOUR SUBMISSION</div>
                            <div style="font-size:12px; color:#15803d; margin-bottom:6px;">Submitted on <?= date('M d, Y H:i', strtotime($sub['submitted_at'])) ?></div>
                            <?php if(!empty($sub['grade'])): ?>
                                <div style="font-size:13px; font-weight:800; color:#024442;">Grade: <span class="badge bg-success"><?= htmlspecialchars($sub['grade']) ?></span></div>
                                <?php if(!empty($sub['feedback'])): ?>
                                    <div style="font-size:12.5px; color:#334155; margin-top:4px;">Feedback: <?= htmlspecialchars($sub['feedback']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size:12px; color:#64748b;">Awaiting instructor grading</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-primary-custom" style="width:100%; justify-content:center;" onclick="openSubmitModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['title'])) ?>')"><i class="fas fa-upload"></i> Upload Submission</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-clipboard-check fs-1 mb-3 text-secondary"></i>
                <h4>No Assignments Assigned</h4>
                <p>You have no pending assignments across your enrolled courses.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt text-success"></i> Submit Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="submit_assignment" value="1">
                    <input type="hidden" name="assignment_id" id="modal_assign_id">
                    <p class="text-muted mb-3">Task: <strong id="modal_assign_title" class="text-dark"></strong></p>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Select Project File (ZIP, PDF, DOCX, PNG)</label>
                        <input type="file" name="sub_file" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Notes / Description (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add any comments for your instructor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Submit Work &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSubmitModal(aid, atitle) {
    document.getElementById('modal_assign_id').value = aid;
    document.getElementById('modal_assign_title').textContent = atitle;
    new bootstrap.Modal(document.getElementById('submitModal')).show();
}
</script>

<?php include('dashboard_footer.php'); ?>
